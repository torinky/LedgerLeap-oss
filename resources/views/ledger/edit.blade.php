<x-app-layout title="{{__('ledger.editTitle')}}">
    @php
        $confidentiality = \App\Services\ConfidentialityLevelService::getEffectiveLevel($ledgerDefineRecord);
        $canEditConfidentiality = auth()->user()->can('update', $ledgerDefineRecord);
    @endphp
    @if($confidentiality && $confidentiality['level'] !== 'public')
        <x-ledger.confidentiality-stamp
            :level="$confidentiality['level']"
            :label="$confidentiality['label']"
            :scopes="$confidentiality['scope_labels']"
            :tenant-id="tenant('id')"
            :source-type="$canEditConfidentiality ? ($confidentiality['source']['type'] ?? null) : null"
            :source-name="$confidentiality['source']['name'] ?? null"
            :source-id="$canEditConfidentiality ? ($confidentiality['source']['id'] ?? null) : null"
            :source-path="$confidentiality['source_path'] ?? null"
            :inherited="$confidentiality['inherited']"
        />
    @endif

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
                            <x-mary-icon name="o-pencil-square" class="text-secondary w-15" />
                        </div>
                        <div class="flex flex-col min-w-0 w-full">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                                <div class="min-w-0">
                                    <x-ledger.livewire-breadcrumbs
                                        :thisLedgerDefine="$ledgerDefineRecord"
                                        :breadcrumbs="$breadcrumbs"
                                        :isLivewire="false" />
                                    <h2 class="flex text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2 space-x-4">
                                        <span class="text-base-content/50"> {{ __('ledger.editTitle') }} </span><span class="divider divider-horizontal"></span><span>{{ $ledgerDefineRecord->title }}</span>
                                    </h2>
                                </div>

                                {{-- Metadata area --}}
                                <div class="flex flex-wrap items-center gap-3 text-sm md:text-base shrink-0 bg-base-200/60 p-1.5 rounded-lg border border-base-300">
                                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-warning/10 border border-warning/20">
                                        <span class="font-bold text-warning text-base md:text-lg">{{ $ledger->status->label() ?? '-' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                        <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.version') }}:</span>
                                        <span class="text-sm md:text-base">{{ $ledger->version+1 }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/30">
                                        <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.modifier.last') }}:</span>
                                        <x-mary-icon name="o-user" class="size-5 text-base-content/40" />
                                        <x-ledger.user-card-popover :user="$ledger->modifier" />
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                        <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.last_updated_at') }}:</span>
                                        <x-mary-icon name="o-calendar" class="size-5" />
                                        <span class="text-sm md:text-base">{{ $ledger->updated_at->format('Y-m-d H:i') }}</span>
                                    </div>
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
                                <x-mary-icon name="o-information-circle" class="size-5 text-primary" />
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

        <livewire:ledger.modify-column :ledger-id="$ledger->id"/>
    </div>

    {{-- vite dummy class to ensure compilation --}}
    <div class="hidden bg-error"></div>
</x-app-layout>
