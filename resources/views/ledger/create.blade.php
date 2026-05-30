<x-app-layout title="{{ __('ledger.create') }}">
    @push('scripts')
        @vite(['resources/js/ledgerEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerEdit.scss'])
    @endpush

    <div class="container max-w-full px-0 md:px-4 mt-4">
        {{-- Unified header card matching the detail page pattern --}}
        <x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
            <x-slot:title>
                <div class="flex flex-col w-full">
                    <div class="flex items-center gap-3 w-full">
                        <div class="shrink-0 hidden md:block">
                            <x-mary-icon name="o-plus-circle" class="text-warning w-15" />
                        </div>
                        <div class="flex flex-col min-w-0 w-full">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                                <div class="min-w-0">
                                    <x-ledger.livewire-breadcrumbs
                                        :thisLedgerDefine="$ledgerDefineRecord"
                                        :breadcrumbs="$breadcrumbs"
                                        :isLivewire="false" />
                                    <h2 class="flex text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2 space-x-4">
                                        <span class="text-base-content/50"> {{ __('ledger.create') }} </span><span class="divider divider-horizontal"></span><span>{{ $ledgerDefineRecord->title }}</span>
                                    </h2>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:title>

            @if($ledgerDefineRecord->create_description)
                <div class="mt-4 text-base-content" x-data="{ expanded: false }">
                    <div class="bg-base-200/70 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/90">
                        <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                            <div class="font-bold text-base md:text-lg flex items-center gap-2">
                                <x-mary-icon name="o-information-circle" class="size-5 text-warning" />
                                {{ __('ledger.description') }} / {{ __('ledger.guideline') }}
                            </div>
                            <span class="inline-flex transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                                <x-mary-icon name="o-chevron-down" class="size-5" />
                            </span>
                        </div>
                        <div x-show="expanded" x-collapse>
                            <div class="pt-3 mt-2 border-t border-base-300">
                                @php
                                    $descriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                        app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->create_description),
                                        null,
                                        $ledgerDefineRecord
                                    );
                                @endphp
                                <div class="prose prose-sm md:prose-base text-sm md:text-base leading-relaxed max-w-none prose-p:my-2 prose-headings:mb-2 prose-headings:mt-4">
                                    {!! $descriptionHtml !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </x-mary-card>

        <livewire:ledger.create-column :ledger-define-id="$ledgerDefineRecord->id" :prefill-params="$prefillParams ?? []" />
    </div>
</x-app-layout>
