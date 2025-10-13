<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $attributes->get('title') ?? 'page'}} | {{ config('app.name', 'Laravel') }}</title>

    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC.
        // This script reads the theme from localStorage and applies it.
        // It also respects the OS preference for dark mode on the first visit.
        (function() {
            const colorTheme = localStorage.getItem('color-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (colorTheme === 'dark' || (!colorTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'coffee');
            } else {
                document.documentElement.setAttribute('data-theme', 'nord');
            }
        })();
    </script>
    <!-- Fonts -->
    {{--        <link rel="preconnect" href="https://fonts.bunny.net">--}}
    {{--        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />--}}

    <!-- Scripts -->
    @vite(['resources/sass/app.scss'])
    @stack('stylesheets')


</head>
<body class="font-sans antialiased {{$attributes->get('class') ?? 'bg-base-200'}}">
<x-mary-toast/>
<div class="min-h-screen">
    @include('layouts.daisyuiNavigation')

    <!-- Page Heading -->
    @if (isset($header))
        <header class=" {{$header->attributes->get('class')}}">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endif

    <!-- Page Content -->
    <main>
        {{ $slot }}
    </main>
</div>
<fooer>
    {{$footer ?? ''}}
</fooer>
@vite(['resources/js/app.js'])
@stack('scripts')
</body>
</html>
