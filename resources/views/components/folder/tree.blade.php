<ul>
    @foreach($folders as $folder)
        <li>
            <a href="{{route('ledgersByFolderId',['folderId'=>$folder->id])}}">
                <i class="fa fa-folder-open"></i> {{ $folder->title }}
            </a>
            @if($folder->children->isNotEmpty())
                @include('components.folder.tree', ['folders' => $folder->children])
            @endif
        </li>
    @endforeach
</ul>
