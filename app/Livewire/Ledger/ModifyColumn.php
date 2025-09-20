<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;

use App\Enums\WorkflowStatus;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use App\Helpers\AttachedFilePathHelper;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ModifyColumn extends CreateColumn
{
    public array $deletedContent = [];


    // --- Workflow ---
    public bool $confirmingEdit = false; // 編集確認モーダルの表示状態

    public ?string $editReason = null; // 編集理由
    // ---------------

    public ?int $selectedInspectorId = null;
    public array $attachmentIdMap = []; // 添付ファイルのIDマップ
    public array $filePondInitialFiles = []; // FilePond初期化用

    public $tenantId='';

    public function mount(int $ledgerId): void
    {

        $this->ledgerId = $ledgerId;
        if ($this->ledgerId) {
            // edit
            $this->ledgerRecord = Ledger::with(['define', 'latestDiff'])->findOrFail($this->ledgerId);
            $this->ledgerDefineId = $this->ledgerRecord->ledger_define_id;
            if (!empty($this->ledgerRecord->define)) {
                $this->ledgerDefineRecord = $this->ledgerRecord->define;
                $this->totalRequireColumnCount = collect($this->ledgerDefineRecord->column_define)->filter(function ($column) {
                    return $column->required;
                })->count();
            }
            if (!empty($this->ledgerRecord->content)) {
                $this->content = $this->ledgerRecord->content;
            }
            if (!empty($this->ledgerRecord->content_attached)) {
                $this->contentAttached = $this->ledgerRecord->content_attached;
            }
            $this->initColumns(); // カラム初期化 (必須マーク色など)
            $this->initRequireColumns();
            $this->updateProgress();
            //            $this->loadRecommendedPersonnel(); // 推奨担当者を読み込む

            // --- Attachment ID マップの作成 ---
            $this->attachmentIdMap = AttachedFile::where('ledger_id', $this->ledgerId)
                ->pluck('id', 'hashedbasename')
                ->toArray();
            // --------------------------------

            $this->prepareFilePondInitialFiles(); // FilePond初期化
        }
        $this->initBackgroundImages();
        $this->initializeGroups(); // 親のグループ初期化メソッドを呼び出す

    }

    public function render(): View
    {
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $this->updateContentStatusLabel($column);
        }

        // 親クラスの render ロジックを再利用しつつ、ビューに渡すデータを準備
        $groupedColumns = collect($this->ledgerDefineRecord->column_define)
            ->groupBy(function ($column) {
                return $column->group ?? __('ledger.form.group_default');
            })
            ->sortBy(function ($columnsInGroup) {
                return $columnsInGroup->first()->order ?? PHP_INT_MAX;
            });

        return view('livewire.ledger.modify-column', [
            'groupedColumns' => $groupedColumns,
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

    /**
     * @param mixed $column
     * @return void
     */
    public function updateColumnFiles(mixed $column): void
    {
        $columnId = $column->id;

        // 新規アップロードされたファイル (TemporaryUploadedFileオブジェクト)
        $newUploads = collect($this->content[$columnId] ?? [])
            ->filter(fn($file) => $file instanceof TemporaryUploadedFile)
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

        // 既存ファイルの取得 (DBから再取得が安全)
        $tmpContent = $this->content[$column->id] ?? [];
        $tmpContentAttached = $this->contentAttached[$column->id] ?? [];

//        dd($column->id,$this->deletedContent ,$tmpContent);

        // 削除指定されたファイルを既存リストから除去
        $deletedBaseFilenames = $this->deletedContent[$column->id] ?? [];
        foreach ($tmpContent as $hashedBaseName => $originalName) {
            if (in_array($hashedBaseName, $deletedBaseFilenames, true)) {
                unset($tmpContent[$hashedBaseName]);
                unset($tmpContentAttached[$hashedBaseName]);
                // 実体ファイル削除や AttachedFile レコード削除はここで行う
                // AttachedFile::where('hashedbasename', $hashedBaseName)
                //     ->where('ledger_id', $this->ledgerId) // ledgerId を使う
                //     ->where('ledger_define_id', $this->ledgerDefineId) // ledgerDefineId も使う
                //     ->where('column_id', $column->id)
                //     ->delete();
            }
        }

        // TemporaryUploadedFile オブジェクトをフィルタリング
        $tmpContent = collect($tmpContent)->filter(fn($value) => !($value instanceof TemporaryUploadedFile))->all();
        $tmpContentAttached = collect($tmpContentAttached)->filter(fn($value) => !($value instanceof TemporaryUploadedFile))->all();

        // 新規ファイルと残った既存ファイルをマージ
        $this->content[$column->id] = array_merge($addedFilenames, $tmpContent);
        $this->contentAttached[$column->id] = array_merge($addedFileContents, $tmpContentAttached);
    }

    /**
     * 保存ボタンクリック時のアクション (フロー中の編集を考慮)
     */
    public function saveChanges(): void
    {
        // ワークフローが無効なら直接保存
        if (!$this->ledgerDefineRecord?->workflow_enabled) {
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
        if (in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
            $this->confirmingEdit = true; // 確認モーダル表示フラグを立てる

            // この時点ではまだ保存しない
            return;
        }

        // APPROVED 状態の場合はエラー (または何もしない)
        if ($currentStatus === WorkflowStatus::APPROVED) {
            $this->error(__('ledger.workflow.cannot_edit_approved')); // エラーメッセージ表示

            return;
        }

        // それ以外の予期せぬステータス
        Log::warning("Attempted to save ledger (ID: {$this->ledgerId}) with unexpected status: {$currentStatus?->value}");
        $this->error(__('messages.error.generic'));
    }

    /**
     * 編集確認モーダルで「保存して作成中に戻す」が押された後の処理
     */
    public function saveChangesAndReturnToDraft(): void
    {
        $this->validate(array_filter($this->rules(), fn($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));
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
            $this->confirmingEdit = false;
            $this->editReason = null;

        } catch (\Exception $e) {
            Log::error('Saving edited record failed: ' . $e->getMessage());
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

        $this->validate(array_filter($this->rules(), fn($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));
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
        } catch (\Exception $e) {
            Log::error('Draft save failed (modify): ' . $e->getMessage());
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
        // 1. この ModifyColumn インスタンスが処理すべきイベントかを確認
        //    ModifyColumn が関与する可能性のある actionType を具体的に指定する
        //    例: 'request_inspection_with_comment_for_modify', 'edit_and_return_to_draft' など
        //    ここでは、ModifyColumn が点検依頼のコメント付きアクションを処理すると仮定します。
        //    また、ledgerId が現在のコンポーネントの ledgerId と一致することも重要です。

        // この ModifyColumn インスタンスが対象とする ledgerId かどうかを確認
        if ($ledgerId !== $this->ledgerId) {
            Log::error("[ModifyColumn] Received 'workflow-action-with-comment' for a different ledger. Component Ledger ID: {$this->ledgerId}, Event Ledger ID: {$ledgerId}. Ignoring.");
            $this->error(__('messages.error.generic'));
            return; // 自分の担当する台帳でなければ無視
        }

        // この ModifyColumn インスタンスが処理すべき actionType かどうかを確認
        // CreateColumn と ModifyColumn で共通の actionType を使っている場合、
        // より具体的な条件や、ModifyColumn 独自の actionType を使うことを検討してください。
        // ここでは、ModifyColumn が 'request_inspection_with_comment' を処理するケースと、
        // フロー中編集の 'save_edited_record_with_reason' (仮) のようなものを想定します。

        // 例: 点検依頼のコメント付きアクションの場合 (CreateColumn と共通の可能性あり)
        if ($actionType === 'request_inspection_with_comment') {
            // さらに ModifyColumn 特有の条件があれば追加
            // (例: 現在のステータスが DRAFT であることなど)
            if ($this->ledgerRecord?->status !== WorkflowStatus::DRAFT) {
                Log::error("[ModifyColumn] Received 'request_inspection_with_comment' but status is not DRAFT. Ledger ID: {$this->ledgerId}. Ignoring.");
                $this->error(__('messages.error.generic'));
                return;
            }

            Log::debug("[ModifyColumn] Handling 'request_inspection_with_comment'. Ledger ID: {$ledgerId}, Comment: " . ($comment ?? 'N/A'));
//            dd("[ModifyColumn] Action: {$actionType}, Ledger ID: {$ledgerId}, Comment: " . ($comment ?? 'N/A'));

            // 親クラスのメソッドを呼び出すか、ModifyColumn 固有の処理を記述
            parent::handleRequestInspectionWithComment($actionType, $ledgerId, $comment);
            return; // 処理が終わったら抜ける
        }

        // 上記のどの条件にも当てはまらない場合は、この ModifyColumn インスタンスが処理すべきイベントではない
        Log::debug("[ModifyColumn] Received 'workflow-action-with-comment' with unhandled actionType: {$actionType} for Ledger ID: {$ledgerId}. Ignoring.");
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
            if (!empty($this->content[$columnId]) && is_array($this->content[$columnId])) {
                $loopIndex = 0;
                foreach ($this->content[$columnId] as $hashedBasename => $originalFilename) {
                    $attachmentId = $this->attachmentIdMap[$hashedBasename] ?? null;
                    /** @var AttachedFile|null $currentAttachedFile */
                    $currentAttachedFile = $attachmentId ? AttachedFile::find($attachmentId) : null;

                    $storagePath = '';
                    $displayMimeType = '';

                    if ($currentAttachedFile) {
                        // 処理失敗時はオリジナルファイルを参照
                        if (
                            in_array($currentAttachedFile->status->value, [
                                AttachedFileStatus::TIKA_FAILED->value,
                                AttachedFileStatus::OCR_FAILED->value,
                            ], true)
                        ) {
                            $storagePath = $currentAttachedFile->original_file_path;
                            $displayMimeType = $currentAttachedFile->original_mime_type;
                        } else {
                            // 成功時や処理中は、DBに保存されている現在のパスを正とする
                            $storagePath = $currentAttachedFile->path;
                            $displayMimeType = $currentAttachedFile->mime;
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

                    if ($fileExists) {
//                        $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($hashedBasename);
                        $posterUrl = route('file.download', ['attachedFile' => $attachmentId, 'thumbnail' => true]);
                    }

                    $fileObject = [
                        'source' => route('file.download', ['attachedFile' => $attachmentId]), // FilePondが直接ロードするURL
                        'options' => [
                            'type' => 'local',
                            'file' => [
                                'name' => $originalFilename,
                                'size' => $fileExists ? Storage::disk('public')->size($storagePath) : 0,
                                'type' => $fileExists ? Storage::disk('public')->mimeType($storagePath) : 'application/octet-stream',
                            ],
                            'metadata' => [
                                'filename' => $originalFilename,
                                'hashedBasename' => $hashedBasename,
                                'poster' => $posterUrl, // サムネイル/アイコンのURLを追加
                            ],
                        ],
                    ];
                    $filesForColumn[] = $fileObject;
//                    $loopIndex++;
                }
            }

            $this->filePondInitialFiles[$columnId] = $filesForColumn;
        }
    }
    /**
     * FilePond で既存ファイルが削除されたときにフロントエンドから呼び出される
     *
     * @param int $columnId 削除されたファイルが属するカラムのID
     * @param string $hashedBasename 削除されたファイルのハッシュ化されたベース名
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
