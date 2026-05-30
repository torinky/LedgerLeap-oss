<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Exceptions\Workflow\InsufficientPermissionsException;
use App\Exceptions\Workflow\InvalidWorkflowActionException;
use App\Exceptions\Workflow\UnauthorizedWorkflowActionException;
use App\Exceptions\Workflow\WorkflowConditionException;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implements the ledger workflow state machine.
 *
 * Manages transitions across the DRAFT → PENDING_INSPECTION → PENDING_APPROVAL → APPROVED
 * lifecycle. Handles submit, inspection, approval, rejection, and rollback operations.
 * Each transition creates a LedgerDiff record, updates the ledger's latest_diff_id,
 * and sends workflow notifications to the affected users.
 *
 * @see \App\Enums\WorkflowStatus
 * @see \App\Models\LedgerDiff
 */
class WorkflowService
{
    protected NotificationService $notificationService; // NotificationService をインジェクト

    protected UserService $userService; // UserService もインジェクトする想定

    public function __construct(NotificationService $notificationService, UserService $userService) // コンストラクタでインジェクト
    {
        $this->notificationService = $notificationService;
        $this->userService = $userService; // インジェクト
    }

    /**
     * ユーザーが指定された台帳の承認を依頼できるか判断する
     * (点検者が「承認申請」できるか)
     */
    public function canRequestApproval(User $user, Ledger $ledgerRecord): bool
    {
        $result = $ledgerRecord->canProceedToApprovalStep()
            && ($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION
                || $ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL)
            && $ledgerRecord->latestDiff?->inspector_id === $user->id;

        return $result;
    }

    /**
     * ユーザーが指定された台帳を承認できるか判断する
     * (承認者が「承認」できるか)
     */
    public function canApprove(User $user, Ledger $ledgerRecord): bool
    {
        $result = ($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL &&
                $ledgerRecord->latestDiff?->approver_id === $user->id) ||
            ($ledgerRecord->status !== WorkflowStatus::DRAFT
                && $ledgerRecord->status !== WorkflowStatus::APPROVED
                && $ledgerRecord->canBeFinallyApproved()
            );

        return $result;
    }

    /**
     * ユーザーが指定された台帳をドラフトに戻せるか判断する
     */
    public function canReturnToDraft(User $user, Ledger $ledgerRecord): bool
    {
        $result = ($ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $ledgerRecord->latestDiff?->inspector_id === $user->id) ||
            ($ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $ledgerRecord->latestDiff?->approver_id === $user->id);

        return $result;
    }

