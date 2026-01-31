@props([
    'folders'=>[],
    'currentFolderId'=>1,
    'selectedFolderIds'=>[],
    'selectedFolderChildrenIds'=>[],
    'writableFolderIds' => [],
    'readableFolderIds' => [],
    'manageableFolderIds' => [],
    'interactive' => true, // true ならクリック動作を有効化
])
<ul class="tree">
    @foreach($folders as $folder)
        <li class="{{$folder->isRoot() ? 'root':''}}" wire:key="f_li_{{$folder->id}}">
            {{-- interactive が true の場合のみクリック動作を有効化。
                 recursive include において Livewire の context が正しく解決されるよう、
                 明示的に component 側の method を呼び出す。 --}}
            <a @if($interactive)
                   wire:click.prevent="changeCurrentFolder({{$folder->id}})"
               @endif
               @class([
                    'flex items-center gap-2 p-1 rounded hover:bg-base-200 transition-colors cursor-pointer',
                    'bg-secondary/30 text-secondary-content font-bold shadow-inner' => $folder->id == $currentFolderId,
                    'bg-info/10' => in_array($folder->id, $selectedFolderIds) && $folder->id != $currentFolderId,
                    'pl-3'
               ])
               wire:key="f_lnk_{{$folder->id}}"
            >
                 <span
                    class="tooltip"
                    data-tip="{{ in_array($folder->id, $manageableFolderIds) ? __('ledger.folder.manageable') : (in_array($folder->id, $writableFolderIds) ? __('ledger.folder.writable') : (in_array($folder->id, $readableFolderIds) ? __('ledger.folder.readable')  : __('ledger.no_view_permissions'))) }}"
                >

                @if($folder->isRoot())
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
                        <span class="fa-stack " style="font-size: 0.9em;">
                    @if(in_array($folder->id,$selectedFolderIds)||in_array($folder->id,$selectedFolderChildrenIds)||$folder->id==$currentFolderId)
                                <i class="fas fa-folder-open {{$color}} fa-stack-2x"></i>
                    @else
                                <i class="fas fa-folder {{$color}} fa-stack-2x"></i>
                    @endif
                        @if(in_array($folder->id, $manageableFolderIds))
                            <i class="fas fa-fw fa-gear text-base-100 fa-stack-1x"></i> {{-- 管理可能なフォルダ --}}
                        @elseif(in_array($folder->id, $writableFolderIds))
                            <i class="fas fa-fw fa-pen text-base-100 fa-stack-1x"></i> {{-- 書き込み可能なフォルダ --}}
                            @elseif(in_array($folder->id, $readableFolderIds))
                            <i class="fas fa-fw fa-eye text-base-100 fa-stack-1x"></i> {{-- 読み取り可能なフォルダ --}}
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
                <x-folder.tree
                     :folders="$folder->children"
                     :interactive="$interactive"
                     :writableFolderIds="$writableFolderIds"
                     :readableFolderIds="$readableFolderIds"
                     :manageableFolderIds="$manageableFolderIds"
                     :currentFolderId="$currentFolderId ?? null"
                     :selectedFolderIds="$selectedFolderIds ?? []"
                />
            @endif
        </li>
    @endforeach
</ul>
