<div>
    <div class="container mx-auto prose lg:prose-xl">
        <input wire:click="changeOffset($event.target.value)" type="range" min="0"
               max="{{$ledgerDiffCount}}" value="{{$offset}}" class="range" step="1"/>
        <div class="w-full flex justify-between text-xs px-2">
            @for($i=0; $i<=$ledgerDiffCount; $i++)
                <span>|</span>
            @endfor
        </div>
    </div>
    <x-ledger.detail.table
        :ledgerRecord="$ledgerRecord"
        :canView="$canView"
    />

    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('updated at: ').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('created at: ').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
    </div>

</div>
