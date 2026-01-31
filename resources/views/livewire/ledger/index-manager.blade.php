<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    <x-slot:drawer>
        <livewire:folder.tree
            :currentFolderId="$currentFolderId"
            :selectedFolderIds="$selectedFolderIds"
            wire:key="folder-tree-{{ $currentFolderId }}"
        />
    </x-slot:drawer>

    <div class="container max-w-full px-0 md:px-4">
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
            wire:key="records-table-{{ $currentFolderId }}-{{ $search }}"
        />
    </div>
</div>

