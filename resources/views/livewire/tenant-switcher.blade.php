<div class="dropdown dropdown-end">
    <label tabindex="0" class="btn btn-ghost flex items-center">
        <x-mary-icon name="o-building-office-2" class="w-5 h-5" />
        <span class="hidden sm:inline">{{ $currentTenant?->name ?? 'No Tenant' }}</span>
        <x-mary-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
    </label>
    <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[10] p-2 shadow bg-base-100 rounded-box w-64 max-h-96 overflow-y-auto">
        <li class="menu-title"><span>{{ __('ledger.navigation.my_tenants') }}</span></li>
        @foreach($tenants->where('is_member', true) as $tenant)
            <li>
                @if($tenant->folders_tree->isNotEmpty())
                    <details>
                        <summary>
                            <a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" wire:navigate>{{ $tenant->name }}</a>
                        </summary>
                        <ul>
                            @foreach($tenant->folders_tree as $folder)
                                {{-- TODO: Recursive component for deeper levels --}}
                                <li><a href="{{ route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder->id]) }}" wire:navigate>{{ $folder->title }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <a href="{{ route('my-portal', ['tenant' => $tenant->id]) }}" wire:navigate>{{ $tenant->name }}</a>
                @endif
            </li>
        @endforeach

        <div class="divider my-1"></div>

        <li class="menu-title"><span>{{ __('ledger.navigation.other_tenants') }}</span></li>
        @foreach($tenants->where('is_member', false) as $tenant)
            <li class="disabled">
                <a class="flex justify-between">
                    <span>{{ $tenant->name }}</span>
                    <x-mary-icon name="o-lock-closed" class="w-4 h-4" />
                </a>
            </li>
        @endforeach
    </ul>
</div>