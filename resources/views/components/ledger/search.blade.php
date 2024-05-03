<div class="w-full flex pb-10 justify-center items-center space-x-8">
    <div class="w-3/6 mx-1">

        <input wire:model.live.debounce.800ms="search" type="search"
               class="input input-bordered input-lg input-primary w-full"
               placeholder="{{__('ledger.search_message')}}">
    </div>
    <div class="w-1/6 relative mx-1">
    </div>
    <div class="w-1/6 relative mx-1">
        <div class="form-control">
            <label class="cursor-pointer label">
                <span class="label-text">{{__('ledger.ascending')}} / {{__('ledger.descending')}}</span>
                <input wire:model.live="orderAsc" type="checkbox" class="toggle toggle-primary"/>
            </label>
        </div>
    </div>
    <div class="w-1/6 relative mx-1">
        <label class="form-control w-full max-w-xs">
            <div class="label">
                <span class="label-text">{{__('ledger.per_page')}}</span>
            </div>
            <select wire:model.live="perPage"
                    class="select select-bordered select-sm w-full max-w-xs py-0"
                    id="grid-state">
                <option>10</option>
                <option>25</option>
                <option>50</option>
                <option>100</option>
            </select>
        </label>
    </div>
</div>
