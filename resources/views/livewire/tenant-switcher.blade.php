<div class="dropdown dropdown-end">
    <label tabindex="0" class="btn btn-ghost flex items-center">
        <x-mary-icon name="o-building-office-2" class="w-5 h-5" />
        <span class="hidden sm:inline">{{ $currentTenant?->name ?? __('ledger.navigation.no_tenant') }}</span>
        <x-mary-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
    </label>
    <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-10 p-2 shadow bg-base-100 rounded-box min-w-max overflow-y-auto">
        <li class="menu-title"><span>{{ __('ledger.navigation.my_tenants') }}</span></li>
        @foreach($tenants->where('is_member', true) as $tenant)
            <li>
                @if($showFolders && !empty($tenant->folders_tree))
                    <details
                        x-data="{ open: localStorage.getItem('details-{{ $tenant->id }}') === 'true' }"
                        @toggle="localStorage.setItem('details-{{ $tenant->id }}', $el.open)"
                        x-bind:open="open"
                    >
                        <summary>
                            <a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" wire:navigate>{{ $tenant->name ?? $tenant->id }}</a>
                        </summary>
                        <ul>
                            <li><a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" wire:navigate>{{ __('ledger.navigation.go_to_my_portal') }}</a></li>
                            <div class="divider my-1"></div>
                            @include('livewire.tenant-switcher-daisyui-folder-tree', ['folders' => $tenant->folders_tree, 'tenant' => $tenant, 'currentFolderId' => $currentFolderId])
                        </ul>
                    </details>
                @else
                    <a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" wire:navigate>{{ $tenant->name ?? $tenant->id }}</a>
                @endif
            </li>
        @endforeach

        <div class="divider my-1"></div>

        <li class="menu-title"><span>{{ __('ledger.navigation.other_tenants') }}</span></li>
        @foreach($tenants->where('is_member', false) as $tenant)
            <li class="disabled">
                <a class="flex justify-between">
                    <span>{{ $tenant->name ?? $tenant->id }}</span>
                    <x-mary-icon name="o-lock-closed" class="w-4 h-4" />
                </a>
            </li>
        @endforeach
    </ul>
</div>