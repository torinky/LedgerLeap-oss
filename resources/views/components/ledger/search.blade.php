<div class="w-full flex pb-10 justify-center items-center">
    <div class="w-3/6 mx-1">
        <label>
            <input wire:model.live.debounce.800ms="search" type="text"
                   class="input input-bordered input-lg input-primary w-full"
                   placeholder="Search content...">
        </label>
    </div>
    <div class="w-1/6 relative mx-1">
        {{--
                    <select wire:model.live="orderBy"
                            class="select select-bordered w-full max-w-xs"
                            id="grid-state">
                        <option value="id">ID</option>
                        <option value="content->0">col1</option>
                        <option value="content->1">col2</option>
                        <option value="created_at">created</option>
                    </select>
        --}}
        {{--
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
        --}}
    </div>
    <div class="w-1/6 relative mx-1">
        <select wire:model.live="orderAsc"
                class="select select-bordered w-full max-w-xs"
                id="grid-state">
            <option value="1">Ascending</option>
            <option value="0">Descending</option>
        </select>
        {{--
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
        --}}
    </div>
    <div class="w-1/6 relative mx-1">
        <select wire:model.live="perPage"
                class="select select-bordered w-full max-w-xs"
                id="grid-state">
            <option>10</option>
            <option>25</option>
            <option>50</option>
            <option>100</option>
        </select>
        {{--
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
        --}}
    </div>
</div>
