<x-app-layout title="CSV Import | DocumentCabinet">
    @push('scripts')
        {{--        @vite(['resources/js/ledgerEdit.js'])--}}
    @endpush
    @push('stylesheets')
        {{--        @vite(['resources/sass/ledgerEdit.scss'])--}}
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Record CSV Import') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        <h1>CSVファイルアップロード</h1>
        <form action="{{ route('ledger.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="file" name="csv_file" accept=".csv">
            <input type="hidden" name="ledger_define_id" value="{{$ledgerDefineId}}">
            <button type="submit">アップロード</button>
        </form>
        <livewire:ledger.import/>
    </div>

</x-app-layout>
