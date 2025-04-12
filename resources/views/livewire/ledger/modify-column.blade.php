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
                     class="card w-full bg-neutral-500/10 shadow-xl"
        >
            @csrf

            {{--            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">--}}
            @php
                $columnJs=[];
            @endphp

            <div class="card-body mb-32 space-y-3 ">
                <x-mary-progress value="{{$progress}}" max="100"
                                 class="progress-warning h-3 w-full sticky top-24 md:top-20 z-10"/>
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    <div class="flex">
                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}}"></div>
                        <div
                            wire:key="content-{{$columnDefine->id}}" {{-- wire:key 追加推奨 --}}
                        x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                            class="w-full opacity-control-block opacity-50 hover:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->first ? 'initial-opacity-100' : '' }}"
                            @if($loop->first)
                                x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                            x-init="updateBackground('{{ $columnDefine->id }}')"
                            @endif
                        >
                            @if($columnDefine->type=='files')
                                <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                                     wire:model="content"
                                                     wire:model="deletedContent"
                                                     wire:key="content-file-{{$columnDefine->id}}"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                                     multiple
                                                     allowImagePreview
                                                     imagePreviewMaxHeight="200"
                                />

                            @else
                                <x-dynamic-component
                                    :component="'ledger.form.'. Str::kebab($columnDefine->type)"
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
            {{-- 変更: アクションボタンエリアをワークフロー対応に --}}
            <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                    <div class="card-body p-4"> {{-- パディング調整 --}}
                        <div class="flex flex-wrap items-center justify-center gap-4"> {{-- gap で間隔調整 --}}

                            {{-- 下書き保存ボタン (常に表示して良い) --}}
                            <x-mary-button label="{{ __('ledger.save_draft') }}" icon="o-pencil"
                                           class="btn-secondary" wire:click.prevent="saveDraft" spinner="saveDraft"/>

                            {{-- 点検者選択 UI (ワークフロー開始時に表示) --}}
                            {{-- ToDo: 現在のステータスが DRAFT の場合のみ表示する条件を追加 (ステップ3以降) --}}
                            <div class="form-control w-full max-w-xs">
                                <label class="label pb-0">
                                    <span class="label-text">{{ __('ledger.workflow.next_inspector') }}</span>
                                </label>
                                <x-mary-select
                                    label=""
                                    wire:model.live="selectedInspectorId"
                                    :options="$this->getInspectorOptions()"
                                    placeholder="{{ __('ledger.workflow.select_inspector') }}"
                                    class="select-sm"
                                />
                                @error('selectedInspectorId') <span
                                    class="text-xs text-error">{{ $message }}</span> @enderror
                            </div>

                            {{-- 作成完了（点検依頼）ボタン (ワークフロー開始時に表示) --}}
                            {{-- ToDo: 現在のステータスが DRAFT の場合のみ表示する条件を追加 (ステップ3以降) --}}
                            <x-mary-button label="{{ __('ledger.workflow.request_inspection') }}"
                                           icon="o-paper-airplane"
                                           class="btn-primary" wire:click.prevent="requestInspection"
                                           spinner="requestInspection"
                                           :disabled="!$selectedInspectorId"
                            />

                            {{-- 既存の削除ボタン (変更なし、位置調整は任意) --}}
                            @if(isset($ledgerRecord->id))
                                <label for="delete-modal" class="btn btn-outline btn-sm btn-error"><i
                                        class="fa-solid fa-trash mr-2"></i>{{__('ledger.delete')}}</label>
                            @endif

                            <x-ledger.close-window-button/> {{-- 既存の閉じるボタン --}}

                        </div>
                        {{-- 現在のステータス表示 --}}
                        <div class="text-center text-xs text-base-content/70 mt-2">
                            現在のステータス: {{ $ledgerRecord?->status?->label() ?? __('ledger.workflow_status.draft') }}
                        </div>
                    </div>
                </div>
            </div>
            {{--
                        <div
                            class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                                <div class="card-body flex flex-row justify-center items-center">
                                    <div class="card-actions justify-center place-items-center">
                                        <x-mary-button label="{{__('ledger.modify_message')}}" icon="o-pencil-square"
                                                       class="btn btn-lg btn-warning btn-wide mr-4" type="submit" spinner="store"/>
                                        --}}
            {{--
                                                                    <button type="submit" class="btn btn-lg btn-warning btn-wide"><i
                                                                            class="fa-solid fa-pencil mr-2"></i>{{__('ledger.modify_message')}}</button>
                                        --}}{{--

                                        @if(isset($ledgerRecord->id))
                                            <label for="delete-modal" class="btn btn-outline btn-sm btn-error ml-10"><i
                                                    class="fa-solid fa-trash mr-2"></i>{{__('ledger.delete')}}</label>
                                        @endif
                                        <x-ledger.close-window-button/>

                                    </div>
                                </div>
                            </div>
                        </div>
            --}}

        </x-mary-form>

        @if(isset($ledgerRecord->id))
            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box bg-warning text-warning-content">
                    <h3 class="font-bold text-lg space-x-2"><i
                            class="fas fa-trash-alt"></i><span>{{__('ledger.remove_title')}}</span></h3>
                    <p class="py-4">{{__('ledger.remove_message')}}</p>
                    <div class="modal-action">
                        <div class="btnContainer">
                            <form method="POST" action="{{route('ledger.destroy',$ledgerRecord)}}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error space-x-2"
                                        name="deleteLedgerDefine"><i
                                        class="fas fa-trash-alt"></i>{{__('ledger.delete')}}
                                </button>
                            </form>
                        </div>
                        <label for="delete-modal" class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                    </div>
                </div>
            </div>
        @endif

    @endif
</div>
