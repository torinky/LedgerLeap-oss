<div>
    <div class="card tree bg-base-300 shadow-bg-300">
        <div class="card-body">
            @include('components.folder.tree', [
                'folders' => $folders,
                'writableFolderIds' => $writableFolderIds,
                'readableFolderIds' => $readableFolderIds,
            ])
        </div>
    </div>
</div>
