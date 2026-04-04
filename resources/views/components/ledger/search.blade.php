@props(['hasWorkflowEnabled', 'orderBy', 'orderByLabel', 'useSemanticSearch', 'defaultSortColumns' => []])

<div class="flex flex-col gap-4 mt-5 pb-5 items-center justify-center">

    {{-- Search Input Section --}}
    <div class="w-full transition-all duration-300">
        <x-mary-input wire:model.live.debounce.300ms="search" type="search" icon="o-magnifying-glass"
                      class="input-primary shadow-md text-lg" placeholder="{{ __('ledger.search_message') }}"
                      clearable/>
    </div>

    {{-- Options Section --}}
    <div class="w-full min-w-0 bg-base-100 border border-base-300 rounded-box p-4 shadow-sm flex-col">
        <div class=" space-x-8 grid grid-cols-1 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">

            {{-- Search Scope Toggles --}}
            <div>
                <div class="tooltip" data-tip="test">
                    <x-mary-toggle wire:model.live="useTechnicalTerm" label="{{ __('ledger.search_technical_term') }}"
                                   class="toggle-primary" right/>
                </div>
            </div>

            <div>
                <div class="tooltip"
                     data-tip="{{ $useSemanticSearch ? __('ledger.synonym_disabled_in_semantic_search') : __('ledger.search_synonym') }}">
                    <x-mary-toggle wire:model.live="useSynonym" label="{{ __('ledger.search_synonym') }}"
                                   class="toggle-primary" :disabled="$useSemanticSearch" right/>
                </div>
            </div>

            <div>
                <div class="tooltip" data-tip="{{ __('ledger.semantic_search_requires_query') }}">
                    <x-mary-toggle wire:model.live="useSemanticSearch" class="toggle-secondary" right>
                        <x-slot:label>
                            <span class="flex items-center gap-1 font-bold text-secondary">
                                <i class="fas fa-brain"></i> {{ __('ledger.semantic_search') }}
                            </span>
                        </x-slot:label>
                    </x-mary-toggle>
                </div>

                @if ($useSemanticSearch ?? false)
                    <div class="mt-1 text-xs text-info inline-flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> {{ __('ledger.semantic_search_active') }}
                    </div>
                @endif
            </div>


            {{-- Divider: Hidden on Desktop (XL), Visible on Mobile/Tablet --}}
            {{--            <div class="divider "></div>--}}

            {{-- Display Options --}}

            {{-- Sort By --}}

                <label class="form-control">
                    <div class="label pt-0 pb-1">
                        <span class="label-text font-semibold">{{ __('ledger.sort_by') }}</span>
                    </div>
                    <select wire:model.live="orderBy" class="select select-primary select-sm">
                        @if ($orderByLabel !== '')
                            <option value="{{ $orderBy }}" selected>{{ $orderByLabel }}</option>
                        @endif
                        @if (!empty($defaultSortColumns) && $orderBy !== 'default')
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


            {{-- Asc/Desc --}}
            <div>
                <div class="tooltip">
                    <x-mary-toggle wire:model.live="orderAsc"
                                   label="{{ __('ledger.ascending') }} / {{ __('ledger.descending') }}"
                                   class="toggle-primary"
                                   right/>
                </div>
            </div>
            {{-- Workflow Status --}}

                @if ($hasWorkflowEnabled)
                    <label class="form-control">
                        <div class="label pt-0 pb-1">
                            <span class="label-text font-semibold">{{ __('ledger.workflow.status.label') }}</span>
                        </div>
                            <select wire:model.live="filterStatus" class="select select-primary select-sm w-full">
                            <option value="">{{ __('ledger.all') }}</option>
                            @foreach (\App\Enums\WorkflowStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif


            {{-- Per Page --}}
            <div class="">
                <label class="form-control">
                    <div class="label pt-0 pb-1">
                        <span class="label-text font-semibold">{{ __('ledger.per_page') }}</span>
                    </div>
                    <select wire:model.live="perPage" class="select select-primary select-sm">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</div>
