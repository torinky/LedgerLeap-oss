@php
    $displayColumns = $filteredColumnDefines ?? [];
    if ($displayColumns instanceof \Illuminate\Support\Collection) {
        $displayColumns = $displayColumns->toArray();
    }
    $displayColumnsWithMock = $displayColumns;
    if (\App\Services\Ledger\MockAttachmentService::isEnabled()) {
        $mockDef = (object) \App\Services\Ledger\MockAttachmentService::getMockColumnDefine();
        $mockDef->label = '添付(モック)';
        $displayColumnsWithMock = array_merge($displayColumns, [$mockDef]);
    }
    $emptyAttachments = collect();

    // Pre-compute confidentiality data for browser-side scroll tracking
    $effective = \App\Services\ConfidentialityLevelService::getEffectiveLevel($ledgerDefine);
    $scopeText = collect($effective['scope_labels'] ?? [])->map(fn ($s) => is_array($s) ? ($s['name'] ?? $s) : $s)->implode(', ');
    $tooltipParts = [];
    if ($effective['source_path'] ?? null) {
        $tooltipParts[] = $effective['inherited']
            ? __('ledger.confidentiality.tooltip.inherited_from', ['name' => $effective['source_path']])
            : __('ledger.confidentiality.tooltip.direct_from', ['name' => $effective['source_path']]);
    } elseif (($effective['source']['type'] ?? null) && ($effective['source']['name'] ?? null)) {
        $sourceLabel = match ($effective['source']['type']) {
            'ledger_define' => __('ledger.confidentiality.tooltip.ledger_define_short', ['name' => $effective['source']['name']]),
            'folder' => __('ledger.confidentiality.tooltip.folder_short', ['name' => $effective['source']['name']]),
            default => $effective['source']['name'],
        };
        $tooltipParts[] = $effective['inherited']
            ? __('ledger.confidentiality.tooltip.inherited_from', ['name' => $sourceLabel])
            : __('ledger.confidentiality.tooltip.direct_from', ['name' => $sourceLabel]);
    }
    if ($scopeText) {
        $tooltipParts[] = __('ledger.confidentiality.tooltip.scope_label', ['scopes' => $scopeText]);
    } elseif (! $effective['level']) {
        $tooltipParts[] = __('ledger.confidentiality.stamp.tooltip_unset');
    }
    $tooltipText = implode(' | ', $tooltipParts);

    $resolvedEditUrl = null;
    $canEditConfidentiality = auth()->user()->can('update', $ledgerDefine);
    if ($canEditConfidentiality && ($effective['source']['type'] ?? null) && ($effective['source']['id'] ?? null)) {
        try {
            $resolvedEditUrl = match ($effective['source']['type']) {
                'ledger_define' => route('ledgerDefine.edit', ['tenant' => $currentTenantId, 'ledgerDefineId' => $effective['source']['id']]),
                'folder' => route('folder.edit', ['tenant' => $currentTenantId, 'folder' => $effective['source']['id']]),
                default => null,
            };
        } catch (\Throwable $e) {
            $resolvedEditUrl = null;
        }
    }
    $confidentialityJson = json_encode([
        'level' => $effective['level'],
        'label' => $effective['label'] ?? '',
        'scopeText' => $scopeText,
        'editUrl' => $resolvedEditUrl,
        'tooltipText' => $tooltipText,
        'visible' => ($effective['level'] || $effective['label']) ? true : false,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
@endphp

<div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden"
    wire:key="ledger_record_{{ $ledgerDefineId }}"
    data-ledger-define-section="{{ $ledgerDefineId }}"
    data-confidentiality-json="{{ $confidentialityJson }}">
    <div class="card-body pt-0 px-0">
        <x-ledgerDefine.header
            :ledgerDefine="$ledgerDefine"
            :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine"
            :search="$search"
            :filter="$filter"
            :keywords="$keywords"
            :canManage="$canManage"
            :canCreate="$canCreate"
            :canView="$canView"
            :ledgerDefineId="$ledgerDefineId"
            :ledgerDefineRecordsKeyById="$ledgerDefineRecordsKeyById"
            :filteredColumnDefines="$displayColumnsWithMock"
            :scoreStats="$scoreStats"
            :overallStats="$overallStats"
            :currentTenantId="$currentTenantId"
        />

        <div class="overflow-x-auto max-h-[70vh]" wire:key="ledgerDefine_block-{{ $ledgerDefineId }}">
            <table class="table table-zebra table-compact table-auto table-pin-rows table-pin-cols w-full">
                <thead>
                    <x-ledger.table-header
                        :ledgerDefine="$ledgerDefine"
                        :orderBy="$orderBy"
                        :orderAsc="$orderAsc"
                        :filteredColumnDefines="$displayColumnsWithMock"
                        :defaultSortColumns="$defaultSortColumns"
                    />
                </thead>
                <tbody>
                    @foreach ($records as $ledgerRecordValues)
                        <x-ledger.table-row
                            :ledgerRecord="$ledgerRecordValues"
                            :highlightKeyword="$search"
                            :canUpdate="$canUpdate"
                            :canView="$canView"
                            :allAttachments="$emptyAttachments"
                            :filteredColumnDefines="$displayColumnsWithMock"
                            :currentTenantId="$currentTenantId"
                            :relatedBadge="null"
                            :selectedFileId="$selectedFileId"
                            :selectedLedgerId="$selectedLedgerId"
                            :selectedColumnId="$selectedColumnId"
                        />
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
