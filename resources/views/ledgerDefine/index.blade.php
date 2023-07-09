<x-appWithDrawer-layout title="Ledger Definitions">
    {{--
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ledger Defines') }}
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

    {{--
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        You're logged in!
                    </div>
                    <p>
                        {{$name}}
                    </p>
                </div>
            </div>
        </div>
    --}}

    <div class="container mx-auto">
        <livewire:ledger-define.records-table/>
    </div>

</x-appWithDrawer-layout>
