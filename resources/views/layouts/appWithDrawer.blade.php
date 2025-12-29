<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $attributes->get('title') ?? 'page' }} | {{ config('app.name', 'Laravel') }}</title>

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
    {{--    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap"> --}}

    <!-- Styles  Scripts -->
    @vite(['resources/sass/app.scss'])
    @stack('stylesheets')

</head>

<body class="font-sans antialiased {{ $attributes->get('class') }}">
    <x-mary-toast />

    <div class="fixed w-full z-10 top-0">
        @include('layouts.daisyuiNavigation', ['showDrawerButton' => true])

        @if (isset($header))
            <!-- Page Heading -->
            <header class=" {{ $header->attributes->get('class') }}">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif
    </div>


    {{--        <div class="drawer drawer-mobile"> --}}
    <div class="drawer pt-20 xl:drawer-open">

        <input id="app-drawer" type="checkbox" class="drawer-toggle" />

        <main class="drawer-content h-screen">
            {{ $slot }}
        </main>
        <div class="drawer-side z-10 h-screen">
            <label for="app-drawer" class="drawer-overlay w-full"></label>
            <ul class="menu">
                {{ $drawer ?? '' }}
            </ul>
        </div>
    </div>

    {{-- グローバルモーダル --}}
    @livewire('attached-file.text-preview-modal')

    @vite(['resources/js/app.js'])
    @stack('scripts')
    <script>
        // 別タブからの更新指令を監視し、一覧リストを更新する
        window.addEventListener('storage', function(e) {
            if (e.key === 'ledger_list_needs_refresh') {
                window.Livewire.dispatch('ledgerStored');
            }
        });
    </script>
</body>

</html>
