<nav x-data="{ open: false }" class="row">
    <div class="navbar bg-base-100 shadow-sm"> {{-- 背景色と影 --}}
        <div class="navbar-start">
            {{-- ハンバーガーメニュー (lg未満で表示) --}}
            <div class="dropdown">
                <label tabindex="0" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h8m-8 6h16" />
                    </svg>
                </label>
                {{-- ドロップダウンメニューの内容 --}}
                <ul tabindex="0"
                    class="menu menu-sm dropdown-content mt-3 z-[30] p-2 shadow bg-base-100 rounded-box w-52">
                    @if (tenant())
                        <li>
                            <x-daisyui-nav-link :href="route('ledger.index', ['tenant' => tenant()->id])" :active="request()->routeIs('ledger.index')">
                                <i class="fas fa-book-open-reader w-4 mr-2"></i>{{ __('ledger.navigation.ledgers') }}
                            </x-daisyui-nav-link>
                        </li>
                    @endif
                    {{-- 他にメニューがあればここに追加 --}}
                </ul>
            </div>

            {{-- アプリロゴ/名称 (マイポータルへのリンク) --}}
            <a href="{{ tenant() ? route('my-portal', ['tenant' => tenant()->id]) : route('global.my-portal') }}"
                data-tip="{{ __('ledger.navigation.go_to_my_portal') }}" @class([
                    'btn btn-ghost tooltip tooltip-bottom flex items-center gap-2',
                    'btn-active' =>
                        request()->routeIs('my-portal') ||
                        request()->routeIs('global.my-portal'),
                ])>
                @php
                    $logo      = config('ledgerleap.branding.logo');
                    $appName   = config('ledgerleap.branding.app_name', config('app.name', 'LedgerLeap'));
                    $shortName = config('ledgerleap.branding.short_name') ?: mb_substr($appName, 0, 2);
                @endphp

                @if ($logo)
                    <img src="{{ asset($logo) }}"
                         alt="{{ $appName }}"
                         style="height: {{ config('ledgerleap.branding.logo_height', '1.75rem') }}"
                         class="object-contain">
                    <span class="hidden sm:inline text-xl font-semibold">{{ $appName }}</span>
                @else
                    <span class="hidden sm:inline text-xl font-semibold">{{ $appName }}</span>
                    <span class="sm:hidden text-xl font-semibold" aria-hidden="true">{{ $shortName }}</span>
                @endif
            </a>

            {{-- 主要メニュー (lg以上でアイコンのみ表示) --}}
            <div class="hidden lg:flex items-center ml-4 space-x-1"> {{-- space-x を調整 --}}
                @if (tenant())
                    {{-- 台帳リンク (アイコン + ツールチップ) --}}
                    <a href="{{ route('ledger.index', ['tenant' => tenant()->id]) }}" @class([
                        'btn btn-ghost btn-square tooltip tooltip-bottom',
                        'btn-active' => request()->routeIs('ledger.index'),
                    ])
                        {{-- アクティブ状態をクラスで表現 --}} data-tip="{{ __('ledger.navigation.ledgers') }}">
                        <i class="fas fa-book-open-reader"></i>
                    </a>
                @endif
                {{-- 他にアイコンメニューがあればここに追加 --}}
            </div>
        </div>

        <div class="navbar-end space-x-1">
            {{-- フォルダツリー表示ボタン (xl未満で表示, Drawerレイアウト用) --}}
            @if ($showDrawerButton ?? false)
                <label for="app-drawer" class="btn btn-ghost xl:hidden btn-sm btn-square tooltip tooltip-bottom"
                    data-tip="{{ __('ledger.navigation.open_folder_tree') }}">
                    <i class="fa-solid fa-folder-tree"></i>
                </label>
            @endif

            @livewire('tenant-switcher')

            {{-- 通知アイコン --}}
            <div class="dropdown dropdown-end">
                <livewire:notifications.icon />
            </div>
            {{-- 設定ドロップダウンメニュー --}}
            @inject('userService', 'App\Services\UserService') {{-- UserService を注入 --}}
            @if ($userService->canUserAccessSettings(Auth::user()))
                <x-mary-dropdown>
                    <x-slot:trigger>
                        <x-mary-button icon="o-cog-6-tooth" class="btn-ghost btn-sm btn-circle tooltip tooltip-bottom"
                            data-tip="{{ __('ledger.navigation.settings') }}" />
                    </x-slot:trigger>

                    @if (tenant())
                        <li class="menu-title"><span>{{ __('ledger.tenant_settings') }}</span></li>
                        <x-mary-menu-item :title="__('ledger.ledger_define')" icon="o-document-plus" :link="route('ledgerDefine.index', ['tenant' => tenant()->id])" />
                        <x-mary-menu-item :title="__('ledger.folder.title')" icon="o-folder" :link="route('filament.admin.resources.folders.index', ['tenant' => tenant()->id])" />
                        <hr class="my-1" />
                        <x-mary-menu-item :title="__('ledger.central_settings')" icon="o-arrow-top-right-on-square" :link="route('filament.admin.pages.dashboard', ['tenant' => tenant()->id])" />
                    @elseif(request()->routeIs('filament.admin.*'))
                        <li class="menu-title"><span>{{ __('ledger.central_settings') }}</span></li>
                        <x-mary-menu-item :title="__('ledger.settings.tenant_management')" icon="o-building-office-2" :link="\App\Filament\Resources\TenantResource::getUrl('index')" />
                        <x-mary-menu-item :title="__('ledger.settings.user_management')" icon="o-users" :link="\App\Filament\Resources\UserResource::getUrl('index')" />
                        <x-mary-menu-item :title="__('ledger.settings.role_permission_management')" icon="o-shield-check" :link="\App\Filament\Resources\RoleResource::getUrl('index')" />
                        <x-mary-menu-item :title="__('ledger.settings.auto_link_management')" icon="o-link" :link="\App\Filament\Resources\AutoLinkResource::getUrl('index')" />
                    @endif
                </x-mary-dropdown>
            @endif

            {{-- ユーザー名メニュー --}}
            <div class="dropdown dropdown-end">
                <label tabindex="2" class="btn btn-ghost btn-sm flex items-center">
                    <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                </label>
                <ul tabindex="2"
                    class="menu menu-sm dropdown-content mt-3 z-[30] p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <x-daisyui-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')"> {{-- active 状態を追加 --}}
                            <i class="fas fa-user-edit w-4 mr-2"></i> {{ __('ledger.navigation.profile') }}
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <x-daisyui-nav-link :href="route('notifications.settings')" :active="request()->routeIs('notifications.settings')"> {{-- active 状態を追加 --}}
                            <i class="fas fa-bell w-4 mr-2"></i>
                            {{ __('ledger.navigation.notification_settings') }}
                        </x-daisyui-nav-link>
                    </li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <li>
                            <x-daisyui-nav-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                <i class="fas fa-sign-out-alt w-4 mr-2"></i> {{ __('ledger.navigation.logout') }}
                            </x-daisyui-nav-link>
                        </li>
                    </form>

                    <li class="menu-title mt-1">
                        <a href="https://github.com/torinky/LedgerLeap-oss/blob/main/CHANGELOG.md"
                           target="_blank" rel="noopener noreferrer"
                           class="text-xs text-base-content/30 link link-hover">
                            {{ config('ledgerleap.version', '0.0.0') }}
                        </a>
                    </li>
                </ul>
            </div>

            @livewire('common.page-qr-code')

            {{-- テーマ切り替え --}}
            <label class="swap swap-rotate btn btn-ghost btn-sm btn-circle" x-data="{
                isDark: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                init() {
                    // 初期化時に現在のテーマを確認して、DOMを更新
                    const theme = localStorage.getItem('theme');
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    this.isDark = theme === 'dark' || (!theme && prefersDark);
            
                    // チェックボックスの状態を設定
                    this.$refs.themeToggle.checked = !this.isDark;
                }
            }">
                <input type="checkbox" x-ref="themeToggle"
                    @change.prevent="
                            isDark = !isDark;
                            const newFilamentTheme = isDark ? 'dark' : 'light';
                            const newDaisyUiTheme = isDark ? '{{ config('daisyui.themes.dark') }}' : '{{ config('daisyui.themes.light') }}';

                            // Filamentのテーマを更新
                            localStorage.setItem('theme', newFilamentTheme);
                            if (isDark) {
                                document.documentElement.classList.add('dark');
                            } else {
                                document.documentElement.classList.remove('dark');
                            }

                            // DaisyUIのテーマを更新
                            document.documentElement.setAttribute('data-theme', newDaisyUiTheme);
                       "
                    :checked="!isDark" />
                <i class="swap-on fas fa-sun"></i>
                <i class="swap-off fas fa-moon"></i>
            </label>
        </div>
    </div>
</nav>
