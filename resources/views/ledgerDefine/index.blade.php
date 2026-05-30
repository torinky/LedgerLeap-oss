<x-appWithDrawer-layout title="{{__('ledger.define.setting')}}" class="bg-warning/30">
    {{--
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ledger Defines') }}
            </h2>
        </x-slot>
    --}}
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush
    <div class="container max-w-full px-0 md:px-4">
        <livewire:ledger-define.records-table/>
    </div>
</x-appWithDrawer-layout>
