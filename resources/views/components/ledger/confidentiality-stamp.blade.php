@props([
    'level' => null,
    'scopes' => [],
    'editUrl' => null,
    'label' => null,
    'sourceType' => null,   // 'folder' | 'ledger_define' | null
    'sourceName' => null,
    'inherited' => false,
])

@php
$levelLabels = [
    'secret' => __('ledger.confidentiality.level.secret'),
    'confidential' => __('ledger.confidentiality.level.confidential'),
    'internal' => __('ledger.confidentiality.level.internal'),
    'public' => __('ledger.confidentiality.level.public'),
];

$displayLabel = $label ?? ($level ? ($levelLabels[$level] ?? $level) : '');

$scopeText = collect($scopes)->map(fn ($s) => is_array($s) ? ($s['name'] ?? $s) : $s)->implode(', ');

// ツールチップ: 由来情報を優先して表示（仕様書 §4.4 準拠）
$tooltipLines = [];

if ($sourceType && $sourceName) {
    $sourceLabel = match ($sourceType) {
        'ledger_define' => __('ledger.confidentiality.tooltip.ledger_define', ['name' => $sourceName]),
        'folder' => __('ledger.confidentiality.tooltip.folder', ['name' => $sourceName]),
        default => $sourceName,
    };
    $tooltipLines[] = __('ledger.confidentiality.tooltip.source_label') . '：' . $sourceLabel;
    if ($inherited) {
        $tooltipLines[] = __('ledger.confidentiality.tooltip.inherited');
    }
} elseif (! $level) {
    $tooltipLines[] = __('ledger.confidentiality.stamp.tooltip_unset');
}

if ($scopeText) {
    $tooltipLines[] = $scopeText;
}

$tooltip = implode("\n", $tooltipLines) ?: ($editUrl ? __('ledger.confidentiality.stamp.edit_link') : '');
@endphp

@if($level || $label)
    {{-- z-[45]: navbar dropdown z-[30] より上、validation badge / sticky announcement z-[50] より下 --}}
    <div class="fixed top-16 right-4 z-[45]" wire:ignore>
        <div class="tooltip tooltip-left whitespace-pre-line"
             data-tip="{{ $tooltip }}">
            @if($editUrl)
                <a href="{{ $editUrl }}" class="block">
            @endif
                {{-- 仕様書準拠: 全区分赤統一・太枠・太字・透明背景 --}}
                <div class="inline-flex items-center px-4 py-2 text-lg font-black tracking-wider text-red-600 bg-transparent border-[3px] border-red-600 shadow-lg backdrop-blur-sm transform rotate-2 hover:rotate-0 transition-transform duration-200 cursor-default whitespace-nowrap">
                    {{ $displayLabel }}
                    @if($scopeText)
                        <span class="mx-1">・</span>
                        <span class="text-sm font-bold">{{ $scopeText }}</span>
                    @endif
                </div>
            @if($editUrl)
                </a>
            @endif
        </div>
    </div>
@endif
