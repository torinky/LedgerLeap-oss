<x-app-layout title="{{__('Ledger.editTitle')}}">
    @push('scripts')
        @vite(['resources/js/ledgerEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerEdit.scss'])
    @endpush
        <x-slot name="header" class="sticky top-0 z-10">
            <div class="ttl_3d5 warn md:flex md:items-center space-x-4 bg-warning/40 rounded">
                <h2 class="font-black text-lg text-warning-content/60 sm:text-xl md:text-2xl">
                    <i class="fas fa-pen-to-square mr-2"></i>
                    {{ __('ledger.editTitle') }}
                </h2>
                <div class="text-warning-content/50 text-sm"><i
                        class="fas fa-book-open"></i> {{$ledgerDefineRecord->title}}</div>
            </div>
        </x-slot>
        {{--    <divclass="p-0 md:p-4 bg-base-100 rounded-b-xl grid grid-cols-1 xl:grid-cols-2 gap-10">--}}
        <div class="p-0 md:p-4 bg-base-100 rounded-b-xl grid grid-cols-1 gap-5">

            <div class="collapse bg-base-200 collapse-arrow border-base-300 border">
                <input type="checkbox" id="createDescription" checked/>
                <label for="createDescription"
                       class="collapse-title font-medium">{{$ledgerDefineRecord->title}}</label>
                <div class="collapse-content">
                    <x-markdown class="prose text-sm leading-relaxed max-w-none">
                        {!! $ledgerDefineRecord->create_description !!}
                    </x-markdown>
                </div>
            </div>

            <livewire:ledger.modify-column/>
    </div>

</x-app-layout>
