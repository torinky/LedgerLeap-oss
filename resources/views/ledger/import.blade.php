<x-app-layout title="CSV Import | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Record CSV Import') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        <livewire:ledger.import/>
    </div>

</x-app-layout>
