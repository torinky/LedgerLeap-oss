<x-app-layout title="SETTING | DocumentCabinet">
    @push('scripts')
        {{--    viteビルドシステムとは別に読み込まないとソートが機能しない（DOM生成タイミング絡み）    --}}
        @vite(['resources/js/ledgerDefineEdit.js'])
        <script src="{{ asset('js/livewire-sortable.js') }}"></script>
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerDefineEdit.scss'])
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Define Setting') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        @if (session('status'))
            @include('components.ledger.alert',[
               'type'=>'success',
               'message'=>session('status'),
               'refreshParentWindow'=>true,
            ])
        @endif

        @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
            <div class="flex flex-wrap items-center justify-center w-full">
                <form action="{{ route('ledgerDefine.update',$ledgerDefineRecord->id)}}" method="post" class="w-full">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $ledgerDefineRecord->id }}">

                    <div class="flex-1 m-5">
                        <label for="title" class="ml-3">{{__('title')}}</label>
                        <input type="hidden" name="title" value="{{$ledgerDefineRecord->title}}">
                        <input name="title" type="text"
                               value="{{$ledgerDefineRecord->title}}"
                               placeholder="Type here"
                               class="input input-bordered w-full max-w-xs"/>

                    </div>
                    <div class="flex-1 m-5">
                        <label for="folder_id" class="ml-3">{{__('Belonging folder')}}</label>
                        <select
                            name="folder_id"
                            class="select input-bordered">
                            @foreach($folderRecords as $folderRecord)
                                <option
                                    value="{{$folderRecord->id}}" {{  $ledgerDefineRecord->folder_id == $folderRecord->id ? 'selected' : '' }}
                                >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                            @endforeach
                        </select>

                    </div>

                    <livewire:ledger-define.modify-column/>


                    <div
                        class="card mx-auto md:w-full lg:w-2/3 bg-primary-content text-base-100 justify-center opacity-30 hover:opacity-90 transition-opacity inset-x-0 fixed bottom-3">
                        <div class="card-body items-center text-center">
                            <div class="  items-center justify-center">
                                <button type="submit" class="btn btn-outline btn-primary btn-wide"><i
                                        class="fa-solid fa-pencil mr-2"></i>{{__('save')}}</button>
                                <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                        class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
                                <label for="delete-modal" class="btn btn-outline btn-error ml-10"><i
                                        class="fa-solid fa-trash mr-2"></i> {{__('delete ledger')}}</label>

                            </div>
                        </div>

                </form>
            </div>

            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{__('delete ledger')}}</h3>
                    <p class="py-4">{{__('This ledger will be deleted')}}<br/>
                        {{__('Ledger in records will be deleted')}}</p>
                    <div class="modal-action">
                        <div class="btnContainer">
                            <form method="POST" action="{{route('ledgerDefine.delete',$ledgerDefineRecord->id)}}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn"
                                        name="deleteLedgerDefine">{{__('delete ledger')}}</button>
                            </form>
                        </div>
                        <label for="delete-modal" class="btn btn-outline ml-5">{{__('cancel')}}</label>
                    </div>
                </div>
            </div>
        @endif
    </div>


</x-app-layout>
