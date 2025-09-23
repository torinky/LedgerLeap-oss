<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $attributes->get('title') ?? 'page'}} | {{ config('app.name', 'Laravel')}}</title>

    <!-- Fonts -->
    {{--    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">--}}

    <!-- Styles  Scripts -->
    @livewireStyles
    @vite(['resources/sass/app.scss','resources/js/app.js'])
    @stack('stylesheets')

</head>
<body class="font-sans antialiased {{$attributes->get('class')}}">
    <x-mary-toast />

    <div class="fixed w-full z-10 top-0">
        @include('layouts.daisyuiNavigation', ['showDrawerButton' => true])

        @if (isset($header))
            <!-- Page Heading -->
            <header class=" {{$header->attributes->get('class')}}">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif
    </div>


    {{--        <div class="drawer drawer-mobile">--}}
    <div class="drawer pt-20 xl:drawer-open">

        <input id="app-drawer" type="checkbox" class="drawer-toggle"/>

        <main class="drawer-content h-screen">
            {{ $slot }}
        </main>
        <div class="drawer-side z-10 h-screen">
            <label for="app-drawer" class="drawer-overlay w-full"></label>
            <ul class="menu">
                {{ $drawer ??''}}
            </ul>
        </div>
    </div>
    @vite(['resources/js/app.js'])
    @stack('scripts')
    @livewireScripts
<script>
    // 別タブからの更新指令を監視し、一覧リストを更新する
    window.addEventListener('storage', function (e) {
        if (e.key === 'ledger_list_needs_refresh') {
            window.Livewire.dispatch('ledgerStored');
        }
    });
</script>
</body>
</html>
