<div>
    @php use App\Helpers\ActivityLogFormatter; @endphp
    {{--    <x-mary-header title="{{ __('ledger.activity.title') }}" icon="o-clock" />--}}


    <x-mary-card class="pt-0">
        @if(!auth()->check() || !auth()->user()->can('view', \App\Models\CustomActivity::class))
            <p class="text-center text-gray-500 py-8">{{ __('ledger.activity.no_permission') }}</p>
        @else
            {{-- ★★★ 新規追加: フィルタリングUI ★★★ --}}
            <x-mary-card shadow>

{{--
                <x-mary-header
                        title="{{ __('ledger.filter') }}"
                        icon="o-funnel"
                        size="text-md"
                />
--}}
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    {{-- 操作者フィルタ --}}
                    <div>
                        <x-mary-choices
                                label="{{ __('ledger.activity.column.causer') }}"
                                wire:model.live="filterByUserId"
                                :options="$userOptions" {{-- computed プロパティをバインド --}}
                                searchFunction="userSearch" {{-- 検索時に `search` メソッドを呼び出す --}}
                                placeholder="{{ __('ledger.all_users') }}"
                                single
                                clearble
                                searchable
                        />
                    </div>
                    {{-- 操作タイプフィルタ --}}
                    <div>
                        <x-mary-select
                                label="{{ __('ledger.activity.column.operation') }}"
                                :options="$this->eventOptions"
                                option-value="event"
                                option-label="event"
                                wire:model.live="filterByEvent"
                                placeholder="{{ __('ledger.all_operations') }}"
                                allow-empty
                        />
                    </div>
                    <div>
                        <x-mary-select
                                label="{{ __('ledger.activity.column.description') }}"
                                :options="$this->descriptionOptions"
                                option-value="description"
                                option-label="description"
                                wire:model.live="filterByDescription"
                                placeholder="{{ __('ledger.all_operations') }}"
                                allow-empty
                        />
                    </div>
                    {{-- 期間フィルタ --}}
                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-mary-datepicker
                                label="{{ __('ledger.activity.filter.start_date') }}"
                                wire:model.live="filterStartDate"
                                icon="o-calendar"
                        />
                        <x-mary-datepicker
                                label="{{ __('ledger.activity.filter.end_date') }}"
                                wire:model.live="filterEndDate"
                                icon="o-calendar"
                        />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <x-mary-button
                            label="{{ __('ledger.reset') }}"
                            wire:click="resetFilters"
                            class="btn-sm btn-ghost"
                            icon="o-arrow-path"
                    />
                </div>
            </x-mary-card>

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