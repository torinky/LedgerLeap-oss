@props([
    'left' => null,
    'right' => null,
    'footer' => null,
    'leftClass' => 'flex flex-wrap items-center justify-center gap-4 order-2 md:order-1',
    'rightClass' => 'flex flex-wrap items-center justify-center gap-4 order-1 md:order-2',
    'containerClass' => 'flex flex-wrap items-center justify-center md:justify-between gap-6',
])

{{--
    デザインガイドライン 7.54-57 準拠のフローティング・アクションバー。
    画面下部に固定（fixed）され、PCでは半透明、モバイルでは引き上げタブ構造となります。
--}}

<div {{ $attributes->merge(['class' => 'mx-auto w-full lg:w-2/3 fixed inset-x-0 z-50 lg:px-4 transition-[transform,bottom] duration-300 ease-in-out']) }}
     x-data="{
        expanded: false,
        isLg: window.matchMedia('(min-width: 1024px)').matches,
        init() {
            let mql = window.matchMedia('(min-width: 1024px)');
            mql.addEventListener('change', (e) => { this.isLg = e.matches; });
        }
     }"
     :style="{ bottom: (isLg ? '16px' : '0'), transform: (!isLg && !expanded) ? 'translateY(calc(100% - 3.5rem))' : 'translateY(0)' }"
     @click.outside="if(!isLg) expanded = false"
>
    <div class="shadow-[0_-10px_40px_rgba(0,0,0,0.1)] lg:shadow-md bg-base-300 transition-opacity duration-300 opacity-100 lg:opacity-[0.65] lg:hover:opacity-100 rounded-t-3xl lg:rounded-box border-t border-base-200 lg:border-none overflow-hidden flex flex-col">
        {{-- モバイル・タブレット用引き上げタブ (デザインガイドライン 7.59 準拠: h-14) --}}
        <div class="lg:hidden w-full flex flex-col items-center justify-center cursor-pointer h-14 bg-base-300 hover:bg-base-200 active:bg-base-200 transition-colors border-b border-base-content/10 shrink-0" @click="expanded = !expanded">
            <div class="w-16 h-1 bg-base-content/20 rounded-full mb-1"></div>
            <div class="flex items-center text-base-content/70 text-xs font-bold tracking-wider gap-2">
                {{-- シェブロン: FontAwesome + Alpine.js ラッパーで回転 --}}
                <span class="inline-flex transition-transform duration-300 ease-in-out"
                      :class="expanded ? 'rotate-180' : 'rotate-0'">
                    <i class="fa-solid fa-chevron-up"></i>
                </span>
                {{-- ラベルテキスト: x-show で確実に切り替え --}}
                <span x-show="expanded" x-cloak style="display:none">{{ __('ledger.action_bar_close') }}</span>
                <span x-show="!expanded">{{ __('ledger.action_bar_open') }}</span>
            </div>
        </div>

        {{-- ボタン・操作エリア --}}
        <div class="p-4 lg:p-6 pb-4 lg:pb-4 overflow-y-auto max-h-[60vh]">
            <div class="{{ $containerClass }}">
                {{-- 左側 --}}
                <div class="{{ $leftClass }}">
                    {{ $left }}
                </div>
                {{-- 右側 --}}
                <div class="{{ $rightClass }}">
                    {{ $right }}
                </div>
            </div>
        </div>

        {{-- 追加フッター領域 (ステータスバッジ等) --}}
        @if ($footer)
            <div class="w-full flex justify-center pb-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

{{--
    body末尾スペーサー: sticky-action-bar がフッターを覆わないよう body 下端に余白を確保する。
    x-teleport により body の末尾に追加されるため @push 制約を回避できる。
--}}
<template x-teleport="body">
    <div class="h-32 shrink-0 pointer-events-none" aria-hidden="true"></div>
</template>
