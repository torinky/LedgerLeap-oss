<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    {{--    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">--}}

    <!-- Styles -->
    @vite(['resources/sass/app.scss','resources/js/app.js'])
    {{--    <link rel="stylesheet" href="{{ asset('css/app.css') }}">--}}
    @stack('stylesheets')
    @livewireStyles

    <!-- Scripts -->
    {{--    <script src="{{ asset('js/app.js') }}" defer></script>--}}

</head>
<body class="font-body antialiased">
<div class="min-h-screen">

    <div class="fixed w-full z-10 top-0">
        @include('layouts.daisyuiNavigation')

        @if (isset($header))
            <!-- Page Heading -->
            <header class=" shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif
    </div>


    {{--        <div class="drawer drawer-mobile">--}}
    <div class="drawer mt-20 xl:drawer-open">

        <input id="app-drawer" type="checkbox" class="drawer-toggle"/>

        <main class="drawer-content flex flex-col items-center pb-36">
            {{--                        <label for="my-drawer-2" class="btn btn-primary drawer-button lg:hidden">Open drawer</label>--}}
            {{--            <label for="my-drawer-2" class="btn btn-primary drawer-button xl:hidden">Open drawer</label>--}}
            {{ $slot }}
        </main>
        <div class="drawer-side">
            <label for="my-drawer-2" class="drawer-overlay"></label>
            <ul class="menu p-4 sm:w-2/3 md:w-1/2 lg:w-1/3 bg-base-100 text-base-content">
                <!-- Sidebar content here -->
                {{ $drawer ??''}}

            </ul>
        </div>
    </div>

</div>
</body>
@livewireScripts
@stack('scripts')
</html>
