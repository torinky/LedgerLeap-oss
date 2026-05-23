<x-appWithDrawer-layout title="{{__('ledger.records_title')}}">
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    {{--
        <x-slot name="header">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('Ledger Records') }}
            </h2>
        </x-slot>
    --}}

    <x-slot name="drawer">
        <livewire:folder.tree/>
    </x-slot>



    <div class="container max-w-full px-0 md:px-4">
        <livewire:ledger.records-table/>
    </div>

</x-appWithDrawer-layout>
