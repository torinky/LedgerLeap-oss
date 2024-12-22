<x-app-layout title="EDIT | DocumentCabinet">
    @push('scripts')
        @vite(['resources/js/ledgerEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerEdit.scss'])
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-info leading-tight">
            <i class="fas fa-pencil mr-2"></i>
            {{ __('ledger.editTitle') }}
        </h2>
    </x-slot>
        {{--    <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 xl:grid-cols-2 gap-10">--}}
        <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 gap-5">

            <div class="collapse bg-base-200 collapse-arrow border-base-300 border">
                <input type="checkbox" id="createDescription" checked/>
                <label for="createDescription"
                       class="collapse-title text-xl font-medium">{{$ledgerDefineRecord->title}}</label>
                <div class="collapse-content">
                    <x-markdown class="prose">
                        {!! $ledgerDefineRecord->create_description !!}
                    </x-markdown>
                </div>
            </div>

            <livewire:ledger.modify-column/>
    </div>

</x-app-layout>
