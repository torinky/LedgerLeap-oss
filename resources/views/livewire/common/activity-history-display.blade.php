<div class="min-h-[400px]">
    @php use App\Helpers\ActivityLogFormatter; @endphp
    @if(!auth()->check() || !auth()->user()->can('view', \App\Models\CustomActivity::class))
        <div class="rounded-2xl border border-base-300 bg-base-100 px-6 py-8 text-center shadow-sm">
            <p class="text-base-content/70">{{ __('ledger.activity.no_permission') }}</p>
        </div>
    @else
        <div class="relative space-y-6">
            @php
                $activityTargets = 'filterByUserId,filterByEvent,filterByDescription,filterStartDate,filterEndDate,resetFilters,gotoPage,nextPage,previousPage';
            @endphp

            <x-element.loading-overlay tier="2" target="{{ $activityTargets }}" />

            <x-mary-header>
                <x-slot:actions>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="badge badge-outline badge-lg shrink-0">
                            {{ $activities->total() }}
                        </span>
                        <x-mary-button
                            label="{{ __('ledger.reset') }}"
                            wire:click="resetFilters"
                            class="btn-sm btn-ghost"
                            icon="o-arrow-path"
                        />
                    </div>
                </x-slot:actions>
            </x-mary-header>

            <x-mary-card class="border border-base-300 bg-base-100 shadow-sm">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
                    <div>
                        <x-mary-choices
                            label="{{ __('ledger.activity.column.causer') }}"
                            wire:model.live="filterByUserId"
                            :options="$userOptions"
                            searchFunction="userSearch"
                            placeholder="{{ __('ledger.all_users') }}"
                            single
                            clearable
                            searchable
                        />
                    </div>

                    <div>
                        <x-mary-select
                            label="{{ __('ledger.activity.column.operation') }}"
                            :options="$this->eventOptions"
                            option-value="event"
                            option-label="label"
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
                            option-label="label"
                            wire:model.live="filterByDescription"
                            placeholder="{{ __('ledger.all_operations') }}"
                            allow-empty
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:col-span-2">
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
            </x-mary-card>

            <x-mary-card class="overflow-hidden border border-base-300 bg-base-100 shadow-sm">
                <div wire:loading wire:target="{{ $activityTargets }}">
                    <x-element.skeleton-table rows="10" cols="5" />
                </div>

                <div wire:loading.remove wire:target="{{ $activityTargets }}">
                    <x-mary-table
                        class="table-sm w-full overflow-x-auto bg-base-100"
                        :headers="$headers"
                        :rows="$activities"
                        striped
                        hover
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
                        @php
                            $changesHtml = ActivityLogFormatter::formatChanges($activity);
                            $changesContent = is_string($changesHtml) ? $changesHtml : $changesHtml->toHtml();
                        @endphp
                        @if(!empty($changesContent))
                            <x-expandable-content
                                :content="$changesContent"
                                max-height="6rem"
                            />
                        @endif
                        @endscope

                        @scope('cell_comment', $activity)
                        @php
                            $comment = ActivityLogFormatter::formatComment($activity);
                        @endphp
                        @if(!empty($comment))
                            <x-expandable-content
                                :content="e($comment)"
                                max-height="4rem"
                            />
                        @endif
                        @endscope

                        <x-slot:empty>
                            <x-mary-icon name="o-cube" label="{{ __('ledger.activity.no_activities_found') }}" />
                        </x-slot:empty>
                    </x-mary-table>

                    <div class="border-t border-base-200 px-4 py-4 sm:px-6">
                        {!! $activities->links('components.common.pagination-links', ['position' => 'activity']) !!}
                    </div>
                </div>
            </x-mary-card>
        </div>
    @endif
</div>

