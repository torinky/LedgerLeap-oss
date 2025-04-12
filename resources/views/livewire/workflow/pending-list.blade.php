@php use App\Enums\WorkflowStatus; @endphp
<div>
    <x-slot name="header">
        <x-mary-header title="{{ __('ledger.workflow.pending_tasks') }}" separator progress-indicator/>
    </x-slot>

    <x-mary-card>
        {{-- テーブルヘッダーのラベルを翻訳キーに --}}
        <x-mary-table :headers="[
            ['key' => 'requester', 'label' => __('ledger.workflow.requester')],
            ['key' => 'requested_at', 'label' => __('ledger.workflow.requested_at')],
            ['key' => 'ledger_title', 'label' => __('ledger.title')],
            ['key' => 'status', 'label' => __('ledger.workflow.status.label')],
            ['key' => 'actions', 'label' => ''], // アクション列はラベル不要
        ]" :rows="$pendingTasks" striped {{-- @row-click は必要なら --}}>

            {{-- scope 内のテキストはモデルから取得しているので翻訳不要なことが多い --}}
            @scope('cell_requester', $task)
            {{ $task->creator->name ?? 'N/A' }}
            @endscope
            @scope('cell_requested_at', $task)
            {{ $task->requested_at?->isoFormat('YYYY/MM/DD HH:mm') }}
            @endscope
            @scope('cell_ledger_title', $task)
            {{ $task->ledger->define->title ?? 'N/A' }} (ID: {{ $task->ledger_id }})
            @endscope
            @scope('cell_status', $task)
            <x-mary-badge :value="$task->status->label()" class="badge-sm {{ $task->status->colorClass() }}"/>
            @endscope

            {{-- アクションボタンのラベルを翻訳キーに --}}
            @scope('actions', $task)
            <div class="flex justify-end gap-1">
                @if($task->status === WorkflowStatus::PENDING_INSPECTION && Auth::id() === $task->inspector_id /* && 権限チェック */)
                    {{-- 承認申請ボタンのラベル --}}
                    <x-mary-button label="{{ __('ledger.workflow.request_approval_short') }}" icon="o-check-badge"
                                   class="btn-sm btn-success" wire:click="openApprovalRequestModal({{ $task->id }})"
                                   spinner/>
                    {{-- 作成中に戻すボタンのラベル --}}
                    <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}" icon="o-arrow-uturn-left"
                                   class="btn-sm btn-warning"
                                   wire:click="$dispatch('open-modal', 'return-to-draft-modal-{{ $task->id }}')"
                                   spinner/>
                @elseif($task->status === WorkflowStatus::PENDING_APPROVAL && Auth::id() === $task->approver_id /* && 権限チェック */)
                    {{-- 承認ボタンのラベル --}}
                    <x-mary-button label="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                   class="btn-sm btn-primary" wire:click="approveTask({{ $task->id }})" spinner/>
                    {{-- 作成中に戻すボタンのラベル --}}
                    <x-mary-button label="{{ __('ledger.workflow.return_to_draft_short') }}" icon="o-arrow-uturn-left"
                                   class="btn-sm btn-warning"
                                   wire:click="$dispatch('open-modal', 'return-to-draft-modal-{{ $task->id }}')"
                                   spinner/>
                @endif
                {{-- 詳細確認ボタンのラベル --}}
                <x-mary-button label="{{ __('ledger.view_details') }}" icon="o-eye" class="btn-sm btn-ghost"
                               link="{{ route('ledger.show', ['ledgerId' => $task->ledger_id]) }}"/>
            </div>
            {{-- 戻し理由入力モーダルのタイトルとボタンラベル --}}
            <x-mary-modal id="return-to-draft-modal-{{ $task->id }}"
                          title="{{ __('ledger.workflow.return_to_draft_reason') }}"> {{-- 修正 --}}
                <x-mary-textarea label="{{ __('ledger.workflow.comments') }}"
                                 wire:model="returnComments.{{ $task->id }}"/> {{-- 修正 --}}
                <x-slot:actions>
                    <x-mary-button label="{{ __('Cancel') }}"
                                   @click="$wire.dispatch('close-modal')"/> {{-- 修正 (既存流用) --}}
                    <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                                   wire:click="returnTaskToDraft({{ $task->id }}, $wire.returnComments[{{ $task->id }}] ?? '')"
                                   spinner/> {{-- 修正 --}}
                </x-slot:actions>
            </x-mary-modal>
            @endscope
        </x-mary-table>

        {{-- 承認者選択モーダルのタイトルとボタンラベル --}}
        <x-mary-modal id="approval-request-modal" title="{{ __('ledger.workflow.select_next_approver') }}"> {{-- 修正 --}}
            <x-mary-select label="{{ __('ledger.workflow.next_approver') }}" :options="$this->getApproverOptions()"
                           wire:model="selectedApproverId"/> {{-- 修正 --}}
            <x-slot:actions>
                <x-mary-button label="{{ __('Cancel') }}" @click="$wire.dispatch('close-modal')"/> {{-- 修正 (既存流用) --}}
                <x-mary-button label="{{ __('ledger.workflow.request_approval') }}" class="btn-primary"
                               wire:click="requestApproval" spinner/> {{-- 修正 --}}
            </x-slot:actions>
        </x-mary-modal>

        {{-- ページネーション (必要なら) --}}
        {{-- <div class="mt-4"> {{ $pendingTasks->links() }} </div> --}}

    </x-mary-card>
</div>
