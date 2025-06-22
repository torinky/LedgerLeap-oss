<div>
    @php use App\Helpers\ActivityLogFormatter; @endphp
    {{--    <x-mary-header title="{{ __('ledger.activity.title') }}" icon="o-clock" />--}}


    <x-mary-card>
        @if(!auth()->check() || !auth()->user()->can('view', \App\Models\CustomActivity::class))
            <p class="text-center text-gray-500 py-8">{{ __('ledger.activity.no_permission') }}</p>
        @else
            <x-mary-table
                    class="table-sm w-full table-zebra overflow-x-auto"
                    :headers="$headers" {{-- ★★★ 動的に生成されたヘッダーを使用 ★★★ --}}
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
                        {{ ActivityLogFormatter::getSubjectDisplay($activity) }}
                    </a>
                @else
                    {{ ActivityLogFormatter::getSubjectDisplay($activity) }}
                @endif
                @endscope

                @scope('cell_operation', $activity)
                {{ ActivityLogFormatter::getOperationDescription($activity) }}
                @endscope

                @scope('cell_changes', $activity)
                {!! ActivityLogFormatter::formatChanges($activity) !!}
                @endscope

                @scope('cell_comment', $activity)
                {{ ActivityLogFormatter::formatComment($activity) }}
                @endscope

                <x-slot:empty>
                    <x-mary-icon name="o-cube" label="{{ __('ledger.activity.no_activities_found') }}"/>
                </x-slot:empty>
            </x-mary-table>
            <div class="mt-4">
                <x-mary-loading wire:loading class=""/>
                {{ $activities->links() }}
            </div>
        @endif

    </x-mary-card>

</div>