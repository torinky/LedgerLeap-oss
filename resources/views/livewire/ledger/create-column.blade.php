<div>
    <div
            class="background-image-change"
            @validation-summary-status.window="validationSummaryOpen = $event.detail.open; validationErrorCount = $event.detail.errorCount;"
            x-data="Object.assign(validationErrorNavigator(), {
            currentBg: null,
            validationSummaryOpen: true,
            validationErrorCount: 0,
            updateBackground(columnId) {
                this.currentBg = $wire.backgroundImages[columnId] || null;

//                console.log($wire.backgroundImages);
//                console.log(this.currentBg);

                if(this.currentBg == null || this.currentBg.length == 0) {
                    document.querySelector('.background-image-change').style.backgroundImage = ``;
                }else{
                    document.querySelector('.background-image-change').style.backgroundImage = `url('${this.currentBg}')`;
                }
            },
            focusFirstInput() {
                const firstInput = document.querySelector('.background-image-change input:first-child');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        })"
            x-init="focusFirstInput()"
    >

        {{--    css生成のためのダミー--}}
        <div class="hidden">
            <div class="bg-success"></div>
            <x-mary-input label="Name" placeholder="Your name" icon="o-user" hint="Your full name"/>
        </div>
        @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
            {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}"--}}
            <x-mary-form wire:submit="store"
                         method="post"
                         class="card mb-32 w-full bg-neutral-500/10 shadow-xl">
                @csrf
                {{--            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">--}}

                @php
                    $columnJs=[];
                @endphp


                <div class="card-body space-y-3 pt-2">
                    <x-mary-progress value="{{$progress}}" max="100"
                                     class="{{ !empty($validationErrors) ? 'progress-error' : 'progress-success' }} h-3 w-full sticky top-24 md:top-20 z-10"/>

                    {{-- バリデーションエラーサマリー (Issue #13-2) --}}
                    <x-validation-error-summary :errors="$validationErrors" :ledger-define="$ledgerDefineRecord" />

                    @foreach($groupedColumns as $groupName => $columnsInGroup)
                        <div class="collapse collapse-plus bg-base-200 hover:bg-base-200/20  mb-2" wire:key="group-{{ $groupName }}"
                             @if(!($collapsedStates[$groupName] ?? true)) open @endif
                             x-data="groupErrorBadge" data-group-name="{{ $groupName }}"> {{-- falseの時にopen --}}
                            <div class="collapse-title text-xl font-medium" wire:click="toggleGroup('{{ $groupName }}')">
                                <h3 class="text-lg font-bold flex items-center pr-10">
                                    <div class="flex items-center">
                                        @if(collect($columnsInGroup)->contains(fn($col) => $col->required))
                                            <div class="tooltip tooltip-right mr-2" data-tip="{{ __('ledger.form.required_group_indicator') }}">
                                                <x-mary-icon name="o-check-circle" class="w-6 h-6 text-error" />
                                            </div>
                                        @endif
                                        {{ $groupName }}
                                    </div>

                                    {{-- エラーバッジ表示 (Issue #17) --}}
                                    <div x-show="errorCount > 0" x-cloak class="ml-auto flex items-center gap-1.5 px-2.5 py-1 bg-error/10 text-error rounded-full border border-error/20 animate-pulse">
                                        <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4" />
                                        <span class="text-sm font-black font-mono leading-none" x-text="errorCount"></span>
                                    </div>
                                </h3>
                            </div>
                            <div class="collapse-content">
                                @foreach($columnsInGroup as $columnDefine)
                                    @php
                                        $validationKey = 'content.' . $columnDefine->id;
                                    @endphp
                                    @if(!$columnDefine->isHidden())
                                        <div class="flex mt-2" id="field-content-{{$columnDefine->id}}">
                                            <div class="w-1 bg-{{$labelColor[$columnDefine->id]}}"></div>
                                            <div
                                                    wire:key="content-{{$columnDefine->id}}"
                                                    x-data="{ showFixed: false }"
                                                    @field-fixed.window="if ($event.detail.field === '{{$validationKey}}') { showFixed = true; setTimeout(() => showFixed = false, 2000); }"
                                                    x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                                                    x-on:focusin="updateBackground('{{ $columnDefine->id }}')"
                                                    class="w-full opacity-control-block opacity-50 hover:opacity-100 focus-within:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->parent->first && $loop->first ? 'initial-opacity-100' : '' }}"
                                                    :class="{
                                                        'validation-error-highlight': {{ isset($validationErrors[$validationKey]) ? 'true' : 'false' }},
                                                        'validation-success-highlight': showFixed && !{{ isset($validationErrors[$validationKey]) ? 'true' : 'false' }}
                                                    }"
                                                    @if($loop->parent->first && $loop->first)
                                                        x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                                                        x-init="updateBackground('{{ $columnDefine->id }}')"
                                                    @endif
                                            >
                                                {{-- エラーアイコン (Issue #18) --}}
                                                @if(isset($validationErrors[$validationKey]))
                                                    <div class="validation-error-icon-wrapper tooltip tooltip-left" data-tip="{{ collect($validationErrors[$validationKey])->first() }}">
                                                        <x-mary-icon name="o-x-circle" class="w-5 h-5 text-error" />
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
                                                    <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success" />
                                                </div>

                                                @if($columnDefine->type==='files')
                                                    <x-ledger.form.files
                                                            :columnDefine="$columnDefine"
                                                            :ledgerDefineId="$ledgerDefineId"
                                                            :initial-files="[]"
                                                            multiple
                                                            allowImagePreview
                                                            imagePreviewMaxHeight="200"
                                                    />
                                                @else
                                                    @php
                                                        $componentName = 'ledger.form.'. str_replace('_', '-', $columnDefine->type);
                                                        // auto_number タイプの場合、text コンポーネントを使用
                                                        if ($columnDefine->type === 'auto_number') {
                                                            $componentName = 'ledger.form.text';
                                                        }
                                                    @endphp
                                                    <x-dynamic-component
                                                            :component="$componentName"
                                                            wire:model.live="content"
                                                            wire:key="content-input-{{$columnDefine->id}}"
                                                            :columnDefine="$columnDefine"
                                                            :ledgerRecord="$ledgerRecord??[]"
                                                    />
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


                {{-- アクションボタンエリア --}}
                <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                    <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                        <div class="card-body p-4"> {{-- パディング調整 --}}
                            <div class="flex flex-wrap items-center justify-center gap-4 w-full"> {{-- gap で間隔調整 --}}

                                {{-- バリデーションエラー再表示ボタン (Issue #49) - 独立したワイドボタンとして配置 --}}
                                <x-mary-button
                                    x-show="!validationSummaryOpen && validationErrorCount > 0"
                                    x-transition:enter="transition cubic-bezier(0.34, 1.56, 0.64, 1) duration-[500ms]"
                                    x-transition:enter-start="opacity-0 scale-0 translate-y-12"
                                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                    x-cloak
                                    icon="o-exclamation-triangle"
                                    class="btn-error btn-wide border-2 border-white/20 animate-pulse relative"
                                    @click="$dispatch('toggle-validation-summary')"
                                >
                                    <span>{{ __('ledger.validation.show_summary') }}</span>
                                    <div class="badge badge-white text-error font-black ml-2 border-none shadow-sm"
                                        x-text="validationErrorCount"></div>
                                </x-mary-button>

                                @if ($ledgerDefineRecord->workflow_enabled)
                                    {{-- 下書き保存ボタン --}}
                                    <x-mary-button label="{{ __('ledger.save_draft') }}" icon="o-pencil"
                                        class="btn-secondary btn-wide" wire:click.prevent="saveDraft" spinner="saveDraft"
                                        wire:key="save-draft-button-{{ $ledgerId ?? $ledgerDefineId ?? 'new' }}" />

                                    {{-- ToDo: 将来的に Role 選択も可能にする --}}
                                    {{-- 点検依頼ボタン (モーダルを開く) --}}
                                    {{-- 条件: 新規作成画面 または 編集画面でステータスが DRAFT --}}

                                    <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                        icon="o-paper-airplane" class="btn-success btn-wide"
                                        {{-- モーダルを開くメソッドを呼び出す --}} wire:click.prevent="requestInspection"
                                        spinner="requestInspection" />
                                @else
                                    {{-- 直接保存ボタン --}}
                                    <x-mary-button label="{{ __('ledger.save') }}" {{-- 通常の保存ラベル --}}
                                        icon="o-pencil" class="btn-primary btn-wide" wire:click.prevent="saveDirectly"
                                        {{-- 直接保存メソッド呼び出し --}} spinner="saveDirectly" />
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center justify-center w-full gap-2">
                                <x-mary-button 
                                    label="{{ __('ledger.prefill.generate_link') }}" 
                                    icon="o-link"
                                    class="btn-outline btn-info"
                                    wire:click.prevent="generatePrefillLink"
                                    spinner="generatePrefillLink"
                                />
                                <x-ledger.close-window-button/>
                            </div>

                            {{-- 現在のステータス表示 --}}
                            <div class="text-center text-xs text-base-content/70 mt-2">
                                {{__('ledger.workflow.current_status')}}: {{ $ledgerRecord?->status?->label() ?? __('ledger.workflow.status.draft') }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-mary-form>

        @endif
    </div>

    {{-- 担当者選択モーダルコンポーネントを呼び出し --}}
    {{-- このコンポーネントは $showAssigneeModal に応じて表示/非表示が切り替わる --}}
    @livewire('workflow.workflow-assignee-modal', [], ['key' => 'assignee-modal'])
    {{-- コメント入力モーダル --}}
    @livewire('workflow.workflow-comment-modal', ['ledgerId' => null], ['key' => 'workflow-comment-modal-create'])

    {{-- 事前入力リンクモーダル --}}
    <x-ledger.prefill-link-modal :generated-prefill-u-r-l="$generatedPrefillURL" />


</div>



