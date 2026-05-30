<div class="space-y-2">
    {{-- Roles Section --}}
    <div>
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('role.roles') }}:</span>
        <div class="flex flex-wrap gap-1 pt-1">
            @forelse($getRecord()->getAllUniqueRoles() as $role)
                <x-filament::badge color="primary">
                    {{-- ledger.php の翻訳を試み、なければロール名をそのまま表示 --}}
                    {{ trans('ledger.role_label.' . $role->name, [], $role->name) }}
                </x-filament::badge>
            @empty
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('role.no_roles_assigned') }}</span>
            @endforelse
        </div>
    </div>

    {{-- Permissions Section --}}
    <div>
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('permission.permissions') }}:</span>
        <div class="flex flex-wrap gap-1 pt-1">
            @forelse($getRecord()->getAllUniquePermissions() as $permission)
                <x-filament::badge color="info">
                    {{-- permission.php の翻訳を使用 --}}
                    {{ __('permission.name.' . $permission->name) }}
                </x-filament::badge>
            @empty
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('permission.no_specific_permissions') }}</span>
            @endforelse
        </div>
    </div>
</div>