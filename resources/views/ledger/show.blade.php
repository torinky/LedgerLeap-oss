<x-app-layout title="{{__('ledger.details')}}">
    @push('scripts')
        @vite(['resources/js/ledgerShow.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerShow.scss'])
    @endpush
    <div class="container max-w-full px-0 md:px-4 mt-4">
        <livewire:ledger.show :ledgerId="$ledger->id"/>
    </div>

    <script>
        (function() {
            @if (session('refresh_opener'))
                localStorage.setItem('ledger_list_needs_refresh', Date.now());
            @endif
        })();
    </script>
</x-app-layout>
