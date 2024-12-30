<x-app-layout title="{{__('Ledger.create')}}">
    @push('scripts')
        @vite(['resources/js/ledgerEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerEdit.scss'])
    @endpush
        <x-slot name="header" class="sticky top-0 z-10">
            <div class="ttl_3d5 md:flex md:items-center space-x-4">
                <h2 class="font-black text-lg text-info md:text-xl">
                    <i class="fas fa-plus-circle mr-2"></i>
                    {{ __('Ledger.create') }}
                </h2>
                <div class="text-info/90 text-sm"><i class="fas fa-home"></i> {{$ledgerDefineRecord->title}}</div>
            </div>
    </x-slot>
        {{--    <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 xl:grid-cols-2 gap-10 ">--}}
        <div class="p-8 bg-base-100 rounded-b-xl grid grid-cols-1 gap-5 ">

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


            <livewire:ledger.create-column/>

        </div>


</x-app-layout>
