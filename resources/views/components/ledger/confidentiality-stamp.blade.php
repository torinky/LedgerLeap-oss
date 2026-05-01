@props(['level' => null, 'scopes' => [], 'editUrl' => null, 'label' => null])

@php
$levelConfig = [
    'secret' => [
        'label' => __('ledger.confidentiality.level.secret'),
        'class' => 'badge-error bg-error text-error-content border-error',
    ],
    'confidential' => [
        'label' => __('ledger.confidentiality.level.confidential'),
        'class' => 'badge-warning bg-warning text-warning-content border-warning',
    ],
    'internal' => [
        'label' => __('ledger.confidentiality.level.internal'),
        'class' => 'badge-info bg-info text-info-content border-info',
    ],
    'public' => [
        'label' => __('ledger.confidentiality.level.public'),
        'class' => 'badge-success bg-success text-success-content border-success',
    ],
];

$config = $level ? ($levelConfig[$level] ?? null) : null;
$displayLabel = $label ?? ($config ? $config['label'] : '');
$badgeClass = $config ? $config['class'] : 'badge-ghost bg-base-300 text-base-content border-base-300';

$scopeText = collect($scopes)->map(fn ($s) => is_array($s) ? ($s['name'] ?? $s) : $s)->implode(', ');
$tooltip = $scopeText ?: ($config ? '' : __('ledger.confidentiality.stamp.tooltip_unset'));
@endphp

@if($level || $label)
    <div class="fixed top-4 right-4 z-[60]" wire:ignore>
        <div class="tooltip tooltip-left"
             data-tip="{{ $tooltip ? $tooltip : ($editUrl ? __('ledger.confidentiality.stamp.edit_link') : '') }}">
            @if($editUrl)
                <a href="{{ $editUrl }}" class="block">
            @endif
                <div class="badge {{ $badgeClass }} badge-lg px-5 py-4 text-xl font-black shadow-2xl border-4 border-white/80 backdrop-blur-sm transform rotate-2 hover:rotate-0 transition-transform duration-200 cursor-default">
                    {{ $displayLabel }}
                </div>
            @if($editUrl)
                </a>
            @endif
        </div>
    </div>
@endif
