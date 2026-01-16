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

@if($errorCount > 0)
    <div x-data="{ open: true }"
         @toggle-validation-summary.window="open = !open"
         @keydown.window.ctrl.e="
            if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                $event.preventDefault();
                open = !open;
            }
         "
         class="mb-6 w-full sticky top-[108px] md:top-[92px] z-[35]"
         wire:key="validation-error-summary-wrapper">
        {{-- メインカード --}}
        <div class="card bg-base-100 border-2 border-error shadow-xl overflow-hidden shadow-error/20" x-show="open" x-cloak x-transition>
            {{-- ヘッダー：背景エラー色 --}}
            <div class="bg-error text-error-content px-4 py-2 flex items-center justify-between sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5 animate-pulse" />
                    <h2 class="font-black text-sm md:text-base tracking-tight">
                        {{ __('ledger.validation.summary_title', ['count' => $errorCount]) }}
                    </h2>
                </div>

                {{-- エラー間ナビゲーションボタン (Issue #23) --}}
                <div class="flex items-center gap-1 ml-auto mr-4 bg-black/10 rounded-full px-2 py-0.5">
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

                <button x-on:click="open = false" type="button" class="btn btn-ghost btn-xs btn-circle text-current opacity-70 hover:opacity-100 hover:bg-black/10" title="{{ __('ledger.validation.hide_summary') }}">
                    <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

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

        {{-- 折りたたみ時のコンパクト表示 --}}
        <div x-show="!open" x-cloak class="flex justify-start sticky top-[108px] md:top-[92px] z-[35]">
             <button x-on:click="open = true" type="button" class="btn btn-error btn-sm items-center gap-2 shadow-lg border-2 border-white/20 animate-bounce">
                 <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4" />
                 <span class="font-black text-xs">{{ __('ledger.validation.show_summary') }}</span>
                 <div class="badge badge-ghost badge-xs font-mono opacity-80">{{ $errorCount }}</div>
             </button>
        </div>
    </div>
@endif

