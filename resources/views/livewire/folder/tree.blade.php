<div>
    {{--
        ツリーカード: サイドバー幅に収まるよう min-w-0 + overflow-x:hidden を設定。
        overflow-x:hidden は深い階層で水平はみ出しを防ぐ。
        縦スクロールは親の .menu (ul) が担うため、ここでは auto としない。
    --}}
    <div class="card bg-base-300 shadow-sm relative min-w-0 overflow-hidden">
        <div class="card-body p-2">
            <div class="min-w-0 overflow-x-hidden">
                <x-folder.tree :folders="$folders" :writableFolderIds="$writableFolderIds" :readableFolderIds="$readableFolderIds" :currentFolderId="$currentFolderId" :manageableFolderIds="$manageableFolderIds"
                    :selectedFolderIds="$selectedFolderIds" :parentComponentId="$parentComponentId" />
            </div>
        </div>
    </div>
</div>
