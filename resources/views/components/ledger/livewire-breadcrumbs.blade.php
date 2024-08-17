@props([
    'thisLedgerDefine' => (object)['title'=>null,'id'=>null] ,
    'breadcrumbs'=>[],
])
<div class="breadcrumbs flex items-center space-x-4">
    <ul class="flex items-center space-x-2">
        @foreach($breadcrumbs as $bKey => $folder)
            @if(is_null($folder->parent_id))
                <li>
                    <a href="#" wire:click.self="changeCurrentFolder({{$folder->id}})"
                       wire:key="bread_folder_{{$folder->id}}"
                       class="flex items-center">
                        <i class="fas fa-home mr-2"></i>Top
                    </a>
                </li>
            @else
                <li>
                    <a href="#" wire:click.self="changeCurrentFolder({{$folder->id}})"
                       wire:key="bread_folder_{{$folder->id}}"
                       class="flex items-center">
                        <i class="fas fa-folder-open mr-2"></i>{{$folder->title}}
                    </a>
                </li>
            @endif
        @endforeach
        @if(!is_null($thisLedgerDefine->title))
            <li class="flex items-center">
                <i class="fas fa-book-open mr-2"></i>{{$thisLedgerDefine->title}}
            </li>
        @endif
    </ul>
    <div class="flex items-center space-x-2">
        @foreach($folder->getAllRoles() as $role)
            <span class="badge badge-sm badge-primary">
                {{ $role->name }}
            </span>
        @endforeach
    </div>
</div>
