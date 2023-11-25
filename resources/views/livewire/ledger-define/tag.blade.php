<div class="flex flex-row place-items-center">
    @foreach($tags as $key => $tag)
        <div wire:key="tag4ledger_{{$tag->id}}">
            <div class="badge badge-info gap-2 mx-1 my-4">
                <a href="#tagRemoveModal-{{$tag->id}}"><i class="fas fa-times"></i></a>
                {{$tag->name}}
            </div>
            <div class="modal" id="tagRemoveModal-{{$tag->id}}">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{__('Remove tag')}}</h3>
                    <p class="py-4">{{$tag->name}}</p>
                    <div class="modal-action">
                        <a href="#" class="btn btn-error" wire:click="removeTag({{$tag->id}})"
                           wire:key="tag_remove_modal_{{$tag->id}}"
                        >{{__('Remove')}}</a> <a
                            href="#" class="btn btn-ghost">{{__('Cancel')}}</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    <input type="text" placeholder="{{__('Add Tag')}}"
           class="input input-bordered input-sm w-full max-w-xs ml-5"
           wire:model.live="newTag" wire:keydown.enter="addTag"
           wire:key="tag_add_{{$ledgerDefineId}}"
    />
</div>

