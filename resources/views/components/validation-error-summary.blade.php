@props(['errors' => [], 'ledgerDefine' => null])

@php
    $allErrors = collect($errors);
    $errorCount = $allErrors->flatten()->count();

    $structuredErrors = [];
    foreach($errors as $field => $messages) {
        $label = $field;
        $group = null;
        $is_required = false;

        if (str_starts_with($field, 'content.') && $ledgerDefine) {
            $columnId = (int) str_replace('content.', '', $field);
            // $ledgerDefine->column_define は ColumnDefine オブジェクトの配列またはコレクション
            $column = collect($ledgerDefine->column_define)->firstWhere('id', $columnId);
            if ($column) {
                // ColumnDefine モデルでは $title ではなく $name が表示名
                $label = $column->name;
                $group = $column->group ?? __('ledger.form.group_default');

                $msgStr = implode(' ', $messages);
                $keywords = __('ledger.validation.required_keywords');
                if (is_array($keywords) && \Illuminate\Support\Str::contains($msgStr, $keywords)) {
                    $is_required = true;
                }
            }
        }

        $structuredErrors[] = [
            'field' => $field,
            'messages' => $messages,
            'label' => $label,
            'group' => $group,
            'is_required' => $is_required
        ];
    }

    $requiredErrorsList = array_values(array_filter($structuredErrors, fn($item) => $item['is_required']));
    $formatErrorsList = array_values(array_filter($structuredErrors, fn($item) => !$item['is_required']));
@endphp

