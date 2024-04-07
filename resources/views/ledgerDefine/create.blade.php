<x-app-layout title="SETTING | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ledger.define.create_title') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        @if (session('status'))
            <div class="flex min-h-[6rem] min-w-[18rem] flex-wrap items-center justify-center gap-2 overflow-x-hidden ">
                <div class="alert alert-success  max-w-4xl">
                    <div>
                        <i class="far fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                </div>
            </div>
            <script type="text/javascript">
                window.onunload = function () {
                    reload_parent_window();
                }

                //親ウィンドウの更新処理
                function reload_parent_window() {
                    //自身を開いたウィンドウが存在する場合
                    if ((window.opener && !window.opener.closed)) {
                        window.opener.location.reload();
                        //自身がiframeの子である場合
                    } else if (window != window.parent) {
                        window.parent.location.reload();
                    }
                }
            </script>
        @endif

        <div class="flex flex-wrap items-center justify-center">
            <form action="{{ route('ledgerDefine.store')}}" method="post">
                @csrf

                <div>
                    <label for="title">{{__('ledger.define.name')}}</label>
                    <input type="hidden" name="title" value="">
                    <input name="title" type="text"
                           value=""
                           placeholder="Type here"
                           class="input input-bordered w-full max-w-xs"/>

                </div>

                <div class="flex-1 m-5">
                    <label for="folder_id" class="ml-3">{{__('ledger.folder_containing')}}</label>
                    <select
                        name="folder_id"
                        class="select input-bordered">
                        @foreach($folderRecords as $folderRecord)
                            <option
                                value="{{$folderRecord->id}}" {{  $initialFolderId == $folderRecord->id ? 'selected' : '' }}
                            >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                        @endforeach
                    </select>

                </div>

                {{--                    <livewire:ledger-define.modify-column/>--}}

                <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
                    <button type="submit" class="btn btn-outline btn-primary btn-wide"><i
                            class="fa-solid fa-pencil mr-2"></i>{{__('ledger.define.save')}}</button>
                    <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                            class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</a>
                </div>

            </form>
        </div>
    </div>

</x-app-layout>
