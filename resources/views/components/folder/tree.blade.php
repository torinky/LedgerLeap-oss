@props([
    'folders'=>[],
    'currentFolderId'=>1
    ])
<ul>
    @foreach($folders as $folder)
        <li>
            {{--            <a href="{{route('ledgersByFolderId',['folderId'=>$folder->id])}}" @class(['bg-secondary-focus/30' => $folder->id == $currentFolderId])>--}}
            <a wire:click="changeCurrentFolder({{$folder->id}})" @class(['bg-secondary-focus/30' => $folder->id == $currentFolderId])>
                <i class="fa fa-folder-open"></i> {{ $folder->title }}
            </a>
            @if($folder->children->isNotEmpty())
                @include('components.folder.tree', ['folders' => $folder->children])
            @endif
        </li>
    @endforeach
</ul>
