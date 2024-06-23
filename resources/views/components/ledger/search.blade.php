<div class="w-full flex flex-wrap pb-10 justify-center items-center space-x-10 px-0 mx-0 mt-5">
    <div class="w-full md:w-3/6 mx-1">

        <input wire:model.change="search" type="search"
               class="input input-bordered input-lg input-primary w-full icon-input"
               placeholder="&#xf002; {{__('ledger.search_message')}}"
        >
    </div>
    {{--
        <div class="w-1/6 relative mx-1">
        </div>
    --}}
    <div class="w-1/2 md:w-1/6 relative md:mt-0">
        <div class="form-control">
            <label class="cursor-pointer label">
                <span class="label-text">{{__('ledger.ascending')}} / {{__('ledger.descending')}}</span>
                <input wire:model.live="orderAsc" type="checkbox" class="toggle toggle-primary"/>
            </label>
        </div>
        <label class="form-control ">
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
