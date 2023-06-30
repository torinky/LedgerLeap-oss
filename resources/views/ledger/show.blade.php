<x-app-layout title="DETAIL | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Record Diffs') }}
        </h2>
    </x-slot>

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

    @include('ledger.detail.table',compact('ledgerRecord'))
    <div class="container mx-auto mt-4 items-center text-sm text-gray-500 flex justify-end">
        <i class="fa-solid fa-user mr-2"></i>{{$ledgerRecord->modifier->name}}
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('updated at: ').$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}</span>
        <span class="ml-3"><i class="fa-solid fa-clock mr-2"></i>{{__('created at: ').$ledgerRecord->created_at->format('Y-m-d H:i:s')}}</span>
    </div>

    <div
        class="card mx-auto md:w-full lg:w-2/3 bg-primary-content text-neutral-content justify-center opacity-30 hover:opacity-100 transition-opacity inset-x-0 fixed bottom-3">
        <div class="card-body items-center text-center">
            <div class="card-actions justify-center">
                <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-outline btn-primary btn-wide"
                ><i class="fa-solid fa-pencil mr-2"></i>{{__('edit')}}</a>

                @if($ledgerRecord->ledger_diff_count>0)
                    <a href="{{ route('ledgerDiff.show', ['ledgerId'=>$ledgerRecord->id]) }}"
                       class="btn btn-outline btn-info ml-5"
                    ><i class="fa-solid fa-clock-rotate-left mr-2"></i>{{__('modifies')}}
                        <div class="badge badge-info badge-outline">{{$ledgerRecord->ledger_diff_count}}</div>
                    </a>
                @endif

                <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                        class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
            </div>
        </div>
    </div>

</x-app-layout>
