<x-app-layout title="Message | {{config('app.name')}}">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ledger.message') }}
        </h2>
    </x-slot>

    @if (session('status'))
        <div class="flex min-h-[6rem] min-w-[18rem] flex-wrap items-center justify-center gap-2 overflow-x-hidden ">
            <div class="alert alert-success max-w-4xl space-x-2">
                <div>
                    <i class="fas fa-circle-check opacity-70"></i>
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


    <div
        class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
        <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
            <div class="card-body flex flex-row justify-center items-center">
                <div class="card-actions justify-center place-items-center">

                    <x-ledger.close-window-button
                        :closeWindowMessage="__('ledger.close_view_window_message')"
                        :cancel="__('ledger.cancel')"
                    />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