    /**
     * 下書きを保存する
     * 新しい LedgerDiff (Content有り) を作成し、Ledger も更新する
     *
     * @param  int|null  $ledgerId  既存レコードID (新規なら null)
     * @param  array  $content  フォームの入力内容
     * @param  array  $contentAttached  添付ファイルの検索用インデックス
     * @param  int  $modifierId  操作者ID
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     *
     * @throws Throwable
     */
    public function saveDraft(
        ?int $ledgerId,
        int $ledgerDefineId,
        array $content,
        array $contentAttached,
        int $modifierId
    ): array {
        // Log::debug('[WorkflowService::saveDraft] Received content_attached:', $contentAttached);
        // ToDo: 権限チェック (modifierId が下書き保存できるか？)

        $ledgerDefine = LedgerDefine::findOrFail($ledgerDefineId);

        return DB::transaction(function () use ($ledgerId, $ledgerDefine, $ledgerDefineId, $content, $contentAttached, $modifierId) {
            $ledger = null;
            $isUpdating = ! is_null($ledgerId);
            $isNewLedger = false;

            // 自動入力項目の計算
            $content = $ledgerDefine->calculateAutoFillValues($content, $isUpdating);

            if ($isUpdating) {
                $ledger = Ledger::findOrFail($ledgerId);
                // 承認済みなら編集できない
                if ($ledger->isLocked()) {
                    throw new Exception('Cannot save draft for an approved record.');
                }

                $currentVersion = $ledger->version;
                if ($content !== $ledger->content) {
                    $currentVersion = $ledger->version + 1; // <<<--- 更新時はインクリメント
                }
            } else {
                $isNewLedger = true;
                $currentVersion = 1;
                $ledger = Ledger::create([
                    'ledger_define_id' => $ledgerDefineId,
                    'creator_id' => $modifierId,
                    'modifier_id' => $modifierId,
                    'status' => WorkflowStatus::DRAFT,
                    'content' => $content, // 新規作成時から content を保存
                    'content_attached' => $contentAttached ?? [],
                    'version' => $currentVersion,
                ]);
                $ledgerId = $ledger->id;
            }
            $columnDefine = $ledger->define->column_define;

            // LedgerDiff (データスナップショット) を作成
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => $content,
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledgerDefineId,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // この Diff 作成時のステータス
                'version' => $currentVersion,
                'completed_inspector_role_ids' => [], // 内容変更なのでリセット
                'completed_approver_role_ids' => [],  // 内容変更なのでリセット
                // 他のワークフローカラムは NULL
                'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
                'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新 (content, modifier, status 等)
            //            dd($isNewLedger);
            if ($isNewLedger) {
                // 新規作成時は latest_diff_id をセット
                $result = $ledger->update(['latest_diff_id' => $ledgerDiff->id]);
            } else {
                $result = $ledger->update([
                    'id' => $ledger->id,
                    'content' => $content,
                    'content_attached' => $contentAttached ?? [],
                    'status' => WorkflowStatus::DRAFT, // 念のため DRAFT に
                    'modifier_id' => $modifierId,
                    'version' => $currentVersion,
                    'latest_diff_id' => $ledgerDiff->id, // 最新Diff ID を更新
                ]);
            }

            // Activity Log 記録 (ステップ4)
            /* activity()->performedOn($ledger)->causedBy(User::find($modifierId))->log('draft_saved'); */
            Log::info("Draft saved for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $ledgerDiff];
        });
    }

    /**
     * 点検依頼を処理する
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param  int  $ledgerId  台帳レコードID
     * @param  int  $requesterId  点検依頼を行った User ID
     * @param  int  $inspectorId  次の担当者 User ID
     * @param  string|null  $comments  点検コメント (任意)
     * @return Ledger 更新後の Ledger
     *
     * @throws Throwable
     */
    public function requestInspection(int $ledgerId, int $requesterId, int $inspectorId, ?string $comments = null): Ledger
    {
        // ToDo: 権限チェック (requesterId が点検依頼できるか？)
        $ledger = null; // 先に宣言
        $ledgerDiff = null; // 先に宣言

        DB::transaction(function () use ($ledgerId, $requesterId, $inspectorId, $comments, &$ledger, &$ledgerDiff) {
            $ledger = Ledger::findOrFail($ledgerId);
            if ($ledger->status !== WorkflowStatus::DRAFT) {
                throw new Exception('Inspection can only be requested from Draft status.');
            }
            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $requesterId,
                'status' => WorkflowStatus::PENDING_INSPECTION, // このアクション時点のステータス
                'version' => $ledgerVersion,
                'inspector_id' => $inspectorId, // 次の担当者
                'approver_id' => null,
                'requested_at' => now(),
                'inspected_at' => null, 'approved_at' => null, 'returned_at' => null,
                'comments' => $comments,
                'completed_inspector_role_ids' => $ledger->latestDiff?->completed_inspector_role_ids ?? [],
                'completed_approver_role_ids' => $ledger->latestDiff?->completed_approver_role_ids ?? [],
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスと担当者、最新Diff ID を更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_INSPECTION,
                'modifier_id' => $requesterId,
                'latest_diff_id' => $ledgerDiff->id, // 最新の Diff ID
            ]);

            // カウンター更新 (点検者+)
            $this->incrementPendingTaskCount($inspectorId, 'inspection');
            // ToDo: イベント発行
            // event(new InspectionRequested($ledgerDiff));
        });

        // --- 通知処理 (トランザクション外) ---
        if ($ledgerDiff && $inspectorId) {
            $recipient = User::find($inspectorId);
            $notificationType = NotificationType::where('name', 'inspection_requested')->first();
            $folder = $ledger->define?->folder; // Folder を取得

            if ($recipient && $notificationType) {
                $this->notificationService->sendWorkflowNotification(
                    $recipient,
                    $notificationType,
                    $ledgerDiff,
                    null, // comment
                    $folder
                );
            }
        }
        // --- ここまで ---

        // ToDo: Activity Log 記録 (ステップ4)
        Log::info(
            "Inspection requested for Ledger ID: {$ledgerId}. Inspector: {$inspectorId}. Diff ID: {$ledgerDiff->id}"
        );

