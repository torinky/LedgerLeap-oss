@props([
    'thisLedgerDefine' => (object)['title'=>null,'id'=>null] ,
    'breadcrumbs'=>[],
])

@if(!empty($breadcrumbs))
    @php
        // collectヘルパとlast()メソッドで安全に最後の要素を取得
        $currentBreadcrumbFolder = collect($breadcrumbs)->last();
    @endphp
    <div class="breadcrumbs flex items-center space-x-4">
        <ul class="flex items-center space-x-2">
            @foreach($breadcrumbs as $bKey => $folder)
                @if(is_null($folder->parent_id))
                    <li>
                        <a href="#" wire:click.self="changeCurrentFolder({{$folder->id}})"
                           wire:key="bread_folder_{{$folder->id}}"
                           class="flex items-center">
                            <i class="fas fa-home"></i>Top
                        </a>
                    </li>
                @else
                    <li>
                        <a href="#" wire:click.self="changeCurrentFolder({{$folder->id}})"
                           wire:key="bread_folder_{{$folder->id}}"
                           class="flex items-center">
                            <i class="fas fa-folder-open"></i>{{$folder->title}}
                        </a>
                    </li>
                @endif
            @endforeach
            @if(!is_null($thisLedgerDefine->title))
                <li class="flex items-center">
                    <i class="fas fa-book-open"></i>{{$thisLedgerDefine->title}}
                </li>
            @endif
        </ul>
        @if($currentBreadcrumbFolder)
            <div class="flex items-center space-x-2">
                @foreach($currentBreadcrumbFolder->getAllRoles() as $role)
                    <span class="badge badge-sm badge-primary">
                        {{ $role->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
