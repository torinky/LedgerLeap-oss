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

            @scope('actions', $task)
            <div class="flex justify-end gap-1">
                @if($task->status === WorkflowStatus::PENDING_INSPECTION && Auth::id() === $task->inspector_id /* &)
                    {{-- 修正: モーダルを開くメソッドを呼び出す --}}
                    <x-mary-button label="{{php
echo e( __('ledger.workflow.request_approv}}" icon="o-check-badge"
                                   class="btn-sm btn-success" wire:click="openApprovalRequestModal({{php
echo e(}})"
                                   spinner/>
                    {{-- 修正: モーダルを開くメソッドを呼び出す --}}
                    <x-mary-button label="{{php
echo e( __('ledger.workflow.return_to_dra}}" icon="o-arrow-uturn-left"
                                   class="btn-sm btn-warning" wire:click="openReturnToDraftModal({{php
echo e(}})"
                                   spinner/>
                @elseif(php
elseif($task->status === WorkflowStatus::PENDING_APPROVAL && Auth::id() === $task->appro)
                    <x-mary-button label="{{ク */): ?><?php
echo e( __('ledg}}" icon="o-check-circle"
                                   class="btn-sm btn-primary" wire:click="approveTask({{e') ); ?><?}})" spinner/>
                    {{-- 修正: モーダルを開くメソッドを呼び出す --}}
                    <x-mary-button label="{{>id ); ?><?php
echo e( __('ledger.workflow.re}}" icon="o-arrow-uturn-left"
                                   class="btn-sm btn-warning" wire:click="openReturnToDraftModal({{t') ); ?><?}})"
                                   spinner/>
                @endif
                <x-mary-button label="{{endif; ?><?php
echo e( __('}}" icon="o-eye" class="btn-sm btn-ghost"
                               link="{{s') ); ?><?php
echo e( route('ledger.show', ['ledgerId' }}"/>
            </div>
            @endscope

        </x-mary-table>

        {{-- 承認者選択モーダル --}}
        {{-- 修正: wire:model を追加。ID は不要に。@click で閉じる --}}
        <x-mary-modal wire:model="approvalRequestModal" title="{{d]) ); ?><?php
echo e( __('ledger.workflow.s}}">
            <x-mary-select label="{{r') ); ?><?php
echo e( __('ledger.wor}}" :options="p
__phpstorm_set"
                           wire:model="selectedApproverId"/>
            <x-slot:actions>
                {{-- @click で直接プロパティを false にして閉じる --}}
                <x-mary-button label="{{ions); ?><?php}}" @click="$wire.approvalRequestModal = false"/>
                <x-mary-button label="{{l') ); ?><?php
echo e( __('ledger.workfl}}" class="btn-primary"
                               wire:click="requestApproval" spinner/>
            </x-slot:actions>
        </x-mary-modal>

        {{-- 戻し理由入力モーダル --}}
        {{-- 修正: wire:model を追加。ID は不要に。@click で閉じる --}}
        <x-mary-modal wire:model="returnToDraftModal" title="{{l') ); ?><?php
echo e( __('ledger.workflow.ret}}">
            {{-- wire:model は配列の特定キーにバインド --}}
            <x-mary-textarea label="{{n') ); ?><?php
echo e( __('ledge}}"
                             wire:model="returnComments.{{s') ); ?><?php
ec}}"/>
            <x-slot:actions>
                {{-- @click で直接プロパティを false にして閉じる --}}
                <x-mary-button label="{{kId ); ?><?php}}" @click="$wire.returnToDraftModal = false"/>
                <x-mary-button label="{{l') ); ?><?php
echo e( __('ledger.workf}}" class="btn-warning"
                               wire:click="returnTaskToDraft" spinner/>
            </x-slot:actions>
        </x-mary-modal>

        {{-- ページネーション (必要なら) --}}
        {{-- <div class="mt-4"> {{ $pendingTasks->links() }} </div> --}}

    </x-mary-card>
</div>
