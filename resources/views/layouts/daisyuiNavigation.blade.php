<nav x-data="{ open: false }" class="row">
    <div class="navbar bg-base-100">
        <div class="navbar-start">
            <div class="dropdown">
                <label tabindex="0" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h8m-8 6h16"/>
                    </svg>
                </label>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <x-daisyui-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <x-daisyui-nav-link :href="route('ledger.index')" :active="request()->routeIs('ledger')">
                            {{ __('Document Cabinet') }}
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <x-daisyui-nav-link :href="route('ledgerDefine.index')"
                                            :active="request()->routeIs('ledgerDefine.index')">
                            {{ __('Ledger Setting') }}
                        </x-daisyui-nav-link>
                    </li>
                    {{--
                                        <li>
                                            <a>Parent</a>
                                            <ul class="p-2">
                                                <li><a>Submenu 1</a></li>
                                                <li><a>Submenu 2</a></li>
                                            </ul>
                                        </li>
                                        <li><a>Item 3</a></li>
                    --}}
                </ul>
            </div>
            <a class="btn btn-ghost normal-case text-xl">LedgerLeap</a>
        </div>
        <div class="navbar-center hidden lg:flex">
            <ul class="menu menu-horizontal px-1">
                <li>
                    <x-daisyui-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-daisyui-nav-link>
                </li>
                <li>
                    <x-daisyui-nav-link :href="route('ledger.index')" :active="request()->routeIs('ledger')">
                        {{ __('Ledger') }}
                    </x-daisyui-nav-link>
                </li>
                <li>
                    <x-daisyui-nav-link :href="route('ledgerDefine.index')"
                                        :active="request()->routeIs('ledgerDefine.index')">
                        {{ __('Setting') }}
                    </x-daisyui-nav-link>
                </li>
                {{--
                                <li tabindex="0">
                                    <details>
                                        <summary>Parent</summary>
                                        <ul class="p-2">
                                            <li><a>Submenu 1</a></li>
                                            <li><a>Submenu 2</a></li>
                                            <li><a>Submenu 2</a></li>
                                            <li><a>Submenu 2</a></li>
                                        </ul>
                                    </details>
                                </li>
                                <li><a>Item 3</a></li>
                --}}
            </ul>
        </div>
        <div class="navbar-end">
            {{--            <a class="btn">Button</a>--}}
            <div class="dropdown dropdown-end">
                {{--                <label tabindex="0" class="btn btn-ghost btn-circle avatar">--}}
                <a tabindex="0" class="btn btn-ghost ">
                    {{--
                                        <div class="w-10 rounded-full">
                                            <img src="/images/stock/photo-1534528741775-53994a69daeb.jpg" />
                                        </div>
                    --}}
                    {{ Auth::user()->name }}
                </a>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <x-daisyui-nav-link :href="route('profile.edit')" class="justify-between">
                            {{ __('Profile') }}
                            <span class="badge">New</span>
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-daisyui-nav-link :href="route('logout')"
                                                onclick="event.preventDefault();
                                        this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-daisyui-nav-link>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

</nav>
