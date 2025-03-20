<x-app-layout title="{{__('ledger.define.edit_title')}}" class="bg-warning/30">
    @push('scripts')
        @vite(['resources/js/ledgerDefineEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerDefineEdit.scss'])
    @endpush
        <x-slot name="header" class="sticky top-0 z-10 ">
            <div class="ttl_3d5 warn md:flex md:items-center space-x-4">
                <h2 class="font-black text-xl text-warning-content md:text-2xl flex items-center">
        <span class="fa-layers fa-fw mr-2">
            <i class="fa-solid fa-book text-3xl" data-fa-transform="left-5 "></i>
            <i class="fa-solid fa-pencil text-2xl text-primary/70"
               data-fa-transform=" right-5 up-3"></i>
        </span>
                    <span> {{ __('ledger.define.edit_title') }}</span>
                </h2>
                <div class="text-warning-content/70 text-sm"><i
                        class="fas fa-book-open"></i> {{$ledgerDefineRecord->title}}</div>
            </div>
    </x-slot>

        <div class="container mx-auto ">
            {{--
                    @if (session('status'))
                        @include('components.ledger.alert',[
                           'type'=>'success',
                           'message'=>session('status'),
                           'refreshParentWindow'=>true,
                        ])
                    @endif
            --}}

        @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 space-y-5">
                    <!-- 2段組みのコンテンツ -->
                    <div class="flex flex-wrap items-center justify-center w-full space-y-5 mt-3">

                    <div class="card w-full bg-base-300 shadow-xl mx-5">
                        <div class="card-body p-3">
                            <h2 class="card-title">{{__('ledger.define.basic_setting')}}</h2>
                            <livewire:ledger-define.edit/>
                        </div>
                    </div>
                    <div class="card w-full bg-base-300 shadow-xl mx-5">
                        <div class="card-body p-3">
                            <h2 class="card-title">{{__('ledger.column.group_title')}}</h2>
                            <livewire:ledger-define.modify-column/>
                        </div>
                    </div>


                    {{--                </form>--}}
                    </div>
                    <div class="flex flex-wrap items-center justify-center w-full">
                        <div class="card w-full bg-base-300 shadow-xl mx-5">
                            <div class="card-body p-3">
                                <h2 class="card-title">{{__('ledger.define.preview')}}</h2>
                                <livewire:ledger-define.preview>
                            </div>
                        </div>

                    </div>
            </div>
                <div
                    class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                    <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                        <div class="card-body">
                            <div class="card-actions justify-center items-center">

                                <x-mary-button
                                    label="{{__('ledger.go_to')}}"
                                    icon="o-arrow-right-circle"
                                    class="btn btn-sm btn-neutral mr-4"
                                    link="{{ route('ledger.index',['l[0]'=>$ledgerDefineRecord->id]) }}"
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
                                          action="{{route('ledgerDefine.delete',$ledgerDefineRecord->id)}}">
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
