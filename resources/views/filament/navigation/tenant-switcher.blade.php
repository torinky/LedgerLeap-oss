<x-filament::dropdown
    teleport
>
    <x-slot name="trigger">
        <x-filament::button
            icon="heroicon-o-building-office-2"
        >
            {{ $currentTenant?->name ?? __('ledger.navigation.no_tenant') }}
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            class="cursor-default!"
        >
            {{ __('ledger.navigation.my_tenants') }}
        </x-filament::dropdown.list.item>

        @foreach($tenants->where('is_member', true) as $tenant)
            <x-filament::dropdown.list.item
                :href="route('filament.admin.pages.dashboard', ['tenant' => $tenant->id])"
                tag="a"
                wire:navigate
            >
                {{ $tenant->name ?? $tenant->id }}
            </x-filament::dropdown.list.item>
        @endforeach

        <x-filament::dropdown.list.item
            class="cursor-default!"
        >
            {{ __('ledger.navigation.other_tenants') }}
        </x-filament::dropdown.list.item>

        @foreach($tenants->where('is_member', false) as $tenant)
            <x-filament::dropdown.list.item
                icon="heroicon-o-lock-closed"
                class="cursor-default! opacity-50"
            >
                {{ $tenant->name ?? $tenant->id }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>