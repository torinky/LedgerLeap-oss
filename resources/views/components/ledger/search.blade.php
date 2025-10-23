@props(['hasWorkflowEnabled', 'orderBy', 'orderByLabel'])
<div class="w-full flex flex-wrap pb-10 justify-center items-center px-0 mx-0 mt-5 flex-col md:flex-row">
    <div class="w-full md:w-3/6 lg:w-2/6 mx-1 mt-4 md:mt-0">
        <input wire:model.change="search" type="search"
               class="input input-bordered input-lg input-primary w-full icon-input"
               placeholder="&#xf002; {{__('ledger.search_message')}}"
        >
    </div>
    <div class="w-full md:w-3/6 lg:w-3/6 mx-1 mt-4 md:mt-0">
        <fieldset class="fieldset p-4 bg-base-100 border border-base-300 rounded-box flex flex-wrap items-center gap-x-4 gap-y-2">
            <legend class="fieldset-legend">{{__('ledger.search_options')}}</legend>
            <label class="fieldset-label">
                <input wire:model.live="orderAsc" type="checkbox" class="toggle toggle-primary"/>
                {{__('ledger.ascending')}} / {{__('ledger.descending')}}
            </label>

            {{-- ソート順 --}}
            <div class="form-control">
                <label class="label">
                    <span class="label-text">{{ __('ledger.sort_by') }}</span>
                </label>
                <select wire:model.change="orderBy" class="select select-bordered select-sm">
                    @if ($orderByLabel !== '')
                        <option value="{{ $orderBy }}" selected>{{ $orderByLabel }}</option>
                    @endif
                    <option value="composite_score">{{ __('ledger.scoring.score') }}</option>
                    <option value="created_at">{{ __('ledger.created_at') }}</option>
                    <option value="updated_at">{{ __('ledger.updated_at') }}</option>
{{--                    <option value="semantic_score" {{ empty($search) ? 'disabled' : '' }}>--}}
                    <option value="semantic_score">
                        {{ __('ledger.semantic_search') }}
                    </option>
                </select>
            </div>


            <label class="fieldset-label">
                <input wire:model.change="useSynonym" type="checkbox" class="toggle toggle-primary"/>
                {{__('ledger.search_synonym')}}
            </label>
            <label class="fieldset-label">
                <input wire:model.change="useTechnicalTerm" type="checkbox" class="toggle toggle-primary"/>
                {{__('ledger.search_technical_term')}}
            </label>
            
            {{-- ステータスフィルタ --}}
            @if($hasWorkflowEnabled)
            <div class="form-control">
                <label class="label">
                    <span class="label-text">{{ __('ledger.workflow.status.label') }}</span>
                </label>
                <select wire:model.live="filterStatus" 
                        class="select select-bordered select-sm">
                    <option value="">{{ __('ledger.all') }}</option>
                    @foreach(\App\Enums\WorkflowStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="form-control">
                <label class="label">
                    <span class="label-text">{{__('ledger.per_page')}}</span>
                </label>
                <select wire:model.live="perPage"
                        class="select select-bordered select-sm"
                        id="grid-state">
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>

        </fieldset>
    </div>
</div>