<div class="w-full min-w-0">
    {{--
        ツリーカード（Sprint 7 修正）:
        - root div に w-full + min-w-0 を付与することで、ul.menu（flex column コンテナ）の
          中で flex child としての幅制約が機能し、ツリーカードが drawer-side 幅（256px）に収まる。
        - min-w-0 がないと block 要素として min-width: auto が効き、コンテンツ幅（深い階層では
          400px 超）で拡張して折りたたみボタンが drawer-side の右端外にはみ出す。
        - 内部の .tree-scroll-container (max-width: 100% + overflow-x: auto) で
          横スクロールを提供し、深い階層でもボタンが常に操作可能。
    --}}
    <div class="card bg-base-300 shadow-sm relative min-w-0 overflow-hidden">
        <div class="card-body p-2">
            <div class="tree-scroll-container">
                <x-folder.tree :folders="$folders" :writableFolderIds="$writableFolderIds" :readableFolderIds="$readableFolderIds" :currentFolderId="$currentFolderId" :manageableFolderIds="$manageableFolderIds"
                    :selectedFolderIds="$selectedFolderIds" :selectedFolderAncestorIds="$selectedFolderAncestorIds ?? []" :parentComponentId="$parentComponentId" />
            </div>
        </div>
    </div>
</div>
