<x-app-layout title="SETTING | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-info leading-tight">
                <span class="fa-layers fa-fw mr-2">
                    <i class="fa-solid fa-book text-3xl" data-fa-transform="left-6 "></i>
                    <i class="fa-solid  fa-plus-circle text-primary/70"
                       data-fa-transform=" right-6 up-10"></i>
                </span>
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

                <input type="hidden" name="title" value="">
                <label class="form-control w-full " for="title">
                    <div class="label">
                        <span class="label-text">{{__('ledger.define.title')}}</span>
                    </div>
                    <input name="title" type="text"
                           value=""
                           placeholder="{{__('ledger.type_here')}}"
                           class="input input-bordered w-full "/>
                </label>


                {{--                <div class="flex-1 m-5">--}}
                <label for="folder_id" class="form-control w-full max-w-xs">
                    <div class="label">
                        <span class="label-text">{{__('ledger.folder.containing')}}</span>
                    </div>
                    <select
                        name="folder_id"
                        class="select input-bordered">
                        @foreach($folderRecords as $folderRecord)
                            <option
                                value="{{$folderRecord->id}}" {{  $initialFolderId == $folderRecord->id ? 'selected' : '' }}
                            >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                        @endforeach
                    </select>
                </label>
                {{--                </div>--}}

                {{--                    <livewire:ledger-define.modify-column/>--}}

                {{--
                                <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
                                    <button type="submit" class="btn btn-outline btn-primary btn-wide"><i
                                            class="fa-solid fa-pencil mr-2"></i>{{__('ledger.define.create')}}</button>
                                    <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                            class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</a>
                                </div>
                --}}
                <div
                    class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                    <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                        <div class="card-body">
                            <div class="card-actions justify-center items-center">
                                <button type="submit" class="btn btn-primary btn-wide btn-lg">
                                    <i class="fa-solid fa-plus-circle mr-2"></i>{{__('ledger.define.create')}}</button>

                                <x-ledger.close-window-button/>
                            </div>
                        </div>
                    </div>
                </div>


            </form>
        </div>
    </div>

</x-app-layout>
