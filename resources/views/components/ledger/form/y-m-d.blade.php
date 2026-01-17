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

    // 最終的な値: Livewireの$this->contentから取得
    $finalValue = $this->content[$columnDefine->id] ?? '';
@endphp

    @if($isDemo)
    <div x-data="{ 
        dateValue: '{{ $finalValue }}',
        initFlatpickr() {
            const fp = flatpickr(this.$refs.datepicker, { 
                locale: 'ja', 
                showMonths: 3, 
                wrap: true,
                enableTime: {{ $columnDefine->type === 'YMDHM' ? 'true' : 'false' }},
                time_24hr: true,
                dateFormat: '{{ $columnDefine->type === 'YMDHM' ? 'Y-m-d H:i' : 'Y-m-d' }}',
                defaultDate: this.dateValue || null,
                onChange: (selectedDates, dateStr) => {
                    this.dateValue = dateStr;
                }
            });
            // 初期値をフィールドに設定
            if (this.dateValue) {
                fp.setDate(this.dateValue, true);
            }
        }
    }" x-init="initFlatpickr()" x-ref="datepicker" class="datepicker">
        <x-mary-input
            label="{{$columnDefine->name}}"
            id="content[{{$columnDefine->id}}]"
            name="content[{{$columnDefine->id}}]"
            x-model="dateValue"
            icon="{{$icon}}"
            class="{{$class}}"
            required="{{$columnDefine->required}}"
            hint="{{$columnDefine->hint}}"
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
        <div x-data="{ 
            dateValue: @entangle('content.' . $columnDefine->id),
            initFlatpickr() {
                flatpickr(this.$refs.datepicker, { 
                    locale: 'ja', 
                    showMonths: 3, 
                    wrap: true,
                    enableTime: {{ $columnDefine->type === 'YMDHM' ? 'true' : 'false' }},
                    time_24hr: true,
                    dateFormat: '{{ $columnDefine->type === 'YMDHM' ? 'Y-m-d H:i' : 'Y-m-d' }}',
                    defaultDate: this.dateValue || null,
                    onChange: (selectedDates, dateStr) => {
                        this.dateValue = dateStr;
                    }
                });
            }
        }" x-init="initFlatpickr()" x-ref="datepicker" class="datepicker">
            <x-mary-input
                wire:model.live="content.{{$columnDefine->id}}"
                label="{{$columnDefine->name}}"
                id="content[{{$columnDefine->id}}]"
                name="content[{{$columnDefine->id}}]"
                x-model="dateValue"
                icon="{{$icon}}"
                class="{{$class}}"
                required="{{$columnDefine->required}}"
                hint="{{$columnDefine->hint}}"
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
        <div x-data="{ 
            dateValue: @entangle('content.' . $columnDefine->id),
            initFlatpickr() {
                flatpickr(this.$refs.datepicker, { 
                    locale: 'ja', 
                    showMonths: 3, 
                    wrap: true,
                    enableTime: {{ $columnDefine->type === 'YMDHM' ? 'true' : 'false' }},
                    time_24hr: true,
                    dateFormat: '{{ $columnDefine->type === 'YMDHM' ? 'Y-m-d H:i' : 'Y-m-d' }}',
                    defaultDate: this.dateValue || null,
                    onChange: (selectedDates, dateStr) => {
                        this.dateValue = dateStr;
                    }
                });
            }
        }" x-init="initFlatpickr()" x-ref="datepicker" class="datepicker">
            <x-mary-input
                wire:model.live="content.{{$columnDefine->id}}"
                label="{{$columnDefine->name}}"
                id="content[{{$columnDefine->id}}]"
                name="content[{{$columnDefine->id}}]"
                x-model="dateValue"
                icon="{{$icon}}"
                class="{{$class}}"
                hint="{{$columnDefine->hint}}"
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
