@foreach ($folders as $folder)
    <li>
        @if (!empty($folder['children']))
            <details
                x-data="{ open: localStorage.getItem('details-{{ $tenant->id }}-{{ $folder['id'] }}') === 'true' }"
                @toggle="localStorage.setItem('details-{{ $tenant->id }}-{{ $folder['id'] }}', $el.open)"
                x-bind:open="open"
            >
                <summary>
                    <a href="{{ route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder['id']]) }}" wire:navigate @class(['bg-neutral/20' => $currentFolderId == $folder['id']])>{{ $folder['title'] }}</a>
                </summary>
                <ul>
{{--                    <li><a href="{{ route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder['id']]) }}" wire:navigate>{{ __('ledger.navigation.go_to_folder_ledgers') }}</a></li>--}}
                    <div class="divider my-1"></div>
                    @include('livewire.tenant-switcher-daisyui-folder-tree', ['folders' => $folder['children'], 'tenant' => $tenant, 'currentFolderId' => $currentFolderId])
                </ul>
            </details>
        @else
            <a href="{{ route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder['id']]) }}" wire:navigate @class(['bg-neutral/20' => $currentFolderId == $folder['id']])>{{ $folder['title'] }}</a>
        @endif
    </li>
@endforeach