@php use App\Enums\WorkflowStatus; @endphp
<div>
    <x-mary-card :title="__('ledger.workflow.other_related_tasks_title')" class="border border-base-300 bg-base-100 shadow-sm">

        <x-mary-table :headers="[
                ['key' => 'ledger_title', 'label' => __('ledger.title'), 'sortable' => true],
                ['key' => 'task_type_display', 'label' => __('ledger.workflow.task_type')],
                ['key' => 'status_and_progress', 'label' => __('ledger.workflow.status.label')],
                ['key' => 'current_assignee_display', 'label' => __('ledger.workflow.current_assignee')],
                ['key' => 'applicant_name', 'label' => __('ledger.workflow.requester')],
                ['key' => 'ledger_updated_at', 'label' => __('ledger.workflow.last_updated_at'), 'sortable' => true],
                ['key' => 'age_display', 'label' => __('ledger.workflow.age')],
                ['key' => 'actions', 'label' => '', 'class' => 'w-1 text-right'],
            ]" :rows="$listedTasks" striped with-pagination wire:sortable="sortBy" wire:key="other-related-tasks-table">

            @scope('cell_ledger_title', $taskData)
            <a href="{{ route('ledger.show', ['tenant' => $taskData['tenant_id'] ?? tenant()?->id, 'ledgerId' => $taskData['ledger_id']]) }}"
               class="hover:underline font-semibold">
                {{ $taskData['ledger_title'] }} (ID: {{ $taskData['ledger_id'] }})
            </a>
            @endscope

            @scope('cell_task_type_display', $taskData) {{-- task_type を直接参照 --}}
            @if ($taskData['task_type'] === 'my_submission_pending_inspection' || $taskData['task_type'] === 'my_submission_pending_approval')
                <x-mary-badge :value="__('ledger.workflow.task_type_my_submission')"
                              class="badge-info badge-outline badge-sm"/>
            @elseif ($taskData['task_type'] === 'claimable')
                <x-mary-badge :value="__('ledger.workflow.task_type_claimable')"
                              class="badge-warning badge-outline badge-sm"/>
            @endif
            @endscope

            @scope('cell_status_and_progress', $taskData)
            <div class="space-y-2">
                <x-mary-badge :value="$taskData['status_label']"
                              class="badge-sm {{ $taskData['status_color_class'] }}"/>
                {{-- 必須ロール進捗サマリー表示 --}}
                @if($taskData['required_roles_progress_summary'])
                    @php $progress = $taskData['required_roles_progress_summary']; @endphp
                    {{--                @dd($progress)--}}
                    {{-- 点検進捗 --}}
                    @if($progress['inspection_total'] > 0)
                        <span class="tooltip tooltip-left inline-flex items-center gap-1">
                            <div class="tooltip-content space-y-2 p-2 text-sm">
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
                                    class="h-4 w-4 {{ $progress['inspection_all_completed'] ? 'text-success' : 'text-warning' }}"/>
                            <span class="text-xs">{{ $progress['inspection_completed'] }}/{{ $progress['inspection_total'] }}</span>
                        </span>
                    @endif
                    {{-- 承認進捗 --}}
                    @if($progress['approval_total'] > 0)

                        <span class="tooltip tooltip-left inline-flex items-center gap-1">
                            <div class="tooltip-content space-y-2 p-2 text-sm">
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
                                    class="h-4 w-4 {{ $progress['approval_all_completed'] ? 'text-success' : 'text-warning' }}"/>
                            <span class="text-xs">{{ $progress['approval_completed'] }}/{{ $progress['approval_total'] }}</span>
                        </span>
                    @endif
                    @if($taskData['status_value'] === WorkflowStatus::APPROVED->value && (!$progress['inspection_all_completed'] || !$progress['approval_all_completed']))
                        <span class="tooltip" data-tip="{{ __('ledger.workflow.required_roles_not_completed') }}">
                                <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 text-error"/>
                        </span>
                    @endif
                @endif
            </div>

            @endscope

            @scope('cell_current_assignee_display', $taskData)
            @if ($taskData['status_value'] === WorkflowStatus::PENDING_INSPECTION->value)
                {{ $taskData['current_inspector_name'] ?? '-' }}
            @elseif ($taskData['status_value'] === WorkflowStatus::PENDING_APPROVAL->value)
                {{ $taskData['current_approver_name'] ?? '-' }}
            @else
                -
            @endif
            @endscope

            @scope('cell_applicant_name', $taskData)
            {{ $taskData['applicant_name'] ?? '-' }}
            @endscope

            @scope('cell_ledger_updated_at', $taskData)
            {{ $taskData['ledger_updated_at']?->isoFormat('YYYY/MM/DD HH:mm') }}
            @endscope

            @scope('cell_age_display', $taskData)
            {{ $taskData['ledger_created_at']?->diffForHumans(null, true) }}
            @endscope

            @scope('actions', $taskData)
            <div class="flex flex-wrap justify-end gap-1">
                @if (in_array($taskData['task_type'], ['my_submission_pending_inspection', 'my_submission_pending_approval']) && !$taskData['is_locked'])
                    <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId' => $taskData['ledger_id']]) }}"
                       class="btn btn-square btn-ghost text-primary tooltip" data-tip="{{__('ledger.edit')}}">
                        <x-mary-icon name="o-pencil-square"/>
                    </a>
                @elseif ($taskData['task_type'] === 'claimable')
                    <x-mary-button wire:click="openClaimTaskCommentModal({{ $taskData['ledger_id'] }})"
                                   class="btn btn-square btn-outline btn-success tooltip"
                                   data-tip="{{__('ledger.workflow.claim_task')}}">
                        <x-mary-icon name="o-hand-raised"/>
                    </x-mary-button>
                @endif
                <a href="{{ route('ledger.show', ['tenant' => $taskData['tenant_id'] ?? tenant()?->id, 'ledgerId' => $taskData['ledger_id']]) }}"
                   class="btn btn-square btn-ghost tooltip" data-tip="{{__('ledger.view_details')}}">
                    <x-mary-icon name="o-eye"/>
                </a>
            </div>
            @endscope
            <x-slot:empty>
                <x-mary-icon name="o-cube" label="{{ __('ledger.workflow.no_other_related_tasks') }}"/>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    {{-- 引き継ぎコメント入力モーダル --}}
    <x-mary-modal wire:model="showClaimCommentModal" :title="__('ledger.workflow.claim_task_comment_title')">
        @if($claimingTaskData)
            <p class="mb-2">{{ __('ledger.title') }}: {{ $claimingTaskData['ledger_title'] }}
                (ID: {{ $claimingTaskData['ledger_id'] }})</p>
            <x-mary-textarea
                    label="{{ __('ledger.workflow.comment_optional') }}"
                    wire:model="claimComment"
                    placeholder="{{ __('ledger.workflow.claim_comment_placeholder') }}"
                    rows="3"
            />
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('ledger.ui.cancel') }}" @click="$wire.showClaimCommentModal = false"/>
            <x-mary-button label="{{ __('ledger.workflow.claim_and_start') }}" class="btn-success"
                           wire:click="claimTaskWithComment" spinner="claimTaskWithComment"/>
        </x-slot:actions>
    </x-mary-modal>
</div>