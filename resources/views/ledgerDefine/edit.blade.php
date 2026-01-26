<x-app-layout title="{{__('ledger.define.edit_title')}}" class="bg-warning/30">
    @push('scripts')
        @vite(['resources/js/ledgerDefineEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerDefineEdit.scss'])
    @endpush
    <x-slot name="header" class="sticky top-0 z-50 ">
        <div class="ttl_3d5 warn md:flex md:items-center space-x-4 bg-warning/40 rounded">
            <h2 class="font-black text-xl text-warning-content/60 md:text-2xl flex items-center">
        <span class="fa-layers fa-fw mr-2">
            <i class="fa-solid fa-book text-3xl" data-fa-transform="left-5 "></i>
            <i class="fa-solid fa-pencil text-2xl text-warning-content/70"
               data-fa-transform=" right-5 up-3"></i>
        </span>
                <span> {{ __('ledger.define.edit_title') }}</span>
            </h2>
            <div class="text-warning-content/50 text-sm"><i
                        class="fas fa-book-open"></i> {{$ledgerDefineRecord->title}}</div>
        </div>
    </x-slot>

    <div class="mx-auto px-4 py-6 max-w-[1600px]">
        @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- 上段: 基本設定 (常に全幅) --}}
                <div class="lg:col-span-2">
                    <div class="card bg-base-100 border border-base-300 shadow-xl overflow-hidden">
                        <h2 class="card-title font-black bg-primary/5 text-primary px-6 py-4 border-b border-primary/50 text-base flex items-center gap-3 uppercase tracking-tighter">
                            <x-mary-icon name="o-cog-6-tooth" />
                            {{__('ledger.define.basic_setting')}}
                        </h2>
                        <div class="card-body p-6 md:p-8">
                            <livewire:ledger-define.edit/>
                        </div>
                    </div>
                </div>

                {{-- 下段左: 項目設定 --}}
                <div class="card bg-base-100 border border-base-300 shadow-xl overflow-hidden h-fit">
                    <h2 class="card-title font-black bg-accent/5 text-accent px-6 py-4 border-b border-accent/50 text-base flex items-center gap-3 uppercase tracking-tighter">
                        <x-mary-icon name="o-queue-list" class="w-5 h-5"/>
                        {{__('ledger.column.group_title')}}
                    </h2>
                    <div class="card-body p-4 md:p-6">
                        <livewire:ledger-define.modify-column/>
                    </div>
                </div>

                {{-- 下段右: プレビュー (追従) --}}
                <div class="card bg-base-100 border border-base-300 shadow-xl overflow-hidden lg:sticky lg:top-24 h-fit">
                    <h2 class="card-title font-black bg-secondary/5 text-secondary px-6 py-4 border-b border-secondary/50 text-base flex items-center gap-3 uppercase tracking-tighter">
                        <x-mary-icon name="o-magnifying-glass" class="w-5 h-5"/>
                        {{__('ledger.define.preview')}}
                    </h2>
                    <div class="card-body p-4 md:p-6">
                        <livewire:ledger-define.preview/>
                    </div>
                </div>
            </div>

            {{-- 画面下部アクションツールバー --}}
            <div class="mx-auto md:w-full inset-x-0 fixed bottom-6 z-50 px-4 pointer-events-none">
                <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                    <div class="card-body">
                        <div class="card-actions justify-center items-center">

                            <x-mary-button
                                    label="{{__('ledger.go_to')}}"
                                    icon="o-arrow-right-circle"
                                    class="btn btn-sm btn-neutral mr-4"
                                    link="{{ route('ledger.index', ['tenant' => tenant()?->id, 'l[0]' => $ledgerDefineRecord->id]) }}"
                            />

                            <label for="delete-modal" class="btn btn-outline btn-error btn-sm ml-5">
                                <i class="fa-solid fa-trash mr-2"></i>{{__('ledger.define.remove')}}</label>

                            <x-ledger.close-window-button/>
                        </div>
                    </div>
                </div>
            </div>

            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box bg-error/70 text-error-content">
                    <h3 class="font-bold text-lg"><i class="fas fa-trash mr-2"></i>{{__('ledger.define.remove')}}
                    </h3>
                    <p class="py-4">{{__('ledger.define.remove_message')}}
                        <br/>{{__('ledger.remove_records_message')}}
                    </p>
                    @can('delete_ledger_defines')
                        <div class="modal-action">
                            <div class="btnContainer">
                                <form method="POST"
                                      action="{{ route('ledgerDefine.delete', ['tenant' => tenant()?->id, 'ledgerDefineId' => $ledgerDefineRecord->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error"
                                            name="deleteLedgerDefine"><i
                                                class="fas fa-trash mr-2"></i>{{__('ledger.define.remove')}}</button>
                                </form>
                            </div>
                            <label for="delete-modal" class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                        </div>
                    @else
                        <!-- 権限がない場合の表示 -->
                        <span class="text-error">削除する権限がありません</span>
                    @endcan
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
