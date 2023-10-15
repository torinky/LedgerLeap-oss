<div>
    <div>
        <div class="flex flex-row place-items-center">
            @foreach($tags as $key => $tag)
                <div class="badge badge-info gap-2 mx-1 my-4">
                    <a href="#tagRemoveModal-{{$key}}"><i class="fas fa-times"></i></a>
                    {{$tag}}
                </div>
                <div class="modal" id="tagRemoveModal-{{$key}}">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">{{__('Remove tag')}}</h3>
                        <p class="py-4">{{$tag}}</p>
                        <div class="modal-action">
                            <a href="#" class="btn btn-error" wire:click="removeTag({{$key}})">{{__('Remove')}}</a> <a
                                href="#" class="btn btn-ghost">{{__('Cancel')}}</a>
                        </div>
                    </div>
                </div>
            @endforeach
            <input type="text" placeholder="{{__('Add Tag')}}"
                   class="input input-bordered input-sm w-full max-w-xs ml-5"
                   wire:model.live="newTag" wire:keydown.enter="addTag"/>
        </div>
    </div>
</div>
