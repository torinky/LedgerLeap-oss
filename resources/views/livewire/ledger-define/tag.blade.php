<div class="flex flex-row place-items-center ">
    @foreach($tags as $key => $tag)
        <span wire:key="tag4ledger_{{$tag->id}}">
            <div
                class="badge badge-accent badge-sm gap-2 py-4 mx-1 my-4 opacity-80 hover:opacity-100 transition-opacity">
                <a href="#tagRemoveModal-{{$tag->id}}"
                   class="btn btn-xs opacity-50 hover:opacity-100 transition-opacity"><i
                        class="fas fa-times"></i></a>
                {{$tag->name}}
            </div>
            <div class="modal" id="tagRemoveModal-{{$tag->id}}">
                <div class="modal-box">
                    <h3 class="font-bold text-lg space-y-4">{{__('ledger.tag.remove')}}</h3>
                    <p class="">{{__('ledger.tag.remove_message')}}<br/>[ {{$tag->name}} ]</p>
                    <div class="modal-action">
                        <a href="#" class="btn btn-error" wire:click="removeTag({{$tag->id}})"
                           wire:key="tag_remove_modal_{{$tag->id}}"
                        >{{__('ledger.remove')}}</a>
                        <a href="#" class="btn">{{__('actions.cancel')}}</a>
                    </div>
                </div>
            </div>
        </span>
    @endforeach
    <form wire:submit="addTag">
        <span wire:loading class="fixed h-10 loading loading-ball loading-xs justify"></span>
        <input type="text" placeholder="{{__('ledger.tag.add')}}"
               class="input input-bordered input-xs w-full max-w-xs ml-5"
               id="newTag-{{$ledgerDefineId}}"
               wire:model="newTag"
               wire:key="tag_add_{{$ledgerDefineId}}"
        />
    </form>
</div>

