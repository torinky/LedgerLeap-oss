@props([
    'hasWorkflowEnabled',
    'orderBy',
    'orderByLabel',
    'useSemanticSearch',
    'defaultSortColumns' => [],
    'search' => '',
    'perPage' => 100,
    'displayLevel' => 1,
    'totalRecordsLoaded' => false,
    'useSynonym' => false,
    'useTechnicalTerm' => false,
    'orderAsc' => true,
    'filterStatus' => '',
    'recentSearches' => [],
    'popularKeywords' => [],
    'querySuggestions' => [],
    'showSearchSuggestions' => false,
])

<div class="mt-15 pb-0">
    <x-mary-card shadow="sm" class="w-full min-w-0 border border-base-300 bg-base-100 py-0"
                 body-class="p-0">
        <div class="space-y-2 py-2">
            @php
                $currentDisplayLevelLabel = __('ledger.form.display_level_options.' . (int) $displayLevel);
            @endphp
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
                {{-- TODO(#243-follow-up): ActivityLog は監査証跡として残す必要があるため、履歴削除ボタンは一時非表示。SearchHistoryService::delete() / IndexManager::deleteSearchHistory() は実装済みで、今後「非表示化」または「管理画面からの削除」設計時に再利用予定。 --}}
                <div class="relative z-40 w-full"
                     x-data="ledgerSearchSuggest()"
                     @click.away="open = false; commitSuggestions()">
                    <x-mary-input type="search" icon="o-magnifying-glass"
                                   class="input-primary input-lg shadow-md" placeholder="{{ __('ledger.search_message') }}"
                                   autocomplete="off"
                                   x-model="localSearch"
                                   @focus="open = true; selectedIndex = -1; syncLocalFromInput()"
                                   @input="onLocalInput()"
                                   @blur="commitSuggestions()"
                                   @keydown.space="onSpaceKey($event)"
                                   @keydown.arrow-down.prevent="if (open && hasItems) navigateDown()"
                                   @keydown.arrow-up.prevent="if (open && hasItems) navigateUp()"
                    />
                    {{-- クリアボタン (Mary UI clearable 非使用: wire:model 依存のため独自実装) --}}
                    <svg x-show="localSearch !== ''" @click="localSearch = ''; open = false; selectedIndex = -1; commitSuggestions(); $wire.clearSearch()"
                         class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 cursor-pointer text-base-content/40 hover:text-base-content/70"
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>

                    <div x-show="open && hasItems"
                         class="absolute top-full left-0 right-0 z-40 mt-1 rounded-box bg-base-100 p-2 shadow-lg border border-base-300"
                         x-cloak
                         wire:ignore>
                        <ul class="menu menu-sm w-full">
                            {{-- Issue #254 Phase A-9: Alpine.js x-for の「単一ルート要素」仕様
                                 (https://alpinejs.dev/directives/for) に従い、recent / related /
                                 popular をそれぞれ独立した x-for で描画する。
                                 旧実装は 1 つの unifiedSuggestions 配列を単一 x-for で回し、
                                 その中に <li> + <template x-if> + <a> の複数要素を置いていたため、
                                 スコープが壊れて `Can't find variable: item` 大量発生していた。 --}}

                            {{-- Recent section --}}
                            <template x-if="showRecent && recentItems.length > 0">
                                <li class="menu-title" x-text="@js(__('ledger.search_suggest.recent'))"></li>
                            </template>
                            <template x-for="(item, idx) in recentItems" :key="item.key">
                                <li :class="{ 'bg-base-200': selectedIndex === idx }"
                                    @mouseenter="selectedIndex = idx">
                                    <a @click="selectUnified(item); open = false; selectedIndex = -1"
                                       class="flex justify-between">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                            <span x-text="item.label" class="truncate"></span>
                                        </span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="badge badge-ghost badge-xs"
                                                  x-text="item.searchCount"></span>
                                        </span>
                                    </a>
                                </li>
                            </template>

                            {{-- Related section --}}
                            <template x-if="relatedItems.length > 0">
                                <li class="menu-title" x-text="@js(__('ledger.search_suggest.related'))"></li>
                            </template>
                            <template x-for="(item, idx) in relatedItems" :key="item.key">
                                <li :class="{ 'bg-base-200': selectedIndex === recentItems.length + idx }"
                                    @mouseenter="selectedIndex = recentItems.length + idx">
                                    <a @click="selectUnified(item); open = false; selectedIndex = -1"
                                       class="flex justify-between">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                                            <span class="truncate" x-html="highlight(item.label)"></span>
                                        </span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="badge badge-ghost badge-xs"
                                                  x-text="item.searchCount"></span>
                                        </span>
                                    </a>
                                </li>
                            </template>

                            {{-- Popular section --}}
                            <template x-if="popularItems.length > 0">
                                <li class="menu-title"
                                    x-text="showPopularFallback ? @js(__('ledger.search_suggest.popular_fallback')) : @js(__('ledger.search_suggest.popular'))"></li>
                            </template>
                            <template x-for="(item, idx) in popularItems" :key="item.key">
                                <li :class="{ 'bg-base-200': selectedIndex === recentItems.length + relatedItems.length + idx }"
                                    @mouseenter="selectedIndex = recentItems.length + relatedItems.length + idx">
                                    <a @click="selectUnified(item); open = false; selectedIndex = -1"
                                       class="flex justify-between">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" /></svg>
                                            <span x-text="item.label" class="truncate"></span>
                                        </span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="badge badge-ghost badge-xs"
                                                  x-text="item.searchCount"></span>
                                        </span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-1.5 md:grid-cols-2 ">
                    <label class="form-control rounded-xl border border-base-300/70 bg-base-200/50 px-3 py-2">
                        @php $hasDefaultSortOption = !empty($defaultSortColumns) && $orderBy !== 'default'; @endphp
                        <div class="label px-0 pb-1">
                            <span class="label-text inline-flex items-center gap-2 text-sm font-medium text-base-content">
                                <x-mary-icon name="o-arrows-up-down" class="text-base-content/70" />
                                <span>{{ __('ledger.sort_by') }}</span>
                            </span>
                        </div>
                        <select wire:model.live="orderBy" class="select select-primary select-sm w-full">
                            <?php
                                $sortOptions = ['default', 'composite_score', 'created_at', 'updated_at', 'semantic_score'];
                            $showCurrentSortOption = $orderByLabel !== '' && ! in_array($orderBy, $sortOptions, true);
                            ?>
                            <?php if ($showCurrentSortOption) { ?>
                            <option value="{{ $orderBy }}">{{ $orderByLabel }}</option>
                            <?php } ?>
                            <?php if ($hasDefaultSortOption) { ?>
                            <option value="default">{{ __('ledger.default_sort_order') }}</option>
                            <?php } ?>
                            <option value="composite_score">{{ __('ledger.scoring.score') }}</option>
                            <option value="created_at">{{ __('ledger.created_at') }}</option>
                            <option value="updated_at">{{ __('ledger.updated_at') }}</option>
                            <?php if ($useSemanticSearch ?? false) { ?>
                            <option value="semantic_score">{{ __('ledger.semantic_score_sort') }}</option>
                            <?php } ?>
                        </select>
                    </label>

                    <label class="form-control rounded-xl border border-base-300/70 bg-base-200/50 px-3 py-2">
                        <div class="label px-0 pb-1">
                            <span class="label-text inline-flex items-center gap-2 text-sm font-medium text-base-content">
                                <x-mary-icon name="o-queue-list" class="text-base-content/70" />
                                <span>{{ __('ledger.per_page') }}</span>
                            </span>
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

            <div x-data="{ open: localStorage.getItem('ledger_search_open') === 'true' }"
                 x-init="$watch('open', value => localStorage.setItem('ledger_search_open', value))"
                 class="rounded-2xl border border-base-300 bg-base-200/40">
                <div @click="open = !open" class="flex cursor-pointer select-none items-center justify-between gap-3 px-3 py-1.5 sm:px-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-base-100 text-primary shadow-sm">
                            <x-mary-icon name="o-adjustments-horizontal" class="h-4 w-4"/>
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-base-content">{{ __('ledger.search_options') }}</div>
                            <div :class="open ? 'hidden' : 'block'" class="text-xs text-base-content/60 whitespace-nowrap overflow-hidden text-ellipsis">{{ __('ledger.show_more') }}</div>
                            <div :class="open ? 'block' : 'hidden'" class="text-xs text-base-content/60">{{ __('ledger.show_less') }}</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                        @php
                            $activeSortLabel = match($orderBy) {
                                'default' => $orderByLabel ?: __('ledger.default_sort_order'),
                                'composite_score' => __('ledger.scoring.score'),
                                'created_at' => __('ledger.created_at'),
                                'updated_at' => __('ledger.updated_at'),
                                'semantic_score' => __('ledger.semantic_score_sort'),
                                default => $orderByLabel ?: $orderBy
                            };
                            $statusEnum = !empty($filterStatus) ? \App\Enums\WorkflowStatus::tryFrom($filterStatus) : null;
                            $workflowStatusLabel = $statusEnum?->label();
                        @endphp
                            <x-mary-badge :value="$activeSortLabel . ': ' . ($orderAsc ? __('ledger.ascending') : __('ledger.descending'))"
                                          :icon="$orderAsc ? 'o-bars-arrow-up' : 'o-bars-arrow-down'"
                                          class="badge-ghost badge-sm hidden sm:inline-flex whitespace-nowrap shadow-xs"/>

                        <div class="tooltip tooltip-bottom" data-tip="{{ __('ledger.form.display_level') }}">
                            <x-mary-badge :value="__('ledger.form.display_level') . ': ' . $currentDisplayLevelLabel"
                                          icon="o-view-columns"
                                          class="badge-info badge-sm hidden sm:inline-flex whitespace-nowrap shadow-xs"/>
                        </div>
                        <?php if ($useSemanticSearch) { ?>
                            <div class="tooltip tooltip-bottom" data-tip="{{ __('ledger.semantic_search_requires_query') }}">
                                <x-mary-badge :value="__('ledger.semantic_search')"
                                              icon="o-sparkles"
                                              class="badge-secondary badge-sm hidden sm:inline-flex shadow-xs"/>
                            </div>
                        <?php } ?>
                        <?php if ($useTechnicalTerm) { ?>
                            <div class="tooltip tooltip-bottom" data-tip="{{ __('ledger.search_technical_term_hint') }}">
                                <x-mary-badge :value="__('ledger.search_technical_term')"
                                              icon="o-book-open"
                                              class="badge-neutral badge-sm hidden sm:inline-flex shadow-xs"/>
                            </div>
                        <?php } ?>
                        <?php if ($useSynonym) { ?>
                            <div class="tooltip tooltip-bottom" data-tip="{{ $useSemanticSearch ? __('ledger.synonym_disabled_in_semantic_search') : __('ledger.search_synonym_hint') }}">
                                <x-mary-badge :value="__('ledger.search_synonym')"
                                              icon="o-chat-bubble-left-right"
                                              class="badge-neutral badge-sm hidden sm:inline-flex shadow-xs"/>
                            </div>
                        <?php } ?>

                        @if (!empty($workflowStatusLabel))
                        <div class="tooltip tooltip-bottom" data-tip="{{ __('ledger.workflow.status.label') }}">
                            <x-mary-badge :value="$workflowStatusLabel"
                                          :icon="$statusEnum?->heroicon() ?? 'o-funnel'"
                                          class="badge-info badge-sm hidden sm:inline-flex shadow-xs"/>
                        </div>
                        @endif
                        <span :class="open ? 'hidden' : 'inline-flex'" class="items-center gap-1 shrink-0">
                            <span class="tooltip tooltip-bottom" data-tip="{{ __('ledger.show_more') }}">
                                <x-mary-icon name="o-chevron-down" />
                            </span>
                        </span>
                        <span :class="open ? 'inline-flex' : 'hidden'" class="items-center gap-1 shrink-0">
                            <span class="tooltip tooltip-bottom" data-tip="{{ __('ledger.show_less') }}">
                                <x-mary-icon name="o-chevron-up" />
                            </span>
                        </span>
                    </div>
                </div>

                <div x-show="open" x-collapse x-cloak>
                    <div class="border-t border-base-300 px-3 pb-1.5 pt-1 sm:px-4">
                        @php
                            $displayLevelOptions = [
                                ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                                ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                                ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                            ];
                        @endphp
                        <div class="grid grid-cols-1 gap-1.5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <label class="flex  gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content whitespace-nowrap">
                                    <x-mary-icon name="o-book-open" class="text-base-content/70" />
                                    <span>{{ __('ledger.search_technical_term') }}</span>
                                </span>
                                <div class="tooltip w-full sm:w-auto"
                                     data-tip="{{ __('ledger.search_technical_term_hint') }}">
                                    <x-mary-toggle wire:model.live="useTechnicalTerm" class="toggle-primary toggle-sm"
                                                   right/>
                                </div>
                            </label>

                            <label class="flex  gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 flex-row items-center justify-between col-start-1 md:col-start-2 ">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content whitespace-nowrap">
                                    <x-mary-icon name="o-chat-bubble-left-right" class="text-base-content/70" />
                                    <span>{{ __('ledger.search_synonym') }}</span>
                                </span>
                                <div class="tooltip w-full sm:w-auto"
                                     data-tip="{{ $useSemanticSearch ? __('ledger.synonym_disabled_in_semantic_search') : __('ledger.search_synonym_hint') }}">
                                    <x-mary-toggle wire:model.live="useSynonym" class="toggle-primary toggle-sm"
                                                   :disabled="$useSemanticSearch" right/>
                                </div>
                            </label>

                            <label class="flex  gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 flex-row items-center justify-between col-start-1 md:col-start-1 lg:col-start-3">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content whitespace-nowrap">
                                    <x-mary-icon name="o-sparkles" class="text-base-content/70" />
                                    <span>{{ __('ledger.semantic_search') }}</span>
                                </span>
                                <div class="tooltip w-full sm:w-auto"
                                     data-tip="{{ __('ledger.semantic_search_requires_query') }}">
                                    <x-mary-toggle wire:model.live="useSemanticSearch" class="toggle-secondary toggle-sm"
                                                   right/>
                                </div>
                            </label>

                            <div class="flex gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 flex-row items-center justify-between col-span-2">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content">
                                    <x-mary-icon name="o-view-columns" class="text-base-content/70" />
                                    <span>{{ __('ledger.form.display_level') }}</span>
                                </span>
                                <div x-data="{ level: {{ (int) $displayLevel }} }" x-init="$watch('level', value => $wire.updateDisplayLevel(value))">
                                    <x-mary-group x-model="level" :options="$displayLevelOptions"
                                        class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary"
                                        option-value="id" option-label="name" />
                                </div>
                            </div>

                            <label class="flex gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 flex-row items-center justify-between">
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content whitespace-nowrap">
                                    <x-mary-icon name="o-bars-arrow-up" class="text-base-content/70" />
                                    <span>{{ __('ledger.sort_direction') }}</span>
                                </span>
                                <div class="tooltip w-full sm:w-auto">
                                    <x-mary-toggle wire:model.live="orderAsc" class="toggle-primary toggle-sm" right/>
                                </div>
                            </label>

                            {{-- @if (!empty($hasWorkflowEnabled)) --}}
                                <label class="flex gap-1 rounded-xl border border-base-300/70 bg-base-100/80 px-3 py-1 flex-row items-center justify-between col-start-1 md:col-start-2 lg:col-start-1 xl:col-start-4">
                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-base-content whitespace-nowrap">
                                        <x-mary-icon name="o-funnel" class="text-base-content/70" />
                                        <span>{{ __('ledger.workflow.status.label') }}</span>
                                    </span>
                                    <select wire:model.live="filterStatus"
                                            class="select select-primary select-xs w-full sm:w-44">
                                        <option value="">{{ __('ledger.all') }}</option>
                                        @foreach (\App\Enums\WorkflowStatus::cases() as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            {{-- @endif --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-mary-card>
</div>
