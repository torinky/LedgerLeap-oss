
@props([
    'class'=>'input-primary',
    'icon'=>'o-chat-bubble-oval-left-ellipsis',
    'columnDefine'=>[],
    'isDemo'=>false,
    ])


@if($isDemo)
    <x-mary-input
        {{--        wire:model.blur="content.{{$columnDefine->id}}"--}}
        label="{{$columnDefine->name}}"
        name="content[{{$columnDefine->id}}]"
        placeholder="{{$columnDefine->name}}"
        icon="{{$icon}}"
        clearable
        class="{{$class}}"
        required="{{$columnDefine->required}}"
        hint="{{$columnDefine->hint}}"
        x-on:focus="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-100');
        opacityBlock.classList.remove('opacity-50');
        updateBackground('{{$columnDefine->id}}');
        "
        x-on:blur="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-50');
        opacityBlock.classList.remove('opacity-100');
        "
    />
@else
    @if($columnDefine->required)
        <x-mary-input
            wire:model.blur="content.{{$columnDefine->id}}"
            label="{{$columnDefine->name}}"
            name="content[{{$columnDefine->id}}]"
            placeholder="{{$columnDefine->name}}"
            icon="{{$icon}}"
            clearable
            class="{{$class}}"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
            x-on:focus="
            const opacityBlock = event.target.closest('.opacity-control-block');
            opacityBlock.classList.add('opacity-100');
            opacityBlock.classList.remove('opacity-50');
            updateBackground('{{$columnDefine->id}}');
            "
            x-on:blur="
            const opacityBlock = event.target.closest('.opacity-control-block');
            opacityBlock.classList.add('opacity-50');
            opacityBlock.classList.remove('opacity-100');
            "
        />
    @else
        <x-mary-input
            wire:model.blur="content.{{$columnDefine->id}}"
            label="{{$columnDefine->name}}"
            name="content[{{$columnDefine->id}}]"
            placeholder="{{$columnDefine->name}}"
            icon="{{$icon}}"
            clearable
            class="{{$class}}"
            hint="{{$columnDefine->hint}}"
            x-on:focus="
            const opacityBlock = event.target.closest('.opacity-control-block');
            opacityBlock.classList.add('opacity-100');
            opacityBlock.classList.remove('opacity-50');
            updateBackground('{{$columnDefine->id}}');
            "
            x-on:blur="
            const opacityBlock = event.target.closest('.opacity-control-block');
            opacityBlock.classList.add('opacity-50');
            opacityBlock.classList.remove('opacity-100');
            "
        />
    @endif
@endif
