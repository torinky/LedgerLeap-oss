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
                               link="{{ route('notifications.settings', ['tenant' => tenant()?->id]) }}" class="btn-ghost"/>
                @endif
            </x-slot:actions>
        </x-mary-header>
    </x-slot>


    <div class="columns-1 gap-4 space-y-4 mx-8 lg:columns-2 lg:gap-8 lg:space-y-8 3xl:columns-3 items-center">

        @if(tenant())
        {{-- ★承認待ちタスク カード (追加) ★ --}}
        <a href="{{ route('workflow.pending', ['tenant' => tenant()?->id]) }}" _target="LedgerLeap_PendingList"
           class="card bg-warning text-warning-content shadow-lg hover:shadow-xl transition-shadow duration-300 ease-in-out {{ $pendingTaskCount > 0 ? '' : 'opacity-50' }}">
            <div class="card-body flex-row items-center justify-between p-4">
                <div>
                    <h2 class="card-title text-lg">{{ __('ledger.workflow.pending_tasks') }}</h2>
                    <p class="text-sm">{{ __('ledger.workflow.pending_tasks_description') }}</p>
                </div>
                <div class="text-4xl font-bold">
                    {{ $pendingTaskCount }}
                </div>
            </div>
        </a>
        @endif

        {{-- 役割と所属エリア --}}
        <x-mary-card title="{{ __('ledger.roles_and_affiliations_title') }}" shadow="sm" class="h-auto">
            <div class="mb-4">
                <h3 class="text-md font-medium text-base-content mb-1">
                    {{ __('ledger.main_role_and_affiliation_title') }}
                </h3>
                <p class="text-base-content/90">{{ $roleDisplayString }}</p>
            </div>

            @if($otherOrganizations->isNotEmpty())
                <div class="mt-4">
                    <h3 class="text-md font-medium text-base-content mb-1">
                        {{ __('ledger.other_affiliations_title') }}
                    </h3>
                    @foreach($otherOrganizations as $organization)
                        <x-mary-list-item :item="$organization" no-separator no-hover>
                            <x-slot:value>
                                {{ $organization->name }}
                            </x-slot:value>
                        </x-mary-list-item>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 pt-4 border-t border-base-300">
                <h3 class="text-md font-medium text-base-content mb-1">
                    {{ __('ledger.your_effective_roles_title') }}
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

        <x-mary-card title="{{ __('ledger.main_abilities_title') }}" shadow="sm" class="h-auto">
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

        @if(tenant())
        {{-- あなたの担当フォルダエリア (ステップ3で追加) --}}
        <x-mary-card title="{{ __('ledger.assigned_folders_title') }}" shadow="sm" class="h-auto">
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
                    <x-mary-button label="{{ __('ledger.go_to_folder_button') }}"
                                   link="{{ route('ledgersByFolderId', ['tenant' => tenant()?->id, 'folderId' => $folder->id]) }}"
                                   class="btn-primary btn-sm"
                                   icon="o-arrow-right-circle"/>
                </div>
            @empty
                <p class="text-sm text-base-content/70">{{ __('ledger.no_assigned_folders') }}</p>
            @endforelse
        </x-mary-card>

        <x-mary-card title="{{ __('ledger.detailed_information_title') }}" shadow="sm" class="h-auto">
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
                        />
                    </div>
                </x-slot:content>
            </x-mary-collapse>
        </x-mary-card>
        @endif

    </div>
</div>
