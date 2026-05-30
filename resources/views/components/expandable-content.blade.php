@props([
    'content' => '',
    'maxHeight' => '6rem',
    'showMoreText' => __('ledger.show_more'),
    'showLessText' => __('ledger.show_less'),
    'observeResize' => true,
])

<div x-data="expandableContent({
    maxHeight: '{{ $maxHeight }}',
    observeResize: @js($observeResize),
})" x-intersect.once.threshold.10="activate()" class="relative">
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
