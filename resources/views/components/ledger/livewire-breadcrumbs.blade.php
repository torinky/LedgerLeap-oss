@props([
    'thisLedgerDefine' => (object)['title'=>null,'id'=>null] ,
    'breadcrumbs'=>[],
    'isLivewire' => true,
])

@if(!empty($breadcrumbs))
    @php
        // collectヘルパとlast()メソッドで安全に最後の要素を取得
        $currentBreadcrumbFolder = collect($breadcrumbs)->last();
    @endphp
    <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 w-full max-w-full">
        <div class="breadcrumbs text-sm overflow-x-auto min-w-0 shrink">
            <ul class="m-0 p-0">
                @foreach($breadcrumbs as $bKey => $folder)
                    @if(is_null($folder->parent_id))
                        <li class="whitespace-nowrap">
                            @if($isLivewire)
                                <a href="#" wire:click.prevent="changeCurrentFolder({{$folder->id}})"
                                   wire:key="bread_folder_{{$folder->id}}"
                                   class="inline-flex items-center gap-1">
                                    <i class="fas fa-home"></i>{{ __('ledger.breadcrumb_top') }}
                                </a>
                            @else
                                <a href="{{ route('ledger.index', ['tenant' => tenant()->id, 'cf' => $folder->id]) }}"
                                   class="inline-flex items-center gap-1 hover:opacity-75">
                                    <i class="fas fa-home"></i>{{ __('ledger.breadcrumb_top') }}
                                </a>
                            @endif
                        </li>
                    @else
                        <li class="whitespace-nowrap">
                            @if($isLivewire)
                                <a href="#" wire:click.prevent="changeCurrentFolder({{$folder->id}})"
                                   wire:key="bread_folder_{{$folder->id}}"
                                   class="inline-flex items-center gap-1">
                                    <i class="fas fa-folder-open"></i>{{$folder->title}}
                                </a>
                            @else
                                <a href="{{ route('ledger.index', ['tenant' => tenant()->id, 'cf' => $folder->id]) }}"
                                   class="inline-flex items-center gap-1 hover:opacity-75">
                                    <i class="fas fa-folder-open"></i>{{$folder->title}}
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
                @if(!is_null($thisLedgerDefine->title))
                    <li class="whitespace-nowrap inline-flex items-center gap-1">
                        <i class="fas fa-book-open"></i>{{$thisLedgerDefine->title}}
                    </li>
                @endif
            </ul>
        </div>
        @if($currentBreadcrumbFolder)
            <div class="flex flex-wrap items-center gap-1 shrink-0">
                @foreach($currentBreadcrumbFolder->getAllRoles() as $role)
                    <span class="badge badge-sm badge-primary truncate max-w-full">
                        {{ $role->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
