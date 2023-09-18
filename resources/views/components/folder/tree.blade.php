@props([
    'folders'=>[],
    'currentFolderId'=>1
    ])
<ul>
    @foreach($folders as $folder)
        <li class="{{$folder->id==1 ? 'root':''}}">
            <a wire:click="changeCurrentFolder({{$folder->id}})" @class(['bg-secondary-focus/30' => $folder->id == $currentFolderId])>
                @if($folder->id==1)
                    <i class="fas fa-home text-primary"></i>
                @else
                    <i class="fas fa-folder-open text-secondary"></i>
                @endif {{ $folder->title }}
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
