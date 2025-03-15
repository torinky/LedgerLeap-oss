@props([
    'folders'=>[],
    'currentFolderId'=>1,
    'selectedFolderIds'=>[],
    'selectedFolderChildrenIds'=>[],
    'writableFolderIds' => [],
    'readableFolderIds' => [],
    'manageableFolderIds' => [],
])
<ul>
    @foreach($folders as $folder)
        <li class="{{$folder->id==1 ? 'root':''}}">
            <a wire:click="changeCurrentFolder({{$folder->id}})"
               @class([
                    'bg-secondary/30' => $folder->id == $currentFolderId,
                    'bg-info/20' => in_array($folder->id,$selectedFolderIds),
               ])
               wire:key="folder_tree_link_{{$folder->id}}"
            >
                <span
                    class="tooltip"
                    data-tip="{{ in_array($folder->id, $manageableFolderIds) ? __('ledger.folder.manageable') : (in_array($folder->id, $writableFolderIds) ? __('ledger.folder.writable') : (in_array($folder->id, $readableFolderIds) ? __('ledger.folder.readable')  : __('ledger.no_view_permissions'))) }}"
                >

                @if($folder->id==1)
                    <i class="fas fa-home text-primary"></i> Top
                @else
                        @php
                            $color = 'text-secondary';

                            if (in_array($folder->id, $manageableFolderIds)) {
                                $color = 'text-accent';
                            } elseif (in_array($folder->id, $writableFolderIds)) {
                                $color = 'text-accent/90';
                            } elseif (in_array($folder->id, $readableFolderIds)) {
                                $color = 'text-accent/80';
                            }
                        @endphp
                        <span class="fa-stack mr-3" style="font-size:0.5em">
                    @if(in_array($folder->id,$selectedFolderIds)||in_array($folder->id,$selectedFolderChildrenIds)||$folder->id==$currentFolderId)
                                <i class="fas fa-folder-open {{$color}} fa-stack-2x"></i>
                    @else
                                <i class="fas fa-folder {{$color}} fa-stack-2x"></i>
                    @endif
                        @if(in_array($folder->id, $manageableFolderIds))
                            <i class="fas fa-fw fa-gear text-base-100 fa-stack-1x pl-2"
                               style="font-size:2em"></i> {{-- 管理可能なフォルダ --}}
                        @elseif(in_array($folder->id, $writableFolderIds))
                            <i class="fas fa-fw fa-pen text-base-100 fa-stack-1x pl-2"
                               style="font-size:2em"></i> {{-- 書き込み可能なフォルダ --}}
                            @elseif(in_array($folder->id, $readableFolderIds))
                            <i class="fas fa-fw fa-eye text-base-100 fa-stack-1x pl-2"
                               style="font-size:2em"></i> {{-- 読み取り可能なフォルダ --}}
                            @endif
                    </span>
                    {{ $folder->title }}
                @endif
                </span>
                @if($folder->ledgerDefines->count()>0)
                    <span class="badge badge-info text-base-100"><i class="fas fa-book mr-2"></i> {{ $folder->ledgerDefines->count() }}</span>
                @else
                    <span class="badge badge-ghost ml-auto"><i class="fas fa-book mr-2"></i> 0</span>
                @endif
            </a>
            @if($folder->children->isNotEmpty())
                @include('components.folder.tree', [
    'folders' => $folder->children,
    ])
            @endif
        </li>
    @endforeach
</ul>
