<x-mary-card shadow class="bg-primary/50 text-primary-content border-none overflow-visible mb-6" body-class="bg-base-100/30 text-base-content p-4 pt-2">
    <x-slot:title>
        <div class="flex flex-col gap-1 -mt-1">
            <div class="bg-white/10 text-primary-content/80 rounded-lg px-3 py-0.5 text-xs font-medium w-fit max-w-full overflow-hidden">
                <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefine->id] ?? []" />
            </div>
            <h3 class="text-xl md:text-2xl font-bold tracking-tight flex items-center gap-3">
                <i class="fa-solid fa-book-open opacity-90"></i>
                {{$ledgerDefine->title}}
            </h3>
        </div>
    </x-slot:title>

    <x-slot:menu>
        <div class="flex items-center gap-1">
            <x-mary-button
                wire:click="openPermissionModal('LedgerDefine', {{ $ledgerDefineId }}, '{{ $ledgerDefineRecordsKeyById[$ledgerDefineId]->title }}')"
                tooltip-left="{{ __('ledger.access_and_permissions.title') }}"
                icon="o-shield-check"
                class="btn-xs md:btn-sm btn-ghost hover:bg-white/20 border-none text-primary-content"
                spinner
            />
            <x-mary-button
                wire:click="openActivityModal('LedgerDefine', {{ $ledgerDefineId }}, '{{ $ledgerDefineRecordsKeyById[$ledgerDefineId]->title }}')"
                tooltip-left="{{ __('ledger.activity.title') }}"
                icon="o-clock"
                class="btn-xs md:btn-sm btn-ghost hover:bg-white/20 border-none text-primary-content"
                spinner
            />
            <x-mary-button
                wire:click.prevent="$parent.toggleLedgerDefineId({{ $ledgerDefine->id }})"
                icon="o-x-mark"
                class="btn-xs md:btn-sm btn-ghost btn-square hover:bg-white/20 border-none text-primary-content"
                tooltip-left="{{__('ledger.close')}}"
                spinner="$parent.toggleLedgerDefineId"
            />
        </div>
    </x-slot:menu>

    <div class="grid grid-cols-1 lg:grid-cols-[auto_1fr] gap-x-6 gap-y-4 items-start">
        {{-- Left: Identity & Functional Actions --}}
        <div class="space-y-4">
            {{-- Score Stats: overall优先，page作为注释 --}}
            @php
                $displayStats = $overallStats ?? $scoreStats ?? null;
                $pageStats = $scoreStats ?? null;
            @endphp
            @if($displayStats && $displayStats['has_scores'])
                <div class="flex flex-wrap gap-1.5 items-center">
                    @php
                        $avgScoreClass = match(true) {
                            $displayStats['avg_score'] >= 70 => 'badge-success text-white',
                            $displayStats['avg_score'] >= 40 => 'badge-primary text-white',
                            $displayStats['avg_score'] >= 20 => 'badge-info text-white',
                            default => 'badge-ghost'
                        };
                    @endphp
                    <x-mary-badge :value="__('ledger.scoring.avg_score') . ': ' . $displayStats['avg_score']"
                                 icon="o-chart-bar"
                                 class="{{ $avgScoreClass }} badge-sm font-bold shadow-sm" />

                    <x-mary-badge :value="__('ledger.scoring.max') . ': ' . $displayStats['max_score']"
                                 icon="o-arrow-trending-up"
                                 class="badge-ghost badge-sm font-medium opacity-70" />

                    <div class="text-sm text-base-content/40 font-medium px-1">
                        ({{ $displayStats['count'] }}{{ __('ledger.records') }})
                    </div>

                    @if($overallStats && $pageStats && $pageStats['count'] !== $overallStats['count'])
                        <div class="text-sm text-base-content/30 font-medium px-1">
                            {{ __('ledger.scoring.this_page') }}: {{ $pageStats['avg_score'] }} / {{ $pageStats['count'] }}{{ __('ledger.records') }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- Main Actions --}}
            <div class="flex flex-wrap items-center gap-2">
                @if($canCreate)
                    <a href="{{ route('ledger.create', ['tenant' => $currentTenantId, 'ledgerDefineId'=>$ledgerDefine->id]) }}"
                       class="btn btn-neutral btn-md px-8 shadow-lg hover:scale-105 active:scale-95 transition-all"
                       target="ledgerCreate_{{$ledgerDefine->id}}}}">
                        <i class="fas fa-circle-plus"></i>
                        {{__('ledger.create')}}
                    </a>
                @else
                    <div class="tooltip" data-tip="{{ __('ledger.not_allow_create') }}">
                        <button class="btn btn-neutral btn-md px-8 opacity-50" disabled>
                            <i class="fas fa-circle-plus"></i>
                            {{__('ledger.create')}}
                        </button>
                    </div>
                @endif

                @if($canView)
                    <livewire:ledger.export :ledgerDefineId="$ledgerDefine->id"
                                            :ledgerDefineTitle="$ledgerDefine->title"
                                            :$keywords
                                            :$filter
                                            wire:key="ledger_export-{{ $ledgerDefine->id }}"
                    />
                @else
                    <div class="tooltip" data-tip="{{ __('ledger.not_allow_view') }}">
                        <button class="btn btn-outline btn-secondary bg-base-200/30 btn-md px-8" disabled>
                            <i class="fas fa-file-csv"></i>
                            {{__('ledger.export_csv')}}
                        </button>
                    </div>
                @endif

                @if($canManage)
                    <a href="{{ route('ledgerDefine.edit', ['tenant' => $currentTenantId, 'ledgerDefineId'=>$ledgerDefine->id]) }}"
                       class="btn btn-outline btn-primary btn-sm bg-primary/5"
                       target="ledgerDefineEdit_{{$ledgerDefine->id}}}}">
                        <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                    </a>
                @else
                    <div class="tooltip" data-tip="{{ __('ledger.not_allow_manage') }}">
                        <button class="btn btn-outline btn-primary btn-sm opacity-50" disabled>
                            <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Description Area --}}
        @if($ledgerDefine->list_description)
            <div class="w-full">
                <div class="bg-base-200/40 rounded-xl p-3 border border-base-300/30 shadow-inner">
                    <div class="prose prose-xs max-w-none text-base-content/70 leading-relaxed custom-scrollbar">
                        @php
                            $descriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefine->list_description ?? ''),
                                null,
                                $ledgerDefine
                            );
                        @endphp
                        <x-expandable-content
                            :content="$descriptionHtml"
                            max-height="4.5rem"
                        />
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Footer: Tags --}}
    <div class="mt-4 pt-3 border-t border-base-200">
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerDefine->id"
                                     :tags="$ledgerDefine->tags"
                                     wire:key="ledger_define_tag-{{ $ledgerDefine->id }}"
        />
    </div>
</x-mary-card>
