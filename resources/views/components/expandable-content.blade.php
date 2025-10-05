@props([
    'content' => '',
    'maxHeight' => '6rem', // デフォルトの最大高さ（約4-5行）
    'showMoreText' => __('ledger.show_more'),
    'showLessText' => __('ledger.show_less'),
])

<div 
    x-data="{ 
        expanded: false, 
        showToggle: false,
        checkOverflow() {
            const content = this.$refs.content;
            if (content) {
                this.showToggle = content.scrollHeight > content.clientHeight;
            }
        }
    }"
    x-init="
        $nextTick(() => checkOverflow());
        window.addEventListener('resize', () => checkOverflow());
    "
    class="relative"
>
    <div 
        x-ref="content"
        :class="{ 'overflow-hidden': !expanded }"
        :style="expanded ? '' : 'max-height: {{ $maxHeight }}'"
        {{ $attributes->merge(['class' => 'transition-all duration-300']) }}
    >
        {!! $content !!}
    </div>
    
    <!-- グラデーションオーバーレイ（折りたたみ時のみ表示） -->
    <div 
        x-show="showToggle && !expanded"
        x-transition
        class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-base-100 to-transparent pointer-events-none"
    ></div>
    
    <!-- Show more/less ボタン -->
    <button 
        x-show="showToggle"
        @click="expanded = !expanded"
        type="button"
        class="btn btn-sm btn-outline btn-primary w-full mt-2 gap-2"
    >
        <span x-text="expanded ? '{{ $showLessText }}' : '{{ $showMoreText }}'"></span>
        <i class="fas text-sm transition-transform duration-200"
           :class="{
               'fa-chevron-up': expanded,
               'fa-chevron-down': !expanded
           }"></i>
    </button>
</div>
