<nav x-data="{ open: false }" class="row">
    <div class="navbar bg-primary/20">
        <div class="navbar-start">
            <div class="dropdown">
                <label tabindex="0" class="btn btn-ghost lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h8m-8 6h16"/>
                    </svg>
                </label>
            </div>
            <div>
                <label for="app-drawer" class="btn btn-ghost xl:hidden btn-square tooltip tooltip-bottom pt-4"
                       data-tip="{{__('ledger.open_tree_view')}}">
                    <i class="fa-solid fa-folder-tree"></i>
                </label>

            </div>
            <x-daisyui-nav-link :href="route('ledger.index')" :active="request()->routeIs('ledger')"
                                class="btn btn-ghost normal-case text-xl tooltip tooltip-bottom pt-2"
                                data-tip="{{__('ledger.reset_search')}}"
            >
                <i class="fas fa-book-open-reader mr-2"></i> {{ config('app.name', 'Laravel') }}
            </x-daisyui-nav-link>
        </div>

        <div class="navbar-end">
            <livewire:notifications.icon/>
            {{--            <a class="btn">Button</a>--}}
            <a tabindex="1" class="btn btn-ghost btn-sm tooltip tooltip-bottom pt-2" data-tip="{{__('ledger.setting')}}"
               href="{{ route('filament.admin.pages.dashboard') }}">
                <i class="fas fa-sliders"></i>
            </a>
            <div class="dropdown dropdown-end">
                {{--                <label tabindex="0" class="btn btn-ghost btn-circle avatar">--}}
                <a tabindex="2" class="btn btn-ghost btn-sm">
                    {{--
                                        <div class="w-10 rounded-full">
                                            <img src="/images/stock/photo-1534528741775-53994a69daeb.jpg" />
                                        </div>
                    --}}
                    {{ Auth::user()->name }}
                </a>
                <ul tabindex="3" class="menu menu-sm dropdown-content mt-3 p-2 shadow bg-base-100 rounded-box w-52">
                    <li>
                        <x-daisyui-nav-link :href="route('profile.edit')" class="justify-between">
                            {{ __('Profile') }}
                            {{--                            <span class="badge">New</span>--}}
                        </x-daisyui-nav-link>
                    </li>
                    <li>
                        <x-daisyui-nav-link :href="route('notifications.settings')">
                            {{ __('Notification Settings') }}
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
            <div tabindex="4">
                <label class="swap swap-rotate btn btn-sm btn-ghost">

                    <!-- this hidden checkbox controls the state -->
                    <input id="theme-toggle" type="checkbox" style="display: none"/>

                    <!-- sun icon -->
                    <i class="swap-on fas fa-sun"></i>
                    {{--                    <svg class="swap-on fill-current w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>--}}

                    <!-- moon icon -->
                    <i class="swap-off fas fa-moon"></i>
                    {{--                    <svg class="swap-off fill-current w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>--}}

                </label>

            </div>
        </div>
    </div>

</nav>
