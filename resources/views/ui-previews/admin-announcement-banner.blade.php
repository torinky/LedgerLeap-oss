@php
    $level = request('level', 'info');
    $level = in_array($level, ['info', 'warning', 'critical'], true) ? $level : 'info';

    $presets = [
        'info' => [
            'title' => '運用告知',
            'body' => '翌日午前に軽微な保守作業を予定しています。作業中も通常利用は継続できます。',
            'level' => 'info',
            'published_at' => '2026/04/28 09:00',
            'links' => [
                ['label' => '詳細を見る', 'url' => '#preview-notes'],
            ],
        ],
        'warning' => [
            'title' => '期限接近のお知らせ',
            'body' => '一部の申請は本日18:00で締切になります。必要な対応があれば早めに確認してください。',
            'level' => 'warning',
            'published_at' => '2026/04/28 09:00',
            'links' => [
                ['label' => '確認手順', 'url' => '#preview-notes'],
            ],
        ],
        'critical' => [
            'title' => '障害連絡',
            'body' => '現在、一部の画面で応答が不安定です。復旧まで操作をお待ちください。',
            'level' => 'critical',
            'published_at' => '2026/04/28 09:00',
            'links' => [
                ['label' => '状況メモ', 'url' => '#preview-notes'],
            ],
        ],
    ];

    $announcement = $presets[$level];
    $announcement['dismiss_storage_key'] = 'ledgerleap.preview.admin-announcement-banner.' . $level;
@endphp

<!DOCTYPE html>
<html lang="ja" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Announcement Banner Preview</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/admin-announcement-banner-preview.js'])
</head>
<body class="min-h-screen bg-base-200 text-base-content">
    <div class="relative isolate min-h-screen overflow-hidden bg-base-200">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(32,193,162,0.18),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(15,118,110,0.16),_transparent_24%),linear-gradient(180deg,_rgba(255,255,255,0.96),_rgba(244,248,250,0.92))]"></div>
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(to_right,rgba(15,23,42,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(15,23,42,0.05)_1px,transparent_1px)] bg-[size:34px_34px] opacity-30 motion-safe:animate-pulse"></div>

        <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <div class="rounded-[1.75rem] border border-base-300/70 bg-base-100/90 px-5 py-4 shadow-[0_18px_40px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-1.5">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-base-content/55">Preview</p>
                        <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Admin announcement banner</h1>
                        <p class="max-w-2xl text-sm leading-6 text-base-content/70">
                            DaisyUI と MaryUI の標準感に寄せた見え方を確認できます。level を切り替えると色・密度・閉じる操作を比較できます。
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ request()->fullUrlWithQuery(['level' => 'info']) }}" class="btn btn-sm {{ $level === 'info' ? 'btn-info' : 'btn-ghost' }}">Info</a>
                        <a href="{{ request()->fullUrlWithQuery(['level' => 'warning']) }}" class="btn btn-sm {{ $level === 'warning' ? 'btn-warning' : 'btn-ghost' }}">Warning</a>
                        <a href="{{ request()->fullUrlWithQuery(['level' => 'critical']) }}" class="btn btn-sm {{ $level === 'critical' ? 'btn-error' : 'btn-ghost' }}">Critical</a>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="window.resetAnnouncementPreview()">Dismiss reset</button>
                    </div>
                </div>
            </div>

            <x-admin.announcement-banner :announcement="$announcement" />

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                <section class="card card-border bg-base-100/90 shadow-sm backdrop-blur">
                    <div class="card-body gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p id="preview-notes" class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Notes</p>
                                <h2 class="card-title mt-1 text-lg">確認ポイント</h2>
                            </div>
                            <span class="badge badge-outline badge-sm">{{ $level }}</span>
                        </div>

                        <p class="text-sm leading-6 text-base-content/75">
                            banner が 2 行以内で収まり、ヘッダーを少し押し下げることを確認してください。発信日時が見えるため、いつの情報かも判別できます。
                        </p>

                        <div class="divider my-0"></div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-base-300 bg-base-200/60 p-4">
                                <p class="text-sm font-semibold">視認性</p>
                                <p class="mt-1 text-sm leading-6 text-base-content/70">背景のグラデーションと淡い動きで、バナーの存在が拾いやすくなるようにしています。</p>
                            </div>
                            <div class="rounded-2xl border border-base-300 bg-base-200/60 p-4">
                                <p class="text-sm font-semibold">操作性</p>
                                <p class="mt-1 text-sm leading-6 text-base-content/70">非 critical では icon-only の閉じるボタンを使い、横幅を節約します。</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-dashed border-base-300 bg-base-100/70 p-4">
                            <p class="text-sm font-semibold">サンプル本文</p>
                            <p class="mt-2 text-sm leading-7 text-base-content/70">ここは実際のコンテンツを模した領域です。banner の上下の余白や、ページ上部での見つかりやすさを確認するために置いてあります。</p>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <div class="card card-border bg-base-100/90 shadow-sm backdrop-blur">
                        <div class="card-body gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Current preset</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">Title</span>
                                    <span class="font-medium text-right">{{ $announcement['title'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">Level</span>
                                    <span class="font-medium capitalize">{{ $announcement['level'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">Published</span>
                                    <span class="font-mono text-xs">{{ $announcement['published_at'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">Dismiss key</span>
                                    <span class="break-all font-mono text-[11px] text-right">{{ $announcement['dismiss_storage_key'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-border bg-base-100/90 shadow-sm backdrop-blur">
                        <div class="card-body gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Interaction</p>
                            <ul class="space-y-2 text-sm leading-6 text-base-content/75">
                                <li>・Info は控えめな見た目</li>
                                <li>・Warning は注意を前提に強調</li>
                                <li>・Critical は sticky にして最上位表示</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        window.resetAnnouncementPreview = () => {
            const prefix = 'ledgerleap.preview.admin-announcement-banner.';

            Object.keys(localStorage)
                .filter((key) => key.startsWith(prefix))
                .forEach((key) => localStorage.removeItem(key));

            window.location.reload();
        };
    </script>
</body>
</html>
