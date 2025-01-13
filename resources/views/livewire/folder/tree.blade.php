<div>
    <div class="card tree bg-base-300 shadow-bg-300">
        <div class="card-body">

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
