<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? ($attributes->get('title') ?? 'page') }} | {{ config('ledgerleap.branding.app_name', config('app.name', 'LedgerLeap')) }}</title>

    <link rel="icon" href="{{ asset(config('ledgerleap.branding.favicon', 'favicon.ico')) }}">

    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC.
        (function() {
            // 'theme' キーでローカルストレージからテーマを取得
            const theme = localStorage.getItem('theme');
            // OSのダークモード設定をフォールバックとして使用
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            // ローカルストレージに設定があればそれを使い、なければOSの設定に従う
            const isDark = theme === 'dark' || (!theme && prefersDark);

            if (isDark) {
                // FilamentとTailwind用の 'dark' クラスを適用
                document.documentElement.classList.add('dark');
                // DaisyUI用のテーマ属性を設定
                document.documentElement.setAttribute('data-theme', '{{ config('daisyui.themes.dark') }}');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', '{{ config('daisyui.themes.light') }}');
            }
        })();
    </script>
    <!-- Fonts -->
    {{--        <link rel="preconnect" href="https://fonts.bunny.net"> --}}
    {{--        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /> --}}

    <!-- Scripts -->
    @vite(['resources/sass/app.scss'])
    @stack('stylesheets')


</head>

<body class="font-sans antialiased {{ $attributes->get('class') ?? 'bg-base-200' }} pb-20">

    {{-- Tier 0: Global Progress Bar (Livewire通信中のみ表示) --}}
    <x-mary-loading wire:loading.delay class="text-primary fixed top-0 w-full h-1 z-110" />

    <div x-data>
        <template x-teleport="body">
            <x-mary-toast />
        </template>
    </div>

    @php($adminAnnouncements = app(\App\Services\AdminAnnouncementService::class)->notificationCenterAnnouncements())
    @if (! empty($adminAnnouncements) && ! request()->routeIs('notifications.index'))
        <x-admin.announcement-stack :announcements="$adminAnnouncements" />
    @endif

    <div class="min-h-screen">
        @include('layouts.daisyuiNavigation')

        <!-- Page Heading -->
        @if (isset($header))
            <header class=" {{ $header->attributes->get('class') }}">
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
    @include('partials.app-footer')
    @livewireScriptConfig
    @vite(['resources/js/app.js'])
    @stack('scripts')
    <script>
        // テーマを適用する関数
        function applyTheme() {
            const theme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = theme === 'dark' || (!theme && prefersDark);

            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', '{{ config('daisyui.themes.dark') }}');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', '{{ config('daisyui.themes.light') }}');
            }
        }

        // Livewireのページ遷移後にテーマを再適用
        document.addEventListener('livewire:navigated', applyTheme);
    </script>
    @livewire('attached-file.text-preview-modal')
</body>

</html>
