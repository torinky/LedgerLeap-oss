<div>
    <x-mary-card :title="__('ledger.workflow.other_related_tasks_title')">
        @if ($listedTasks->isEmpty())
            <div class="text-center py-8">
                <x-mary-icon name="o-information-circle" class="w-12 h-12 mx-auto text-gray-400"/>
                <p class="mt-2 text-gray-500">{{ __('ledger.workflow.no_other_related_tasks') }}</p>
            </div>
        @else
            <x-mary-table :headers="[
                ['key' => 'ledger_title', 'label' => __('ledger.title'), 'sortable' => true],
                ['key' => 'task_type_display', 'label' => __('ledger.workflow.task_type')],
                ['key' => 'status_display', 'label' => __('ledger.workflow.status.label')],
                ['key' => 'current_assignee_display', 'label' => __('ledger.workflow.current_assignee')],
                ['key' => 'applicant_name', 'label' => __('ledger.workflow.requester')],
                ['key' => 'ledger_updated_at', 'label' => __('ledger.workflow.last_updated_at'), 'sortable' => true],
                ['key' => 'age_display', 'label' => __('ledger.workflow.age')],
                ['key' => 'actions', 'label' => '', 'class' => 'w-1 text-right'],
            ]" :rows="$listedTasks" striped with-pagination wire:sortable="sortBy">

                @scope('cell_ledger_title', $taskData)
                <a href="{{ route('ledger.show', ['ledgerId' => $taskData['ledger_id']]) }}" class="hover:underline font-semibold">
                    {{ $taskData['ledger_title'] }} (ID: {{ $taskData['ledger_id'] }})
                </a>
                @endscope

                @scope('cell_task_type_display', $taskData) {{-- task_type を直接参照 --}}
                @if ($taskData['task_type'] === 'my_submission_pending_inspection' || $taskData['task_type'] === 'my_submission_pending_approval')
                    <x-mary-badge :value="__('ledger.workflow.task_type_my_submission')" class="badge-info badge-outline badge-sm"/>
                @elseif ($taskData['task_type'] === 'claimable')
                    <x-mary-badge :value="__('ledger.workflow.task_type_claimable')" class="badge-warning badge-outline badge-sm"/>
                @endif
                @endscope

                @scope('cell_status_display', $taskData)
                <x-mary-badge :value="$taskData['status_label']" class="badge-sm {{ $taskData['status_color_class'] }}"/>
                @endscope

                @scope('cell_current_assignee_display', $taskData)
                @if ($taskData['status_value'] === \App\Enums\WorkflowStatus::PENDING_INSPECTION->value)
                    {{ $taskData['current_inspector_name'] ?? '-' }}
                @elseif ($taskData['status_value'] === \App\Enums\WorkflowStatus::PENDING_APPROVAL->value)
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
                <div class="flex justify-end gap-1">
                    @if (in_array($taskData['task_type'], ['my_submission_pending_inspection', 'my_submission_pending_approval']) && !$taskData['is_locked'])
                        <a href="{{ route('ledger.edit', ['ledgerId' => $taskData['ledger_id']]) }}"
                           class="btn btn-xs btn-ghost text-primary tooltip" data-tip="{{__('ledger.edit')}}">
                            <x-mary-icon name="o-pencil-square"/>
                        </a>
                    @elseif ($taskData['task_type'] === 'claimable')
                        <x-mary-button wire:click="openClaimTaskCommentModal({{ $taskData['ledger_id'] }})"
                                       class="btn-xs btn-outline btn-success tooltip" data-tip="{{__('ledger.workflow.claim_task')}}">
                            <x-mary-icon name="o-hand-raised"/>
                        </x-mary-button>
                    @endif
                    <a href="{{ route('ledger.show', ['ledgerId' => $taskData['ledger_id']]) }}"
                       class="btn btn-xs btn-ghost tooltip" data-tip="{{__('ledger.view_details')}}">
                        <x-mary-icon name="o-eye"/>
                    </a>
                </div>
                @endscope
            </x-mary-table>
        @endif
    </x-mary-card>

    {{-- 引き継ぎコメント入力モーダル --}}
    <x-mary-modal wire:model="showClaimCommentModal" :title="__('ledger.workflow.claim_task_comment_title')">
        @if($claimingTaskData)
            <p class="mb-2">{{ __('台帳') }}: {{ $claimingTaskData['ledger_title'] }} (ID: {{ $claimingTaskData['ledger_id'] }})</p>
            <x-mary-textarea
                    label="{{ __('ledger.workflow.comment_optional') }}"
                    wire:model="claimComment"
                    placeholder="{{ __('ledger.workflow.claim_comment_placeholder') }}"
                    rows="3"
            />
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('Cancel') }}" @click="$wire.showClaimCommentModal = false" />
            <x-mary-button label="{{ __('ledger.workflow.claim_and_start') }}" class="btn-success" wire:click="claimTaskWithComment" spinner="claimTaskWithComment" />
        </x-slot:actions>
    </x-mary-modal>
</div>