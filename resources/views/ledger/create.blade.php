<x-app-layout title="CREATE | DocumentCabinet">
    @push('scripts')
        <script src="{{ asset('js/ledgerEdit.js') }}"></script>
    @endpush
    @push('stylesheets')
        <link rel="stylesheet" href="{{ asset('css/ledgerEdit.css') }}">
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Record Create') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        <livewire:ledger.create-column/>
    </div>

</x-app-layout>
