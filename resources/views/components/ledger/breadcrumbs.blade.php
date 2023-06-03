@props([
    'thisLedgerDefine' => (object)['title'=>null,'id'=>null] ,
    'breadcrumbs'=>[],
])
<div class="breadcrumbs">
    <ul>
        @foreach($breadcrumbs as $bKey => $folder)
            @if(is_null($folder->parent_id))
                <li><a href="{{route('ledger.index')}}"><i class="fas fa-home mr-3"></i>Top</a></li>
            @else
                <li><a href="{{route('ledgersByFolderId',['folderId'=>$folder->id])}}"><i
                            class="fas fa-folder-open mr-3"></i>{{$folder->title}}</a></li>
            @endif
        @endforeach
        @if(!is_null($thisLedgerDefine->title))
            <li><i class="fas fa-book-open mr-3"></i>{{$thisLedgerDefine->title}}</li>
        @endif
    </ul>
</div>
