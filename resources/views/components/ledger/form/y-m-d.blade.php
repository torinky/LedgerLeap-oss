{{--
<label for="content[{{$columnDefine->id}}]"
       class="form-control">
    <div class="label">
            <span class="label-text">
                @if($columnDefine->required)
                    <i class="fas fa-check-circle text-accent"></i>
                @endif
                {{$columnDefine->name}}

            </span>

    </div>
    <div class="join datepicker">
        <input wire:model.live="content.{{$columnDefine->id}}"
               id="content[{{$columnDefine->id}}]"
               name="content[{{$columnDefine->id}}]"
               value="{{$this->content[$columnDefine->id] ?? ''}}"
               class="input input-primary ps-10 join-item @if($columnDefine->required) input-accent @endif"
               autocomplete="off"
               data-input
        />
        <a class=" btn join-item btn-primary btn-outline @if($columnDefine->required) btn-accent @endif" data-toggle><i
                class="fas fa-calendar-alt"></i></a>
        <a class=" btn join-item btn-primary btn-outline @if($columnDefine->required) btn-accent @endif" title="clear"
           data-clear>
            <i class="fas fa-close"></i>
        </a>
    </div>
    @error('content.' . $columnDefine->id)
    <label class="label">
        <span class="label-text-alt text-red-500 text-xs space-x-2">
            <i class="fas fa-times-circle"></i>
            <span class="error">{{ $message }}</span>
        </span>
    </label>
    @enderror
</label>
--}}


@php
    $icon='';
    $class="input-primary";
    $mark='';
    if($columnDefine->required){
        $icon='c-check-circle';
        $class="input-accent";
        $mark='<i class="fas fa-check-circle text-accent"></i>';
    }

@endphp

<div class="datepicker">
    <x-mary-input
        label="{{$columnDefine->name}}"
        {{--        :label="$mark.$columnDefine->name"--}}
        wire:model.live="content.{{$columnDefine->id}}"
        id="content[{{$columnDefine->id}}]"
        name="content[{{$columnDefine->id}}]"
        value="{{$this->content[$columnDefine->id] ?? ''}}"
        icon="{{$icon}}"
        {{--        clearable--}}
        :class="$class"
        data-input
    >
        <x-slot:append>
            <x-mary-button label="" icon="s-calendar-days" class="btn-primary btn-outline rounded-s-none {{$class}}"
                           data-toggle/>
            <x-mary-button label="" icon="c-x-mark" class="btn-primary btn-outline rounded-s-none {{$class}}"
                           data-clear/>
        </x-slot:append>
    </x-mary-input>
</div>
