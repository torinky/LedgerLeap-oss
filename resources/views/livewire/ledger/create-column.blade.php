<div>
    <x-element.loading-overlay tier="1" target="store" :delay="false"/>

    <div class="background-image-change"
         @validation-summary-status.window="validationSummaryOpen = $event.detail.open; validationErrorCount = $event.detail.errorCount;"
         x-data="Object.assign(validationErrorNavigator(), {
            currentBg: null,
            validationSummaryOpen: true,
            validationErrorCount: 0,
            updateBackground(columnId) {
                this.currentBg = $wire.backgroundImages[columnId] || null;
        
                //                console.log($wire.backgroundImages);
                //                console.log(this.currentBg);
        
                if (this.currentBg == null || this.currentBg.length == 0) {
                    document.querySelector('.background-image-change').style.backgroundImage = ``;
                } else {
                    document.querySelector('.background-image-change').style.backgroundImage = `url('${this.currentBg}')`;
                }
            },
            focusFirstInput() {
                const firstInput = document.querySelector('.background-image-change input:first-child');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        })" x-init="focusFirstInput()">

        {{--    css生成のためのダミー --}}
        <div class="hidden">
            <div class="bg-success"></div>
            <x-mary-input label="Name" placeholder="Your name" icon="o-user" hint="Your full name"/>
        </div>
        @if ($ledgerDefineRecord && $ledgerDefineRecord->column_define)
            {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}" --}}
            <x-mary-form wire:submit="store" method="post" class="card mb-32 w-full bg-neutral-500/10 shadow-xl">
                @csrf
                {{--            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}"> --}}

                @php
                    $columnJs = [];
                @endphp


                <div class="card-body space-y-3 pt-2">
                    <x-mary-progress value="{{ $progress }}" max="100"
                                     class="{{ !empty($validationErrors) ? 'progress-error' : 'progress-success' }} h-3 w-full sticky top-24 md:top-20 z-10"/>

                    {{-- バリデーションエラーサマリー (Issue #13-2) --}}
                    <x-validation-error-summary :errors="$validationErrors" :ledger-define="$ledgerDefineRecord"/>

                    {{-- 全て展開・折りたたみトグル (Issue #53) --}}
                    <div
                            class="flex justify-end items-center gap-3 bg-base-200/30 px-4 py-2 rounded-full border border-base-300 mb-4 self-end transition-all hover:bg-base-200/50">
                        <span
                                class="text-[10px] font-black text-base-content/40 uppercase tracking-widest">{{ __('ledger.column.expand_all') }}</span>
                        <x-mary-toggle wire:model.live="allExpanded" right tight class="toggle-xs toggle-primary"/>
                    </div>

                    @foreach ($groupedColumns as $groupName => $columnsInGroup)
                        @php
                            $isGroupRequired = collect($columnsInGroup)->contains(fn($col) => $col->required);
                        @endphp
                        <div class="collapse collapse-plus bg-base-200 hover:bg-base-200/20  mb-2 {{ !$collapsedStates[$groupName] ? 'collapse-open' : '' }}"
                             wire:key="group-{{ md5($groupName) }}" x-data="{
                                ...groupErrorBadge(),
                                isCollapsed: @entangle('collapsedStates.' . $groupName).live,
                                toggle() {
                                    this.isCollapsed = !this.isCollapsed;
                                }
                            }"
                             :class="{ 'collapse-open': !isCollapsed }" data-group-name="{{ $groupName }}">
                            {{-- checkboxを使わずにJS for immediate animation --}}
                            <div class="collapse-title text-xl font-medium cursor-pointer" @click="toggle()">
                                <h3 class="text-lg font-bold flex items-center pr-10">
                                    <div class="flex items-center">
                                        @if ($isGroupRequired)
                                            <div class="tooltip tooltip-right mr-2"
                                                 data-tip="{{ __('ledger.form.required_group_indicator') }}">
                                                <x-mary-icon name="o-check-circle" class="w-6 h-6 text-error"/>
                                            </div>
                                        @endif
                                        {{ $groupName }}
                                    </div>

                                    {{-- エラーバッジ表示 (Issue #17) --}}
                                    <div x-show="errorCount > 0" x-cloak
                                         class="ml-auto flex items-center gap-1.5 px-2.5 py-1 bg-error/10 text-error rounded-full border border-error/20 animate-pulse">
                                        <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4"/>
                                        <span class="text-sm font-black font-mono leading-none"
                                              x-text="errorCount"></span>
                                    </div>
                                </h3>
                            </div>
                            <div class="collapse-content">
                                @foreach ($columnsInGroup as $columnDefine)
                                    @php
                                        $validationKey = 'content.' . $columnDefine->id;
                                    @endphp
                                    @if (!$columnDefine->isHidden())
                                        <div class="flex mt-2" id="field-content-{{ $columnDefine->id }}">
                                            <div class="w-1 bg-{{ $labelColor[$columnDefine->id] }}"></div>
                                            <div wire:key="content-{{ $columnDefine->id }}"
                                                 x-data="{ showFixed: false }"
                                                 @field-fixed.window="if ($event.detail.field === '{{ $validationKey }}') { showFixed = true; setTimeout(() => showFixed = false, 2000); }"
                                                 x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                                                 x-on:focusin="updateBackground('{{ $columnDefine->id }}')"
                                                 class="w-full opacity-control-block opacity-50 hover:opacity-100 focus-within:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->parent->first && $loop->first ? 'initial-opacity-100' : '' }}"
                                                 :class="{
                                                    'validation-error-highlight': {{ isset($validationErrors[$validationKey]) ? 'true' : 'false' }},
                                                    'validation-success-highlight': showFixed && !
                                                        {{ isset($validationErrors[$validationKey]) ? 'true' : 'false' }}
                                                }"
                                                 @if ($loop->parent->first && $loop->first)
                                                     x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                                                 x-init="updateBackground('{{ $columnDefine->id }}')"
                                                    @endif
                                            >
                                                {{-- エラーアイコン (Issue #18) --}}
                                                @if (isset($validationErrors[$validationKey]))
                                                    <div class="validation-error-icon-wrapper tooltip tooltip-left"
                                                         data-tip="{{ collect($validationErrors[$validationKey])->first() }}">
                                                        <x-mary-icon name="o-x-circle" class="w-5 h-5 text-error"/>
                                                    </div>
                                                @endif

                                                {{-- 修正成功アイコン (Issue #24) --}}
                                                <div x-show="showFixed && !{{ isset($validationErrors[$validationKey]) ? 'true' : 'false' }}"
                                                     class="validation-success-icon-wrapper"
                                                     x-transition:enter="transition ease-out duration-300"
                                                     x-transition:enter-start="opacity-0 scale-90"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-500"
                                                     x-transition:leave-start="opacity-100"
                                                     x-transition:leave-end="opacity-0"
                                                     x-cloak>
                                                    <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success"/>
                                                </div>

                                                @if ($columnDefine->type === 'files')
                                                    <x-ledger.form.files :columnDefine="$columnDefine"
                                                                         :ledgerDefineId="$ledgerDefineId"
                                                                         :initial-files="[]"
                                                                         multiple allowImagePreview
                                                                         imagePreviewMaxHeight="150"/>
                                                @else
                                                    @php
                                                        $componentName =
                                                            'ledger.form.' . str_replace('_', '-', $columnDefine->type);
                                                        // auto_number タイプの場合、text コンポーネントを使用
                                                        if ($columnDefine->type === 'auto_number') {
                                                            $componentName = 'ledger.form.text';
                                                        } elseif (in_array($columnDefine->type, ['YMD', 'YMDHM'])) {
                                                            // YMD/YMDHM は同じ y-m-d コンポーネントで処理（enableTime で切り替え）
                                                            $componentName = 'ledger.form.y-m-d';
                                                        }
                                                    @endphp
                                                    <x-dynamic-component :component="$componentName"
                                                                         wire:model.live="content"
                                                                         wire:key="content-input-{{ $columnDefine->id }}"
                                                                         :columnDefine="$columnDefine"
                                                                         :ledgerRecord="$ledgerRecord ?? []"/>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>


                {{--
                                                <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
                                                    <button type="submit" class="btn btn-outline btn-warning btn-wide"><i
                                                            class="fa-solid fa-pencil mr-2"></i>{{__('save')}}</button>
                                                    <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                                            class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
                                                </div>
                                --}}


                {{-- 統一アクションバー（透過・ホバー＆スライドアップ対応） --}}
                <div class="mx-auto w-full lg:w-2/3 fixed bottom-0 lg:bottom-4 inset-x-0 z-50 lg:px-4 transition-transform duration-300 ease-in-out"
                     x-data="{ expanded: false, isLg: window.innerWidth >= 1024 }"
                     @resize.window="isLg = window.innerWidth >= 1024"
                     :style="(!isLg && !expanded) ? 'transform: translateY(calc(100% - 3.5rem));' : 'transform: translateY(0);'"
                     @click.outside="if(!isLg) expanded = false"
                >
                    <div class="shadow-[0_-10px_40px_rgba(0,0,0,0.1)] lg:shadow-md bg-base-300 transition-opacity duration-300 opacity-100 lg:opacity-[0.65] lg:hover:opacity-100 rounded-t-3xl lg:rounded-box border-t border-base-200 lg:border-none overflow-hidden flex flex-col">
                        {{-- タブレット用引き上げタブ (Edge-to-Edge) --}}
                        <div class="lg:hidden w-full flex flex-col items-center justify-center cursor-pointer h-14 bg-base-300 hover:bg-base-200 active:bg-base-200 transition-colors border-b border-base-content/10 shrink-0"
                             @click="expanded = !expanded">
                            <div class="w-20 h-1.5 bg-base-content/30 rounded-full mb-2"></div>
                            <div class="flex items-center text-base-content/80 text-sm font-bold tracking-wider gap-2">
                                <i class="fa-solid fa-chevron-up transition-transform duration-300"
                                   :class="expanded ? 'rotate-180' : ''"></i>
                                <span x-text="expanded ? '{{ __('ledger.action_bar_close') }}' : '{{ __('ledger.action_bar_open') }}'"></span>
                            </div>
                        </div>

                        <div class="p-4 lg:p-4 pb-8 lg:pb-4 overflow-y-auto max-h-[60vh]">
                            <div class="flex flex-wrap items-center justify-center md:justify-between gap-4">
                                <div class="flex flex-wrap items-center justify-center gap-2 order-2 md:order-1">
                                    <x-ledger.close-window-button/>
                                    <x-mary-button label="{{ __('ledger.prefill.generate_link') }}" icon="o-link"
                                                   class="btn-outline btn-info" wire:click.prevent="generatePrefillLink"
                                                   spinner="generatePrefillLink"/>
                                </div>
                                <div class="flex flex-wrap items-center justify-center gap-2 order-1 md:order-2">
                                    {{-- バリデーションエラー再表示ボタン --}}
                                    <x-mary-button x-show="!validationSummaryOpen && validationErrorCount > 0"
                                                   x-transition:enter="transition cubic-bezier(0.34, 1.56, 0.64, 1) duration-[500ms]"
                                                   x-transition:enter-start="opacity-0 scale-0 translate-y-12"
                                                   x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-cloak
                                                   icon="o-exclamation-triangle"
                                                   class="btn-error border-2 border-white/20 animate-pulse relative"
                                                   @click="$dispatch('toggle-validation-summary')">
                                        <span>{{ __('ledger.validation.show_summary') }}</span>
                                        <div class="badge badge-white text-error font-black ml-2 border-none shadow-sm"
                                             x-text="validationErrorCount"></div>
                                    </x-mary-button>

                                    @if ($ledgerDefineRecord->workflow_enabled)
                                        {{-- 下書き保存ボタン --}}
                                        <x-mary-button label="{{ __('ledger.save_draft') }}" icon="o-pencil"
                                                       class="btn-secondary btn-lg px-8 tracking-wide shadow-md"
                                                       wire:click.prevent="saveDraft" spinner="saveDraft"
                                                       wire:key="save-draft-button-{{ $ledgerId ?? ($ledgerDefineId ?? 'new') }}"/>

                                        {{-- 点検依頼ボタン --}}
                                        <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                                       icon="o-paper-airplane"
                                                       class="btn-success btn-lg px-8 tracking-wide shadow-md"
                                                       wire:click.prevent="requestInspection"
                                                       spinner="requestInspection"/>
                                    @else
                                        {{-- 直接保存ボタン --}}
                                        <x-mary-button label="{{ __('ledger.save') }}" icon="o-pencil"
                                                       class="btn-primary btn-lg px-8 tracking-wide shadow-md"
                                                       wire:click.prevent="saveDirectly" spinner="saveDirectly"/>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- 現在のステータス表示 --}}
                <div class="text-center text-xs text-base-content/70 mt-3 w-full">
                    {{ __('ledger.workflow.current_status') }}:
                    {{ $ledgerRecord?->status?->label() ?? __('ledger.workflow.status.draft') }}
                </div>
            </x-mary-form>
    </div>
    @endif


    {{-- 担当者選択モーダルコンポーネントを呼び出し --}}
    {{-- このコンポーネントは $showAssigneeModal に応じて表示/非表示が切り替わる --}}
    @livewire('workflow.workflow-assignee-modal', [], ['key' => 'assignee-modal'])
    {{-- コメント入力モーダル --}}
    @livewire('workflow.workflow-comment-modal', ['ledgerId' => null], ['key' => 'workflow-comment-modal-create'])

    {{-- 事前入力リンクモーダル --}}
    <x-ledger.prefill-link-modal :generated-prefill-u-r-l="$generatedPrefillURL"/>


</div>
