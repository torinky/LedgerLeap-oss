<div class="join datepicker">
    <input type="text" wire:model.live="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
           name="content[{{$columnDefine->id}}]"
           value="{{$this->content[$columnDefine->id] ?? ''}}"
           class="input input-bordered join-item @if($columnDefine->required) input-accent @endif"
           autocomplete="off"
           data-input
    />
    <a class="input-button btn join-item" data-toggle><i class="fas fa-calendar-alt"></i></a>
    <a class="input-button btn join-item" title="clear" data-clear>
        <i class="fas fa-close"></i>
    </a>
</div>
