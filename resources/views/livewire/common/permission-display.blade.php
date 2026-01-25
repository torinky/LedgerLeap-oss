<div class="relative">
    <x-element.loading-overlay tier="2" target="filterByRoleId,filterByOrganizationId,filterByPermissionType,gotoPage" />

    {{--
        <x-mary-header :title="__('ledger.access_and_permissions.title')"
                       separator progress-indicator
                       icon="o-shield-check"
        />
    --}}

    {{-- ★★★ フィルタリングUI ★★★ --}}
    <x-mary-card class="pt-0" shadow>
        {{--        <h4 class="font-semibold text-base-content mb-2">{{ __('Filter') }}</h4>--}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- ロールフィルタ --}}
            <div>
                <x-mary-choices
                        label="{{ __('ledger.access_and_permissions.column.role_name') }}"
                        wire:model.live="filterByRoleId"
                        :options="$roleOptions"
                        search-function="roleSearch"
                        placeholder="{{ __('ledger.all_roles') }}"
                        single
                        clearable
                        searchable
                />
            </div>
            {{-- 組織フィルタ --}}
            <div>
                <x-mary-choices
                        label="{{ __('ledger.access_and_permissions.column.organization_name') }}"
                        wire:model.live="filterByOrganizationId"
                        :options="$organizationOptions"
                        search-function="organizationSearch"
                        placeholder="{{ __('ledger.all_organizations') }}"
                        single
                        clearable
                        searchable
                >
                    @scope('item', $org)
                    <x-mary-list-item :item="$org" value="id" no-hover no-separator>
                        <x-slot:value>
                            <span>{{ $org['name'] }}</span>
                        </x-slot:value>
                        <x-slot:sub-value>
                            @if(!empty($org['full_name']) && $org['full_name'] !== $org['name'])
                                <span class="block text-xs text-neutral">
                                    {{ $org['full_name'] }}
                                </span>
                            @endif
                        </x-slot:sub-value>
                    </x-mary-list-item>
                    @endscope
                </x-mary-choices>
            </div>
            {{-- 権限タイプフィルタ --}}
            <div>
                <x-mary-select
                        label="{{ __('ledger.access_and_permissions.column.permissions') }}"
                        :options="$permissionOptions"
                        option-label="label"
                        option-value="value"
                        wire:model.live="filterByPermissionValue"
                        placeholder="{{ __('ledger.all_permissions') }}"
                        allow-empty
                />
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <x-mary-button
                    label="{{ __('ledger.reset') }}"
                    wire:click="resetFilters"
                    class="btn-sm btn-ghost"
                    icon="o-arrow-path"
            />
        </div>
    </x-mary-card>

    <div class="divider"></div>
    {{-- ログインユーザーの最高権限概要 --}}
    <div class="mb-6 p-4 rounded-lg bg-info/20 text-info-content border border-info/50">
        @if ($this->currentUserHighestPermission)
            <p class="text-lg font-medium">
                <i class="fas fa-user-check mr-2"></i>
                <span class="mr-2">
                    {{ __('ledger.access_and_permissions.your_access_level') }}:
                </span>
                {{--
                                <span class="badge font-bold badge-lg badge-{{ $this->currentUserHighestPermission->getColor() }} text-{{ $this->currentUserHighestPermission->getColor() }}-content">
                                    {{ $this->currentUserHighestPermission->getLabel() }}
                                </span>
                --}}
                @foreach($this->currentUserAllPermissions as $permission)
                    <span class="badge font-bold badge-lg badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content">
                            {{ $permission->getLabel() }}
                    </span>
                @endforeach
            </p>
        @else
            <p class="text-lg font-medium">
                <i class="fas fa-user-times mr-2"></i>
                {{ __('ledger.access_and_permissions.no_direct_access') }}
            </p>
        @endif
        <p class="text-sm mt-2 text-info-content/80">{{ __('ledger.access_and_permissions.check_details_below') }}</p>
    </div>
    <div class="divider"></div>

    {{-- アクセス権限を持つロールのリスト --}}
    <div class="mb-6">
        <h4 class="text-lg font-semibold mb-3 text-base-content">
            {{--            {{ __('ledger.access_and_permissions.roles_with_access') }}--}}
            <x-mary-icon name="o-sparkles"
                         label="{{ __('ledger.access_and_permissions.roles_with_access') }}"></x-mary-icon>
        </h4>
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
//                    ['key' => 'source', 'label' => __('ledger.access_and_permissions.column.source'), 'class' => 'min-w-[6rem]'],
                ]"
                    :rows="$this->accessRoles"
                    striped
            >
                @scope('cell_role_name', $item)
                <span class="font-medium text-base-content">{{ $item->role->name }}</span>
                @if($item->is_inherited)
                    <span class="tooltip tooltip-bottom text-base-content/70"
                          data-tip="{{ __('ledger.access_and_permissions.inherited_from_parent') }}">
                            <i class="fas fa-level-up-alt ml-1 text-sm"></i>
                        </span>
                @endif
                @endscope

                @scope('cell_permissions', $item)
                @forelse($item->permissions as $permission)
                    <span class="badge badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content mr-1 mb-1">
                            {{ $permission->getLabel() }}
                        </span>
                @empty
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_specific_permissions') }}</span>
                @endforelse
                @endscope

                {{--
                                @scope('cell_source', $item)
                                <span class="badge badge-outline text-base-content/70">
                                        {{ __('ledger.access_and_permissions.source.' . ($item->source ?? 'unknown')) }}
                                    </span>
                                @endscope
                --}}

                <x-slot:empty>
                    <x-mary-icon name="o-folder-minus"
                                 label="{{ __('ledger.access_and_permissions.no_roles_found') }}"/>
                </x-slot:empty>
            </x-mary-table>
        @endif
    </div>

    <div class="divider"></div>

    {{-- アクセス権限を持つ組織のリスト --}}
    <div class="mb-6">
        <h4 class="text-lg font-semibold mb-3 text-base-content">
            <x-mary-icon name="o-building-office-2"
                         label="{{ __('ledger.access_and_permissions.organizations_with_access') }}"/>
        </h4>
        @if ($this->accessOrganizations->isEmpty())
            <div class="alert alert-info bg-info/10 text-info-content border-info">
                <i class="fas fa-building text-info"></i>
                <span>{{ __('ledger.access_and_permissions.no_organizations_found') }}</span>
            </div>
        @else
            <x-mary-table
                    class="table-compact w-full table-zebra overflow-x-auto"
                    :headers="[
                    ['key' => 'organization_name', 'label' => __('ledger.access_and_permissions.column.organization_name'), 'class' => 'min-w-[8rem]'],
                    ['key' => 'roles', 'label' => __('ledger.access_and_permissions.column.roles'), 'class' => 'min-w-[10rem]'], {{-- ロールカラムを追加 --}}
                    ['key' => 'permissions', 'label' => __('ledger.access_and_permissions.column.permissions'), 'class' => 'min-w-[12rem]'],
{{--                    ['key' => 'source', 'label' => __('ledger.access_and_permissions.column.source'), 'class' => 'min-w-[6rem]'],--}}
                ]"
                    :rows="$this->accessOrganizations"
                    striped
            >
                @scope('cell_organization_name', $item)
                <span class="font-medium text-base-content">{{ $item->display_name }}</span>
                @if($item->is_inherited)
                    <span class="tooltip tooltip-bottom text-base-content/70"
                          data-tip="{{ __('ledger.access_and_permissions.inherited_from_parent') }}">
                            <i class="fas fa-level-up-alt ml-1 text-sm"></i>
                        </span>
                @endif
                @endscope

                @scope('cell_roles', $item) {{-- 新しいロール表示スコープ --}}
                @forelse($item->direct_roles as $role)
                    <div class="tooltip" data-tip="{{ __('ledger.access_and_permissions.direct_role') }}">
                        <span class="badge badge-primary text-primary-content mr-1 mb-1">
                            {{ $role->name }}
                        </span>
                    </div>
                @empty
                    {{-- 直接のロールがない場合は表示しない --}}
                @endforelse
                @forelse($item->inherited_roles as $role)
                    <div class="tooltip" data-tip="{{ __('ledger.access_and_permissions.inherited_role') }}">
                        <span class="badge  badge-neutral text-neutral-content mr-1 mb-1">
                            {{ $role->name }}
                            <i class="fas fa-level-up-alt ml-1 text-xs"></i>
                        </span>
                    </div>
                @empty
                    {{-- 継承ロールがない場合は表示しない --}}
                @endforelse
                @if($item->direct_roles->isEmpty() && $item->inherited_roles->isEmpty())
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_roles_assigned') }}</span>
                @endif
                @endscope

                @scope('cell_permissions', $item)
                @forelse($item->permissions as $permission)
                    <span class="badge badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content mr-1 mb-1">
                            {{ $permission->getLabel() }}
                        </span>
                @empty
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_specific_permissions') }}</span>
                @endforelse
                @endscope

                {{--
                                @scope('cell_source', $item)
                                <span class="badge badge-outline text-base-content/70">
                                        {{ __('ledger.access_and_permissions.source.' . ($item->source ?? 'unknown')) }}
                                    </span>
                                @endscope
                --}}

                <x-slot:empty>
                    <x-mary-icon name="o-building-office-2"
                                 label="{{ __('ledger.access_and_permissions.no_organizations_found') }}"/>
                </x-slot:empty>
            </x-mary-table>
        @endif
    </div>
    <div class="divider"></div>

    {{-- アクセス可能なユーザーのリスト --}}
    <div>
        <h4 class="text-lg font-semibold mb-3 text-base-content">
            <x-mary-icon name="o-users" label="{{ __('ledger.access_and_permissions.users_with_access') }}"/>
        </h4>

        {{-- ユーザー検索フィルター --}}
        <div class="mb-4">
            <x-mary-input
                    wire:model.live.debounce.300ms="searchUserQuery"
                    placeholder="{{ __('ledger.access_and_permissions.search_users_placeholder') }}"
                    icon="o-magnifying-glass"
                    class="bg-base-200 text-base-content placeholder-base-content/50"
            >
            </x-mary-input>
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
                    ['key' => 'organizations', 'label' => __('ledger.access_and_permissions.column.organizations'), 'class' => 'min-w-[10rem]'],
                    ['key' => 'roles', 'label' => __('ledger.access_and_permissions.column.roles'), 'class' => 'min-w-[10rem]'],
                    ['key' => 'permissions', 'label' => __('ledger.access_and_permissions.column.permissions'), 'class' => 'min-w-[12rem]'],
                ]"
                    :rows="$this->accessUsers"
                    striped
            >
                @scope('cell_name', $user)
                <span class="font-medium text-base-content">{{ $user->name }}</span>
                @endscope

                @scope('cell_email', $user)
                <span class="text-base-content/80">{{ $user->email }}</span>
                @endscope

                @scope('cell_organizations', $user)
                @forelse($user->organizations->sortBy('name') as $org)
                    <span class="badge badge-neutral text-neutral-content mr-1 mb-1 tooltip"
                          data-tip="{{$org->fullname}}"
                    >
                            {{ $org->name }}@if($org->pivot->is_primary)
                            <span class="text-xs ml-1 font-bold text-info-content/80">(主)</span>
                        @endif
                    </span>
                @empty
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_organizations') }}</span>
                @endforelse
                @endscope

                @scope('cell_roles', $user)
                {{-- `optional()` と `?? []` を使って null アクセスを防止 --}}
                @forelse(optional($user->categorized_roles)['direct'] ?? [] as $role)
                    <span class="badge badge-primary text-primary-content mr-1 mb-1"
                          title="{{ __('ledger.access_and_permissions.direct_role') }}">
                            {{ $role->name }}
                    </span>
                @empty
                    {{-- 直接のロールがない場合は表示しない --}}
                @endforelse
                @forelse(optional($user->categorized_roles)['inherited_from_organizations'] ?? [] as $role)
                    <span class="badge badge-neutral text-neutral-content mr-1 mb-1"
                          title="{{ __('ledger.access_and_permissions.inherited_role') }}">
                            {{ $role->name }}
                            <i class="fas fa-level-up-alt ml-1 text-xs"></i>
                    </span>
                @empty
                    {{-- 継承ロールがない場合は表示しない --}}
                @endforelse
                @if(empty(optional($user->categorized_roles)['direct']) && empty(optional($user->categorized_roles)['inherited_from_organizations']))
                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_roles_assigned') }}</span>
                @endif
                @endscope

                @scope('cell_permissions', $item)
                {{-- こちらも同様に修正 --}}
                @forelse(optional($item->categorized_permissions)['direct'] ?? [] as $permission)
                    <span class="badge badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content mr-1 mb-1">
                            {{ $permission->getLabel() }}
                    </span>
                @empty
                    {{-- 表示なし --}}
                @endforelse
                @forelse(optional($item->categorized_permissions)['inherited_from_organizations'] ?? [] as $permission)
                    <span class="badge badge-{{ $permission->getColor() }} text-{{ $permission->getColor() }}-content mr-1 mb-1">
                            {{ $permission->getLabel() }}
                            <i class="fas fa-level-up-alt ml-1 text-xs"></i>
                    </span>
                @empty
                    {{-- 表示なし --}}
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
</div> {{-- End of relative container for loading-overlay --}}
