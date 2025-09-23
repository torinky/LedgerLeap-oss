<div>
@php
    use App\Enums\WorkflowStatus;
    use Illuminate\Support\Facades\DB;
@endphp

<x-mary-card>
    @if(app()->environment() !== 'testing')
        <x-mary-table class="table-sm w-full table-zebra overflow-x-auto"
                      :headers="[
                            ['key' => 'created_at', 'label' => __('ledger.workflow.history_datetime')],
                            ['key' => 'modifier_name', 'label' => __('ledger.workflow.history_user')],
                            ['key' => 'status', 'label' => __('ledger.workflow.history_action')],
                            ['key' => 'detail', 'label' => __('ledger.workflow.history_detail')],
                            ['key' => 'actions', 'label' => '', 'class' => 'text-center'],
                        ]"
                      :rows="$workflowHistory"
                      wire:key="workflow-history-table"
        >
            @scope('cell_created_at', $diff)
            {{ $diff->created_at->isoFormat('YYYY/MM/DD HH:mm:ss') }}
            @endscope

            @scope('cell_modifier_name', $diff)
            {{ $diff->modifier->name ?? 'N/A' }}
            @endscope

            @scope('cell_status', $diff)
            @if ($diff->status !== WorkflowStatus::NONE)
                <x-mary-badge :value="$diff->status->label()"
                              class="badge-sm {{ $diff->status->colorClass() }}"/>
            @else
                <span class="text-xs">{{ __('ledger.workflow.history_action_modified') }}</span>
            @endif
            @endscope

            @scope('cell_detail', $diff)
            @if ($diff->status !== WorkflowStatus::NONE)
                @if ($diff->status === WorkflowStatus::PENDING_INSPECTION && $diff->inspector)
                    <span class="text-xs">{{ __('ledger.workflow.next_inspector') }}: {{ $diff->inspector->name }}</span>
                @elseif ($diff->status === WorkflowStatus::PENDING_APPROVAL && $diff->approver)
                    <span class="text-xs">{{ __('ledger.workflow.next_approver') }}: {{ $diff->approver->name }}</span>
                @elseif ($diff->status === WorkflowStatus::APPROVED && $diff->approver)
                    <span class="text-xs">{{ __('ledger.workflow.approved_by') }}: {{ $diff->approver->name }}</span>
                @endif
                @if ($diff->comments)
                    <div class="text-xs mt-1 p-1 bg-base-200 rounded"
                         title="{{ __('ledger.workflow.comments') }}">{!! nl2br(e($diff->comments)) !!}</div>
                @endif
            @else
                <span class="text-xs">{{ __('ledger.workflow.workflow_inactive_at_this_point') }}</span>
            @endif
            @endscope

            @scope('cell_actions', $diff)
            @if ($diff->content)
                <a href="{{ route('ledgerDiff.show', ['tenant' => tenant()?->id, 'ledgerId' => $diff->ledger_id, 'diffId' => $diff->id]) }}"
                   class="btn btn-square tooltip"
                   target="_blank"
                   data-tip="{{ __('ledger.view_content_at_this_point') }}">
                    <i class="far fa-eye"></i>
                </a>
            @endif
            @endscope

            <x-slot:empty>
                <x-mary-icon name="o-cube" label="{{ __('ledger.workflow.no_history') }}"/>
            </x-slot:empty>
        </x-mary-table>
    @else
        <div id="workflow-history-table-placeholder-for-testing"></div>
    @endif
</x-mary-card>
</div>