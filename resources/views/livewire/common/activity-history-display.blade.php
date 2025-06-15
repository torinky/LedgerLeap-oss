<div class="card p-4">
    <h3 class="text-xl font-semibold mb-4">{{ __('ledger.activity.title') }}</h3>

    <x-mary-loading wire:loading class="my-4"/>

    @if(!auth()->check() || !auth()->user()->can('view', \App\Models\CustomActivity::class))
        <p class="text-center text-gray-500 py-8">{{ __('ledger.activity.no_permission') }}</p>
    @else
        <x-mary-table
                class="table-sm w-full table-zebra overflow-x-auto"
                :headers="[
                [
                    'key' => 'time', 'label' => __('ledger.activity.column.time'),
                                        'class' => 'min-w-[10rem]',

                ],
                [
                    'key' => 'causer',
                    'label' => __('ledger.activity.column.causer'),
                    'class' => 'min-w-[6rem]',
                ],
                [
                    'key' => 'subject',
                    'label' => __('ledger.activity.column.subject'),
                    'class' => 'min-w-[10rem]',
                ],
                [
                    'key' => 'operation',
                    'label' => __('ledger.activity.column.operation'),
                    'class' => 'min-w-[10rem]',
                ],
                ['key' => 'changes', 'label' => __('ledger.activity.column.changes')],
                [
                    'key' => 'comment',
                    'label' => __('ledger.activity.column.comment'),
                    'class' => 'min-w-[10rem]',
                 ],
            ]"
                :rows="$activities"
                striped
        >

            @scope('cell_time', $activity)
            {{ $activity->created_at->format('Y/m/d H:i') }}<br>
            <span class="text-sm text-base-content/70">
                    ({{ $activity->created_at->diffForHumans() }})
                </span>
            @endscope

            @scope('cell_causer', $activity)
            {{ $this->getCauserDisplayName($activity) }}
            @endscope

            @scope('cell_subject', $activity)
            @if($this->getSubjectDetailLink($activity))
                <a href="{{ $this->getSubjectDetailLink($activity) }}" class="link link-primary">
                    {{ $this->getSubjectDisplay($activity) }}
                </a>
            @else
                {{ $this->getSubjectDisplay($activity) }}
            @endif
            @endscope

            @scope('cell_operation', $activity)
            {{ $this->getOperationDescription($activity) }}
            @endscope

            @scope('cell_changes', $activity)
            {!! $this->formatChanges($activity) !!}
            @endscope

            @scope('cell_comment', $activity)
            {{ $this->formatComment($activity) }}
            @endscope

            <x-slot:empty>
                <x-mary-icon name="o-cube" label="{{ __('ledger.activity.no_activities_found') }}"/>
            </x-slot:empty>
        </x-mary-table>
    @endif
</div>