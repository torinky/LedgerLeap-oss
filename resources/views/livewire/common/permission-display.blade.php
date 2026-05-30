<div class="relative min-h-[400px]">
    @php
        $permissionTargets = 'filterByRoleId,filterByOrganizationId,filterByPermissionValue,resetFilters,gotoPage,nextPage,previousPage,searchUserQuery';
        $roleCount = $this->accessRoles->count();
        $organizationCount = $this->accessOrganizations->count();
        $userCount = $this->accessUsers->total();
        $currentPermissionCount = count($this->currentUserAllPermissions ?? []);
        $resourceTypeLabel = match ($this->resourceType) {
            'Folder' => __('ledger.access_and_permissions.source.folder'),
            'LedgerDefine' => __('ledger.access_and_permissions.source.ledger_define'),
            'Ledger' => __('ledger.ledger'),
            default => __('ledger.access_and_permissions.source.unknown'),
        };
        $resourceTypeIcon = match ($this->resourceType) {
            'Folder' => 'o-folder',
            default => 'o-shield-check',
        };
        $viewerName = auth()->user()->name;
        $accessStateItems = [
            'direct' => [
                'label' => __('ledger.access_and_permissions.direct_role'),
                'tooltip' => __('ledger.access_and_permissions.direct_role_hint'),
                'icon' => 'o-link',
                'badgeClass' => 'badge-primary',
                'textClass' => 'text-primary-content',
            ],
            'inherited' => [
                'label' => __('ledger.access_and_permissions.inherited_role'),
                'tooltip' => __('ledger.access_and_permissions.inherited_role_hint'),
                'icon' => 'o-arrow-trending-up',
                'badgeClass' => 'badge-neutral',
                'textClass' => 'text-neutral-content',
            ],
        ];
    @endphp

    <x-element.loading-overlay tier="2" :target="$permissionTargets"/>

    <div class="space-y-6" wire:loading.class="opacity-50 pointer-events-none" wire:target="{{ $permissionTargets }}">
        <x-mary-card class="bg-base-100 border border-base-300 shadow" shadow>
            <x-slot:title>
                <div class="flex items-start gap-3">
                    <x-mary-icon name="o-shield-check" class="text-primary mt-0.5"/>
                    <div class="space-y-1">
                        <h3 class="text-xl font-semibold text-base-content">{{ __('ledger.access_and_permissions.overview') }}</h3>
                        {{--                            <p class="text-sm text-base-content/70">{{ __('ledger.access_and_permissions.check_details_below') }}</p>--}}
                    </div>
                </div>
            </x-slot:title>

            <div class="space-y-3">
                {{--
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-mary-badge :value="__('ledger.activity.column.subject') . ': ' . $resourceTypeLabel" :icon="$resourceTypeIcon" class="badge-outline badge-primary badge-sm" />
                                        <x-mary-badge :value="__('ledger.access_and_permissions.viewer') . ': ' . $viewerName" icon="o-user" class="badge-outline badge-neutral badge-sm" />
                                    </div>
                --}}

                @if ($this->currentUserHighestPermission)
                    {{--
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-base-content/70">
                                                    {{ __('ledger.access_and_permissions.your_access_level') }}:
                                                </span>
                                                <x-mary-badge
                                                        :value="$this->currentUserHighestPermission->getLabel()"
                                                        :icon="$this->currentUserHighestPermission->icon()"
                                                        :class="'badge-lg badge-' . $this->currentUserHighestPermission->getColor()"
                                                />
                                            </div>
                    --}}
                    @if(!empty($this->currentUserAllPermissions))
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-base-content/70">
                                {{ __('ledger.access_and_permissions.your_permissions') }}:
                            </span>
                            @forelse($this->currentUserAllPermissions ?? [] as $permission)
                                <x-mary-badge
                                        :value="$permission->getLabel()"
                                        :icon="$permission->icon()"
                                        :class="'badge-' . $permission->getColor()"
                                />
                            @empty
                            @endforelse
                        </div>
                    @endif
                @else
                    <div class="flex items-center gap-2 text-base-content/70">
                        <x-mary-icon name="o-no-symbol" class="text-error"/>
                        <span class="text-sm font-medium">{{ __('ledger.access_and_permissions.no_direct_access') }}</span>
                    </div>
                @endif

                <div class="mt-5 stats stats-vertical gap-3 bg-base-200/70 border border-base-300 shadow-sm w-full lg:stats-horizontal lg:gap-0">
                    <div class="stat">
                        <div class="stat-figure text-primary">
                            <x-mary-icon name="o-sparkles" class="h-8 w-8"/>
                        </div>
                        <div class="stat-title">{{ __('ledger.access_and_permissions.roles_with_access') }}</div>
                        <div class="stat-value text-primary">{{ $roleCount }}</div>
                        <div class="stat-desc">{{ __('ledger.access_and_permissions.roles') }}</div>
                    </div>

                    <div class="stat">
                        <div class="stat-figure text-primary">
                            <x-mary-icon name="o-building-office-2" class="h-8 w-8"/>
                        </div>
                        <div class="stat-title">{{ __('ledger.access_and_permissions.organizations_with_access') }}</div>
                        <div class="stat-value text-primary">{{ $organizationCount }}</div>
                        <div class="stat-desc">{{ __('ledger.access_and_permissions.organizations') }}</div>
                    </div>

                    <div class="stat">
                        <div class="stat-figure text-primary">
                            <x-mary-icon name="o-users" class="h-8 w-8"/>
                        </div>
                        <div class="stat-title">{{ __('ledger.access_and_permissions.users_with_access') }}</div>
                        <div class="stat-value text-primary">{{ $userCount }}</div>
                        <div class="stat-desc">{{ __('ledger.access_and_permissions.users') }}</div>
                    </div>

                    <div class="stat">
                        <div class="stat-figure text-primary">
                            <x-mary-icon name="o-lock-closed" class="h-8 w-8"/>
                        </div>
                        <div class="stat-title">{{ __('ledger.access_and_permissions.column.permissions') }}</div>
                        <div class="stat-value text-primary">{{ $currentPermissionCount }}</div>
                        <div class="stat-desc">{{ __('ledger.access_and_permissions.your_access_level') }}</div>
                    </div>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card class="bg-base-100 border border-base-300 shadow" shadow>
            <x-slot:title>
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-funnel" class="text-primary mt-0.5"/>
                    <h4 class="text-lg font-semibold text-base-content">{{ __('ledger.access_and_permissions.filters') }}</h4>
                </div>
            </x-slot:title>

            {{-- <x-slot:menu>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    @foreach($accessStateItems as $stateItem)
                        <div class="tooltip tooltip-bottom" data-tip="{{ $stateItem['tooltip'] }}">
                            <span class="badge badge-outline {{ $stateItem['badgeClass'] }} {{ $stateItem['textClass'] }} inline-flex items-center justify-center gap-0.5">
                                <x-mary-icon :name="$stateItem['icon']" class="shrink-0"/>
                                <span class="sr-only">{{ $stateItem['label'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-slot:menu> --}}

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
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
                                    <span class="block text-sm text-neutral">
                                        {{ $org['full_name'] }}
                                    </span>
                                @endif
                            </x-slot:sub-value>
                        </x-mary-list-item>
                        @endscope
                    </x-mary-choices>
                </div>

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

            <x-slot:actions>
                <x-mary-button
                        label="{{ __('ledger.reset') }}"
                        wire:click="resetFilters"
                        class="btn-sm btn-ghost"
                        icon="o-arrow-path"
                />
            </x-slot:actions>
        </x-mary-card>

        <div class="space-y-6">
            <x-mary-card class="bg-base-100 border border-base-300 shadow" shadow>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-sparkles" class="text-primary"/>
                        <h4 class="text-lg font-semibold text-base-content">{{ __('ledger.access_and_permissions.roles_with_access') }}</h4>
                    </div>
                </x-slot:title>

                <x-slot:menu>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="badge badge-outline text-base-content/60">{{ $roleCount }}</span>
                        <span class="badge badge-primary badge-sm">
                            <x-mary-icon name="o-arrow-trending-up"
                                         label="{{ __('ledger.access_and_permissions.direct_role') }}"/>
                        </span>
                        <span class="badge badge-neutral badge-sm">
                            <x-mary-icon name="o-link"
                                         label="{{ __('ledger.access_and_permissions.inherited_role') }}"/>
                        </span>
                    </div>
                </x-slot:menu>

                <div>
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
                            ]"
                                :rows="$this->accessRoles"
                                striped
                        >
                            @scope('cell_role_name', $item)
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $inheritedSourceSegments = collect($item->source_folder_path_items ?? []);
                                    $sourceFolderUrl = $item->source_folder_id
                                        ? route('folder.edit', ['tenant' => $this->resolveTenantId(), 'folder' => $item->source_folder_id])
                                        : null;
                                @endphp

                                @if($item->is_inherited)
                                    <div class="flex flex-col items-start gap-1">
                                        <div class="tooltip"
                                             data-tip="{{ __('ledger.access_and_permissions.inherited_role_hint') }}">
                                            <span class="badge badge-neutral text-neutral-content inline-flex items-center justify-center gap-0.5">
                                                <x-mary-icon name="o-link" label="{{ $item->role->name }}" />
                                                <span class="sr-only">{{ __('ledger.access_and_permissions.inherited_role') }}</span>
                                            </span>
                                        </div>

                                        <span class="text-sm text-base-content/70">{{ __('ledger.access_and_permissions.inherited_from_folder_label') }}</span>

                                        @if(!empty($item->source_folder_id))
                                            <div class="ml-6">
                                                @if($inheritedSourceSegments->isNotEmpty())
                                                    <div class="breadcrumbs text-xs min-w-0 shrink overflow-x-auto">
                                                        <ul class="m-0 p-0">
                                                            @foreach($inheritedSourceSegments as $segment)
                                                                <li class="whitespace-nowrap inline-flex items-center gap-1">
                                                                    <a href="{{ route('folder.edit', ['tenant' => $this->resolveTenantId(), 'folder' => $segment['id']]) }}"
                                                                       class="inline-flex items-center gap-1 link link-primary hover:no-underline">
                                                                        <x-mary-icon name="o-folder" class="shrink-0"/>
                                                                        <span>{{ $segment['title'] }}</span>
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @else
                                                    <a href="{{ $sourceFolderUrl }}"
                                                       class="inline-flex items-center gap-1 font-medium link link-primary hover:no-underline">
                                                        <x-mary-icon name="o-folder" class="shrink-0"/>
                                                        <span>{{ $item->source_folder_title ?? __('ledger.access_and_permissions.inherited_from_folder_label') }}</span>
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="tooltip"
                                         data-tip="{{ __('ledger.access_and_permissions.direct_role_hint') }}">
                                        <span class="badge badge-primary badge-sm text-primary-content inline-flex items-center justify-center gap-0.5">
                                            <x-mary-icon name="o-arrow-trending-up" label="{{ $item->role->name }}" />
                                            <span class="sr-only">{{ __('ledger.access_and_permissions.direct_role') }}</span>
                                        </span>
                                    </div>
                                @endif
                                            @endscope

                                            @scope('cell_permissions', $item)
                                            @forelse($item->permissions as $permission)
                                                <x-mary-badge
                                                        :value="$permission->getLabel()"
                                                        :icon="$permission->icon()"
                                                        class="badge-{{ $permission->getColor() }} badge-sm mr-1 mb-1"
                                                />
                                            @empty
                                                <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_specific_permissions') }}</span>
                                            @endforelse
                                            @endscope

                            <x-slot:empty>
                                <x-mary-icon name="o-folder-minus"
                                             label="{{ __('ledger.access_and_permissions.no_roles_found') }}"/>
                            </x-slot:empty>
                        </x-mary-table>
                    @endif
                </div>
            </x-mary-card>

            <x-mary-card class="bg-base-100 border border-base-300 shadow" shadow>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-building-office-2" class="text-primary"/>
                        <h4 class="text-lg font-semibold text-base-content">{{ __('ledger.access_and_permissions.organizations_with_access') }}</h4>
                    </div>
                </x-slot:title>

                <x-slot:menu>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="badge badge-outline text-base-content/60">{{ $this->accessOrganizations->count() }}</span>
                        <span class="badge badge-primary badge-sm">
                            <x-mary-icon name="o-arrow-trending-up"
                                         label="{{ __('ledger.access_and_permissions.direct_role') }}"/>
                        </span>
                        <span class="badge badge-neutral badge-sm">
                            <x-mary-icon name="o-link" label="{{ __('ledger.access_and_permissions.inherited_role') }}"/>
                        </span>
                    </div>
                </x-slot:menu>
                
                <div class="mt-4">
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
                                ['key' => 'roles', 'label' => __('ledger.access_and_permissions.column.roles'), 'class' => 'min-w-[10rem]'],
                                ['key' => 'permissions', 'label' => __('ledger.access_and_permissions.column.permissions'), 'class' => 'min-w-[12rem]'],
                            ]"
                                :rows="$this->accessOrganizations"
                                striped
                        >
                            @scope('cell_organization_name', $item)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-base-content">{{ $item->display_name }}</span>
                                @if($item->is_inherited)
                                    <div class="tooltip tooltip-bottom"
                                         data-tip="{{ __('ledger.access_and_permissions.inherited_role_hint') }}">
                                        <span class="badge badge-neutral badge-sm text-neutral-content inline-flex items-center gap-1">
                                            <x-mary-icon name="o-arrow-trending-up" class="h-3.5 w-3.5 shrink-0"/>
                                            <span>{{ __('ledger.access_and_permissions.inherited_role') }}</span>
                                        </span>
                                    </div>
                                @endif
                            </div>
                            @if($item->is_inherited)
                                <p class="mt-1 text-xs text-base-content/60">{{ __('ledger.access_and_permissions.inherited_from_parent') }}</p>
                            @endif
                            @endscope

                            @scope('cell_roles', $item)
                            <div class="flex flex-wrap gap-1">
                                @forelse($item->direct_roles as $role)
                                    <div class="tooltip tooltip-bottom"
                                         data-tip="{{ __('ledger.access_and_permissions.direct_role_hint') }}">
                                        <x-mary-badge
                                                :value="$role->name"
                                                icon="o-arrow-trending-up"
                                                class="badge-primary badge-sm"
                                        />
                                    </div>
                                @empty
                                @endforelse

                                @forelse($item->inherited_roles as $role)
                                    <div class="tooltip tooltip-bottom"
                                         data-tip="{{ __('ledger.access_and_permissions.inherited_role_hint') }}">
                                        <x-mary-badge
                                                :value="$role->name"
                                                icon="o-link"
                                                class="badge-neutral badge-sm"
                                        />
                                    </div>
                                @empty
                                @endforelse

                                @if($item->direct_roles->isEmpty() && $item->inherited_roles->isEmpty())
                                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_roles_assigned') }}</span>
                                @endif
                            </div>
                            @endscope

                            @scope('cell_permissions', $item)
                            @forelse($item->permissions as $permission)
                                <x-mary-badge
                                        :value="$permission->getLabel()"
                                        :icon="$permission->icon()"
                                        class="badge-{{ $permission->getColor() }} badge-sm mr-1 mb-1"
                                />
                            @empty
                                <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_specific_permissions') }}</span>
                            @endforelse
                            @endscope

                            <x-slot:empty>
                                <x-mary-icon name="o-building-office-2"
                                             label="{{ __('ledger.access_and_permissions.no_organizations_found') }}"/>
                            </x-slot:empty>
                        </x-mary-table>
                    @endif
                </div>
            </x-mary-card>

            <x-mary-card class="bg-base-100 border border-base-300 shadow" shadow>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-users" class="text-primary"/>
                        <h4 class="text-lg font-semibold text-base-content">{{ __('ledger.access_and_permissions.users_with_access') }}</h4>
                    </div>
                </x-slot:title>

                <x-slot:menu>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="badge badge-outline text-base-content/60">{{ $userCount }}</span>
                        <span class="badge badge-primary badge-sm">
                            <x-mary-icon name="o-arrow-trending-up"
                                         label="{{ __('ledger.access_and_permissions.direct_role') }}"/>
                        </span>
                        <span class="badge badge-neutral badge-sm">
                            <x-mary-icon name="o-link"
                                         label="{{ __('ledger.access_and_permissions.inherited_role') }}"/>
                        </span>
                    </div>
                </x-slot:menu>

                <div>
                    <x-mary-input
                            wire:model.live.debounce.300ms="searchUserQuery"
                            placeholder="{{ __('ledger.access_and_permissions.search_users_placeholder') }}"
                            icon="o-magnifying-glass"
                            class="bg-base-200 text-base-content placeholder-base-content/50"
                    />
                </div>

                <div class="mt-4">
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
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->organizations->sortBy('name') as $org)
                                    <span class="badge badge-neutral text-neutral-content mr-1 mb-1 tooltip"
                                          data-tip="{{ $org->fullname }}">
                                        {{ $org->name }}
                                        @if($org->pivot->is_primary)
                                            <span class="text-xs ml-1 font-bold text-info-content/80">(主)</span>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_organizations') }}</span>
                                @endforelse
                            </div>
                            @endscope

                            @scope('cell_roles', $user)
                            <div class="flex flex-wrap gap-1">
                                @forelse(optional($user->categorized_roles)['direct'] ?? [] as $role)
                                    <div class="tooltip tooltip-bottom"
                                         data-tip="{{ __('ledger.access_and_permissions.direct_role_hint') }}">
                                        <x-mary-badge
                                                :value="$role->name"
                                                icon="o-arrow-trending-up"
                                                class="badge-primary badge-sm"
                                        />
                                    </div>
                                @empty
                                @endforelse

                                @forelse(optional($user->categorized_roles)['inherited_from_organizations'] ?? [] as $role)
                                    <div class="tooltip tooltip-bottom"
                                         data-tip="{{ __('ledger.access_and_permissions.inherited_role_hint') }}">
                                        <x-mary-badge
                                                :value="$role->name"
                                                icon="o-link"
                                                class="badge-neutral badge-sm"
                                        />
                                    </div>
                                @empty
                                @endforelse

                                @if(empty(optional($user->categorized_roles)['direct']) && empty(optional($user->categorized_roles)['inherited_from_organizations']))
                                    <span class="text-base-content/70">{{ __('ledger.access_and_permissions.no_roles_assigned') }}</span>
                                @endif
                            </div>
                            @endscope

                            @scope('cell_permissions', $item)
                            <div class="flex flex-wrap gap-1">
                                @forelse(optional($item->categorized_permissions)['direct'] ?? [] as $permission)
                                    <x-mary-badge
                                            :value="$permission->getLabel()"
                                            :icon="$permission->icon()"
                                            class="badge-{{ $permission->getColor() }} badge-sm mr-1 mb-1"
                                    />
                                @empty
                                @endforelse

                                @forelse(optional($item->categorized_permissions)['inherited_from_organizations'] ?? [] as $permission)
                                    <x-mary-badge
                                            :value="$permission->getLabel()"
                                            :icon="$permission->icon()"
                                            class="badge-{{ $permission->getColor() }} badge-sm mr-1 mb-1"
                                    />
                                @empty
                                @endforelse
                            </div>
                            @endscope

                            <x-slot:empty>
                                <x-mary-icon name="o-users"
                                             label="{{ __('ledger.access_and_permissions.no_users_found') }}"/>
                            </x-slot:empty>
                        </x-mary-table>

                        <div class="mt-4">
                            {{ $this->accessUsers->links() }}
                        </div>
                    @endif
                </div>
            </x-mary-card>
        </div>
    </div>
</div>
