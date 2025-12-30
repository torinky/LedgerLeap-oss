{{-- Permissions Tab --}}
<div class="px-6 py-4 space-y-6 pb-10">
    {{-- 1. 権限概要 --}}
    <section>
        <h3 class="text-xs font-bold mb-3 flex items-center gap-2 text-base-content/50 uppercase tracking-wider">
            <i class="fa-solid fa-user-shield"></i>
            {{ __('ledger.file_inspector.access.your_permissions') }}
        </h3>

        <div class="card bg-base-200 border border-base-300 shadow-sm overflow-hidden">
            <div class="p-4 flex items-center justify-between bg-primary/5 border-b border-primary/10">
                <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                        <div class="bg-primary text-primary-content rounded-full w-10">
                            <span class="text-xs font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-bold">{{ auth()->user()->name }}
                        </p>
                        <p class="text-[10px] opacity-60">
                            {{ auth()->user()->email }}</p>
                    </div>
                </div>
                @php
                    $highestPerm = $this->userPermissions['folder_permission'] ?? 'none';
                    $badgeColor = match ($highestPerm) {
                        'admin' => 'error',
                        'approve' => 'warning',
                        'inspect' => 'info',
                        'write' => 'primary',
                        'read' => 'success',
                        default => 'ghost',
                    };
                @endphp
                <div class="badge badge-{{ $badgeColor }} font-bold p-3">
                    {{ __('permission.name.' . $highestPerm) }}
                </div>
            </div>

            <div class="p-4 bg-base-100">
                <div class="grid grid-cols-2 gap-4">
                    @foreach (['read', 'write', 'download', 'delete'] as $perm)
                        <div class="flex items-center justify-between">
                            <span class="text-xs opacity-70">{{ __('ledger.file_inspector.access.' . $perm) }}</span>
                            @if ($this->userPermissions[$perm])
                                <i class="fa-solid fa-check-circle text-success text-xs"></i>
                            @else
                                <i class="fa-solid fa-times-circle text-base-content/20 text-xs"></i>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 2. 公開範囲 (サマリー) --}}
    <section class="mt-8">
        <h3
            class="text-xs font-bold mb-3 flex items-center justify-between text-base-content/50 uppercase tracking-wider">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-users-viewfinder"></i>
                <span
                    class="text-sm font-bold opacity-80">{{ __('ledger.file_inspector.access.org_role_settings') }}</span>
            </div>
        </h3>

        <div class="grid grid-cols-1 gap-3">
            {{-- ロール --}}
            @if($isInLedgerDetailPage)
            <div class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors"
                wire:click="navigateToPermissionsTab">
            @elseif($this->permissionsTabUrl)
            <a href="{{ $this->permissionsTabUrl }}" target="_blank" rel="noopener noreferrer"
                class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors block">
            @else
            <div class="card bg-base-200 border border-base-300 p-3 opacity-50">
            @endif
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary">
                            <x-mary-icon name="o-sparkles" class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-xs font-bold">
                                {{ __('ledger.access_and_permissions.roles_with_access') }}
                            </p>
                            <p class="text-[10px] opacity-60">
                                {{ $this->accessRoles->count() }}
                                {{ __('ledger.file_inspector.access.role') }}
                            </p>
                        </div>
                    </div>
                    @if ($this->accessRoles->isNotEmpty())
                        <div class="flex -space-x-2">
                            @foreach ($this->accessRoles->take(3) as $item)
                                <div class="badge badge-primary badge-xs border-2 border-base-200 tooltip"
                                    data-tip="{{ $item->role->name }}">
                                    {{ mb_substr($item->role->name, 0, 1) }}
                                </div>
                            @endforeach
                            @if ($this->accessRoles->count() > 3)
                                <div class="badge badge-ghost badge-xs border-2 border-base-200 text-[8px]">
                                    +{{ $this->accessRoles->count() - 3 }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @if($isInLedgerDetailPage || !$this->permissionsTabUrl)
            </div>
            @else
            </a>
            @endif

            {{-- 組織 --}}
            @if($isInLedgerDetailPage)
            <div class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors"
                wire:click="navigateToPermissionsTab">
            @elseif($this->permissionsTabUrl)
            <a href="{{ $this->permissionsTabUrl }}" target="_blank" rel="noopener noreferrer"
                class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors block">
            @else
            <div class="card bg-base-200 border border-base-300 p-3 opacity-50">
            @endif
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-info/10 flex items-center justify-center text-info">
                            <x-mary-icon name="o-building-office-2" class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-xs font-bold">
                                {{ __('ledger.access_and_permissions.organizations_with_access') }}
                            </p>
                            <p class="text-[10px] opacity-60">
                                {{ $this->accessOrganizations->count() }}
                                {{ __('ledger.file_inspector.access.organization') }}
                            </p>
                        </div>
                    </div>
                    @if ($this->accessOrganizations->isNotEmpty())
                        <div class="flex -space-x-2">
                            @foreach ($this->accessOrganizations->take(3) as $item)
                                <div class="badge badge-info badge-xs border-2 border-base-200 tooltip"
                                    data-tip="{{ $item->display_name }}">
                                    {{ mb_substr($item->display_name, 0, 1) }}
                                </div>
                            @endforeach
                            @if ($this->accessOrganizations->count() > 3)
                                <div class="badge badge-ghost badge-xs border-2 border-base-200 text-[8px]">
                                    +{{ $this->accessOrganizations->count() - 3 }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @if($isInLedgerDetailPage || !$this->permissionsTabUrl)
            </div>
            @else
            </a>
            @endif

            {{-- ユーザー --}}
            @if($isInLedgerDetailPage)
            <div class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors"
                wire:click="navigateToPermissionsTab">
            @elseif($this->permissionsTabUrl)
            <a href="{{ $this->permissionsTabUrl }}" target="_blank" rel="noopener noreferrer"
                class="card bg-base-200 border border-base-300 p-3 cursor-pointer hover:bg-base-300 transition-colors block">
            @else
            <div class="card bg-base-200 border border-base-300 p-3 opacity-50">
            @endif
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-success/10 flex items-center justify-center text-success">
                            <x-mary-icon name="o-users" class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-xs font-bold">
                                {{ __('ledger.access_and_permissions.users_with_access') }}
                            </p>
                            <p class="text-[10px] opacity-60">
                                {{ $this->accessUsers->count() }}
                                {{ __('ledger.user') }}
                            </p>
                        </div>
                    </div>
                    @if ($this->accessUsers->isNotEmpty())
                        <div class="flex -space-x-2">
                            @foreach ($this->accessUsers->take(3) as $user)
                                <div class="avatar placeholder tooltip" data-tip="{{ $user->name }}">
                                    <div
                                        class="bg-success text-success-content rounded-full w-4 border-2 border-base-200">
                                        <span class="text-[8px]">{{ mb_substr($user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                            @endforeach
                            @if ($this->accessUsers->count() > 3)
                                <div class="avatar placeholder">
                                    <div
                                        class="bg-neutral text-neutral-content rounded-full w-4 border-2 border-base-200">
                                        <span class="text-[8px]">+{{ $this->accessUsers->count() - 3 }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @if($isInLedgerDetailPage || !$this->permissionsTabUrl)
            </div>
            @else
            </a>
            @endif
        </div>
    </section>

    {{-- 3. アクションセクション --}}
    <section class="mt-8">
        <h3 class="text-xs font-bold mb-3 flex items-center gap-2 text-base-content/50 uppercase tracking-wider">
            <i class="fa-solid fa-bolt"></i>
            {{ __('ledger.file_inspector.actions.title') }}
        </h3>

        <div class="space-y-3">
            {{-- 全処理を再実行 --}}
            <div class="card bg-base-200 border border-base-300">
                <div class="p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-warning/10 flex items-center justify-center">
                            <i class="fa-solid fa-rotate text-warning"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold">
                                {{ __('ledger.file_inspector.actions.retry_all') }}
                            </p>
                            <p class="text-[10px] opacity-60">
                                {{ __('ledger.file_inspector.actions.retry_all_description') }}
                            </p>
                        </div>
                    </div>
                    <x-mary-button wire:click="retryProcessing"
                        wire:confirm="{{ __('ledger.file_inspector.messages.retry_confirm') }}"
                        class="btn-xs btn-outline btn-warning" :disabled="!$this->userPermissions['retry']">
                        {{ __('ledger.file_inspector.actions.execute') }}
                    </x-mary-button>
                </div>
            </div>

            {{-- VLM再処理 (管理者) --}}
            @if ($this->userPermissions['is_admin'])
                <div class="card bg-base-200 border border-base-300">
                    <div class="p-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-error/10 flex items-center justify-center">
                                <i class="fa-solid fa-robot text-error"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold">
                                    {{ __('ledger.file_inspector.actions.vlm_retry') }}
                                </p>
                                <p class="text-[10px] opacity-60">
                                    {{ __('ledger.file_inspector.actions.vlm_retry_description') }}
                                </p>
                            </div>
                        </div>
                        <x-mary-button wire:click="retryVlmProcessing"
                            wire:confirm="{{ __('ledger.file_inspector.messages.vlm_retry_confirm') }}"
                            class="btn-xs btn-outline btn-error" :disabled="!$this->userPermissions['admin_retry']">
                            {{ __('ledger.file_inspector.actions.execute') }}
                        </x-mary-button>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- 3. 注意事項 --}}
    <div class="alert alert-ghost border border-base-300 bg-base-200/50 p-3 mt-4">
        <i class="fa-solid fa-circle-info text-info"></i>
        <div class="text-[10px] opacity-70">
            {{ __('ledger.file_inspector.permissions.delete_notice') }}
        </div>
    </div>
</div>
