@props([
    'content' => '',
    'maxHeight' => '6rem',
    'showMoreText' => __('ledger.show_more'),
    'showLessText' => __('ledger.show_less'),
    'showToggleHint' => null,
    'skipMeasurement' => false,
])

<div x-data="expandableContent({
    maxHeight: '{{ $maxHeight }}',
    showToggleHint: @js($showToggleHint),
    skipMeasurement: @js($skipMeasurement),
})" @if (! $skipMeasurement) x-intersect.once.threshold.10="checkOverflow()" @endif class="relative">
    <div x-ref="content" :class="{ 'overflow-hidden': !expanded }" :style="contentStyle"
        {{ $attributes->merge(['class' => 'transition-all duration-500']) }}>
        {!! $content !!}
    </div>

    <button x-show="showToggle" @click="toggle()" type="button" class="btn btn-sm btn-ghost w-full mt-2 gap-2">
        <span x-text="expanded ? '{{ $showLessText }}' : '{{ $showMoreText }}'"></span>
        <i class="fas text-sm transition-transform duration-200"
            :class="{
                'fa-chevron-up': expanded,
                'fa-chevron-down': !expanded
            }"></i>
    </button>
</div>
