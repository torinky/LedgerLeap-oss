<x-app-layout title="{{__('ledger.details')}}">
    @push('scripts')
        @vite(['resources/js/ledgerShow.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerShow.scss'])
    @endpush
    <x-slot name="header" class="sticky top-0 z-10">
        <div class="ttl_3d5 md:flex md:items-center space-x-4 bg-info/40 rounded">
            <h2 class="font-black text-lg text-info-content/60 md:text-xl">
                <i class="fas fa-list mr-2"></i>
                {{ __('ledger.details') }}
            </h2>
            <div class="text-info-content/50 text-sm"><i class="fas fa-book-open"></i> {{$ledgerDefineRecord->title}}
            </div>
        </div>
    </x-slot>

    <div class="p-0 md:p-4 bg-base-100 rounded-b-xl grid grid-cols-1 gap-5">

        <div class="collapse bg-base-200 collapse-arrow border-base-300 border">
            <input type="checkbox" id="createDescription" checked/>
            <label for="createDescription"
                   class="collapse-title font-medium">{{$ledgerDefineRecord->title}}</label>
            <div class="collapse-content">
                <x-markdown class="prose text-sm leading-relaxed max-w-none">
                @if($ledgerDefineRecord->detail_description)
                    {!! app(App\Services\AutoLinkService::class)->convert(app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->detail_description), null, $ledgerDefineRecord) !!}
                @endif
                </x-markdown>
            </div>
        </div>
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
