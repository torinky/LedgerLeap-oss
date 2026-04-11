@props([
    'hasWorkflowEnabled',
    'orderBy',
    'orderByLabel',
    'useSemanticSearch',
    'defaultSortColumns' => [],
    'search' => '',
    'perPage' => 100,
    'useSynonym' => false,
    'useTechnicalTerm' => false,
])

<div class="mt-15 pb-0">
    <x-mary-card shadow="sm" class="w-full min-w-0 overflow-hidden border border-base-300 bg-base-100 py-0"
                 body-class="p-0">
        <div class="space-y-2 py-2">
            {{--
                        <div class="relative overflow-hidden rounded-2xl border border-primary/15 bg-linear-to-r from-primary/10 via-base-100 to-secondary/10 px-3 py-1.5">
                            <div class="absolute inset-y-0 left-0 w-1 bg-primary/60"></div>
                            <div class="flex items-center gap-3 pl-2">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                                    <x-mary-icon name="o-magnifying-glass" class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary">
                                        <span>{{ __('ledger.search_view') }}</span>
                                        @if ($useSemanticSearch ?? false)
                                            <x-mary-badge :value="__('ledger.semantic_search')" class="badge-secondary badge-sm" />
                                        @endif
                                    </div>
                                    <h2 class="truncate text-base font-bold leading-tight text-base-content md:text-lg">
                                        {{ __('ledger.search_hero_title') }}
                                    </h2>
                                </div>
                                @if (!empty($search))
                                    <x-mary-badge :value="__('ledger.search_active')" class="badge-primary badge-sm shrink-0" />
                                @endif
                            </div>
                        </div>
            --}}

            <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-[minmax(0,1.3fr)_minmax(19.5rem,19.5rem)] xl:grid-cols-[minmax(0,1.5fr)_minmax(20.5rem,20.5rem)] items-center">
                <x-mary-input wire:model.change="search" type="search" icon="o-magnifying-glass"
                              class="input-primary input-lg shadow-md" placeholder="{{ __('ledger.search_message') }}"
                              clearable/>

                <div class="grid grid-cols-1 gap-1.5 md:grid-cols-2 ">
                    <label class="form-control rounded-xl border border-base-300/70 bg-base-200/50 px-3 py-2">
                        @php $hasDefaultSortOption = !empty($defaultSortColumns) && $orderBy !== 'default'; @endphp
                        <div class="label px-0 pb-1">
                            <span class="label-text text-sm font-medium text-base-content">{{ __('ledger.sort_by') }}</span>
                        </div>
                        <select wire:model.live="orderBy" class="select select-primary select-sm w-full">
                            @if ($orderByLabel !== '')
                                <option value="{{ $orderBy }}" selected>{{ $orderByLabel }}</option>
                            @endif
                            @if ($hasDefaultSortOption)
                                <option value="default">{{ __('ledger.default_sort_order') }}</option>
                            @endif
                            <option value="composite_score">{{ __('ledger.scoring.score') }}</option>
                            <option value="created_at">{{ __('ledger.created_at') }}</option>
                            <option value="updated_at">{{ __('ledger.updated_at') }}</option>
                            @if ($useSemanticSearch ?? false)
                                <option value="semantic_score">{{ __('ledger.semantic_score_sort') }}</option>
                            @endif
                        </select>
                    </label>

                    <label class="form-control rounded-xl border border-base-300/70 bg-base-200/50 px-3 py-2">
                        <div class="label px-0 pb-1">
                            <span class="label-text text-sm font-medium text-base-content">{{ __('ledger.per_page') }}</span>
                        </div>
                        <select wire:model.live="perPage" class="select select-primary select-sm w-full">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                    </label>
                </div>
            </div>

            <details class="group rounded-2xl border border-base-300 bg-base-200/40">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-1.5 sm:px-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-base-100 text-primary shadow-sm">
                            <x-mary-icon name="o-adjustments-horizontal" class="h-4 w-4"/>
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-base-content">{{ __('ledger.search_options') }}</div>
                            <div class="text-xs text-base-content/60 group-open:hidden">{{ __('ledger.show_more') }}</div>
                            <div class="hidden text-xs text-base-content/60 group-open:block">{{ __('ledger.show_less') }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-base-content/60">
                        @if (!empty($hasWorkflowEnabled))
                            <x-mary-badge :value="__('ledger.workflow.status.label')"
                                          class="badge-ghost badge-sm hidden sm:inline-flex"/>
                        @endif
                        @if ($useTechnicalTerm)
                            <x-mary-badge :value="__('ledger.search_technical_term')"
                                          class="badge-neutral badge-sm hidden sm:inline-flex"/>
                        @endif
                        @if ($useSynonym)
                            <x-mary-badge :value="__('ledger.search_synonym')"
                                          class="badge-neutral badge-sm hidden sm:inline-flex"/>
                        @endif
                        <span class="group-open:hidden inline-flex items-center gap-1">
                            <x-mary-icon name="o-chevron-down" class="h-4 w-4"/>
                        </span>
                        <span class="hidden items-center gap-1 group-open:inline-flex">
                            <x-mary-icon name="o-chevron-up" class="h-4 w-4"/>
                        </span>
                    </div>
                </summary>

                <div class="border-t border-base-300 px-3 pb-1.5 pt-1 sm:px-4">
                    <div class="grid grid-cols-1 gap-1.5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <div class="flex flex-col gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-base-content">{{ __('ledger.search_technical_term') }}</span>
                            <div class="tooltip w-full sm:w-auto"
                                 data-tip="{{ __('ledger.search_technical_term_hint') }}">
                                <x-mary-toggle wire:model.live="useTechnicalTerm" class="toggle-primary toggle-sm"
                                               right/>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-base-content">{{ __('ledger.search_synonym') }}</span>
                            <div class="tooltip w-full sm:w-auto"
                                 data-tip="{{ $useSemanticSearch ? __('ledger.synonym_disabled_in_semantic_search') : __('ledger.search_synonym_hint') }}">
                                <x-mary-toggle wire:model.live="useSynonym" class="toggle-primary toggle-sm"
                                               :disabled="$useSemanticSearch" right/>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-base-content">{{ __('ledger.semantic_search') }}</span>
                            <div class="tooltip w-full sm:w-auto"
                                 data-tip="{{ __('ledger.semantic_search_requires_query') }}">
                                <x-mary-toggle wire:model.live="useSemanticSearch" class="toggle-secondary toggle-sm"
                                               right/>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-base-content">{{ __('ledger.ascending') }} / {{ __('ledger.descending') }}</span>
                            <div class="tooltip w-full sm:w-auto">
                                <x-mary-toggle wire:model.live="orderAsc" class="toggle-primary toggle-sm" right/>
                            </div>
                        </div>

                        @if (!empty($hasWorkflowEnabled))
                            <label class="flex flex-col gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-sm font-medium text-base-content">{{ __('ledger.workflow.status.label') }}</span>
                                <select wire:model.live="filterStatus"
                                        class="select select-primary select-xs w-full sm:w-44">
                                    <option value="">{{ __('ledger.all') }}</option>
                                    @foreach (\App\Enums\WorkflowStatus::cases() as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </div>
                </div>
            </details>
        </div>
    </x-mary-card>
</div>
