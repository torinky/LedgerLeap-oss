<div>
    <div class="card bg-base-300 shadow-bg-300 relative"> {{-- Added relative for overlay --}}
        <div class="card-body">
            <x-element.loading-overlay tier="2" target="changeCurrentFolder" />

            <div wire:loading.delay target="changeCurrentFolder">
                <x-element.skeleton-list items="12" />
            </div>

            <div wire:loading.delay.remove target="changeCurrentFolder">
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
</div>
