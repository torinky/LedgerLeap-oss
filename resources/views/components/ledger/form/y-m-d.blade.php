@props([
    'icon'=>'o-calendar',
    'class'=>'input-primary',
    'columnDefine'=>[],
    'isDemo'=>false,
    'mark'=>'',
    ])

@php
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
        $mark='<i class="fas fa-check-circle text-accent"></i>';
    }

@endphp

@if($isDemo)
    <div class="datepicker">
        <x-mary-input
            label="{{$columnDefine->name}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            value="{{$this->content[$columnDefine->id] ?? ''}}"
            icon="{{$icon}}"
            class="{{$class}}"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
            {{--        clearable--}}
            data-input
            x-on:focus="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-100');
        opacityBlock.classList.remove('opacity-30');
        updateBackground('{{$columnDefine->id}}');
        "
            x-on:blur="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-30');
        opacityBlock.classList.remove('opacity-100');
        "
        >

            <x-slot:append>
                <x-mary-button label="" icon="s-calendar-days" class="btn-primary btn-outline rounded-s-none {{$class}}"
                               data-toggle/>
                <x-mary-button label="" icon="c-x-mark" class="btn-primary btn-outline rounded-s-none {{$class}}"
                               data-clear/>
            </x-slot:append>
        </x-mary-input>
    </div>
@else
    <div class="datepicker">
        <x-mary-input
            wire:model.live="content.{{$columnDefine->id}}"
            label="{{$columnDefine->name}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            value="{{$this->content[$columnDefine->id] ?? ''}}"
            icon="{{$icon}}"
            class="{{$class}}"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
            {{--        clearable--}}
            data-input
            x-on:focus="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-100');
        opacityBlock.classList.remove('opacity-30');
        updateBackground('{{$columnDefine->id}}');
        "
            x-on:blur="
        const opacityBlock = event.target.closest('.opacity-control-block');
        opacityBlock.classList.add('opacity-30');
        opacityBlock.classList.remove('opacity-100');
        "
        >

            <x-slot:append>
                <x-mary-button label="" icon="s-calendar-days" class="btn-primary btn-outline rounded-s-none {{$class}}"
                               data-toggle/>
                <x-mary-button label="" icon="c-x-mark" class="btn-primary btn-outline rounded-s-none {{$class}}"
                               data-clear/>
            </x-slot:append>
        </x-mary-input>
    </div>
@endif
