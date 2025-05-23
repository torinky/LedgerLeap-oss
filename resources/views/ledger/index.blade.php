<x-appWithDrawer-layout title="{{__('ledger.records_title')}}">
{{--
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight">
            {{ __('Ledger Records') }}
        </h2>
    </x-slot>
--}}
    @push('stylesheets')
        {{--        <link rel="stylesheet" href="{{ asset('css/ledgerIndex.css') }}">--}}
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    <x-slot name="drawer">
        <livewire:folder.tree/>
    </x-slot>



    <div class="container max-w-full px-0 md:px-4">
        <livewire:ledger.records-table/>
    </div>

</x-appWithDrawer-layout>
