@props(['hasWorkflowEnabled', 'orderBy', 'orderByLabel', 'useSemanticSearch'])

<div class="flex flex-col md:flex-row gap-4 mt-5 pb-10 items-center justify-center">

    {{-- Search Input Section --}}
    <div class="basis-1/2 lg:basis-2/3 transition-all duration-300">
        <x-mary-input wire:model.change="search" type="search" icon="o-magnifying-glass"
            class="input-primary shadow-md text-lg" placeholder="{{ __('ledger.search_message') }}" clearable />
    </div>

    {{-- Options Section --}}
    <div class="basis-1/2 lg:basis-1/3 min-w-0 bg-base-100 border border-base-300 rounded-box p-4 shadow-sm">
        <div class="flex flex-row gap-10 justify-between">

            {{-- Search Scope Toggles --}}
            <div class="basis-1/2 gap-2 min-w-[180px]">

                <x-mary-toggle wire:model.change="useTechnicalTerm" label="{{ __('ledger.search_technical_term') }}"
                    class="toggle-primary" right />

                <div class="tooltip w-full text-left"
                    data-tip="{{ $useSemanticSearch ? __('ledger.synonym_disabled_in_semantic_search') : __('ledger.search_synonym') }}">
                    <x-mary-toggle wire:model.change="useSynonym" label="{{ __('ledger.search_synonym') }}"
                        class="toggle-primary" :disabled="$useSemanticSearch" right />
                </div>

                <div class="tooltip w-full text-left" data-tip="{{ __('ledger.semantic_search_requires_query') }}">
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
            <div class="divider xl:hidden my-0"></div>

            {{-- Display Options --}}
            <div class="basis-1/2 gap-2 min-w-[180px]">

                {{-- Sort By --}}
                <div class="flex gap-2 items-end">
                    <label class="form-control w-full">
                        <div class="label pt-0 pb-1">
                            <span class="label-text font-semibold">{{ __('ledger.sort_by') }}</span>
                        </div>
                        <select wire:model.change="orderBy" class="select select-primary select-sm w-full">
                            @if ($orderByLabel !== '')
                                <option value="{{ $orderBy }}" selected>{{ $orderByLabel }}</option>
                            @endif
                            <option value="composite_score">{{ __('ledger.scoring.score') }}</option>
                            <option value="created_at">{{ __('ledger.created_at') }}</option>
                            <option value="updated_at">{{ __('ledger.updated_at') }}</option>
                            @if ($useSemanticSearch ?? false)
                                <option value="semantic_score">{{ __('ledger.semantic_score_sort') }}</option>
                            @endif
                        </select>
                    </label>
                </div>

                {{-- Asc/Desc --}}
                <x-mary-toggle wire:model.live="orderAsc"
                    label="{{ __('ledger.ascending') }} / {{ __('ledger.descending') }}" class="toggle-primary"
                    right />

                {{-- Workflow Status --}}
                @if ($hasWorkflowEnabled)
                    <label class="form-control w-full">
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
                <label class="form-control w-full">
                    <div class="label pt-0 pb-1">
                        <span class="label-text font-semibold">{{ __('ledger.per_page') }}</span>
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
    </div>
</div>
