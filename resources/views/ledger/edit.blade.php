<x-app-layout title="EDIT | DocumentCabinet">
    @push('scripts')
        {{--        <script src="{{ asset('js/ledgerEdit.js') }}"></script>--}}
        @vite(['resources/js/ledgerEdit.js'])
    @endpush
    @push('stylesheets')
        {{--        <link rel="stylesheet" href="{{ asset('css/ledgerEdit.css') }}">--}}
        @vite(['resources/sass/ledgerEdit.scss'])
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-info leading-tight">
            <i class="fas fa-pencil mr-2"></i>
            {{ __('ledger.editTitle') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        <livewire:ledger.modify-column/>
    </div>

</x-app-layout>
