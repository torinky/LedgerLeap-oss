<?php

namespace App\Livewire\LedgerDefine;

use App\Enums\WorkflowStatus;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasFolderTree;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Renderless;
use Mary\Traits\Toast;

class Edit extends BaseLivewireComponent
{
    use HasFolderTree, InitializesTenantContext, Toast;

    public $ledgerDefineRecord;

    public $createDescription;

    public $detailDescription;

    public $listDescription;

    public $title;

    public $parentFolderId;

    public $descriptionGroup = 'createDescription';

    public bool $workflow_enabled = false;

    public function render()
    {
        return view('livewire.ledger-define.edit');
    }

    /**
     * リクエストに基づいてコンポーネントの状態を初期化します。
     *
     * このメソッドは、台帳定義レコードの詳細やフォルダ構造のマッピングを含む、
     * コンポーネントのさまざまなプロパティを設定します。また、階層的なフォルダデータを処理し、
     * フォルダIDとそれに対応する名前や選択状態のマップを構築します。
     *
     * @param  \Illuminate\Http\Request  $request  入力やルートデータを含むリクエスト。
     */
    public function mount(request $request)
    {
        if ($request->input('fromCreate')) {
            $this->dispatch('reloadParentWindow');
        }

        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int) $request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->title = $this->ledgerDefineRecord->title;
        $this->parentFolderId = $this->ledgerDefineRecord->folder_id;
        $this->createDescription = $this->ledgerDefineRecord->create_description;
        $this->listDescription = $this->ledgerDefineRecord->list_description;
        $this->detailDescription = $this->ledgerDefineRecord->detail_description;
        $this->workflow_enabled = (bool) $this->ledgerDefineRecord->workflow_enabled;

        $this->workflow_enabled = (bool) $this->ledgerDefineRecord->workflow_enabled;

        $this->initializeFolderTree($this->parentFolderId);
    }

    public function store(): void
    {
        // --- ステップ5: ワークフロー設定変更時の処理 ---
        $originalWorkflowEnabled = (bool) $this->ledgerDefineRecord->getOriginal('workflow_enabled');
        $newWorkflowEnabled = $this->workflow_enabled;

        // 有効 -> 無効 に変更された場合の処理
        if ($originalWorkflowEnabled === true && $newWorkflowEnabled === false) {
            // 関連する進行中 Ledger のステータスを NONE に変更
            // 大量データの場合に備え、チャンクで処理するか、ジョブに投入を検討
            try {
                $count = Ledger::where('ledger_define_id', $this->ledgerDefineRecord->id)
                    ->whereIn('status', [
                        //                        WorkflowStatus::DRAFT,
                        WorkflowStatus::PENDING_INSPECTION,
                        WorkflowStatus::PENDING_APPROVAL,
                    ])
                    ->update([
                        'status' => WorkflowStatus::NONE,
                        //                        'inspector_id' => null, // 担当者等もクリア
                        //                        'approver_id' => null,
                        //                        'requested_at' => null,
                        //                        'inspected_at' => null,
                        //                        'approved_at' => null,
                        //                        'returned_at' => null,
                        //                        'comments' => null,
                        'modifier_id' => auth()->id(), // 操作者を記録
                    ]);
                if ($count > 0) {
                    $this->info(__('ledger.define.workflow_disabled_and_reset', ['count' => $count])); // メッセージ表示
                    // ToDo: 関係者への通知イベント発行 (ステップ6)
                    // event(new WorkflowDisabledEvent($this->ledgerDefineRecord));
                }
            } catch (\Exception $e) {
                Log::error("Failed to reset workflow status for LedgerDefine ID: {$this->ledgerDefineRecord->id}. Error: ".$e->getMessage());
                // エラー処理 (トースト表示など)
                $this->error(__('messages.error.generic'));

                return; // 保存処理を中断
            }
        }
        $this->ledgerDefineRecord->title = $this->title;
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->ledgerDefineRecord->create_description = $this->createDescription;
        $this->ledgerDefineRecord->list_description = $this->listDescription;
        $this->ledgerDefineRecord->detail_description = $this->detailDescription;
        $this->ledgerDefineRecord->workflow_enabled = $this->workflow_enabled;
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();
        $this->success(__('ledger.has_been_updated'));
        $this->dispatch('ledgerDefineRecordStored');

        // イベントを発行
        //        $this->dispatch('ledgerDefineRecordStored');
    }

    #[Renderless]
    public function toggleDescriptionGroup($name)
    {
        $this->descriptionGroup = $name;
        $this->dispatch('toggleDescriptionGroup', name: $name);

    }
}
