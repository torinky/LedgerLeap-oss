@props([
    'columnDefine' => [],
    'ledgerRecord' => [],
])

<x-mary-markdown
    label="{{ $columnDefine->name }}"
    wire:model.defer="content.{{ $columnDefine->id }}"
    placeholder="{{ $columnDefine->options['placeholder'] ?? '' }}"
    hint="{{ $columnDefine->options['hint'] ?? '' }}"
    rows="5" {{-- You can adjust the default rows --}}
/>