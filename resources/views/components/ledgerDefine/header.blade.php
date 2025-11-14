@props([
    'canCreate'=>false,
    'canView'=>false,
    'canManage'=>false,
    'ledgerDefine'=>null,
    'breadcrumbsPerLedgerDefine'=>[],
    'keywords'=>[],
    'filter'=>[],
    'ledgerDefineId'=>null,
    'ledgerDefineRecordsKeyById'=>[],
    'scoreStats'=>null,
    'currentTenantId' => null,
])
<div
    class="flex flex-row justify-content-between items-center bg-base-300 mt-0 px-4 text-sm rounded-t-box text-base-content/70 ">
    <h3 class="text-2xl font-medium leading-tight text-primary space-x-3 my-2 mr-4">
        <span><i class="fa-solid fa-book-open mr-2"></i>{{$ledgerDefine->title}}</span>
        @if($scoreStats && $scoreStats['has_scores'])
            <span class="text-sm font-normal text-base-content/70 ml-4">
                @php
                    $avgScoreClass = match(true) {
                        $scoreStats['avg_score'] >= 70 => 'badge-success',
                        $scoreStats['avg_score'] >= 40 => 'badge-primary',
                        $scoreStats['avg_score'] >= 20 => 'badge-info',
                        $scoreStats['avg_score'] > 0 => 'badge-ghost',
                        default => 'badge-ghost'
                    };
                @endphp
                <span class="badge {{ $avgScoreClass }} badge-sm gap-1">
                    <i class="fas fa-chart-line text-xs"></i>
                    {{ __('ledger.scoring.avg_score') }}: {{ $scoreStats['avg_score'] }}
                </span>
                <span class="badge badge-ghost badge-sm gap-1 ml-1">
                    <i class="fas fa-arrow-up text-xs"></i>
                    {{ __('ledger.scoring.max') }}: {{ $scoreStats['max_score'] }}
                </span>
                <span class="text-xs text-base-content/50">
                    ({{ $scoreStats['count'] }}{{ __('ledger.records') }})
                </span>
            </span>
        @endif
    </h3>
        <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefine->id] ?? []"
        />
    <div class="flex-grow text-right">
        <x-mary-button
                wire:click="openPermissionModal('LedgerDefine', {{ $ledgerDefineId }}, '{{ $ledgerDefineRecordsKeyById[$ledgerDefineId]->title }}')"
                label="{{ __('ledger.access_and_permissions.title') }}"
                icon="o-shield-check"
                class="btn-xs btn-ghost"
                spinner
        />
        <x-mary-button
                wire:click="openActivityModal('LedgerDefine', {{ $ledgerDefineId }}, '{{ $ledgerDefineRecordsKeyById[$ledgerDefineId]->title }}')"
                label="{{ __('ledger.activity.title') }}"
                icon="o-clock"
                class="btn-xs btn-ghost"
                spinner
        />
        <a href="#" class="btn btn-square btn-xs tooltip items-center pt-1"
           data-tip="{{__('ledger.close')}}"
           wire:click="toggleLedgerDefineId({{ $ledgerDefine->id }})"
        ><i class="fas fa-times"></i></a>
    </div>
</div>
<div class="prose text-xs leading-relaxed w-full max-w-none px-4">
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

<div class="grid justify-items-end mx-4">

    <div class="flex flex-row  space-x-2 place-items-center">
        @if($canCreate)
            <a href="{{ route('ledger.create', ['tenant' => $currentTenantId, 'ledgerDefineId'=>$ledgerDefine->id]) }}"
               class="btn btn-neutral relative inline-flex w-48 "
               target="ledgerCreate_{{$ledgerDefine->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
                {{__('ledger.create')}}
            </a>
        @else
            <div class="tooltip" data-tip="{{ __('ledger.not_allow_create') }}">
                <button class="btn btn-neutral relative inline-flex w-48 " disabled>
                    <i class="fas fa-circle-plus mr-1"></i>
                    {{__('ledger.create')}}
                </button>
            </div>
        @endif
            @if($canView)
                <livewire:ledger.export :ledgerDefineId="$ledgerDefine->id"
                                        :$keywords
                                        :$filter
                                        key="{{Hash::make('ledger_export-'. $ledgerDefine->id)}}"
                />
            @else
                <div class="tooltip" data-tip="{{ __('ledger.not_allow_view') }}">
                    <button class="btn btn-outline btn-secondary w-48" disabled>
                        <i class="fas fa-file-csv"></i>
                        {{__('ledger.export_csv')}}
                    </button>
                </div>
            @endif
        <div class="w-6"></div>
            @if($canManage)
                <a href="{{ route('ledgerDefine.edit', ['tenant' => $currentTenantId, 'ledgerDefineId'=>$ledgerDefine->id]) }}"
                   class="btn btn-outline btn-primary btn-sm relative inline-flex"
                   target="ledgerDefineEdit_{{$ledgerDefine->id}}}}">
                    <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                </a>
            @else
                <div class="tooltip" data-tip="{{ __('ledger.not_allow_manage') }}">
                    <button class="btn btn-outline btn-primary btn-sm relative inline-flex" disabled>
                        <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                    </button>
                </div>
            @endif

    </div>
</div>
{{--    <div class="flex flex-row">--}}
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerDefine->id"
                                     key="{{Hash::make('ledger_define_tag-'. $ledgerDefine->id)}}"
        />
{{--    </div>--}}