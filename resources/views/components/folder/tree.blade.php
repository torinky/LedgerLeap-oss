@props([
    'folders'=>[],
    'currentFolderId'=>1,
    'selectedFolderIds'=>[],
    'selectedFolderChildrenIds'=>[],
    ])
<ul>
    @foreach($folders as $folder)
        <li class="{{$folder->id==1 ? 'root':''}}">
            <a wire:click="changeCurrentFolder({{$folder->id}})"
               @class(['bg-secondary/30' => $folder->id == $currentFolderId,'bg-info/20' => in_array($folder->id,$selectedFolderIds)])
               wire:key="folder_tree_link_{{$folder->id}}"
            >
                @if($folder->id==1)
                    <i class="fas fa-home text-primary"></i> Top
                @else
                    @if(in_array($folder->id,$selectedFolderIds)||in_array($folder->id,$selectedFolderChildrenIds)||$folder->id==$currentFolderId)
                        <i class="fas fa-folder-open text-secondary"></i>
                    @else
                        <i class="fas fa-folder text-secondary"></i>
                    @endif
                    {{ $folder->title }}
                @endif
                @if($folder->ledgerDefines->count()>0)
                    <span class="badge badge-info text-base-100"><i class="fas fa-book mr-2"></i> {{ $folder->ledgerDefines->count() }}</span>
                @endif
            </a>
            @if($folder->children->isNotEmpty())
                @include('components.folder.tree', ['folders' => $folder->children])
            @endif
        </li>
    @endforeach
</ul>
