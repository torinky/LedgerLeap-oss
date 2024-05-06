<x-appWithDrawer-layout title="Ledger Definitions">
    {{--
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ledger Defines') }}
            </h2>
        </x-slot>
    --}}
    <div class="">
        @push('stylesheets')
            @vite(['resources/sass/ledgerIndex.scss'])
        @endpush
        <x-slot name="drawer">
            <livewire:folder.tree/>
        </x-slot>
        <div class="container mx-auto">
            <livewire:ledger-define.records-table/>
        </div>
    </div>
</x-appWithDrawer-layout>
