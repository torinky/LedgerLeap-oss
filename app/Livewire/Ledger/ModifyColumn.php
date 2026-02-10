<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Enums\WorkflowStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Policies\LedgerDefinePolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ModifyColumn extends CreateColumn
{
    use InitializesTenantContext;

    public array $deletedContent = [];

    // --- Workflow ---
    public bool $confirmingEdit = false; // 編集確認モーダルの表示状態

    public ?string $editReason = null; // 編集理由
    // ---------------

    public ?int $selectedInspectorId = null;

    public array $attachmentIdMap = []; // 添付ファイルのIDマップ

    public array $filePondInitialFiles = []; // FilePond初期化用

    //    public $tenantId='';

    public function mount(int $ledgerId, LedgerDefinePolicy $ledgerDefinePolicy, array $prefillParams = []): void
    {
        // ModifyColumnではprefillParamsは使用しないが、親クラスとの互換性のために引数として受け取る
        $this->ledgerId = $ledgerId;
        if ($this->ledgerId) {
            // edit
            // テナントスコープを一時的に無視してLedgerを取得
            $ledgerRecord = Ledger::withoutTenancy()->findOrFail($this->ledgerId);

            // テナントコンテキストを初期化 (テナント間移動への対応)
            // InitializesTenantContextトレイトは未初期化時のみ動作するため、
            // 既に別のテナントで初期化されている場合(管理者が別テナントのデータを編集する場合など)は
            // ここで明示的にコンテキストを切り替える必要がある。
            if ($ledgerRecord && $ledgerRecord->tenant_id !== tenancy()->tenant?->id) {
                \Illuminate\Support\Facades\Log::info('ModifyColumn: Switching tenant', [
                    'from' => tenancy()->tenant?->id,
                    'to' => $ledgerRecord->tenant_id,
                ]);
                tenancy()->initialize($ledgerRecord->tenant_id);
            }

            // 権限チェックのために、LedgerDefineもテナントスコープを無視して取得
            $ledgerDefineRecord = $ledgerRecord
                ? LedgerDefine::withoutTenancy()->find($ledgerRecord->ledger_define_id)
                : null;

            // 権限チェック: ユーザーがこの台帳を閲覧できるか確認
            if (! $ledgerDefineRecord || ! $ledgerDefinePolicy->ledgerView(Auth::user(), $ledgerDefineRecord)) {
                throw new ModelNotFoundException('Ledger not found or user does not have permission.');
            }

            // 権限チェックをパスした場合のみ、コンポーネントのプロパティを設定
            $this->ledgerRecord = $ledgerRecord;
            $this->ledgerDefineRecord = $ledgerDefineRecord;

            // 手動でリレーションをロード
            $this->ledgerRecord->load(['latestDiff']);

            $this->tenantId = $this->ledgerRecord->tenant_id; // tenant_id は define からではなく ledger から取得
            $this->ledgerDefineId = $this->ledgerRecord->ledger_define_id;

            // DBから取得したデータを正規化（二重エンコード等の破損データへの耐性を持たせる）
            $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->ledgerRecord->content ?? []);
            $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->ledgerRecord->content_attached ?? []);

            // 親クラスの初期化メソッドを呼び出す
            $this->initColumns();
            $this->initRequireColumns();
            $this->initializeDateDefaults();
            $this->updateProgress();
            $this->initBackgroundImages();
            $this->initializeGroups();

            // --- Attachment ID マップの作成 ---
            $this->attachmentIdMap = AttachedFile::where('ledger_id', $this->ledgerId)
                ->pluck('id', 'hashedbasename')
                ->toArray();
            // --------------------------------

            $this->prepareFilePondInitialFiles(); // FilePond初期化
        }
    }

    public function render(): View
    {
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $this->updateContentStatusLabel($column);
        }

        return view('livewire.ledger.modify-column', [
            'groupedColumns' => $this->getGroupedColumns(),
        ]);
    }

    public function storeLedgerDiff(): void
    {
        $ledgerDiff = new LedgerDiff;
        $ledgerDiff->timestamps = false;
        $ledgerDiff->create([
            'content' => $this->ledgerRecord->content,
            'column_define' => $this->ledgerDefineRecord->column_define,
            'ledger_id' => $this->ledgerRecord->id,
            'ledger_define_id' => $this->ledgerDefineRecord->id,
            'modifier_id' => $this->ledgerRecord->modifier_id,
            'creator_id' => $this->ledgerRecord->creator_id,
            'created_at' => $this->ledgerRecord->created_at,
            'updated_at' => $this->ledgerRecord->updated_at,
        ]);
    }

    // Modify 用のファイルマージ処理をオーバーライド

    public function updateColumnFiles(mixed $column): void
    {
        $columnId = $column->id;

        // 新規アップロードされたファイル (TemporaryUploadedFileオブジェクト)
        $newUploads = collect($this->content[$columnId] ?? [])
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
            ->all();

        // 画面上で削除されずに残っている既存ファイル
        $existingFiles = collect($this->ledgerRecord->content[$columnId] ?? [])
            ->reject(function ($originalFilename, $hashedBasename) use ($columnId) {
                return in_array($hashedBasename, $this->deletedContent[$columnId] ?? [], true);
            })
            ->all();

        // バリデーションのために、新規ファイルと既存ファイルを結合したものを content にセットする
        // これにより、ファイルが必須の場合でも、既存ファイルがあればバリデーションをパスする
        // 注意: ここでセットするのはバリデーション目的。実際の保存処理は processFilesForSave で行う。
        $this->content[$columnId] = array_merge($newUploads, $existingFiles);
    }

    protected function mergeFilesForSave(object $column, array $storedFiles): void
    {
        $addedFilenames = [];
        $addedFileContents = [];
        foreach ($storedFiles as $stored) {
            $addedFilenames[$stored->hashedBaseName] = $stored->originalName;
            // content_attached の構造を維持するため、meta と content のプレースホルダーを追加
            $addedFileContents[$stored->hashedBaseName] = ['meta' => ['content' => '']];
        }

        // 既存ファイルの取得 (DBのレコードを正とする)
        $originalLedgerContentForColumn = $this->ledgerRecord->content[$column->id] ?? [];
        $originalLedgerContentAttachedForColumn = $this->ledgerRecord->content_attached[$column->id] ?? [];

        // 画面の最新状態 (TemporaryUploadedFile を含む可能性あり)
        $currentContentForColumn = $this->content[$column->id] ?? [];
        $currentContentAttachedForColumn = $this->contentAttached[$column->id] ?? [];

        // 削除指定されたファイルを処理
        $deletedBaseFilenames = $this->deletedContent[$column->id] ?? [];
        foreach ($originalLedgerContentForColumn as $hashedBasename => $originalName) {
            if (in_array($hashedBasename, $deletedBaseFilenames, true)) {
                // AttachedFileレコードを論理削除
                $attachedFile = AttachedFile::where('hashedbasename', $hashedBasename)
                    ->where('ledger_id', $this->ledgerId)
                    ->where('column_id', $column->id)
                    ->first();

                if ($attachedFile) {
                    $attachedFile->delete();
                }
            }
        }

        // 画面上で残っているファイル（TemporaryUploadedFile を除く）を取得
        // ここでは $currentContentForColumn から削除対象を除外する
        $remainingFiles = collect($currentContentForColumn)
            ->filter(function ($value, $hashedBasename) use ($deletedBaseFilenames) {
                return ! ($value instanceof TemporaryUploadedFile) &&
                    ! in_array($hashedBasename, $deletedBaseFilenames, true);
            })
            ->all();

        $remainingFilesAttached = collect($currentContentAttachedForColumn)
            ->filter(function ($value, $hashedBasename) use ($deletedBaseFilenames) {
                return ! ($value instanceof TemporaryUploadedFile) &&
                    ! in_array($hashedBasename, $deletedBaseFilenames, true);
            })
            ->all();

        // 新規ファイルと残った既存ファイルをマージ
        $this->content[$column->id] = array_merge($addedFilenames, $remainingFiles);
        $this->contentAttached[$column->id] = array_merge($addedFileContents, $remainingFilesAttached);
    }

    /**
     * 保存ボタンクリック時のアクション (フロー中の編集を考慮)
     */
    public function saveChanges(): void
    {
        // ワークフローが無効なら直接保存
        if (! $this->ledgerDefineRecord?->workflow_enabled) {
            $this->saveDirectly(); // 親クラスの直接保存メソッド呼び出し

            return;
        }
        // 現在のステータスを確認
        $currentStatus = $this->ledgerRecord?->status;

        // DRAFT またはワークフロー無効の場合は、下書き保存を実行
        if ($currentStatus === WorkflowStatus::DRAFT || $currentStatus === WorkflowStatus::NONE) {
            $this->saveDraft(); // 親クラスの saveDraft を呼び出す

            return;
        }

        // フロー中の場合 (PENDING_*) は確認モーダルを表示
        $pendingStatuses = [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL];
        if (in_array($currentStatus, $pendingStatuses)) {
            $this->confirmingEdit = true; // 確認モーダル表示フラグを立てる

            return;
        }

        // APPROVED 状態の場合はエラー (または何もしない)
        if ($currentStatus === WorkflowStatus::APPROVED) {
            $this->error(__('ledger.workflow.cannot_edit_approved'));

            return;
        }

        // それ以外の予期せぬステータス
        Log::warning(
            "Attempted to save ledger (ID: {$this->ledgerId}) with unexpected status: {$currentStatus?->value}"
        );
        $this->error(__('messages.error.generic'));
    }

    /**
     * 編集確認モーダルで「保存して作成中に戻す」が押された後の処理
     */
    public function saveChangesAndReturnToDraft(): void
    {
        $contentRules = array_filter(
            $this->rules(),
            fn ($key) => str_starts_with($key, 'content.'),
            ARRAY_FILTER_USE_KEY
        );
        $this->validate($contentRules);
        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            // 修正: WorkflowService の saveEditedRecord メソッドを呼び出す
            $result = $this->workflowService->saveEditedRecord(
                $this->ledgerRecord, // 現在の Ledger オブジェクト
                $this->content, // 編集後の content
                $this->contentAttached, // 編集後の content_attached
                $userId,
                $this->editReason // 入力された理由
            );

            // プロパティ更新
            $this->ledgerRecord = $result['ledger']; // 更新された Ledger

            $this->addAttachedFileRecordIfNecessary(); // 必要なら実行
            $this->success(__('ledger.workflow.returned_to_draft_message'));
            $this->js(
                "if (window.opener && !window.opener.closed) { window.opener.Livewire.dispatch('ledgerStored'); }"
            );
            $this->confirmingEdit = false;
            $this->editReason = null;
        } catch (\Exception $e) {
            Log::error('Saving edited record failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    // --- 親クラスから継承・オーバーライドするメソッド ---

    /**
     * バリデーションの前に、ファイルが変更されていない場合でも
     * 既存のファイル情報を content プロパティにマージする。
     */
    protected function prepareForValidation($attributes)
    {
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type === 'files') {
                $this->updateColumnFiles($column);
            }
        }

        return $attributes;
    }

    /**
     * 下書きとして保存する処理
     *
     * 承認済みのレコードである場合はエラーを表示し、それ以外の場合は下書きの保存を実行します。
     */
    public function saveDraft(): void
    {
        // 承認済みならエラー
        if ($this->ledgerRecord?->isLocked()) {
            $this->error(__('ledger.workflow.cannot_edit_approved'));

            return;
        }

        $contentRules = array_filter(
            $this->rules(),
            fn ($key) => str_starts_with($key, 'content.'),
            ARRAY_FILTER_USE_KEY
        );
        $this->validate($contentRules);
        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            $result = $this->workflowService->saveDraft(
                $this->ledgerId, // 既存 ID を渡す
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );
            $this->ledgerRecord = $result['ledger']; // 更新されたレコードを反映
            $this->addAttachedFileRecordIfNecessary();
            $this->success(__('ledger.draft_saved'));
            $this->js(
                "if (window.opener && !window.opener.closed) { window.opener.Livewire.dispatch('ledgerStored'); }"
            );
        } catch (\Exception $e) {
            Log::error('Draft save failed (modify): '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    // --- 点検依頼ボタンのアクション (親クラスとほぼ同じだが下書き保存は不要) ---
    public function requestInspection(): void
    {
        // ステータスが DRAFT でなければ依頼できない
        if ($this->ledgerRecord?->status !== WorkflowStatus::DRAFT) {
            $this->error(__('ledger.workflow.can_request_inspection_only_from_draft'));

            return;
        }
        parent::requestInspection();
        // 下書き保存と併せて申請する場合に対応
        //        $this->saveDraft();
        // モーダルを開く
        //        $this->openAssigneeModal('inspector');
    }

    #[On('workflow-action-with-comment')]
    public function handleRequestInspectionWithComment(string $actionType, int $ledgerId, ?string $comment): void
    {
        // 1. この ModifyColumn インスタンスが対象とする ledgerId かどうかを確認
        if ($ledgerId !== $this->ledgerId) {
            Log::error(
                "[ModifyColumn] Received 'workflow-action-with-comment' for a different ledger. ".
                "Component Ledger ID: {$this->ledgerId}, Event Ledger ID: {$ledgerId}. Ignoring."
            );
            $this->error(__('messages.error.generic'));

            return;
        }

        // 例: 点検依頼のコメント付きアクションの場合
        if ($actionType === 'request_inspection_with_comment') {
            if ($this->ledgerRecord?->status !== WorkflowStatus::DRAFT) {
                Log::error(
                    "[ModifyColumn] Received 'request_inspection_with_comment' but status is not DRAFT. ".
                    "Ledger ID: {$this->ledgerId}. Ignoring."
                );
                $this->error(__('messages.error.generic'));

                return;
            }

            parent::handleRequestInspectionWithComment($actionType, $ledgerId, $comment);

            return;
        }

        $this->error(__('messages.error.generic'));
    }

    // --- ワークフロー無効時の直接保存 (親クラスのメソッドを呼び出す) ---
    public function saveDirectly(): void
    {
        // 承認済みチェック
        if ($this->ledgerRecord?->isLocked()) {
            $this->error(__('ledger.workflow.cannot_edit_approved'));

            return;
        }
        // バリデーション、ファイル処理は親クラスで行われる
        parent::saveDirectly();
    }

    public function prepareFilePondInitialFiles(): void
    {
        $this->filePondInitialFiles = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type !== 'files') {
                continue;
            }

            $columnId = $column->id;
            $filesForColumn = [];

            // contentのキー（hashedBasename）を元に、attachmentIdMapからIDを取得し、
            // FilePondが要求する 'source' をキーとするオブジェクトの配列に変換する
            if (! empty($this->content[$columnId]) && is_array($this->content[$columnId])) {
                foreach ($this->content[$columnId] as $hashedBasename => $originalFilename) {
                    $attachmentId = $this->attachmentIdMap[$hashedBasename] ?? null;
                    /** @var AttachedFile|null $currentAttachedFile */
                    $currentAttachedFile = $attachmentId ? AttachedFile::find($attachmentId) : null;

                    $storagePath = '';

                    if ($currentAttachedFile) {
                        // 処理失敗時はオリジナルファイルを参照
                        $failedStatuses = [
                            AttachedFileStatus::TIKA_FAILED->value,
                            AttachedFileStatus::OCR_FAILED->value,
                        ];
                        if (in_array($currentAttachedFile->status->value, $failedStatuses, true)) {
                            $storagePath = $currentAttachedFile->original_file_path;
                        } else {
                            // 成功時や処理中は、DBに保存されている現在のパスを正とする
                            $storagePath = $currentAttachedFile->path;
                        }
                    } else {
                        // 念のため、AttachedFileレコードが見つからない場合のフォールバック
                        $storagePath = AttachedFilePathHelper::getAttachmentPath(
                            $this->ledgerDefineId,
                            $hashedBasename
                        );
                    }

                    $fileExists = $storagePath && Storage::disk('public')->exists($storagePath);
                    $posterUrl = '';
                    $isIconFlag = false; // デフォルトはfalse（画像扱い）

                    if ($fileExists) {
                        // MIMEタイプを取得（AttachedFileレコードまたはストレージから）
                        $mimeType = $currentAttachedFile?->mime ?? Storage::disk('public')->mimeType($storagePath);

                        // サムネイルが存在するか、または画像ファイル（ImagePreviewプラグインが処理）であるかを確認
                        $isImage = str_starts_with($mimeType, 'image/');
                        $thumbnailPath = $currentAttachedFile ? AttachedFilePathHelper::getThumbnailStoragePath($currentAttachedFile->hashedbasename, $currentAttachedFile->tenant_id) : '';
                        $hasThumbnail = $thumbnailPath && Storage::disk('public')->exists($thumbnailPath);

                        if ($isImage || $hasThumbnail) {
                            $posterUrl = route('file.download', [
                                'tenant' => $this->tenantId,
                                'attachedFile' => $attachmentId,
                                'thumbnail' => true,
                            ]);
                            $isIconFlag = false;
                        } else {
                            // サムネイルがない非画像ファイルについては、アイコンURLを直接セットしてリダイレクトを避ける
                            $posterUrl = route('api.fontawesome.icon.by_mime', ['type' => $mimeType]);
                            $isIconFlag = true;
                        }
                    }

                    $fileObject = [
                        'source' => route('file.download', [
                            'tenant' => $this->tenantId,
                            'attachedFile' => $attachmentId,
                        ]),
                        'options' => [
                            'type' => 'local',
                            'file' => [
                                'name' => $originalFilename,
                                'size' => $fileExists ? Storage::disk('public')->size($storagePath) : 0,
                                'type' => $fileExists
                                    ? Storage::disk('public')->mimeType($storagePath)
                                    : 'application/octet-stream',
                            ],
                            'metadata' => [
                                'filename' => $originalFilename,
                                'hashedBasename' => $hashedBasename,
                                'poster' => $posterUrl,
                                'is_icon' => $isIconFlag,
                            ],
                        ],
                    ];
                    $filesForColumn[] = $fileObject;
                }
            }

            $this->filePondInitialFiles[$columnId] = $filesForColumn;
        }
    }

    /**
     * FilePond で既存ファイルが削除されたときにフロントエンドから呼び出される
     *
     * @param  int  $columnId  削除されたファイルが属するカラムのID
     * @param  string  $hashedBasename  削除されたファイルのハッシュ化されたベース名
     */
    public function handleFileRemoval(int $columnId, string $hashedBasename): void
    {
        // 1. 削除リストにファイルを追加
        $this->deletedContent[$columnId][] = $hashedBasename;
        // 重複を削除
        $this->deletedContent[$columnId] = array_unique($this->deletedContent[$columnId]);

        // 2. バリデーション用に content プロパティを更新
        $column = collect($this->ledgerDefineRecord->column_define)->firstWhere('id', $columnId);
        if ($column) {
            $this->updateColumnFiles($column);
        }

        // 3. ラベルの色を更新
        $this->updateContentStatusLabel($column, true); // trueで強制更新
    }
}
