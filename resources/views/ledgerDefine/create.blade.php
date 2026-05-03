<x-app-layout title="{{__('ledger.define.create_title')}}" class="bg-warning/50">
    <x-slot name="header" class="sticky top-0 z-10 ">
        <div class="ttl_3d5 warn md:flex md:items-center space-x-4 bg-warning/40 rounded">
            <h2 class="font-black text-xl text-warning-content/70 md:text-2xl flex items-center">
                <span class="relative inline-flex items-center justify-center w-10 h-10 mr-2">
                    <i class="fa-solid fa-book text-3xl"></i>
                    <i class="fa-solid fa-plus-circle text-xl text-warning-content/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                </span>
            {{ __('ledger.define.create_title') }}
        </div>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 space-y-5">
        <!-- 2段組みのコンテンツ -->
        {{--        <div class="flex flex-wrap items-center justify-center w-full space-y-5 mt-3">--}}

        <div class="card w-full bg-base-300 shadow-xl mx-5">
            <div class="card-body p-3">
                <h2 class="card-title">{{__('ledger.define.basic_setting')}}</h2>
                <livewire:ledger-define.create/>
                </div>
            </div>
        {{--
                    <div class="card w-full bg-base-300 shadow-xl mx-5">
                        <div class="card-body p-3">
                            <h2 class="card-title">{{__('ledger.column.group_title')}}</h2>
                            <livewire:ledger-define.modify-column/>
                        </div>
                    </div>
        --}}


        {{--                </form>--}}
        {{--        </div>--}}
    </div>
    <div
        class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-[100]">
        <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
            <div class="card-body">
                <div class="card-actions justify-center items-center">
                    <x-ledger.close-window-button/>
                </div>
            </div>
        </div>
    </div>


</x-app-layout>
