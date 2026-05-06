<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $attributes->get('title') ?? 'page' }} | {{ config('app.name', 'Laravel') }}</title>

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
                document.documentElement.setAttribute("data-theme", "{{ config('daisyui.themes.dark') }}");
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute("data-theme", "{{ config('daisyui.themes.light') }}");
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

    {{-- Tier 0: Global Progress Bar (Livewire通信中のみ表示) --}}
    <x-mary-loading wire:loading.delay class="text-primary fixed top-0 w-full h-1 z-110" />

    <div x-data>
        <template x-teleport="body">
            <x-mary-toast />
        </template>
    </div>

    @php($adminAnnouncements = app(\App\Services\AdminAnnouncementService::class)->notificationCenterAnnouncements())
    @if (! empty($adminAnnouncements) && ! request()->routeIs('notifications.index', 'ledger.index', 'ledgersByFolderId', 'ledgersByDefineId', 'ledgerDefine.index', 'ledgerDefinesByFolderId'))
        <x-admin.announcement-stack :announcements="$adminAnnouncements" />
    @endif

    <div class="fixed top-0 w-full z-30">
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
    <div class="drawer xl:drawer-open" style="padding-top: 5rem;">

        <input id="app-drawer" type="checkbox" class="drawer-toggle" />

        {{--
            サイドバー幅をレスポンシブに変化させる。
            xl: 256px (w-64) / 2xl: 288px (w-72) / 3xl+: 320px (w-80)
            コンテンツ領域 (pl) も同じ幅を確保する。
        --}}
        <main class="drawer-content h-screen xl:pl-64 2xl:pl-72">
            {{ $slot }}
        </main>
        {{--
            DaisyUI の drawer-side は xl:drawer-open 時に position:sticky で描画されるが、
            親要素(.drawer)がスクロールコンテナでないためページスクロールと一緒に流れてしまう。
            position:fixed をインラインスタイルで強制することでビューポートに完全固定する。
            top: ナビバー高さ(64px)分下げ、height: calc(100vh-64px) でビューポートに収める。
            overflow-y:auto でツリーが独立スクロールできるようにする。
            サイドバー幅は xl:w-64 / 2xl:w-72 でレスポンシブに変化。
        --}}
        <div class="drawer-side z-40 fixed top-16 h-[calc(100vh-4rem)] w-80 md:w-80 xl:w-64 2xl:w-72 overflow-y-auto overflow-x-clip">
            <label for="app-drawer" class="drawer-overlay w-full" aria-label="Close sidebar"></label>
            {{--
                overflow-x は hidden から clip に変更。
                clip は overflow-y: auto との組み合わせで縦スクロールを妨げない。
                また、子の .tree-scroll-container (overflow-x: auto) はスクロールコンテナとして
                独立しているため、clip によってクリップされず横スクロールが正常に機能する。
                hidden は子のスクロールコンテナもブロックするが、clip はそれを許容する。
            --}}
            <ul class="menu overflow-y-auto h-full w-80 md:w-80 xl:w-64 2xl:w-72 p-2 bg-base-100 text-base-content overflow-x-clip">
                {{ $drawer ?? '' }}
            </ul>
        </div>
    </div>

    {{-- グローバルモーダル --}}
    @livewire('attached-file.text-preview-modal')

    @vite(['resources/js/app.js'])
    <script>

        // テーマを適用する関数
        function applyTheme() {
            const theme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = theme === 'dark' || (!theme && prefersDark);

            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute("data-theme", "{{ config('daisyui.themes.dark') }}");
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute("data-theme", "{{ config('daisyui.themes.light') }}");
            }
        }

        // Livewireのページ遷移後にテーマを再適用
        document.addEventListener('livewire:navigated', applyTheme);

        // 別タブからの更新指令を監視し、一覧リストを更新する
        window.addEventListener('storage', function(e) {
            if (e.key === 'ledger_list_needs_refresh') {
                window.Livewire.dispatch('ledgerStored');
            }
        });
    </script>
    @stack('scripts')
</body>

</html>
