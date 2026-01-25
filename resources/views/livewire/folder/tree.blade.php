<div>
    <div class="card bg-base-300 shadow-bg-300 relative"> {{-- Added relative for overlay --}}
        <div class="card-body">
            <x-element.loading-overlay tier="2" target="changeCurrentFolder" />

            <x-folder.tree
                :folders="$folders"
                :writableFolderIds="$writableFolderIds"
                :readableFolderIds="$readableFolderIds"
                :currentFolderId="$currentFolderId"
                :manageableFolderIds="$manageableFolderIds"
                :selectedFolderIds="$selectedFolderIds"
            />
        </div>
    </div>
</div>
