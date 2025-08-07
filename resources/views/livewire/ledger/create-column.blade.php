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
                    @foreach($groupedColumns as $groupName => $columnsInGroup)
                        <div class="collapse collapse-plus bg-base-200 mb-2" wire:key="group-{{ $groupName }}"
                             @if(!($collapsedStates[$groupName] ?? true)) open @endif> {{-- falseの時にopen --}}
                            <div class="collapse-title text-xl font-medium" wire:click="toggleGroup('{{ $groupName }}')">
                                <h3 class="text-lg font-bold flex items-center">
                                    {{ $groupName }}
                                    @if(collect($columnsInGroup)->contains(fn($col) => $col->required))
                                        <span class="ml-2 text-error text-sm">{{ __('ledger.form.required_group_indicator') }}</span>
                                    @endif
                                </h3>
                            </div>
                            <div class="collapse-content">
                                @foreach($columnsInGroup as $columnDefine)
                                    <div class="flex mt-2">
                                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}}"></div>
                                        <div
                                                wire:key="content-{{$columnDefine->id}}" {{-- wire:key 追加推奨 --}}
                                                x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                                                class="w-full opacity-control-block opacity-50 hover:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->parent->first && $loop->first ? 'initial-opacity-100' : '' }}"
                                                @if($loop->parent->first && $loop->first)
                                                    x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                                                    x-init="updateBackground('{{ $columnDefine->id }}')"
                                                @endif
                                        >
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
                                                    $componentName = 'ledger.form.'. Str::kebab($columnDefine->type);
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
                            <div class="flex flex-wrap items-center justify-center w-full">
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
    @livewire('workflow.workflow-assignee-modal', key('assignee-modal'))
    {{-- コメント入力モーダル --}}
    @livewire('workflow.workflow-comment-modal', ['ledgerId' => null],
    key('workflow-comment-modal-create'))


</div>

