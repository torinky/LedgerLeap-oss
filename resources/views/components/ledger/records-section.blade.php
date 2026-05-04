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
@endphp

<div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden"
    wire:key="ledger_record_{{ $ledgerDefineId }}"
    data-ledger-define-section="{{ $ledgerDefineId }}">
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
