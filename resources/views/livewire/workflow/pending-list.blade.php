@php use App\Enums\WorkflowStatus; @endphp
<div>

    <x-mary-card :title="__('ledger.workflow.pending_tasks')">
        {{-- テーブルヘッダーのラベルを翻訳キーに --}}
        {{--        // 修正: 申請日時は latestDiff から取得 or updated_at を使う？ -> updated_at が現実的か--}}
        <x-mary-table class="table-sm w-full table-zebra overflow-x-auto"
                      :headers="[
            ['key' => 'requester', 'label' => __('ledger.workflow.requester')],
            ['key' => 'updated_at', 'label' => __('ledger.workflow.last_updated_at')],
            ['key' => 'ledger_title', 'label' => __('ledger.title')],
            ['key' => 'status_and_progress', 'label' => __('ledger.workflow.status.label')],
            ['key' => 'actions', 'label' => ''],
            ]"
                      :rows="$pendingTasks" striped
                      wire:key="pending-tasks-table"
        >

            @scope('cell_requester', $ledger)
            {{ $ledger->creator->name ?? 'N/A' }}
            @endscope

            @scope('cell_updated_at', $ledger)
            {{-- Ledger の updated_at を表示 (これが最終アクション日時のはず) --}}
            {{ $ledger->updated_at?->isoFormat('YYYY/MM/DD HH:mm') }}
            @endscope

            @scope('cell_ledger_title', $ledger) {{-- $task を $ledger に変更 --}}
            {{ $ledger->define->title ?? 'N/A' }} (ID: {{ $ledger->id }}) {{-- ledger_id ではなく id --}}
            @endscope

            @scope('cell_status_and_progress', $ledger) {{-- スコープ名変更 --}}
            <div class="space-2 grid grid-cols-1">
                <x-mary-badge :value="$ledger->status->label()" class="badge-sm {{ $ledger->status->colorClass() }}"/>
                {{-- 必須ロール進捗サマリー表示 --}}
                @if($ledger->required_roles_progress_summary)
                    @php $progress = $ledger->required_roles_progress_summary; @endphp
                    {{-- 点検進捗 --}}
                    @if($progress['inspection_total'] > 0)

                        <span class="tooltip tooltip-left">
                            <div class="tooltip-content p-2 space-y-2 text-sm">
{{--                                <div class="h3"> {{ __('ledger.workflow.required_inspector_roles') }}:</div>--}}
                                <strong>{{__('ledger.workflow.inspection_completed')}}:</strong>
                                @foreach($progress['inspection_completed_roles_names'] as $role)
                                    <span class="badge badge-success badge-sm">{{ trim($role) }}</span>
                                @endforeach
                                @if($progress['inspection_completed_roles_names']->isEmpty())
                                    {{ __('ledger.none') }}
                                @endif
                                <br>
                                <strong>{{__('ledger.workflow.inspection_pending')}}:</strong>
                                @foreach($progress['inspection_pending_roles_names'] as $role)
                                    <span class="badge badge-warning badge-sm">{{ trim($role) }}</span>
                                @endforeach
                                @if($progress['inspection_pending_roles_names']->isEmpty())
                                    {{ __('ledger.none') }}
                                @endif
                            </div>
                            <x-mary-icon
                                    name="{{ $progress['inspection_all_completed'] ? 'o-check-circle' : 'o-ellipsis-horizontal-circle' }}"
                                    class="w-4 h-4 {{ $progress['inspection_all_completed'] ? 'text-success' : 'text-warning' }}"/>
                            <span class="text-xs">{{ $progress['inspection_completed'] }}/{{ $progress['inspection_total'] }}</span>
                        </span>
                    @endif
                    {{-- 承認進捗 --}}
                    @if($progress['approval_total'] > 0)

                        <span class="tooltip tooltip-left">
                            <div class="tooltip-content p-2 space-y-2 text-sm">
{{--                                <div class="h3"> {{ __('ledger.workflow.required_approver_roles') }}:</div>--}}
                                <strong>{{__('ledger.workflow.approval_completed')}}:</strong>
                                @foreach($progress['approval_completed_roles_names'] as $role)
                                    <span class="badge badge-success badge-sm">{{ trim($role) }}</span>
                                @endforeach
                                @if($progress['approval_completed_roles_names']->isEmpty())
                                    {{ __('ledger.none') }}
                                @endif
                                <br>
                                <strong>{{__('ledger.workflow.approval_pending')}}:</strong>
                                @foreach($progress['approval_pending_roles_names'] as $role)
                                    <span class="badge badge-warning badge-sm">{{ trim($role) }}</span>
                                @endforeach
                                @if($progress['approval_pending_roles_names']->isEmpty())
                                    {{ __('ledger.none') }}
                                @endif
                            </div>
                            <x-mary-icon
                                    name="{{ $progress['approval_all_completed'] ? 'o-check-circle' : 'o-ellipsis-horizontal-circle' }}"
                                    class="w-4 h-4 {{ $progress['approval_all_completed'] ? 'text-success' : 'text-warning' }}"/>
                            <span class="text-xs">{{ $progress['approval_completed'] }}/{{ $progress['approval_total'] }}</span>
                        </span>
                    @endif
                    @if($ledger->status === WorkflowStatus::APPROVED && (!$progress['inspection_all_completed'] || !$progress['approval_all_completed']))
                        <span class="tooltip" data-tip="{{ __('ledger.workflow.required_roles_not_completed') }}">
                                <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 text-error"/>
                        </span>
                    @endif
                @endif
            </div>

            @endscope

            @scope('actions', $ledger) {{-- $task を $ledger に変更 --}}
            <div class="flex justify-end gap-1">
                @if($ledger->status === WorkflowStatus::PENDING_INSPECTION && Auth::id() === $ledger->latestDiff->inspector_id /* && 権限チェック */)
                    {{-- 承認申請ボタン (モーダルを開く) --}}
                    <x-mary-button data-tip="{{ __('ledger.workflow.request_approval_short') }}" icon="o-check-badge"
                                   class="btn-square btn-success tooltip"
                                   {{-- モーダルを開くメソッド呼び出し (Ledger ID を渡す) --}}
                                   wire:click="openApproverSelectModal({{ $ledger->id }})"
                                   spinner
                                   wire:key="openApproverSelectModal-{{ $ledger->id }}"
                    />
                    <x-mary-button data-tip="{{ __('ledger.workflow.return_to_draft_short') }}"
                                   icon="o-arrow-uturn-left"
                                   class="btn-square btn-warning tooltip"
                                   wire:click="openReturnToDraftModal({{ $ledger->latestDiff->id }})"
                                   spinner
                                   wire:key="openReturnToDraftModal-{{ $ledger->latestDiff->id }}"
                    />
                @elseif($ledger->status === WorkflowStatus::PENDING_APPROVAL && Auth::id() === $ledger->latestDiff->approver_id /* && 権限チェック */)
                    <x-mary-button data-tip="{{ __('ledger.workflow.approve') }}" icon="o-check-circle"
                                   class="btn-square btn-primary tooltip"
                                   wire:click="approveTask({{ $ledger->latestDiff->id }})"
                                   spinner
                                   wire:key="approveTask-{{ $ledger->latestDiff->id }}"
                    />
                    <x-mary-button data-tip="{{ __('ledger.workflow.return_to_draft_short') }}"
                                   icon="o-arrow-uturn-left"
                                   class="btn-square btn-warning tooltip"
                                   wire:click="openReturnToDraftModal({{ $ledger->latestDiff->id }})"
                                   spinner
                                   wire:key="openReturnToDraftModal-{{ $ledger->latestDiff->id }}"
                    />
                @endif
                <x-mary-button data-tip="{{ __('ledger.view_details') }}" icon="o-eye"
                               class="btn-square btn-ghost tooltip"
                               link="{{ route('ledger.show', ['ledgerId' => $ledger->id]) }}"/>

            </div>
            {{-- 戻し理由入力モーダル (修正: task->id ではなく ledger->id を使う？ $selectedTaskId で制御) --}}
            {{-- モーダル自体は $selectedTaskId で制御するため、ここの task/ledger ID は不要 --}}
            {{-- <x-mary-modal id="return-to-draft-modal-{{ $ledger->id }}" ... > は不要 --}}
            @endscope
            <x-slot:empty>
                <x-mary-icon name="o-cube" label="{{ __('ledger.workflow.no_pending_tasks') }}"/>
            </x-slot:empty>
        </x-mary-table>

        {{-- 担当者選択モーダルコンポーネント呼び出し --}}
        @livewire('workflow.workflow-assignee-modal', key('assignee-modal-pending'))

        {{-- 承認者選択モーダル --}}
        {{--
                <x-mary-modal wire:model="approvalRequestModal" title="{{ __('ledger.workflow.select_next_approver') }}">
                    <x-mary-select label="{{ __('ledger.workflow.next_approver') }}" :options="$approverOptions"
                                   wire:model="selectedApproverId"/>
                    <x-slot:actions>
        --}}
        {{-- @click で直接プロパティを false にして閉じる --}}
        {{--

                        <x-mary-button label="{{ __('Cancel') }}" @click="$wire.approvalRequestModal = false"/>
                        <x-mary-button label="{{ __('ledger.workflow.request_approval') }}" class="btn-primary"
                                       wire:click="requestApproval" spinner/>
                    </x-slot:actions>
                </x-mary-modal>
        --}}

        {{-- 戻し理由入力モーダル (コンポーネントの外に追加) --}}
        <x-mary-modal wire:model="returnToDraftModal" title="{{ __('ledger.workflow.return_to_draft_reason') }}">
            {{-- selectedTaskId を使って対応するコメントにバインド --}}
            <x-mary-textarea label="{{ __('ledger.workflow.comments') }}"
                             wire:model="returnComments.{{ $selectedTaskId }}"
                             placeholder="{{ __('ledger.workflow.return_reason_placeholder') }}"
                             hint="{{ __('ledger.workflow.optional_comment') }}" rows="3"/>
            <x-slot:actions>
                <x-mary-button label="{{ __('Cancel') }}" @click="$wire.returnToDraftModal = false"/>
                <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                               wire:click="returnTaskToDraft" spinner/>
            </x-slot:actions>
        </x-mary-modal>

        {{-- ページネーション (必要なら) --}}
        <div class="mt-4"> {{ $pendingTasks->links() }} </div>

    </x-mary-card>
</div>
