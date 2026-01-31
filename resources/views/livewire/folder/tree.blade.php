<div>
    <div class="card bg-base-300 shadow-sm relative"> {{-- Added relative for overlay --}}
        <div class="card-body p-2"> {{-- Reduced padding --}}

            <div>
                <x-folder.tree :folders="$folders" :writableFolderIds="$writableFolderIds" :readableFolderIds="$readableFolderIds" :currentFolderId="$currentFolderId" :manageableFolderIds="$manageableFolderIds"
                    :selectedFolderIds="$selectedFolderIds" :parentComponentId="$parentComponentId" />
            </div>
        </div>
    </div>
</div>
