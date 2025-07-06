<!-- number.blade.php -->
@props([
    'class' => 'input-primary',
    'icon' => 'o-hashtag',
    'columnDefine' => [],
    'isDemo' => false,
])

@php
    // min, max, step, unit の値を取得。未設定の場合は適切なデフォルト値を設定。
    $min = $columnDefine->min ?? 0;
    $max = $columnDefine->max ?? 100;
    $step = $columnDefine->step ?? 1;
    $unit = $columnDefine->unit ?? '';
@endphp

<div class="form-control">
    <label class="label">
        <span class="label-text">{{ $columnDefine->name }} @if($columnDefine->required)<span class="text-error">*</span>@endif</span>
    </label>
    <div class="flex items-center space-x-2">
        <x-mary-input
            wire:model.live="content.{{ $columnDefine->id }}"
            type="number"
            name="content[{{ $columnDefine->id }}]"
            placeholder="{{ $columnDefine->name }}"
            icon="{{ $icon }}"
            class="{{ $class }} focus:opacity-100 flex-grow"
            required="{{ $columnDefine->required }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            x-on:focus="
                const opacityBlock = event.target.closest('.opacity-control-block');
                opacityBlock.classList.add('opacity-100');
                opacityBlock.classList.remove('opacity-50');
                updateBackground('{{ $columnDefine->id }}');
            "
            x-on:blur="
                const opacityBlock = event.target.closest('.opacity-control-block');
                opacityBlock.classList.add('opacity-50');
                opacityBlock.classList.remove('opacity-100');
            "
        />
        @if($unit)
            <span class="ml-2 text-gray-500">{{ $unit }}</span>
        @endif
    </div>
    <div class="flex items-center justify-between mt-2">
        <span class="text-xs">{{ $min }}{{ $unit }}</span>
        <input
            type="range"
            wire:model.live="content.{{ $columnDefine->id }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            class="range range-primary flex-grow mx-2"
            x-on:focus="
                const opacityBlock = event.target.closest('.opacity-control-block');
                opacityBlock.classList.add('opacity-100');
                opacityBlock.classList.remove('opacity-50');
                updateBackground('{{ $columnDefine->id }}');
            "
            x-on:blur="
                const opacityBlock = event.target.closest('.opacity-control-block');
                opacityBlock.classList.add('opacity-50');
                opacityBlock.classList.remove('opacity-100');
            "
        />
        <span class="text-xs">{{ $max }}{{ $unit }}</span>
    </div>
    @if($columnDefine->hint)
        <div class="label">
            <span class="label-text-alt">{{ $columnDefine->hint }}</span>
        </div>
    @endif
</div>