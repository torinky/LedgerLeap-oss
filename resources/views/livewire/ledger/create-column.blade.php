<div>
    <div
            class="background-image-change"
            x-data="{
            currentBg: null,
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
        }"
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
                                     class="progress-warning h-3 w-full sticky top-24 md:top-20 z-10"/>

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
                                    <div class="flex mt-2">
                                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}}"></div>
                                        <div
                                                wire:key="content-{{$columnDefine->id}}"
                                                x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                                                x-on:focusin="updateBackground('{{ $columnDefine->id }}')"
                                                class="w-full opacity-control-block opacity-50 hover:opacity-100 focus-within:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->parent->first && $loop->first ? 'initial-opacity-100' : '' }} {{ isset($validationErrors['content.'.$columnDefine->id]) ? 'validation-error-highlight' : '' }}"
                                                @if($loop->parent->first && $loop->first)
                                                    x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                                                    x-init="updateBackground('{{ $columnDefine->id }}')"
                                                @endif
                                        >
                                            {{-- エラーアイコン (Issue #18) --}}
                                            @if(isset($validationErrors['content.'.$columnDefine->id]))
                                                <div class="validation-error-icon-wrapper tooltip tooltip-left" data-tip="{{ collect($validationErrors['content.'.$columnDefine->id])->first() }}">
                                                    <x-mary-icon name="o-x-circle" class="w-5 h-5 text-error" />
                                                </div>
                                            @endif

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
                                @if ($ledgerDefineRecord->workflow_enabled)
                                    <div class="join flex flex-wrap items-center justify-center w-full">

                                        {{-- 下書き保存ボタン --}}
                                        <x-mary-button label="{{ __('ledger.save_draft') }}" icon="o-pencil"
                                                       class="btn-secondary btn-wide join-item"
                                                       wire:click.prevent="saveDraft"
                                                       spinner="saveDraft"
                                                       wire:key="save-draft-button-{{$ledgerId ?? $ledgerDefineId ??'new'}}"
                                        />

                                        {{-- ToDo: 将来的に Role 選択も可能にする --}}
                                        {{-- 点検依頼ボタン (モーダルを開く) --}}
                                        {{-- 条件: 新規作成画面 または 編集画面でステータスが DRAFT --}}

                                        <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                                       icon="o-paper-airplane"
                                                       class="btn-success btn-wide join-item"
                                                       {{-- モーダルを開くメソッドを呼び出す --}}
                                                       wire:click.prevent="requestInspection"
                                                       spinner="requestInspection"
                                        />
                                    </div>

                                    {{-- (ステップ2以降で追加) 点検完了（承認申請）ボタン --}}
                                    {{-- @if($this->canRequestApproval()) --}}
                                    {{-- <x-mary-button label="{{ __('ledger.workflow.request_approval') }}" ... /> --}}
                                    {{-- @endif --}}

                                    {{-- (ステップ2以降で追加) 承認ボタン --}}
                                    {{-- @if($this->canApprove()) --}}
                                    {{-- <x-mary-button label="{{ __('ledger.workflow.approve') }}" ... /> --}}
                                    {{-- @endif --}}

                                @else

                                    {{-- 直接保存ボタン --}}
                                    <div class="flex flex-wrap items-center justify-center w-full">
                                        <x-mary-button label="{{ __('ledger.save') }}" {{-- 通常の保存ラベル --}}
                                        icon="o-pencil"
                                                       class="btn-primary btn-wide join-item"
                                                       wire:click.prevent="saveDirectly" {{-- 直接保存メソッド呼び出し --}}
                                                       spinner="saveDirectly"
                                        />
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center justify-center w-full gap-2">
                                <x-mary-button 
                                    label="{{ __('ledger.prefill.generate_link') }}" 
                                    icon="o-link"
                                    class="btn-outline btn-info"
                                    wire:click.prevent="generatePrefillLink"
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
    <x-mary-modal wire:model="showPrefillModal" class="backdrop-blur" title="">
        <div class="space-y-4" x-data="{ 
            showSuccess: false, 
            showWarning: false,
            init() {
                // モーダルが開いたときにURLを選択
                this.$watch('$wire.showPrefillModal', (value) => {
                    if (value) {
                        this.$nextTick(() => {
                            setTimeout(() => {
                                const textarea = document.getElementById('prefill-url-textarea');
                                if (textarea) {
                                    textarea.focus();
                                    textarea.select();
                                }
                            }, 200);
                        });
                    }
                });
            }
        }">
            {{-- カスタムタイトル --}}
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                <span>{{ __('ledger.prefill.modal_title') }}</span>
            </h3>
            <p class="text-sm">{{ __('ledger.prefill.description') }}</p>
            
            {{-- コピー成功メッセージ --}}
            <div x-show="showSuccess" 
                 x-transition
                 @prefill-copy-success.window="showSuccess = true; showWarning = false; setTimeout(() => showSuccess = false, 3000)"
                 class="alert alert-success wrap-break-word">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="wrap-break-word">{{ __('ledger.prefill.copy_success') }}</span>
            </div>
            
            {{-- コピー失敗メッセージ --}}
            <div x-show="showWarning" 
                 x-transition
                 @prefill-copy-failed.window="showWarning = true; showSuccess = false; setTimeout(() => { const textarea = document.getElementById('prefill-url-textarea'); if (textarea) textarea.select(); }, 100)"
                 class="alert alert-warning wrap-break-word">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <div class="wrap-break-word overflow-hidden">
                    <div class="font-bold wrap-break-word">{{ __('ledger.prefill.auto_copy_failed_title') }}</div>
                    <div class="text-xs wrap-break-word">{{ __('ledger.prefill.auto_copy_failed_description') }}</div>
                </div>
            </div>
            
            {{-- URLを選択可能なテキストエリアとして表示（Safari対応） --}}
            <div>
{{--
                <label class="label">
                    <span class="label-text text-xs text-base-content/70 wrap-break-word whitespace-normal">
                        {{ __('ledger.prefill.manual_copy_instruction') }}
                    </span>
                </label>
--}}
                <textarea
                    id="prefill-url-textarea"
                    readonly 
                    class="textarea textarea-bordered w-full font-mono text-xs"
                    rows="3"
                    @click="$el.select()"
                >{{ $generatedPrefillURL }}</textarea>
            </div>
            
            <x-mary-alert icon="o-information-circle" class="alert-info">
                {{ __('ledger.prefill.info_qr_or_share') }}
            </x-mary-alert>
        </div>
        
        <x-slot:actions>
            <x-mary-button 
                label="{{ __('ledger.prefill.copy_to_clipboard') }}" 
                icon="o-clipboard-document"
                class="btn-primary"
                wire:click="copyPrefillLinkToClipboard"
            />
            <x-mary-button 
                label="{{ __('ledger.close') }}" 
                @click="$wire.showPrefillModal = false"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- クリップボードコピー用のAlpine.jsスクリプト --}}
    @script
    <script>
        Livewire.on('copy-to-clipboard', (event) => {
            console.log('copy-to-clipboard event received:', event);
            
            // Livewire 3では名前付き引数が直接プロパティとして渡される
            const url = event.url || event[0]?.url || event[0];
            
            if (!url) {
                console.error('No URL provided to copy');
                return;
            }
            
            console.log('Copying URL to clipboard:', url);
            
            // Safariなど一部のブラウザでClipboard APIが動作しない場合のフォールバック
            // テキストエリアを使った古い方法を試す
            const copyToClipboardFallback = (text) => {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    // iOS Safari対応
                    textArea.setSelectionRange(0, textArea.value.length);
                    const successful = document.execCommand('copy');
                    console.log('Fallback copy result:', successful);
                    document.body.removeChild(textArea);
                    return successful;
                } catch (err) {
                    console.error('Fallback copy error:', err);
                    document.body.removeChild(textArea);
                    return false;
                }
            };
            
            // まずClipboard APIを試す
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    console.log('Clipboard copy successful');
                    $wire.call('notifyCopySuccess');
                }).catch((error) => {
                    console.warn('Clipboard API failed, trying fallback method:', error);
                    // フォールバック方法を試す
                    if (copyToClipboardFallback(url)) {
                        console.log('Fallback copy successful');
                        $wire.call('notifyCopySuccess');
                    } else {
                        console.error('Fallback copy failed, showing manual copy instruction');
                        // フォールバックも失敗した場合は、テキストエリアを選択して手動コピーを促す
                        const textarea = document.getElementById('prefill-url-textarea');
                        if (textarea) {
                            textarea.select();
                        }
                        $wire.call('notifyCopyFailed');
                    }
                });
            } else {
                // Clipboard APIがサポートされていない場合は直接フォールバックを使う
                console.log('Clipboard API not supported, using fallback method');
                if (copyToClipboardFallback(url)) {
                    console.log('Fallback copy successful (no API)');
                    $wire.call('notifyCopySuccess');
                } else {
                    console.error('Fallback copy failed (no API)');
                    const textarea = document.getElementById('prefill-url-textarea');
                    if (textarea) {
                        textarea.select();
                    }
                    $wire.call('notifyCopyFailed');
                }
            }
        });
    </script>
    @endscript

</div>