        return $ledger->refresh();
    }

    /**
     * 点検完了・承認申請を処理する
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param  int  $approverId  次の承認者 ID
     * @param  int  $inspectorId  点検操作を行った User ID
     * @return Ledger 更新後の Ledger
     *
     * @throws Throwable
     */
    public function requestApproval(int $ledgerId, int $approverId, int $inspectorId, ?string $comments): Ledger
    {
        $ledger = Ledger::findOrFail($ledgerId);
        $applicant = null; // 申請者

        DB::transaction(function () use (
            $ledgerId,
            $approverId,
            $inspectorId,
            $comments,
            &$ledger,
            &$ledgerDiff,
            &$applicant
        ) {
            $ledger->refresh(); // 最新のLedgerオブジェクトを取得
            $applicant = $ledger->creator; // 申請者を取得
            $ledgerDiff = $ledger->latestDiff()->first();
            $previousDiff = $ledgerDiff;

            /*            if ($ledger->status !== WorkflowStatus::PENDING_INSPECTION || $ledger->latestDiff?->inspector_id !== $inspectorId) {
                            throw new Exception("User not authorized or invalid status for requesting approval.");
                        }
                        // 権限チェック: 点検者がそのフォルダで点検権限を持つか
                        if (!$this->userService->hasFolderPermission(User::find($inspectorId), $ledger->define->folder, FolderPermissionType::INSPECT)) {
                            throw new Exception(__('messages.error.no_permission_to_inspect'));
                        }

                        $completedInspectorRoleIds = $previousDiff?->completed_inspector_role_ids ?? [];
                        $inspectorUser = User::find($inspectorId);
                        if ($inspectorUser && $ledger->define->folder) {
                            foreach ($ledger->define->folder->requiredInspectorRoles as $requiredRole) {
                                if ($inspectorUser->hasRole($requiredRole->name) && !in_array($requiredRole->id, $completedInspectorRoleIds)) {
                                    $completedInspectorRoleIds[] = $requiredRole->id;
                                }
                            }
                        }*/
            // 1. 基本的な状態と担当者の正当性チェック
            if ($previousDiff?->inspector_id !== $inspectorId) {
                throw new UnauthorizedWorkflowActionException(__('messages.error.unauthorized_as_inspector'));
            }
            // 2. 点検者の権限チェック
            $inspectorUser = User::find($inspectorId);
            if (! $inspectorUser ||
                ! $this->userService->hasFolderPermission(
                    $inspectorUser,
                    $ledger->define->folder,
                    FolderPermissionType::INSPECT
                )
            ) {
                throw new InsufficientPermissionsException(__('messages.error.no_permission_to_inspect'));
            }
            // 3. ★ 進行条件チェック: この点検完了アクションで、全ての必須点検ロールが完了するかどうか
            //    （Ledger::getRequiredRolesProgressDetails を利用して判定）
            //    requestApproval の時点では、この点検者が点検することで「全必須点検が完了する」ことを
            //    システム的に強制するのではなく、UI側で促すに留め、
            //    Service側では「この点検者による点検が行われた」という事実を記録することに主眼を置く。
            //    最終的な全点検完了チェックは approve の前に行う。
            //    ただし、この点検者が必須点検ロールに属していて、その処理を記録することは行う。

            $completedInspectorRoleIds = $previousDiff?->completed_inspector_role_ids ?? [];
            foreach ($ledger->define->folder->requiredInspectorRoles as $requiredRole) {
                if ($inspectorUser->hasRole($requiredRole->name)
                    && ! in_array($requiredRole->id, $completedInspectorRoleIds)
                ) {
                    $completedInspectorRoleIds[] = $requiredRole->id;
                }
            }

            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $inspectorId,
                'status' => WorkflowStatus::PENDING_APPROVAL, // このアクション時点のステータス
                'version' => $ledgerVersion,
                'inspector_id' => $inspectorId, // 点検完了者
                'approver_id' => $approverId, // 次の承認者
                'requested_at' => self::getInspectedAtCarbonDate($ledgerDiff->requested_at),
                'inspected_at' => now(), // 点検完了日時
                'comments' => $comments,
                'approved_at' => self::getInspectedAtCarbonDate($ledgerDiff->approved_at),
                'returned_at' => null,
                'completed_inspector_role_ids' => array_unique($completedInspectorRoleIds),
                'completed_approver_role_ids' => $previousDiff?->completed_approver_role_ids ?? [], // 承認ロールは引き継ぎ

            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスと担当者、最新Diff ID を更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_APPROVAL,
                'modifier_id' => $inspectorId,
                'latest_diff_id' => $ledgerDiff->id,
            ]);

            // カウンター更新 (点検者-, 承認者+)
            $this->decrementPendingTaskCount($inspectorId, 'inspection');
            $this->incrementPendingTaskCount($approverId, 'approval');
            // ToDo: イベント発行
            // event(new ApprovalRequested($ledgerDiff));
        });
        // --- 通知処理 ---
        // 承認依頼通知 (担当者向け - オプション)
        if ($ledgerDiff && $approverId) {
            $recipient = User::find($approverId);
            $notificationType = NotificationType::where('name', 'approval_requested')->first();
            $folder = $ledger->define?->folder;
            if ($recipient && $notificationType) {
                $this->notificationService->sendWorkflowNotification(
                    $recipient,
                    $notificationType,
                    $ledgerDiff,
                    $comments,
                    $folder
                );
            }
        }
        // 点検完了通知 (申請者向け - オプション)
        if ($applicant && $ledgerDiff) {
            $notificationType = NotificationType::where('name', 'inspection_completed')->first();
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, $comments, $folder);
            }
        }

        Log::info("Inspection completed, approval requested for Ledger ID: {$ledgerId}. Approver: {$approverId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();
    }

    /**
     * 承認処理を行う
     * 新しい LedgerDiff (Content無し) を作成し、Ledger を承認済みに更新し内容を反映
     *
     * @param  int  $ledgerId  台帳レコードID
     * @param  int  $currentApproverId  現在の承認者ID
     * @param  string|null  $comments  承認コメント
     * @param  int|null  $nextApproverId  次の承認者ID
     * @return Ledger 更新後の Ledger
     *
     * @throws Throwable
     */
    public function approve(int $ledgerId, int $currentApproverId, ?string $comments = null, ?int $nextApproverId = null): Ledger
    {
        $ledger = Ledger::with([
            'define.folder.requiredInspectorRoles',
            'define.folder.requiredApproverRoles',
        ])->findOrFail($ledgerId);
        $newLedgerDiff = null;
        $applicant = null;
        DB::transaction(function () use ($ledgerId, $currentApproverId, $comments, $nextApproverId, &$ledger, &$newLedgerDiff, &$applicant) {
            $ledger->refresh(); // 最新のLedgerオブジェクトを取得
            $applicant = $ledger->creator;
            $previousDiff = $ledger->latestDiff;

            // 1. 基本的な状態と担当者の正当性チェック
            if ($ledger->status !== WorkflowStatus::PENDING_APPROVAL && ! $ledger->areAllRequiredInspectionsCompleted()) {
                throw new InvalidWorkflowActionException(__('ledger.workflow.error.invalid_status_for_approve'));
            }
            /*            if ($previousDiff?->approver_id !== $currentApproverId) {
                            throw new UnauthorizedWorkflowActionException(__('messages.error.unauthorized_as_approver'));
                        }*/
            // 2. 承認者の権限チェック
            $approverUser = User::find($currentApproverId);
            if (! $approverUser || ! $this->userService->hasFolderPermission($approverUser, $ledger->define->folder, FolderPermissionType::APPROVE)) {
                throw new InsufficientPermissionsException(__('messages.error.no_permission_to_approve'));
            }
            // 3. ★ 進行条件チェック: いずれかの必須点検ロールによる点検が完了しているか
            if (! $ledger->hasAnyRequiredInspectionBeenDoneForCurrentContent() && $ledger->define->folder->requiredInspectorRoles->isNotEmpty()) {
                throw new WorkflowConditionException(__('ledger.workflow.error.approve_requires_any_prior_inspection'));
            }

            // 今回の承認者によって完了した必須承認ロールを記録
            $completedInspectorRoleIds = $previousDiff?->completed_inspector_role_ids ?? []; // 点検ロールは引き継ぎ
            $completedApproverRoleIds = $previousDiff?->completed_approver_role_ids ?? [];
            foreach ($ledger->define->folder->requiredApproverRoles as $requiredRole) {
                if ($approverUser->hasRole($requiredRole->name) && ! in_array($requiredRole->id, $completedApproverRoleIds)) {
                    $completedApproverRoleIds[] = $requiredRole->id;
                }
            }
            $completedApproverRoleIds = array_unique($completedApproverRoleIds);

            // --- 次のステータスを決定 ---
            // この時点での「全必須ロール完了」を仮判定
            $allInspectionsDone = collect($ledger->define->folder->requiredInspectorRoles)
                ->every(fn ($role) => in_array($role->id, $completedInspectorRoleIds));
            $allApprovalsDoneNow = collect($ledger->define->folder->requiredApproverRoles) // 今回の承認を含めた判定
                ->every(fn ($role) => in_array($role->id, $completedApproverRoleIds));

            $nextStatus = ($allInspectionsDone && $allApprovalsDoneNow)
                ? WorkflowStatus::APPROVED
                : WorkflowStatus::PENDING_APPROVAL;
            // ---------------------------------------------

            $newLedgerDiff = LedgerDiff::create([
                'ledger_id' => $ledgerId, 'content' => '', 'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id, 'creator_id' => $ledger->creator_id,
                'modifier_id' => $currentApproverId, // 今回のアクション実行者
                'status' => $nextStatus,
                'version' => $ledger->version,
                'inspector_id' => $previousDiff?->inspector_id,
                // 次の担当者を設定: 最終承認ならnull、そうでなければ $nextApproverId (UIから指定)
                'approver_id' => ($nextStatus === WorkflowStatus::APPROVED) ? null : $nextApproverId,
                'requested_at' => self::getInspectedAtCarbonDate($previousDiff?->requested_at),
                'inspected_at' => self::getInspectedAtCarbonDate($previousDiff?->inspected_at),
                'approved_at' => ($nextStatus === WorkflowStatus::APPROVED) ? now() : null,
                'comments' => $comments,
                'completed_inspector_role_ids' => $completedInspectorRoleIds,
                'completed_approver_role_ids' => $completedApproverRoleIds,
            ]);

            $ledger->update([
                'status' => $nextStatus,
                'modifier_id' => $currentApproverId,
                'latest_diff_id' => $newLedgerDiff->id,
            ]);

            $this->decrementPendingTaskCount($currentApproverId, 'approval');
            if ($nextStatus === WorkflowStatus::PENDING_APPROVAL && $nextApproverId) {
                $this->incrementPendingTaskCount($nextApproverId, 'approval');
            }
        });

        // --- 通知処理 ---
        if ($newLedgerDiff) {
            // 申請者への通知
            if ($applicant) {
                $notificationType = ($newLedgerDiff->status === WorkflowStatus::APPROVED)
                    ? NotificationType::where('name', 'approved')->first()
                    : NotificationType::where('name', 'approval_requested')->first(); // 承認が進んだことを通知 (任意)
                if ($notificationType) {
                    $this->notificationService->sendWorkflowNotification(
                        $applicant,
                        $notificationType,
                        $newLedgerDiff,
                        $comments,
                        $ledger->define?->folder
                    );
                }
            }
            // 次の承認者への通知
            if ($newLedgerDiff->status === WorkflowStatus::PENDING_APPROVAL && $newLedgerDiff->approver_id) {
                $nextRecipient = User::find($newLedgerDiff->approver_id);
                $approvalRequestType = NotificationType::where('name', 'approval_requested')->first();
                if ($nextRecipient && $approvalRequestType) {
                    $this->notificationService->sendWorkflowNotification(
                        $nextRecipient,
                        $approvalRequestType,
                        $newLedgerDiff,
                        __('ledger.mail.greeting.approval_requested_forwarded', ['previous_approver' => User::find($currentApproverId)?->name ?? '']),
                        $ledger->define?->folder
                    );
                }
            }
        }

        return $ledger->refresh();
    }

    public static function getValidInspectedAt($inspectedAt)
    {
        if (
            $inspectedAt &&
            Carbon::hasFormat($inspectedAt, 'Y-m-d H:i:s') &&
            $inspectedAt !== '0000-00-00 00:00:00' &&
            $inspectedAt !== '-0001-11-30 00:00:00'
        ) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $inspectedAt);
        }

        return now();
    }

    /**
     * ステータスを DRAFT に戻す (担当者が「作成中に戻す」ボタンを押した時)
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param  int  $modifierId  操作者ID (点検者 or 承認者)
     * @param  string|null  $comments  理由コメント
     * @return Ledger 更新後の Ledger
     *
     * @throws Throwable
     */
    public function returnToDraft(int $ledgerId, int $modifierId, ?string $comments): Ledger
    {
        $ledger = Ledger::findOrFail($ledgerId);
        $applicant = null;

        DB::transaction(callback: function () use ($ledgerId, $modifierId, $comments, &$ledger, &$ledgerDiff, &$applicant) {
            $ledger->refresh(); // 最新のLedgerオブジェクトを取得
            $applicant = $ledger->creator;
            $currentStatus = $ledger->status;
            $previousDiff = $ledger->latestDiff()->first();
            $handlerId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION)
                ? $previousDiff?->inspector_id : $previousDiff?->approver_id;

            if (in_array($ledger->status, [WorkflowStatus::DRAFT, WorkflowStatus::APPROVED])) {
                throw new Exception('Invalid status for returning to draft.');
            }

            // 権限チェック
            $actor = User::find($modifierId);
            $canReturn = false;
            if ($currentStatus === WorkflowStatus::PENDING_INSPECTION
                && $previousDiff?->inspector_id === $modifierId
                && $this->userService->hasFolderPermission($actor, $ledger->define->folder, FolderPermissionType::INSPECT)
            ) {
                $canReturn = true;
            } elseif ($currentStatus === WorkflowStatus::PENDING_APPROVAL
                && $previousDiff?->approver_id === $modifierId
                && $this->userService->hasFolderPermission($actor, $ledger->define->folder, FolderPermissionType::APPROVE)
            ) {
                $canReturn = true;
            }
            if (! $canReturn) {
                throw new Exception(__('messages.error.unauthorized_to_return_to_draft'));
            }

            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // このアクション時点のステータス
                'version' => $ledgerVersion, // <<<--- Ledger の version を記録
                'comments' => $comments,
                'returned_at' => now(), // 戻された日時
                'inspector_id' => $previousDiff->inspector_id, // 戻す直前の担当者
                'approver_id' => $previousDiff->approver_id,  // 戻す直前の担当者
                'requested_at' => self::getInspectedAtCarbonDate($previousDiff->requested_at),
                'inspected_at' => self::getInspectedAtCarbonDate($previousDiff->inspected_at),
                'approved_at' => self::getInspectedAtCarbonDate($previousDiff->approved_at), // クリア
                'completed_inspector_role_ids' => $previousDiff?->completed_inspector_role_ids ?? [],
                'completed_approver_role_ids' => $previousDiff?->completed_approver_role_ids ?? [],
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新
            $ledger->update([
                'status' => WorkflowStatus::DRAFT,
                'returned_at' => now(), // Ledgerにも記録
                'modifier_id' => $modifierId,
                'latest_diff_id' => $ledgerDiff->id,
            ]);

            // カウンター調整 (担当者-)
            if ($previousDiff) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $decrementTargetId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? $previousDiff->inspector_id : $previousDiff->approver_id;
                if ($decrementTargetId) {
                    $this->decrementPendingTaskCount($decrementTargetId, $taskType);
                }
            }

            // ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledgerDiff, $comments));
        });

        // --- 通知処理 ---
        if ($applicant && $ledgerDiff) {
            $notificationType = NotificationType::where('name', 'status_returned_to_draft')->first();
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, $comments, $folder);
            }
        }

        // ToDo: Activity Log 記録 (ステップ4)
        Log::info("Ledger ID: {$ledgerId} returned to DRAFT by User ID: {$modifierId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();
    }

    /**
     * 編集による DRAFT 戻し処理 (編集画面での保存時)
     * 新しい LedgerDiff (Content有り) を作成し、Ledger も更新。
     *
     * @param  array  $newContentAttached
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     *
     * @throws Throwable
     */
    public function saveEditedRecord(
        Ledger $ledger,
        array $newContent,
        $newContentAttached,
        int $modifierId,
        ?string $comments
    ): array {
        // Log::debug('[WorkflowService::saveEditedRecord] Received content_attached:', is_array($newContentAttached) ? $newContentAttached : []);
        if ($ledger->isLocked()) {
            throw new Exception('Cannot modify an approved record.');
        }
        // ToDo: modifierId が編集権限を持っているかチェック

        $newLedgerDiff = null; // 先に宣言

        DB::transaction(function () use ($ledger, $newContent, $newContentAttached, $modifierId, $comments, &$newLedgerDiff) {
            $currentStatus = $ledger->status; // 戻す前のステータス
            $latestDiff = $ledger->latestDiff()->first();
            $handlerId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? $latestDiff->inspector_id : $latestDiff->approver_id;
            $ledgerVersion = $ledger->version;
            if ($newContent !== $ledger->content) {
                $ledgerVersion = $ledger->version + 1; // <<<--- 更新時はインクリメント
            }

            // 1. 新しい LedgerDiff を作成 (Content有り)
            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => $newContent ?? [], // 編集後の内容
                'column_define' => $ledger->define->column_define, // 編集時の定義
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // Diff ステータス
                'version' => $ledgerVersion, // <<<--- Ledger の version を記録
                'comments' => $comments, // 編集理由
                // 他のWFカラムはクリア
                'inspector_id' => $latestDiff->inspector_id ?? null,
                'approver_id' => $latestDiff->approver_id ?? null,
                'requested_at' => self::getInspectedAtCarbonDate($latestDiff->requested_at),
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => now(), // 編集により戻された日時
                'completed_inspector_role_ids' => [], // 内容変更なのでリセット
                'completed_approver_role_ids' => [],  // 内容変更なのでリセット
            ];
            $newLedgerDiff = LedgerDiff::create($diffData);

            // 2. Ledger を更新
            $ledger->update([
                'content' => $newContent ?? [], // 最新内容を反映
                'content_attached' => $newContentAttached ?? [],
                'status' => WorkflowStatus::DRAFT,
                'modifier_id' => $modifierId,
                'latest_diff_id' => $newLedgerDiff->id, // 最新Diff ID 更新
                'version' => $ledgerVersion,
            ]);

            // 4. ToDo: カウンター調整
            if ($handlerId && in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $this->decrementPendingTaskCount($handlerId, $taskType);
            }

            // 5. ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledger, $comments));
        });

        // --- 通知処理 ---
        $applicant = $ledger->creator;
        if ($applicant && $newLedgerDiff) {
            $notificationType = NotificationType::where('name', 'status_returned_to_draft')->first(); // 同じ通知タイプを使う？
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $newLedgerDiff, $comments, $folder);
            }
        }
        // --- ここまで ---

        // 3. ToDo: Activity Log 記録 (ステップ4)
        Log::info("Ledger ID: {$ledger->id} edited and returned to DRAFT by User ID: {$modifierId}. Diff ID: {$newLedgerDiff->id}");

        return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $newLedgerDiff];
    }

    // --- カウンター操作メソッド (実装) ---

    /**
     * 指定されたユーザーの未処理タスクカウンターをインクリメントする
     *
     * @param  int  $userId  対象ユーザーID
     * @param  string  $type  'inspection' または 'approval'
     */
    public function incrementPendingTaskCount(int $userId, string $type = 'approval'): void
    {
        try {
            $column = ($type === 'inspection') ? 'pending_inspection_count' : 'pending_approval_count';
            // DB::table('users')->where('id', $userId)->increment($column); // Query Builder
            User::where('id', $userId)->increment($column); // Eloquent
            Log::info("Incremented pending {$type} count for user: {$userId}");
        } catch (Exception $e) {
            Log::error("Failed to increment pending {$type} count for user {$userId}: ".$e->getMessage());
            // ここで例外を再スローするかどうかは要件による
        }
    }

    /**
     * 指定されたユーザーの未処理タスクカウンターをデクリメントする
     *
     * @param  int  $userId  対象ユーザーID
     * @param  string  $type  'inspection' または 'approval'
     */
    public function decrementPendingTaskCount(int $userId, string $type): void
    {
        try {
            $column = ($type === 'inspection') ? 'pending_inspection_count' : 'pending_approval_count';
            // マイナスにならないように where でガード
            // DB::table('users')->where('id', $userId)->where($column, '>', 0)->decrement($column);
            User::where('id', $userId)->where($column, '>', 0)->decrement($column); // Eloquent
            Log::info("Decremented pending {$type} count for user: {$userId}");
        } catch (Exception $e) {
            Log::error("Failed to decrement pending {$type} count for user {$userId}: ".$e->getMessage());
        }
    }

    /**
     * @param  $targetDate  string|Carbon|null
     */
    public static function getInspectedAtCarbonDate($targetDate): ?Carbon
    {
        // null の場合はそのまま null を返す
        if ($targetDate === null) {
            return null;
        }

        // すでに有効な Carbon インスタンスであればそのまま返す
        if ($targetDate instanceof Carbon) {
            // Carbon の無効な日付値 (timestamp=0 由来の -0001-11-30 等) をガード
            if ($targetDate->year < 1) {
                return null;
            }

            return $targetDate;
        }

        return Carbon::hasFormat($targetDate, 'Y-m-d H:i:s')
        && $targetDate !== '0000-00-00 00:00:00'
        && $targetDate !== '-0001-11-30 00:00:00'
            ? Carbon::createFromFormat('Y-m-d H:i:s', $targetDate)
            : null;
    }

    /**
     * 特定の台帳定義と役割タイプにおいて、過去に頻繁に担当したユーザーを取得する
     *
     * @param  string  $roleType  'inspector' または 'approver'
     * @param  int  $limit  取得する最大件数
     * @param  string  $searchQuery  ユーザー名での検索クエリ (オプション)
     * @param  array  $excludeUserIds  除外するユーザーIDリスト (オプション)
     * @return array [['id' => userId, 'name' => userName, 'count' => N], ...]
     */
    public function getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit, string $searchQuery = '', array $excludeUserIds = []): array
    {
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';
        $query = LedgerDiff::select("ledger_diffs.{$column} as user_id", 'users.name as user_name', DB::raw('count(*) as count'))
            ->join('users', 'users.id', '=', "ledger_diffs.{$column}")
            ->where('ledger_diffs.ledger_define_id', $ledgerDefineId)
            ->whereNotNull("ledger_diffs.{$column}")
            ->when(! empty($excludeUserIds), function ($q) use ($column, $excludeUserIds) {
                // 除外IDの条件
                $q->whereNotIn("ledger_diffs.{$column}", $excludeUserIds);
            })
            ->when($searchQuery, function ($q) use ($searchQuery) {
                $q->where('users.name', 'like', "%{$searchQuery}%");
            })
            ->groupBy('user_id', 'users.name')
            ->orderByDesc('count')
            ->limit($limit);

        return $query->get()
            ->map(fn ($diff) => ['id' => $diff->user_id, 'name' => $diff->user_name ?? __('ledger.unknown_user'), 'count' => $diff->count])
            ->filter(fn ($item) => $item['id'] !== null)
            ->toArray();
    }

    /**
     * ワークフロータスクを引き継ぐ
     *
     * @param  Ledger  $ledger  引き継ぎ対象のLedger
     * @param  User  $claimer  引き継ぎを行うユーザー (新しい担当者)
     * @param  string|null  $comments  引き継ぎコメント
     * @return Ledger 更新後のLedger
     *
     * @throws Throwable
     */
    public function claimTask(Ledger $ledger, User $claimer, ?string $comments): Ledger
    {
        // 1. 基本的なバリデーション
        if (! $ledger->status->isWorkflowPending()) { // isWorkflowPending() は Enum に定義されている想定
            throw new Exception(__('ledger.errors.cannot_claim_not_pending_task'));
        }
        if ($ledger->creator_id === $claimer->id) {
            throw new Exception(__('ledger.errors.applicant_cannot_claim'));
        }

        $latestDiff = $ledger->latestDiff()->first();
        if (! $latestDiff) {
            throw new Exception(__('ledger.errors.latest_diff_not_found'));
        }

        // 2. 現在の担当者情報を取得 (元の担当者)
        $originalAssignee = null;
        $currentTaskRoleType = ''; // 元の担当者の役割タイプ (inspector or approver)

        if ($ledger->status === WorkflowStatus::PENDING_INSPECTION && $latestDiff->inspector_id) {
            $originalAssignee = $latestDiff->inspector; // リレーションで User を取得
            $currentTaskRoleType = 'inspection';
        } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL && $latestDiff->approver_id) {
            $originalAssignee = $latestDiff->approver; // リレーションで User を取得
            $currentTaskRoleType = 'approval';
        }

        // 既に自分が担当者ならエラー
        if ($originalAssignee && $originalAssignee->id === $claimer->id) {
            throw new Exception(__('ledger.errors.already_assignee'));
        }

        // 3. 引き継ぎ者の権限チェック
        $requiredPermission = ($ledger->status === WorkflowStatus::PENDING_INSPECTION)
            ? FolderPermissionType::INSPECT
            : FolderPermissionType::APPROVE;
        if (! $ledger->define?->folder || ! $this->userService->hasFolderPermission($claimer, $ledger->define->folder, $requiredPermission)) {
            throw new Exception(__('ledger.errors.no_permission_to_claim'));
        }

        return DB::transaction(function () use ($ledger, $claimer, $comments, $latestDiff, $originalAssignee, $currentTaskRoleType) {
            $now = now();
            // ステータスは引き継ぎなので変更しない
            $newStatus = $ledger->status;

            // 4. 新しい LedgerDiff を作成
            $newDiffData = [
                'ledger_id' => $ledger->id,
                'content' => '', // 引き継ぎ時は内容は変更しない
                'column_define' => '', // 同上
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id, // 元の申請者は維持
                'modifier_id' => $claimer->id, // 操作者は引き継ぎ者
                'status' => $newStatus, // ステータスは維持
                'version' => $ledger->version, // バージョンも維持
                'comments' => $comments, // 引き継ぎコメント
                'requested_at' => self::getInspectedAtCarbonDate($latestDiff->requested_at), // 元の依頼日時は維持
                'inspected_at' => ($newStatus === WorkflowStatus::PENDING_APPROVAL) ? self::getInspectedAtCarbonDate($latestDiff->inspected_at) : null, // 点検完了日時は維持 (承認待ちの場合)
                'approved_at' => null, // 承認日時はクリア
                'returned_at' => null, // 差し戻しではない
                // 新しい担当者を設定
                'inspector_id' => ($newStatus === WorkflowStatus::PENDING_INSPECTION) ? $claimer->id : $latestDiff->inspector_id,
                'approver_id' => ($newStatus === WorkflowStatus::PENDING_APPROVAL) ? $claimer->id : $latestDiff->approver_id,
                'completed_inspector_role_ids' => $latestDiff->completed_inspector_role_ids ?? [],
                'completed_approver_role_ids' => $latestDiff->completed_approver_role_ids ?? [],
            ];

            $newLedgerDiff = LedgerDiff::create($newDiffData);

            // 5. Ledger の latest_diff_id と modifier_id を更新
            $ledger->update([
                'latest_diff_id' => $newLedgerDiff->id,
                'modifier_id' => $claimer->id, // Ledger の最終更新者も引き継ぎ者に
            ]);

            // 6. カウンター調整
            if ($originalAssignee && ! empty($currentTaskRoleType)) { // 元の担当者がいて、役割タイプが特定できればデクリメント
                $this->decrementPendingTaskCount($originalAssignee->id, $currentTaskRoleType);
            }
            // 新しい担当者のカウンターをインクリメント (役割タイプはステータスから判断)
            $newTaskType = ($newStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
            $this->incrementPendingTaskCount($claimer->id, $newTaskType);

            // 7. 通知処理
            $notificationType = NotificationType::where('name', 'task_claimed')->first();
            if ($notificationType) {
                $folder = $ledger->define?->folder;
                $recipients = collect();

                // 申請者に通知 (引き継ぎ者と異なる場合)
                if ($ledger->creator_id !== $claimer->id && $ledger->creator) {
                    $recipients->push($ledger->creator);
                }
                // 元の担当者に通知 (存在し、引き継ぎ者と異なる場合)
                if ($originalAssignee && $originalAssignee->id !== $claimer->id) {
                    $recipients->push($originalAssignee);
                }
                // 新しい担当者(引き継ぎ者本人)にも通知が必要か？ -> 通常は操作者なので不要だが、確認のため通知しても良い
                $recipients->push($claimer); // 自分にも通知を送る場合

                foreach ($recipients->unique('id') as $recipientUser) {
                    if ($recipientUser) { // null チェック
                        $this->notificationService->sendWorkflowNotification(
                            $recipientUser,
                            $notificationType,
                            $ledger, // subject は Ledger
                            $comments,
                            $folder,
                            $originalAssignee // originalAssignee を渡す
                        );
                    }
                }
            }

            return $ledger->refresh();
        });
    }
}
