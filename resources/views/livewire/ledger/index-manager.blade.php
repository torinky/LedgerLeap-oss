<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    @php
        $navTargets = 'search,filter,orderBy,orderAsc,filterStatus,selectedLedgerDefineIds,selectedFolderIds,currentFolderId,displayLevel,useSemanticSearch,useSynonym,useTechnicalTerm,perPage';
        $navTargets .= ',changeCurrentFolder,toggleFolderId,toggleLedgerDefineId,sort,updateDisplayLevel';
    @endphp

    <div class="relative min-h-screen">
        {{-- High-level loading overlay for all major transitions --}}
        <x-element.loading-overlay tier="1" message="{{ __('ledger.loading') }}" />

        <x-slot:drawer>
            <livewire:folder.tree
                :currentFolderId="$currentFolderId"
                :selectedFolderIds="$selectedFolderIds"
                wire:key="folder-tree-stable"
            />
        </x-slot:drawer>

        {{-- Always visible search section moved from RecordsTable --}}
        <div class="px-4 pt-4 sticky top-0 z-10 bg-base-200/80 backdrop-blur-md pb-4 rounded-b-2xl">
            <x-ledger.search
                :hasWorkflowEnabled="$hasWorkflowEnabled"
                :orderBy="$orderBy"
                :orderByLabel="$orderByLabel"
                :useSemanticSearch="$useSemanticSearch"
                :defaultSortColumns="$defaultSortColumns"
            />
        </div>

        <div class="container max-w-full px-0 md:px-4 mt-4">
            <livewire:ledger.records-table
                :search="$search"
                :orderBy="$orderBy"
                :orderAsc="$orderAsc"
                :filterStatus="$filterStatus"
                :filter="$filter"
                :selectedLedgerDefineIds="$selectedLedgerDefineIds"
                :selectedFolderIds="$selectedFolderIds"
                :currentFolderId="$currentFolderId"
                :displayLevel="$displayLevel"
                :useSemanticSearch="$useSemanticSearch"
                :useSynonym="$useSynonym"
                :useTechnicalTerm="$useTechnicalTerm"
                :perPage="$perPage"
                wire:key="records-table-{{ $currentFolderId }}-{{ $search }}"
            />
        </div>
    </div>
</div>

