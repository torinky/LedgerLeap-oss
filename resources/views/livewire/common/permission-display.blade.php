<div class="card p-4 bg-base-100 shadow-xl">
    <h3 class="text-xl font-semibold mb-4 text-base-content">{{ __('ledger.access_and_permissions.title') }}</h3>


    {{-- ログインユーザーの最高権限概要 --}}
    <div class="mb-6 p-4 rounded-lg bg-info/20 text-info-content border border-info/50">
        @if ($this->currentUserHighestPermission)
            <p class="text-lg font-medium">
                <i class="fas fa-user-check mr-2"></i>
                {{ __('ledger.access_and_permissions.your_access_level') }}:
                <span class="badge font-bold badge-lg badge-{{ $this->currentUserHighestPermission->getColor() }} text-{{ $this->currentUserHighestPermission->getColor() }}-content">
                    {{ $this->currentUserHighestPermission->getLabel() }}
                </span>
            </p>
        @else
            <p class="text-lg font-medium">
                <i class="fas fa-user-times mr-2"></i>
                {{ __('ledger.access_and_permissions.no_direct_access') }}
            </p>
        @endif
        <p class="text-sm mt-2 text-info-content/80">{{ __('ledger.access_and_permissions.check_details_below') }}</p>
    </div>

    {{-- アクセス権限を持つロールのリスト --}}
    <div class="mb-6">
        <h4 class="text-lg font-semibold mb-3 text-base-content">{{ __('ledger.access_and_permissions.roles_with_access') }}</h4>
        @if ($this->accessRoles->isEmpty())
            <div class="alert alert-info bg-info/10 text-info-content border-info">
                <i class="fas fa-exclamation-circle text-info"></i>
                <span>{{ __('ledger.access_and_permissions.no_roles_found') }}</span>
            </div>
        @else
            <x-mary-table
                    class="table-compact w-full table-zebra overflow-x-auto"
                    :headers="[
                    ['key' => 'role_name', 'label' => __('ledger.access_and_permissions.column.role_name'), 'class' => 'min-w-[8rem]'],
                    ['key' => 'permissions', 'label' => __('ledger.access_and_permissions.column.permissions'), 'class' => 'min-w-[12rem]'],
                    ['key' => 'source', 'label' => __('ledger.access_and_permissions.column.source'), 'class' => 'min-w-[6rem]'],
                ]"
                    :rows="$this->accessRoles"
                    striped
            >
                @scope('cell_role_name', $item)
                <span class="font-medium text-base-content">{{ $item->role->name }}</span>
                @if($item->is_inherited)
                    <span class="tooltip tooltip-bottom text-base-content/70" data-tip="{{ __('ledger.access_and_permissions.inherited_from_parent') }}">
                            <i class="fas fa-level-up-alt ml-1 text-sm"></i>
                        </span>
                @endif
                @endscope

                @scope('cell_permissions', $item)
                @forelse($item->permissions as $permission)
{{--                    <span class="badge badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content mr-1 mb-1">--}}
                    <span class="badge badge-{{ $permission->getColor() }} mr-1 mb-1">
                            {{ $permission->getLabel() }}
                        </span>
                @empty
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_specific_permissions') }}</span>
                @endforelse
                @endscope

                @scope('cell_source', $item)
                <span class="badge badge-outline text-base-content/70">
                        {{ __('ledger.access_and_permissions.source.' . ($item->source ?? 'unknown')) }}
                    </span>
                @endscope

                <x-slot:empty>
                    <x-mary-icon name="o-folder-minus" label="{{ __('ledger.access_and_permissions.no_roles_found') }}"/>
                </x-slot:empty>
            </x-mary-table>
        @endif
    </div>

    {{-- アクセス可能なユーザーのリスト --}}
    <div>
        <h4 class="text-lg font-semibold mb-3 text-base-content">{{ __('ledger.access_and_permissions.users_with_access') }}</h4>

        {{-- ユーザー検索フィルター --}}
        <div class="mb-4">
            <x-mary-input
                    wire:model.live.debounce.300ms="searchUserQuery"
                    placeholder="{{ __('ledger.access_and_permissions.search_users_placeholder') }}"
                    icon="o-magnifying-glass"
                    class="bg-base-200 text-base-content placeholder-base-content/50"
                    loading
            />
        </div>

        @if ($this->accessUsers->isEmpty())
            <div class="alert alert-info bg-info/10 text-info-content border-info">
                <i class="fas fa-users-slash text-info"></i>
                <span>{{ __('ledger.access_and_permissions.no_users_found') }}</span>
            </div>
        @else
            <x-mary-table
                    class="table-compact w-full table-zebra overflow-x-auto"
                    :headers="[
                    ['key' => 'name', 'label' => __('ledger.access_and_permissions.column.user_name'), 'class' => 'min-w-[8rem]'],
                    ['key' => 'email', 'label' => __('ledger.access_and_permissions.column.email'), 'class' => 'min-w-[8rem]'],
                    ['key' => 'roles', 'label' => __('ledger.access_and_permissions.column.roles'), 'class' => 'min-w-[10rem]'],
                ]"
                    :rows="$this->accessUsers"
                    striped
            >
                @scope('cell_name', $user)
                <span class="font-medium text-base-content">{{ $user->name }}
                    {{-- ローディングインジケーター --}}
                <x-mary-loading wire:loading class="my-4" />
                </span>
                @endscope

                @scope('cell_email', $user)
                <span class="text-base-content/80">{{ $user->email }}</span>
                @endscope

                @scope('cell_roles', $user)
                @forelse($user->getAllUniqueRoles() as $role)
                    <span class="badge badge-neutral  mr-1 mb-1">
                            {{ $role->name }}
                        </span>
                @empty
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_roles') }}</span>
                @endforelse
                @endscope

                <x-slot:empty>
                    <x-mary-icon name="o-users" label="{{ __('ledger.access_and_permissions.no_users_found') }}"/>
                </x-slot:empty>
            </x-mary-table>

            <div class="mt-4">
                {{ $this->accessUsers->links() }}
            </div>
        @endif
    </div>
</div>