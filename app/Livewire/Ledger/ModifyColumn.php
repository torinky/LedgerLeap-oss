<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Http\Requests\Ledger\StoreRequest;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModifyColumn extends CreateColumn
{
    public array $deletedContent = [];

    private array $contentAttached = [];

    // --- Workflow ---
    public bool $confirmingEdit = false; // 編集確認モーダルの表示状態

    public ?string $editReason = null; // 編集理由
    // ---------------

    public ?int $selectedInspectorId = null;

    public function mount(request $request): void
    {

        $this->ledgerId = (int)$request->route('ledgerId');
        if ($this->ledgerId) {
            // edit
            $this->ledgerRecord = Ledger::with('define', 'latestDiff')->where('ledgers.id', $this->ledgerId)->firstOrFail();
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
            $this->initColumns(); // カラム初期化 (必須マーク色など)
            $this->initRequireColumns();
            $this->updateProgress();
            $this->loadRecommendedPersonnel(); // 推奨担当者を読み込む

            foreach ($this->ledgerDefineRecord->column_define as $column) {
                if ($column->type === 'files') {
                    $this->deletedContent[$column->id] = [];
                    $this->content[$column->id] = [];
                }
                if (!empty($this->content[$column->id])) {
                    $this->labelColor[$column->id] = 'success';
                } elseif ($column->required) {
                    $this->labelColor[$column->id] = 'warning';
                } else {
                    $this->labelColor[$column->id] = 'muted';
                }
            }
        }
        $this->initBackgroundImages();
    }

    public function render(): View
    {
        return view('livewire.ledger.modify-column');
    }

    /**
     * @throws Exception
     */
    /*    public function store(StoreRequest $request)
        {
            $this->validate();

            foreach ($this->ledgerDefineRecord->column_define as $column) {
                if ($column->type === 'files') {
                    $storedFiles = [];
                    foreach ($this->content[$column->id] as $uploadedFile) {
                        $stored = $this->storeFile($uploadedFile, $column->id);
                        $storedFiles[] = $stored;
                    }

                    $this->mergeContentFiles($column, $storedFiles);
                    //                dd($storedFiles,$this->content,$this->content_attached);
                }
            }
            $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
            $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);

            if ($this->ledgerId) {
                $this->storeLedgerDiff();

                $ledgerRecord = Ledger::find($this->ledgerId);
                $ledgerRecord->content = $this->content;
                $ledgerRecord->content_attached = $this->contentAttached;
                $ledgerRecord->modifier_id = Auth::user()->id;
                $ledgerRecord->save();

                $this->dispatch('ledgerStored', $this->ledgerRecord->id);

                $this->addAttachedFileRecord();
                $this->success(
                    __('ledger.updated.success'),
                    redirectTo: route('ledger.show', ['ledgerId' => $ledgerRecord->id])
                );

                return;
            }
            abort(404);
        }*/

    /**
     * @param [object] $addingStoredFiles
     */
    public function mergeContentFiles(mixed $column, $addingStoredFiles): void
    {
        $addedFilenames = [];
        $addedFileContents = [];
        foreach ($addingStoredFiles as $stored) {
            $addedFilenames[$stored->hashedBaseName] = $stored->originalName;
            $addedFileContents[$stored->hashedBaseName] = null;
        }

        // 既存ファイルの削除処理
        if (!empty($this->ledgerRecord->content[$column->id])) {
            /*
             * fileの保存状態
             * ['originalFilename'=>'savedFilePath']
             */
            $tmpContent = $this->ledgerRecord->content[$column->id] ?? [];
            $tmpContentAttached = $this->ledgerRecord->content_attached[$column->id] ?? [];

            $deletedBaseFilenames = [];
            //            パスがついているのでファイル名を取得
            foreach ($this->deletedContent[$column->id] as $deletedFilePath) {
                $deletedBaseFilenames[] = basename($deletedFilePath);
            }
            foreach ($this->ledgerRecord->content[$column->id] as $hashedBaseName => $filepath) {
                if (in_array($hashedBaseName, $deletedBaseFilenames, true)) {
                    unset($tmpContent[$hashedBaseName], $tmpContentAttached[$hashedBaseName]);
                    // 実体ファイルを消したければここに削除処理を追加
                    AttachedFile::where('hashedbasename', $hashedBaseName)
                        ->where('ledger_id', $this->ledgerRecord->id)
                        ->where('ledger_define_id', $this->ledgerRecord->ledger_define_id)
                        ->where('column_id', $column->id)
                        ->delete();
                }
            }
            // 以前保存したファイルとのマージ
            $this->content[$column->id] = array_merge($addedFilenames, $tmpContent);
            $this->contentAttached[$column->id] = array_merge($addedFileContents, $tmpContentAttached);
        } else {
            $this->content[$column->id] = $addedFilenames;
            $this->contentAttached[$column->id] = $addedFileContents;

        }
    }

    private function getThumbnailUrl($filename): string
    {
        return Storage::url('Ledger/thumbs/' . basename($filename));
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
    protected function mergeFilesForSave(object $column, array $storedFiles): void
    {
        $addedFilenames = [];
        $addedFileContents = [];
        foreach ($storedFiles as $stored) {
            $addedFilenames[$stored->hashedBaseName] = $stored->originalName;
            $addedFileContents[$stored->hashedBaseName] = null;
        }

        // 既存ファイルの取得 (DBから再取得が安全)
        $existingLedger = Ledger::find($this->ledgerId);
        $tmpContent = $existingLedger?->content[$column->id] ?? [];
        $tmpContentAttached = $existingLedger?->content_attached[$column->id] ?? [];

        // 削除指定されたファイルを既存リストから除去
        $deletedBaseFilenames = [];
        foreach ($this->deletedContent[$column->id] ?? [] as $deletedFilePath) {
            $deletedBaseFilenames[] = basename($deletedFilePath);
        }
        foreach ($tmpContent as $hashedBaseName => $originalName) { // $filepath ではなく $originalName
            if (in_array($hashedBaseName, $deletedBaseFilenames, true)) {
                unset($tmpContent[$hashedBaseName], $tmpContentAttached[$hashedBaseName]);
                // 実体ファイル削除や AttachedFile レコード削除はここで行う
                AttachedFile::where('hashedbasename', $hashedBaseName)
                    ->where('ledger_id', $this->ledgerId) // ledgerId を使う
                    ->where('ledger_define_id', $this->ledgerDefineId) // ledgerDefineId も使う
                    ->where('column_id', $column->id)
                    ->delete();
            }
        }

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
        if (!$this->isWorkflowEnabled) {
            $this->saveDirectly(); // 親クラスの直接保存メソッド呼び出し
            return;
        }
        // 現在のステータスを確認
        $currentStatus = $this->ledgerRecord?->status;

        // DRAFT またはワークフロー無効の場合は、下書き保存を実行
//        if ($currentStatus === WorkflowStatus::DRAFT || !$this->ledgerDefineRecord?->workflow_enabled) {
        if ($currentStatus === WorkflowStatus::DRAFT) {
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
            Log::error("Saving edited record failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }


    // --- 親クラスから継承・オーバーライドするメソッド ---

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

    // 点検依頼 (Modify 時も基本的に同じだが、権限や状態チェックが必要)
    public function requestInspection(): void
    {
        if ($this->ledgerRecord?->status !== WorkflowStatus::DRAFT) {
            $this->error(__('ledger.workflow.cannot_request_inspection_approved'));

            return;
        }

        // 承認済みならエラー
        if ($this->ledgerRecord?->isLocked()) {
            $this->error(__('ledger.workflow.cannot_request_inspection_approved'));

            return;
        }
        // ステータスが DRAFT でなければエラー (フロー中編集は saveChanges 経由)
        if ($this->ledgerRecord?->status !== WorkflowStatus::DRAFT) {
            $this->error(__('ledger.workflow.can_request_inspection_only_from_draft'));

            return;
        }

        // バリデーション
        $validated = $this->validate(array_merge(
            array_filter($this->rules(), fn($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY),
            ['selectedInspectorId' => ['required', 'integer', 'exists:users,id']]
        ));

        $userId = Auth::id();
        $this->processFilesForSave();

        try {
            $result = $this->workflowService->requestInspection(
                $this->ledgerId, // 既存 ID を渡す
                $userId,
                $validated['selectedInspectorId']
            );
            $this->ledgerRecord = $result['ledger']; // ステータスが更新されたレコードを反映
            $this->addAttachedFileRecordIfNecessary();
            $this->success(__('ledger.workflow.inspection_requested_message'),
                redirectTo: route('ledger.show', ['ledgerId' => $this->ledgerId]));
        } catch (\Exception $e) {
            Log::error('Inspection request failed (modify): ' . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }
}
