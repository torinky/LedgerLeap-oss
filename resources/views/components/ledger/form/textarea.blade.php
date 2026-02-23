@props([
    'columnDefine' => [],
    'ledgerRecord' => [],
    'isDemo' => false,
])

<x-mary-markdown
    label="{{ $columnDefine->name }}"
    @if(!$isDemo) wire:model.defer="content.{{ $columnDefine->id }}" @endif
    placeholder="{{ $columnDefine->options['placeholder'] ?? '' }}"
    hint="{{ $columnDefine->options['hint'] ?? '' }}"
    rows="5"
/>