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
                     class="card w-full bg-neutral-500/10 shadow-xl">
            @csrf
            {{--            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">--}}

            @php
                $columnJs=[];
            @endphp


            <div class="card-body mb-32 space-y-3 pt-2">
                <x-mary-progress value="{{$progress}}" max="100"
                                 class="progress-warning h-3 w-full sticky top-24 md:top-20 z-10"/>
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    <div class="flex">
                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}} "></div>
                        <div
                            wire:key="content-{{$columnDefine->id}}"
                            x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                            class="w-full opacity-control-block opacity-50 hover:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->first ? 'initial-opacity-100' : '' }}"
                            @if($loop->first)
                                x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                            x-init="updateBackground('{{ $columnDefine->id }}')"
                            @endif

                        >

                            @if($columnDefine->type=='files')

                                <div class="">
                                    <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                                         wire:model.live="content"
                                                         wire:key="content-file-{{$columnDefine->id}}"
                                                         :columnDefine="$columnDefine"
                                                         :ledgerRecord="$ledgerRecord??[]"
                                                         multiple
                                                         allowImagePreview
                                                         imagePreviewMaxHeight="200"
                                    />
                                </div>
                            @else
                                <x-dynamic-component :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                                                     wire:model="content"
                                                     wire:key="content-input-{{$columnDefine->id}}"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                />

                            @endif
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
                        <div class="flex flex-wrap items-center justify-center gap-4"> {{-- gap で間隔調整 --}}

                            {{-- 下書き保存ボタン --}}
                            {{-- wire:click で下書き保存用メソッドを呼び出す --}}
                            <x-mary-button label="{{ __('ledger.save_draft') }}" icon="o-pencil"
                                           class="btn-secondary" wire:click.prevent="saveDraft" spinner="saveDraft"/>

                            {{-- 点検者/承認者 選択 (ステップ1では点検者のみ) --}}
                            {{-- ToDo: 将来的に Role 選択も可能にする --}}
                            <div class="form-control w-full max-w-xs">
                                <label class="label pb-0">
                                    <span class="label-text">{{ __('ledger.workflow.next_inspector') }}</span>
                                </label>
                                {{-- ユーザー選択 (シンプルな Select の例) --}}
                                {{-- ToDo: よりリッチなコンポーネント (SelectTree や検索付き Select) に変更検討 --}}
                                <x-mary-select
                                    label="" {{-- 上でラベル表示済み --}}
                                wire:model.live="selectedInspectorId"
                                    :options="$this->getInspectorOptions()" {{-- コンポーネント側で選択肢を取得するメソッドを用意 --}}
                                    placeholder="{{ __('ledger.workflow.select_inspector') }}"
                                    class="select-sm" {{-- 少し小さく表示 --}}
                                />
                                @error('selectedInspectorId') <span
                                    class="text-xs text-error">{{ $message }}</span> @enderror
                            </div>

                            {{-- 作成完了（点検依頼）ボタン --}}
                            {{-- wire:click で点検依頼用メソッドを呼び出す --}}
                            <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                           icon="o-paper-airplane"
                                           class="btn-primary" wire:click.prevent="requestInspection"
                                           spinner="requestInspection"
                                           :disabled="!$selectedInspectorId" {{-- 点検者が選択されていないと無効 --}}
                            />

                            {{-- (ステップ2以降で追加) 点検完了（承認申請）ボタン --}}
                            {{-- @if($this->canRequestApproval()) --}}
                            {{-- <x-mary-button label="{{ __('ledger.workflow.request_approval') }}" ... /> --}}
                            {{-- @endif --}}

                            {{-- (ステップ2以降で追加) 承認ボタン --}}
                            {{-- @if($this->canApprove()) --}}
                            {{-- <x-mary-button label="{{ __('ledger.workflow.approve') }}" ... /> --}}
                            {{-- @endif --}}

                            <x-ledger.close-window-button/> {{-- 既存の閉じるボタン --}}

                        </div>
                        {{-- 現在のステータス表示 (任意) --}}
                        <div class="text-center text-xs text-base-content/70 mt-2">
                            現在のステータス: {{ $ledgerRecord?->status?->label() ?? __('ledger.workflow.status.draft') }}
                        </div>
                    </div>
                </div>
            </div>
        </x-mary-form>

    @endif
    </div>
</div>

