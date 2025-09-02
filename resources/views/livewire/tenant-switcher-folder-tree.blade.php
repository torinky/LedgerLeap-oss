@foreach ($folders as $folder)
    <li>
        <a href="{{ route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder->id]) }}" wire:navigate>{{ $folder->title }}</a>
        @if ($folder->children->isNotEmpty())
            <ul>
                @include('livewire.tenant-switcher-folder-tree', ['folders' => $folder->children, 'tenant' => $tenant])
            </ul>
        @endif
    </li>
@endforeach