<div x-data="{
        open: true,
        userClosed: false,
        isResolving: false,
        get errorCount() {
            // Livewire のバリデーションエラーをリアクティブにカウント
            return Object.keys($wire.validationErrors || {}).length;
        }
    }"
     x-init="
        // errorCount (getter) の変更を監視
        $watch('errorCount', (newVal, oldVal) => {
            if (newVal > 0) {
                isResolving = false;
                if (!userClosed) open = true;
            }
            if (newVal === 0 && oldVal > 0) {
                // Issue #26 改良: 成功状態を視覚的に示し、トーストに合わせて長く滞在させる
                isResolving = true;

                // ステップ1: まず「成功」を認識させる猶予（1秒）をおいてからスクロール
                setTimeout(() => {
                    if (errorCount === 0) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }, 1000);

                // ステップ2: トーストが消え始めるタイミング（約3.5秒後）でフェードアウト開始
                setTimeout(() => {
                    if (errorCount === 0) {
                        open = false;
                        userClosed = false;
                        // アニメーション完了後に状態リセット
                        setTimeout(() => { isResolving = false; }, 2000);
                    }
                }, 3500);
            }
        });
     "
     @toggle-validation-summary.window="open = !open; if(open) userClosed = false;"
     @keydown.window.ctrl.e="
        if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            $event.preventDefault();
            open = !open;
            if(open) userClosed = false;
        }
     "
     class="mb-6 w-full sticky top-[108px] md:top-[92px] z-[35]"
     wire:key="validation-error-summary-wrapper">

    {{-- メインカード --}}
    <div class="card bg-base-100 border-2 shadow-xl overflow-hidden transition-all duration-700"
         x-bind:class="isResolving ? 'border-success shadow-success/20' : 'border-error shadow-error/20'"
         x-show="open && (errorCount > 0 || isResolving)"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-[1500ms]"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-10">
        {{-- ヘッダー：状態に応じて色を切り替え --}}
        <div class="px-4 py-2 flex items-center justify-between sticky top-0 z-10 transition-colors duration-700"
             x-bind:class="isResolving ? 'bg-success text-success-content' : 'bg-error text-error-content'">
            <div class="flex items-center gap-3">
                {{-- アイコン：状態に応じて切り替え --}}
                <div x-show="!isResolving">
                    <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5 animate-pulse" />
                </div>
                <div x-show="isResolving">
                    <x-mary-icon name="o-check-circle" class="w-5 h-5 animate-bounce" />
                </div>
                <h2 class="font-black text-sm md:text-base tracking-tight">
                    <span x-show="!isResolving" x-text="'{{ __('ledger.validation.summary_title', ['count' => ':count']) }}'.replace(':count', errorCount)"></span>
                    <span x-show="isResolving">{{ __('ledger.validation.all_errors_fixed') }}</span>
                </h2>
            </div>

            {{-- エラー間ナビゲーションボタン --}}
            <div x-show="!isResolving" class="flex items-center gap-1 ml-auto mr-4 bg-black/10 rounded-full px-2 py-0.5">
                <button type="button"
                        x-on:click="$dispatch('navigate-error', { direction: 'prev' })"
                        class="btn btn-xs btn-circle btn-ghost text-current hover:bg-black/20"
                        title="{{ __('ledger.validation.nav_prev') }}">
                    <x-mary-icon name="o-chevron-up" class="w-4 h-4" />
                </button>
                <span class="text-[9px] font-black opacity-70 px-1 select-none">NAV</span>
                <button type="button"
                        x-on:click="$dispatch('navigate-error', { direction: 'next' })"
                        class="btn btn-xs btn-circle btn-ghost text-current hover:bg-black/20"
                        title="{{ __('ledger.validation.nav_next') }}">
                    <x-mary-icon name="o-chevron-down" class="w-4 h-4" />
                </button>
            </div>

            <button x-on:click="open = false; userClosed = true;" type="button" class="btn btn-ghost btn-xs btn-circle text-current opacity-70 hover:opacity-100 hover:bg-black/10" title="{{ __('ledger.validation.hide_summary') }}">
                <x-mary-icon name="o-x-mark" class="w-4 h-4" />
            </button>
        </div>

        {{-- エラーリスト：成功時は隠す --}}
        <div x-show="!isResolving" x-collapse>
            <div class="card-body p-4 space-y-4 max-h-[40vh] overflow-y-auto overscroll-contain bg-base-100/95 backdrop-blur-sm">
                {{-- 必須入力エラー --}}
                @if(count($requiredErrorsList) > 0)
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-error font-black uppercase tracking-wider">
                            <x-mary-icon name="o-exclamation-circle" class="w-5 h-5" />
                            <span>{{ __('ledger.validation.required_errors') }}</span>
                            <div class="badge badge-error font-mono">{{ count($requiredErrorsList) }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($requiredErrorsList as $item)
                                <button type="button"
                                    x-on:click="$dispatch('scroll-to-error', { field: '{{ $item['field'] }}' })"
                                    class="flex items-center gap-3 bg-base-100 p-2.5 rounded-xl hover:ring-2 hover:ring-error/50 cursor-pointer transition-all shadow-sm border border-error/10 text-left group min-w-[240px] flex-1 sm:flex-initial"
                            >
                                <div class="bg-error/10 p-2 rounded-lg text-error group-hover:bg-error group-hover:text-white transition-colors shrink-0">
                                    <x-mary-icon name="o-no-symbol" class="" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    @if($item['group'])
                                        <div class="badge badge-error badge-outline border-none bg-error/10  font-black px-1.5 h-4 min-h-0 mb-1 leading-none">
                                            {{ $item['group'] }}
                                        </div>
                                    @endif
                                    <div class="text-sm font-bold text-base-content wrap-break-word leading-tight">
                                        {{ implode(__('uploadedFile.status.detailed.separator'), $item['messages']) }}
                                    </div>
                                </div>
                                <x-mary-icon name="o-chevron-right" class="w-3.5 h-3.5 opacity-20 group-hover:opacity-100 transition-opacity shrink-0" />
                            </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- 2. 形式系統のエラー --}}
                @if(count($formatErrorsList) > 0)
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-warning font-black uppercase tracking-wider">
                            <x-mary-icon name="o-pencil-square" class="w-5 h-5" />
                            <span>{{ __('ledger.validation.format_errors') }}</span>
                            <div class="badge badge-warning font-mono text-warning-content">{{ count($formatErrorsList) }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($formatErrorsList as $item)
                                <button type="button"
                                    x-on:click="$dispatch('scroll-to-error', { field: '{{ $item['field'] }}' })"
                                    class="flex items-center gap-3 bg-base-100 p-2.5 rounded-xl hover:ring-2 hover:ring-warning/50 cursor-pointer transition-all shadow-sm border border-warning/20 text-left group min-w-[240px] flex-1 sm:flex-initial"
                            >
                                <div class="bg-warning/10 p-2 rounded-lg text-warning group-hover:bg-warning group-hover:text-warning-content transition-colors shrink-0">
                                    <x-mary-icon name="o-information-circle" class="" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    @if($item['group'])
                                        <div class="badge badge-warning badge-outline border-none bg-warning/10 text-[9px] font-black px-1.5 h-4 min-h-0 mb-1 leading-none text-warning-content">
                                            {{ $item['group'] }}
                                        </div>
                                    @endif
                                    <div class="text-sm font-bold text-base-content wrap-break-word leading-tight">
                                        {{ implode(__('uploadedFile.status.detailed.separator'), $item['messages']) }}
                                    </div>
                                </div>
                                <x-mary-icon name="o-chevron-right" class="w-3.5 h-3.5 opacity-20 group-hover:opacity-100 transition-opacity shrink-0" />
                            </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ヒント --}}
                <div class="mt-2 pt-3 border-t border-error/10 flex items-center gap-2 text-xs font-bold text-error/60 italic">
                    <x-mary-icon name="o-light-bulb" class="" />
                    <span>{{ __('ledger.validation.summary_hint') }}</span>
                </div>
            </div>
        </div>

        {{-- 成功表示：エラーリストの代わりに表示（スクロール中にトーストと並んで表示される） --}}
        <div x-show="isResolving" x-transition:enter="transition ease-out duration-500 delay-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="p-8 text-center bg-success/5 backdrop-blur-sm shadow-inner italic">
            <x-mary-icon name="o-sparkles" class="w-10 h-10 text-success mx-auto mb-2 opacity-40 animate-pulse" />
            <div class="text-sm font-black text-success/80 tracking-widest uppercase">
                {{ __('ledger.validation.all_errors_fixed') }}
            </div>
        </div>
    </div>

    {{-- 再表示オプション (Issue #26): 閉じられたがエラーがある場合に表示されるフローティングバッジ --}}
    <div x-show="!open && errorCount > 0"
         class="fixed bottom-24 right-6 z-[50]"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-cloak>
        <button type="button"
                @click="open = true; userClosed = false"
                class="btn btn-error btn-circle shadow-2xl shadow-error/40 hover:scale-110 transition-transform group animate-bounce-slow">
            <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 rotate-12 group-hover:rotate-0 transition-transform" />
            <div class="badge badge-sm badge-white font-black text-error absolute -top-1 -right-1 border-2 border-error" x-text="errorCount"></div>
            {{-- ラベル（ホバー時） --}}
            <span class="absolute right-14 whitespace-nowrap bg-error text-error-content px-3 py-1.5 rounded-lg text-xs font-black shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                {{ __('ledger.validation.show_summary') }}
            </span>
        </button>
    </div>
</div>
