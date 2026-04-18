<x-app-layout title="{{__('ledger.details')}}">
    @push('scripts')
        @vite(['resources/js/ledgerShow.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerShow.scss'])
    @endpush
    <div class="container max-w-full px-0 md:px-4 mt-4">
        {{-- ヘッダー集約カード --}}
        <x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
            <x-slot:title>
                <div class="flex flex-col w-full">
                    <div class="flex items-center gap-3 w-full">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-info/80 text-xl"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <div class="text-sm font-normal text-base-content/70 w-full mb-2">
                                <x-ledger.livewire-breadcrumbs 
                                    :thisLedgerDefine="$ledgerDefineRecord" 
                                    :breadcrumbs="$breadcrumbs" 
                                    :isLivewire="false" />
                            </div>
                            {{-- <div class="text-xs text-base-content/50 font-bold tracking-wider mb-1 uppercase">{{ __('ledger.details') }}</div> --}}
                            <h2 class="text-xl md:text-2xl font-black tracking-tighter text-base-content flex items-center gap-2 truncate">
                                <i class="fas fa-book-open text-base-content/40 text-lg"></i>
                                <span class="truncate">{{ $ledgerDefineRecord->title }}</span>
                            </h2>
                        </div>
                    </div>
                </div>
            </x-slot:title>
            
            @if($ledgerDefineRecord->detail_description)
                <div class="mt-4 text-base-content" x-data="{ expanded: false }">
                    <div class="bg-base-200/50 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/80">
                        <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                            <div class="font-bold text-sm flex items-center gap-2">
                                <x-mary-icon name="o-information-circle" class="w-4 h-4 text-info" />
                                説明 / ガイドライン
                            </div>
                            <x-mary-icon name="o-chevron-down" class="w-4 h-4 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                        </div>
                        <div x-show="expanded" x-collapse>
                            <div class="pt-3 mt-2 border-t border-base-300">
                                @php
                                    $detailDescriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                        app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->detail_description), 
                                        null, 
                                        $ledgerDefineRecord
                                    );
                                @endphp
                                <div class="prose text-sm leading-relaxed max-w-none">
                                    {!! $detailDescriptionHtml !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </x-mary-card>

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
