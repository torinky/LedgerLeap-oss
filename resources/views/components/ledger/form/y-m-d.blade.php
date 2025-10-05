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

    // Get default date from DateType if available and field is empty
    $defaultDate = '';
    if (empty($this->content[$columnDefine->id] ?? null)) {
        $inputType = $columnDefine->getInputType();
        if (method_exists($inputType, 'getDefaultDate')) {
            $defaultDate = $inputType->getDefaultDate();
        }
    }
@endphp

    @if($isDemo)
    <div x-init="flatpickr($el, { locale: 'ja', showMonths: 3, wrap: true, defaultDate: '{{ $defaultDate }}' })" class="datepicker">
        <x-mary-input
            label="{{$columnDefine->name}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            value="{{$this->content[$columnDefine->id] ?? $defaultDate}}"
            icon="{{$icon}}"
            class="{{$class}}"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
            {{--        clearable--}}
            data-input
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
    @if($columnDefine->required)
        <div x-init="flatpickr($el, { locale: 'ja', showMonths: 3, wrap: true, defaultDate: '{{ $defaultDate }}' })" class="datepicker">
            <x-mary-input
                wire:model.live="content.{{$columnDefine->id}}"
                label="{{$columnDefine->name}}"
                id="content[{{$columnDefine->id}}]"
                name="content[{{$columnDefine->id}}]"
                value="{{$this->content[$columnDefine->id] ?? $defaultDate}}"
                icon="{{$icon}}"
                class="{{$class}}"
                required="{{$columnDefine->required}}"
                hint="{{$columnDefine->hint}}"
                {{--        clearable--}}
                data-input
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
            >

                <x-slot:append>
                    <x-mary-button label="" icon="s-calendar-days"
                                   class="btn-primary btn-outline rounded-s-none {{$class}}"
                                   data-toggle/>
                    <x-mary-button label="" icon="c-x-mark" class="btn-primary btn-outline rounded-s-none {{$class}}"
                                   data-clear/>
                </x-slot:append>
            </x-mary-input>
        </div>
    @else
        <div x-init="flatpickr($el, { locale: 'ja', showMonths: 3, wrap: true, defaultDate: '{{ $defaultDate }}' })" class="datepicker">
            <x-mary-input
                wire:model.live="content.{{$columnDefine->id}}"
                label="{{$columnDefine->name}}"
                id="content[{{$columnDefine->id}}]"
                name="content[{{$columnDefine->id}}]"
                value="{{$this->content[$columnDefine->id] ?? $defaultDate}}"
                icon="{{$icon}}"
                class="{{$class}}"
                {{--                required="{{$columnDefine->required}}"--}}
                hint="{{$columnDefine->hint}}"
                {{--        clearable--}}
                data-input
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
            >

                <x-slot:append>
                    <x-mary-button label="" icon="s-calendar-days"
                                   class="btn-primary btn-outline rounded-s-none {{$class}}"
                                   data-toggle/>
                    <x-mary-button label="" icon="c-x-mark" class="btn-primary btn-outline rounded-s-none {{$class}}"
                                   data-clear/>
                </x-slot:append>
            </x-mary-input>
        </div>
    @endif
@endif
