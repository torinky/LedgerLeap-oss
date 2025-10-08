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
                // コンテンツの実高さが max-height よりも大きい場合にトグルを表示
                // style.maxHeight は '6rem' のような文字列なので、pixel値に変換して比較する
                const maxHeightInPixels = parseFloat(getComputedStyle(content).maxHeight);
                this.showToggle = content.scrollHeight > maxHeightInPixels;
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
        :style="expanded ? '' : (showToggle ? `max-height: {{ $maxHeight }}; -webkit-mask-image: linear-gradient(to bottom, black calc(100% - 3rem), transparent 100%); mask-image: linear-gradient(to bottom, black calc(100% - 3rem), transparent 100%);` : `max-height: {{ $maxHeight }}`)"
        {{ $attributes->merge(['class' => 'transition-all duration-300']) }}
    >
        {!! $content !!}
    </div>

    <!-- Show more/less ボタン -->
    <button
        x-show="showToggle"
        @click="expanded = !expanded"
        type="button"
        class="btn btn-sm btn-ghost w-full mt-2 gap-2"
    >
        <span x-text="expanded ? '{{ $showLessText }}' : '{{ $showMoreText }}'"></span>
        <i class="fas text-sm transition-transform duration-200"
           :class="{
               'fa-chevron-up': expanded,
               'fa-chevron-down': !expanded
           }"></i>
    </button>
</div>
