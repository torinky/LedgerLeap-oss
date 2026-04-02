<div>
    <x-element.loading-overlay tier="1" target="store" :delay="false" />

    <div class="relative">
        {{-- Temporarily disabled to debug button click issues --}}
        {{-- <x-element.loading-overlay tier="1" target="store" /> --}}

        {{-- Tier 1 Skeleton - Only for store (saving) --}}
        <div wire:loading.delay wire:target="store" class="p-8 shimmer">
            <x-element.skeleton-input-form rows="6" />
        </div>

        <div wire:loading.delay.remove wire:target="store">
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
                    <x-mary-input label="Name" placeholder="Your name" icon="o-user" hint="Your full name" />
                </div>
                @if ($ledgerDefineRecord && $ledgerDefineRecord->column_define)
                    <x-mary-form wire:submit="store" method="post"
                        class="card mb-32 w-full bg-neutral-500/10 shadow-xl">
                        @csrf

                        {{--            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}"> --}}
                        @php
                            $columnJs = [];
                        @endphp

                        <div class="card-body space-y-3 ">
                            <x-mary-progress value="{{ $progress }}" max="100"
                                class="{{ !empty($validationErrors) ? 'progress-error' : 'progress-success' }} h-3 w-full sticky top-24 md:top-20 z-10" />

                            {{-- バリデーションエラーサマリー (Issue #13-2) --}}
                            <x-validation-error-summary :errors="$validationErrors" :ledger-define="$ledgerDefineRecord" />

                            {{-- 全て展開・折りたたみトグル (Issue #53) --}}
                            <div
                                class="flex justify-end items-center gap-3 bg-base-200/30 px-4 py-2 rounded-full border border-base-300 mb-4 self-end transition-all hover:bg-base-200/50">
                                <span
                                    class="text-[10px] font-black text-base-content/40 uppercase tracking-widest">{{ __('ledger.column.expand_all') }}</span>
                                <x-mary-toggle wire:model.live="allExpanded" right tight
                                    class="toggle-xs toggle-primary" />
                            </div>

                            @foreach ($groupedColumns as $groupName => $columnsInGroup)
                                @php
                                    $isGroupRequired = collect($columnsInGroup)->contains(fn($col) => $col->required);
                                @endphp
                                <div class="collapse collapse-plus bg-base-200 hover:bg-base-200/20 mb-2 {{ !$collapsedStates[$groupName] ? 'collapse-open' : '' }}"
                                    wire:key="group-{{ md5($groupName) }}" x-data="{
                                        ...groupErrorBadge(),
                                        isCollapsed: @entangle('collapsedStates.' . $groupName).live,
                                        toggle() {
                                            this.isCollapsed = !this.isCollapsed;
                                        }
                                    }"
                                    :class="{ 'collapse-open': !isCollapsed }" data-group-name="{{ $groupName }}">
                                    {{-- checkboxを使わずにJSで制御することで、瞬時の開閉アニメーションを実現 --}}
                                    <div class="collapse-title text-xl font-medium cursor-pointer" @click="toggle()">
                                        <h3 class="text-lg font-bold flex items-center pr-10">
                                            <div class="flex items-center">
                                                @if ($isGroupRequired)
                                                    <div class="tooltip tooltip-right mr-2"
                                                        data-tip="{{ __('ledger.form.required_group_indicator') }}">
                                                        <x-mary-icon name="o-check-circle" class="w-6 h-6 text-error" />
                                                    </div>
                                                @endif
                                                {{ $groupName }}
                                            </div>

                                            {{-- エラーバッジ表示 (Issue #17) --}}
                                            <div x-show="errorCount > 0" x-cloak
                                                class="ml-auto flex items-center gap-1.5 px-2.5 py-1 bg-error/10 text-error rounded-full border border-error/20 animate-pulse">
                                                <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4" />
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
                                            @endif>

                                            {{-- エラーアイコン (Issue #18) --}}
                                            @if (isset($validationErrors[$validationKey]))
                                                <div class="validation-error-icon-wrapper tooltip tooltip-left"
                                                    data-tip="{{ collect($validationErrors[$validationKey])->first() }}">
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
                                                x-transition:leave-end="opacity-0" x-cloak>
                                                <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success" />
                                            </div>

                                            @if ($columnDefine->type === 'files')
                                                <x-ledger.form.files :columnDefine="$columnDefine" :ledgerDefineId="$ledgerDefineId"
                                                    :initialFiles="$filePondInitialFiles[$columnDefine->id] ?? []" multiple allowImagePreview
                                                    imagePreviewMaxHeight="150" />
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
                                                <x-dynamic-component :component="$componentName" wire:model.live="content"
                                                    wire:key="content-input-{{ $columnDefine->id }}" :columnDefine="$columnDefine"
                                                    :ledgerRecord="$ledgerRecord ?? []" />
                                            @endif
                                    </div>
                                </div>
                            @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
        <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
            <div class="card-body p-4">
                <div class="flex flex-wrap items-center justify-center gap-4">
                    {{-- バリデーションエラー再表示ボタン (Issue #49) - 独立したワイドボタンとして配置 --}}
                    <x-mary-button x-show="!validationSummaryOpen && validationErrorCount > 0"
                        x-transition:enter="transition cubic-bezier(0.34, 1.56, 0.64, 1) duration-[500ms]"
                        x-transition:enter-start="opacity-0 scale-0 translate-y-12"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-cloak
                        icon="o-exclamation-triangle"
                        class="btn-error btn-wide border-2 border-white/20 animate-pulse relative"
                        @click="$dispatch('toggle-validation-summary')">
                        <span>{{ __('ledger.validation.show_summary') }}</span>
                        <div class="badge badge-white text-error font-black ml-2 border-none shadow-sm"
                            x-text="validationErrorCount"></div>
                    </x-mary-button>

                    @if ($ledgerDefineRecord->workflow_enabled)
                        {{-- 保存ボタン --}}
                        <x-mary-button label="{{ __('ledger.save_changes') }}" icon="o-pencil"
                            class="btn-primary btn-wide" wire:click.prevent="saveChanges" spinner="saveChanges"
                            :disabled="$ledgerRecord?->isLocked()" />

                        {{-- 点検者選択 UI --}}
                        @if ($ledgerRecord?->status === \App\Enums\WorkflowStatus::DRAFT)
                            <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                icon="o-paper-airplane" class="btn-success btn-wide"
                                wire:click.prevent="requestInspection" spinner="requestInspection" />
                        @endif
                    @else
                        {{-- 直接保存ボタン --}}
                        <x-mary-button label="{{ __('ledger.save') }}" icon="o-pencil" class="btn-primary btn-wide"
                            wire:click.prevent="saveDirectly" spinner="saveDirectly" :disabled="$ledgerRecord?->isLocked()" />
                    @endif

                    <div class="flex flex-wrap items-center justify-center w-full gap-2">
                        <x-mary-button label="{{ __('ledger.prefill.generate_link') }}" icon="o-link"
                            class="btn-outline btn-info" wire:click.prevent="generatePrefillLink"
                            spinner="generatePrefillLink" />
                        <x-ledger.close-window-button />

                        {{-- 削除ボタン --}}
                        @if ($ledgerRecord?->id && !$ledgerRecord?->isLocked())
                            <label for="delete-modal" class="btn btn-outline btn-error btn-sm">
                                <i class="fa-solid fa-trash mr-2"></i>{{ __('ledger.delete') }}
                            </label>
                        @endif
                    </div>
                </div>
                {{-- 現在のステータス表示 --}}
                <div class="text-center text-xs text-base-content/70 mt-2">
                    {{ __('ledger.workflow.current_status') }}
                    : {{ $ledgerRecord?->status?->label() ?? __('ledger.workflow.status.draft') }}
                </div>
            </div>
        </div>
    </div>

    </x-mary-form>

    {{-- 担当者選択モーダルコンポーネント呼び出し --}}
    @livewire('workflow.workflow-assignee-modal', [], ['key' => 'assignee-modal-workflow'])

    {{-- 編集確認モーダル --}}
    <x-mary-modal wire:model="confirmingEdit" title="{{ __('ledger.workflow.confirm_edit_while_pending_title') }}"
        persistent icon="o-exclamation-triangle">
        {{ __('ledger.workflow.confirm_edit_while_pending_text') }}

        <div class="mt-4">
            <x-mary-textarea label="{{ __('ledger.workflow.edit_reason_label') }}"
                hint="{{ __('ledger.workflow.edit_reason_hint') }}" wire:model="editReason" rows="3" />
        </div>

        <x-slot:actions>
            <x-mary-button label="{{ __('Cancel') }}" @click="$wire.confirmingEdit = false" icon="o-x-circle" />
            <x-mary-button label="{{ __('ledger.save_and_return_to_draft') }}" icon="o-arrow-uturn-left"
                class="btn-warning" wire:click="saveChangesAndReturnToDraft" spinner="saveChangesAndReturnToDraft" />
        </x-slot:actions>
    </x-mary-modal>

    @if (isset($ledgerRecord->id))
        <input type="checkbox" id="delete-modal" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box bg-warning text-warning-content">
                <h3 class="font-bold text-lg space-x-2"><i
                        class="fas fa-trash-alt"></i><span>{{ __('ledger.remove_title') }}</span></h3>
                <p class="py-4">{{ __('ledger.remove_message') }}</p>
                <div class="modal-action">
                    <div class="btnContainer">
                        <form method="POST"
                            action="{{ route('ledger.destroy', ['tenant' => $this->tenantId, 'ledger' => $ledgerRecord]) }}">
                            {{--                                @dd($tenantId); --}}
                            {{--                                <form method="POST" action="{{route('ledger.destroy',['tenant' => $tenantId, 'ledger' => $ledgerRecord])}}"> --}}
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-error space-x-2" name="deleteLedgerDefine"><i
                                    class="fas fa-trash-alt"></i>{{ __('ledger.delete') }}
                            </button>
                        </form>
                    </div>
                    <label for="delete-modal" class="btn btn-outline ml-5">{{ __('actions.cancel') }}</label>
                </div>
            </div>
        </div>
    @endif

    @endif

    {{-- このコンポーネントは $showAssigneeModal に応じて表示/非表示が切り替わる --}}
    @livewire('workflow.workflow-assignee-modal', [], ['key' => 'assignee-modal-bottom'])

    {{-- コメント入力モーダル --}}
    @livewire('workflow.workflow-comment-modal', ['ledgerId' => $ledgerRecord?->id], ['key' => 'workflow-comment-modal-show'])

    {{-- 事前入力リンクモーダル --}}
    <x-ledger.prefill-link-modal :generated-prefill-u-r-l="$generatedPrefillURL" />

    {{-- ファイルインスペクター --}}
    @livewire('attached-file.file-inspector', [], ['key' => 'file-inspector-modify-column'])
</div>
</div>
</div>
</div>
