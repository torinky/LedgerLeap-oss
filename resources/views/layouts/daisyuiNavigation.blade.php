<nav x-data="{ open: false }" class="row">
    <div class="navbar bg-base-100 shadow-sm"> {{-- 背景色と影 --}}
        <div class="navbar-start">
            {{-- ハンバーガーメニュー (lg未満で表示) --}}
            <div class="dropdown">
                <label tabindex="0" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h8m-8 6h16"/>
                    </svg>
                </label>
                {{-- ドロップダウンメニューの内容 --}}
                <ul tabindex="0"
                    class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                    {{-- マイポータル (アプリ名/ロゴで代用するため、ここでは不要かも) --}}
                    {{-- <li><x-daisyui-nav-link :href="route('my-portal')" :active="request()->routeIs('my-portal')"><i class="fas fa-home w-4 mr-2"></i>{{ __('ledger.navigation.my_portal') }}</x-daisyui-nav-link></li> --}}
                    <li>
                        <x-daisyui-nav-link :href="route('ledger.index')" :active="request()->routeIs('ledger.index')">
                            <i class="fas fa-book-open-reader w-4 mr-2"></i>{{ __('ledger.navigation.ledgers') }}
                        </x-daisyui-nav-link>
                    </li>
                    {{-- 他にメニューがあればここに追加 --}}
                </ul>
            </div>

            {{-- アプリロゴ/名称 (マイポータルへのリンク) --}}
            <a href="{{ route('my-portal') }}"
               {{--               class="btn btn-ghost normal-case text-xl tooltip tooltip-bottom"--}}
               data-tip="{{ __('ledger.navigation.go_to_my_portal') }}"
                    @class(['btn btn-ghost tooltip tooltip-bottom text-xl', 'btn-active' => request()->routeIs('my-portal')]) {{-- アクティブ状態をクラスで表現 --}}
            >
                {{ config('app.name', 'Laravel') }}
            </a>

            {{-- 主要メニュー (lg以上でアイコンのみ表示) --}}
            <div class="hidden lg:flex items-center ml-4 space-x-1"> {{-- space-x を調整 --}}
                {{-- 台帳リンク (アイコン + ツールチップ) --}}
                <a href="{{ route('ledger.index') }}"
                   @class(['btn btn-ghost btn-square tooltip tooltip-bottom', 'btn-active' => request()->routeIs('ledger.index')]) {{-- アクティブ状態をクラスで表現 --}}
                   data-tip="{{ __('ledger.navigation.ledgers') }}"
                >
                    <i class="fas fa-book-open-reader"></i>
                </a>
                {{-- 他にアイコンメニューがあればここに追加 --}}
            </div>
        </div>

        <div class="navbar-end space-x-1">
            {{-- フォルダツリー表示ボタン (xl未満で表示, Drawerレイアウト用) --}}
            @if($showDrawerButton ?? false)
                <label for="app-drawer" class="btn btn-ghost xl:hidden btn-sm btn-square tooltip tooltip-bottom"
                       data-tip="{{ __('ledger.navigation.open_folder_tree') }}">
                    <i class="fa-solid fa-folder-tree"></i>
                </label>
            @endif

            {{-- 通知アイコン --}}
            <div class="dropdown dropdown-end">
                <livewire:notifications.icon/>
            </div>
            {{-- 設定画面 (Filament) へのリンク (UserService で判定) --}}
            @inject('userService', 'App\Services\UserService') {{-- UserService を注入 --}}
            @if($userService->canUserAccessSettings(Auth::user()))
                <a href="{{ route('filament.admin.pages.dashboard') }}"
                   class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                   data-tip="{{ __('ledger.navigation.settings') }}">
                    <i class="fas fa-sliders"></i>
                </a>
            @endif

            {{-- ユーザー名メニュー --}}
            <div class="dropdown dropdown-end">
                <label tabindex="2" class="btn btn-ghost btn-sm flex items-center">
                    <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                </label>
                <ul tabindex="2"
                    class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <x-daisyui-nav-link :href="route('profile.edit')"
                                            :active="request()->routeIs('profile.edit')"> {{-- active 状態を追加 --}}
                            <i class="fas fa-user-edit w-4 mr-2"></i> {{ __('ledger.navigation.profile') }}
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <x-daisyui-nav-link :href="route('notifications.settings')"
                                            :active="request()->routeIs('notifications.settings')"> {{-- active 状態を追加 --}}
                            <i class="fas fa-bell w-4 mr-2"></i> {{ __('ledger.navigation.notification_settings') }}
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
                </ul>
            </div>

            {{-- テーマ切り替え --}}
            <div>
                <label class="swap swap-rotate btn btn-sm btn-ghost btn-square tooltip tooltip-bottom"
                       data-tip="{{ __('ledger.navigation.toggle_theme') }}"> {{-- ツールチップ追加 --}}
                    <input id="theme-toggle" type="checkbox" style="display: none"/>
                    <i class="swap-on fas fa-sun"></i>
                    <i class="swap-off fas fa-moon"></i>
                </label>
            </div>
        </div>
    </div>
</nav>
