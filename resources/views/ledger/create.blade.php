<x-app-layout title="CREATE | DocumentCabinet">
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
            <i class="fas fa-plus-circle mr-2"></i>
            {{ __('Ledger.create_title') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        <livewire:ledger.create-column/>
    </div>

</x-app-layout>
