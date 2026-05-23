<div>
    @push('stylesheets')
        @vite(['resources/css/tree.css'])
    @endpush

    <x-slot name="header">
        <x-mary-header :title="__('ledger.my_portal_title')" subtitle="ようこそ、{{ Auth::user()->name }} さん！"
                       size="text-xl" separator progress-indicator
                       icon="o-home-modern"
        >
            <x-slot:actions>
                <x-mary-button label="{{ __('ledger.edit_profile_title') }}" icon="o-user"
                               link="{{ route('profile.edit') }}" class="btn-ghost"/>
                @if(tenant())
                    <x-mary-button label="{{ __('ledger.edit_notifications_settings_title') }}" icon="o-bell"
                                   link="{{ route('notifications.settings', ['tenant' => tenant()?->id]) }}"
                                   class="btn-ghost"/>
                @endif
            </x-slot:actions>
        </x-mary-header>
    </x-slot>


    <div class="mx-8 space-y-4 relative">
        <x-element.loading-overlay tier="2"/>

        <div class="columns-1 gap-4 space-y-4 lg:columns-2 lg:gap-8 lg:space-y-8 3xl:columns-3 items-center relative">

            <div class="2xl:columns-2 space-y-4">

                <div wire:loading class="w-full">
                    <x-element.skeleton-card/>
                </div>
                <div wire:loading.remove class="">
                    <a
                            href="{{ route('notifications.index') }}"
                            class="w-full stats stats-vertical sm:stats-horizontal lg:max-w-none lg:mx-0 overflow-hidden rounded-2xl border border-secondary/20 bg-secondary/15 text-secondary-content shadow-sm transition-shadow duration-300 ease-in-out hover:shadow-lg {{ $notificationCount > 0 ? '' : 'opacity-60' }}"
                            data-my-portal-notifications-card
                    >
                        <div class="stat">
                            <div class="stat-figure text-secondary-content/90">
                                <x-mary-icon name="o-bell" class="h-8 w-8"/>
                            </div>
                            <div class="stat-title text-secondary-content/90 text-lg font-semibold normal-case">
                                {{ __('ledger.notifications') }}
                            </div>
                            <div
                                    class="stat-value text-secondary-content"
                                    data-my-portal-notification-count="{{ $notificationCount }}"
                            >
                                {{ $notificationCount }}
                            </div>
                            <div class="stat-desc text-secondary-content/80">
                                {{ __('ledger.portal_notifications_subtitle') }}
                            </div>
                        </div>
                    </a>
                </div>

                @if(tenant())
                    {{-- ★承認待ちタスク カード ★ --}}
                    <div wire:loading class="w-full">
                        <x-element.skeleton-stats items="1" class="md:grid-cols-1 lg:grid-cols-1"/>
                    </div>
                    <div wire:loading.remove class="">
                        <a href="{{ route('workflow.pending', ['tenant' => tenant()?->id]) }}"
                           _target="LedgerLeap_PendingList"
                           class="w-full stats stats-vertical sm:stats-horizontal lg:max-w-none lg:mx-0 overflow-hidden rounded-2xl border border-warning/30 bg-warning/15 text-warning-content shadow-sm transition-shadow duration-300 ease-in-out hover:shadow-lg {{ $pendingTaskCount > 0 ? '' : 'opacity-60' }}">
                            <div class="stat">
                                <div class="stat-figure text-warning-content/90">
                                    <x-mary-icon name="o-clock" class="h-8 w-8"/>
                                </div>
                                <div class="stat-title text-warning-content/90 text-lg font-semibold normal-case">{{ __('ledger.workflow.pending_tasks') }}</div>
                                <div class="stat-value text-warning-content">{{ $pendingTaskCount }}</div>
                                <div class="stat-desc text-warning-content/80">{{ __('ledger.workflow.pending_tasks_description') }}</div>
                            </div>
                        </a>
                    </div>
                @endif
            </div>


            {{-- 役割と所属エリア --}}
            <div wire:loading class="w-full">
                <x-element.skeleton-card/>
            </div>
            <div wire:loading.remove class="w-full">
                <x-mary-card shadow="sm" separator class="h-auto" subtitle="{{ __('ledger.portal_roles_subtitle') }}">
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-identification" class="w-5 h-5 shrink-0 text-primary"/>
                            <span>{{ __('ledger.roles_and_affiliations_title') }}</span>
                        </div>
                    </x-slot:title>
                    <div class="stats stats-vertical sm:stats-horizontal w-full bg-base-200/60 border border-base-300 shadow-sm">
                        <div class="stat">
                            <div class="stat-figure text-primary">
                                <x-mary-icon name="o-building-office-2" class="h-8 w-8"/>
                            </div>
                            <div class="stat-title">{{ __('ledger.portal_primary_organization_label') }}</div>
                            <div class="stat-value text-2xl text-base-content leading-tight">{{ $primaryOrganizationName }}</div>
                            <div class="stat-desc">{{ $primaryOrganizationNote }}</div>
                        </div>

                        <div class="stat">
                            <div class="stat-figure text-secondary">
                                <x-mary-icon name="o-user" class="h-8 w-8"/>
                            </div>
                            <div class="stat-title">{{ __('ledger.portal_primary_role_label') }}</div>
                            <div class="stat-value text-2xl text-base-content leading-tight">{{ $primaryRoleName }}</div>
                            <div class="stat-desc">{{ __('ledger.portal_primary_role_desc') }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-base-content mb-2">
                            <x-mary-icon name="o-building-office-2" class="w-4 h-4 text-secondary"/>
                            <span>{{ __('ledger.other_affiliations_title') }}</span>
                        </h3>
                        @if($otherOrganizations->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($otherOrganizations as $organization)
                                    <x-mary-list-item :item="$organization" no-separator no-hover>
                                        <x-slot:value>
                                            {{ $organization->name }}
                                        </x-slot:value>
                                    </x-mary-list-item>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-base-content/70">{{ __('ledger.no_organization_assigned') }}</p>
                        @endif
                    </div>

                    <div class="mt-4 pt-4 border-t border-base-300">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-base-content mb-2">
                            <x-mary-icon name="o-sparkles" class="w-4 h-4 text-secondary"/>
                            <span>{{ __('ledger.your_effective_roles_title') }}</span>
                        </h3>
                        @if($activeRoles->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach($activeRoles as $role)
                                    @php
                                        $roleKey = $role->label ?? $role->name;
                                        $translationKey = 'ledger.role_label.' . $roleKey;
                                        $displayName = trans()->has($translationKey) ? __($translationKey) : $roleKey;
                                    @endphp
                                    <x-mary-badge :value="$displayName" class="badge-neutral"/>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-base-content/70">{{ __('role.no_roles_assigned') }}</p>
                        @endif
                    </div>
                </x-mary-card>
            </div>

            <div wire:loading class="w-full">
                <x-element.skeleton-card/>
            </div>
            <div wire:loading.remove class="w-full">
                <x-mary-card shadow="sm" separator class="h-auto">
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-mary-icon name="o-shield-check" class="w-5 h-5 shrink-0 text-secondary"/>
                            <span>{{ __('ledger.main_abilities_title') }}</span>
                        </div>
                    </x-slot:title>
                    @if(!empty($majorPermissions))
                        <ul class="space-y-2">
                            @foreach($majorPermissions as $permission)
                                <li class="flex items-center">
                                    @if($permission['has'])
                                        <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success mr-2 shrink-0"/>
                                    @else
                                        <x-mary-icon name="o-x-circle"
                                                     class="w-5 h-5 text-error/50 mr-2 shrink-0"/>
                                    @endif
                                    <span class="{{ $permission['has'] ? 'text-base-content' : 'text-base-content/70' }}">
                                       {{ $permission['description'] }}
                                       </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-base-content/70">{{ __('ledger.basic_operations_permission') }}</p>
                    @endif
                </x-mary-card>
            </div>

            @if(tenant())
                {{-- あなたの担当フォルダエリア --}}
                <div wire:loading class="w-full">
                    <x-element.skeleton-card/>
                </div>
                <div wire:loading.remove class="w-full">
                    <x-mary-card shadow="sm" separator class="h-auto">
                        <x-slot:title>
                            <div class="flex items-center gap-2">
                                <x-mary-icon name="o-folder" class="w-5 h-5 shrink-0 text-secondary"/>
                                <span>{{ __('ledger.assigned_folders_title') }}</span>
                            </div>
                        </x-slot:title>
                        @forelse($assignedFolders as $folder)
                            <div class="border border-base-300 rounded-lg p-4 mb-2 flex justify-between items-center">
                                <div>
                                    <div class="flex items-center mb-1">
                                        @php
                                            $icon = 'o-folder';
                                            $iconColor = 'text-secondary';
                                            $permissionText = '';
                                            if (in_array($folder->id, $manageableFolderIds)) {
                                                $icon = 'o-cog-6-tooth';
                                                $iconColor = 'text-accent';
                                                $permissionText = __('ledger.folder_permission_manageable');
                                            } elseif (in_array($folder->id, $writableFolderIds)) {
                                                $icon = 'o-pencil-square';
                                                $iconColor = 'text-accent/90';
                                                $permissionText = __('ledger.folder_permission_editable');
                                            }
                                        @endphp
                                        <x-mary-icon :name="$icon" class="w-5 h-5 mr-2 shrink-0 {{ $iconColor }}"/>
                                        <span class="font-semibold text-base-content">{{ $folder->title }}</span>
                                    </div>
                                    <span class="text-xs {{ $iconColor }}">{{ $permissionText }}</span>
                                </div>
                                <x-mary-button label="{{ __('ledger.go_to_ledger_list_button') }}"
                                               link="{{ route('ledgersByFolderId', ['tenant' => tenant()?->id, 'folderId' => $folder->id]) }}"
                                               class="btn-primary btn-sm"
                                               icon="o-arrow-right-circle"/>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/70">{{ __('ledger.no_assigned_folders') }}</p>
                        @endforelse
                    </x-mary-card>
                </div>

                <div wire:loading class="w-full">
                    <x-element.skeleton-card/>
                </div>
                <div wire:loading.remove class="w-full">
                    <div wire:ignore class="w-full">
                        <x-mary-card shadow="sm" separator class="h-auto border border-secondary/15 bg-secondary/5"
                                     subtitle="{{ __('ledger.portal_folder_tree_hint') }}">
                            <x-slot:title>
                                <div class="flex items-center gap-2">
                                    <x-mary-icon name="o-arrow-right-circle" class="w-5 h-5 shrink-0 text-secondary"/>
                                    <span>{{ __('ledger.portal_folder_handoff_title') }}</span>
                                </div>
                            </x-slot:title>
                            <x-mary-collapse>
                                <x-slot:heading>
                                    {{ __('ledger.all_accessible_folders_link') }}
                                </x-slot:heading>
                                <x-slot:content>
                                    <div class="p-4 menu w-full">
                                        <x-folder.tree
                                                :folders="$allRootFolders"
                                                :writableFolderIds="$writableFolderIds"
                                                :readableFolderIds="$readableFolderIds"
                                                :manageableFolderIds="$manageableFolderIds"
                                                :interactive="false"
                                                :clickNavigatesToLedgerList="true"
                                                :showPermissionTooltip="false"
                                        />
                                    </div>
                                </x-slot:content>
                            </x-mary-collapse>
                        </x-mary-card>
                    </div>
                </div>
            @endif

        </div>

    </div>
</div>
