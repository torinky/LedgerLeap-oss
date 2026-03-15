@props([
    'columnDefine' => [],
    'ledgerRecord' => [],
    'isDemo' => false,
])

@php
    $placeholder = $columnDefine->options['placeholder'] ?? '';
    $hint = $columnDefine->options['hint'] ?? '';
    $markdownConfig = [
        'placeholder' => $placeholder,
        'minHeight' => '140px',
    ];
@endphp

@if($isDemo)
    <x-mary-textarea
        label="{{ $columnDefine->name }}"
        name="content[{{ $columnDefine->id }}]"
        placeholder="{{ $placeholder }}"
        hint="{{ $hint }}"
        required="{{ $columnDefine->required }}"
        rows="5"
    />
@else
    <x-mary-markdown
        label="{{ $columnDefine->name }}"
        wire:model.defer="content.{{ $columnDefine->id }}"
        hint="{{ $hint }}"
        required="{{ $columnDefine->required }}"
        :config="$markdownConfig"
    />
@endif
