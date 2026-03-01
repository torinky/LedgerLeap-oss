@props(['reason' => 'identifier'])

@php
    $config = match($reason) {
        'identifier' => ['class' => 'badge-warning',  'icon' => 'fa-bookmark',     'label' => __('ledger.related.reason_identifier')],
        'semantic'   => ['class' => 'badge-info',     'icon' => 'fa-magnifying-glass', 'label' => __('ledger.related.reason_semantic')],
        'both'       => ['class' => 'badge-success',  'icon' => 'fa-star',          'label' => __('ledger.related.reason_both')],
        default      => ['class' => 'badge-ghost',    'icon' => 'fa-circle',        'label' => $reason],
    };
@endphp

<span class="badge {{ $config['class'] }} badge-sm gap-1 whitespace-nowrap">
    <i class="fas {{ $config['icon'] }} text-xs"></i>
    {{ $config['label'] }}
</span>

