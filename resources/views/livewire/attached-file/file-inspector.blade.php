<div
    x-data="{ open: @entangle('open') }"
    @keydown.escape.window="open = false; $wire.close()"
    @open-file-inspector.window="console.log('FileInspector received event:', $event.detail); $wire.openInspector($event.detail.id)"
>
    {{-- Overlay --}}
    <div x-show="open" class="fixed inset-0 bg-black/50 z-40" @click="open = false; $wire.close()"></div>

    {{-- Drawer --}}
    <div class="fixed right-0 top-0 h-full w-96 md:w-1/3 lg:w-1/4 bg-base-100 shadow-xl z-40 transform transition-transform flex flex-col"
         :class="open ? 'translate-x-0' : 'translate-x-full'"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title"
    >
        {{-- Header with context --}}
        <div class="p-4 flex flex-col border-b bg-base-200 shrink-0">
            <div class="flex items-center justify-between mb-2">
                <h2 id="drawer-title" class="text-base font-bold truncate flex-1 line-clamp-2" title="{{ $file?->original_filename ?? $file?->filename ?? 'ファイル' }}">
                    <i class="fa-solid fa-file mr-2"></i>
                    {{ $file?->original_filename ?? $file?->filename ?? 'ファイル' }}
                </h2>
                <button class="btn btn-square btn-ghost btn-xs ml-2" @click="open = false; $wire.close()" aria-label="閉じる">
                    <i class="fa-solid fa-xmark fa-lg"></i>
                </button>
            </div>

            {{-- コンテキスト情報（台帳名・フォルダパス）- 小さく --}}
            @if($file && ($file->mock_ledger_title ?? $file->ledger ?? null))
            <div class="text-xs text-base-content/70 space-y-1 mb-3">
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-folder text-warning text-xs"></i>
                    <span>{{ $file->mock_folder_path ?? $file->ledger?->folder?->title ?? '' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-book text-info text-xs"></i>
                    <a href="#" class="link link-hover">
                        {{ $file->mock_ledger_title ?? $file->ledger?->define?->title ?? '' }}
                    </a>
                </div>
            </div>
            @endif

            {{-- Quick actions --}}
            <div class="flex gap-2">
                <button class="btn btn-primary btn-sm flex-1" title="ダウンロード">
                    <i class="fa-solid fa-download"></i>
                    DL
                </button>
                <button class="btn btn-outline btn-sm" title="リンクをコピー">
                    <i class="fa-solid fa-link"></i>
                </button>
                <button class="btn btn-outline btn-sm" title="新しいタブで開く">
                    <i class="fa-solid fa-external-link-alt"></i>
                </button>
            </div>
        </div>

        {{-- Preview Area - 画像/PDFのプレビュー --}}
        @php
            $mime = $file?->original_mime_type ?? $file?->mime ?? '';
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';
            $showPreview = $isImage || $isPdf;
            // モックの場合はダミーURLを使用
            $previewUrl = $file && $file->id >= 1 && $file->id <= 8
                ? ($isImage ? 'https://via.placeholder.com/600x400/4CAF50/FFFFFF?text=' . urlencode($file->original_filename ?? 'Image')
                    : ($isPdf ? '#pdf-preview' : null))
                : null;
        @endphp

        @if($showPreview)
        <div class="border-b bg-base-200/50 shrink-0">
            @if($isImage)
                {{-- 画像プレビュー --}}
                <div class="relative aspect-video bg-base-300">
                    <img src="{{ $previewUrl }}"
                         alt="{{ $file?->original_filename ?? 'Preview' }}"
                         class="w-full h-full object-contain"
                         loading="lazy">
                    <div class="absolute top-2 right-2 flex gap-1">
                        <button class="btn btn-xs btn-ghost bg-base-100/80 hover:bg-base-100"
                                title="拡大表示"
                                @click="window.open('{{ $previewUrl }}', '_blank')">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </button>
                    </div>
                </div>
            @elseif($isPdf)
                {{-- PDFプレビュー --}}
                <div class="relative aspect-video bg-base-300 flex items-center justify-center">
                    @if($file && $file->id >= 1 && $file->id <= 8)
                        {{-- モック: PDFアイコン表示 --}}
                        <div class="text-center">
                            <i class="fa-solid fa-file-pdf fa-4x text-red-500 mb-3"></i>
                            <p class="text-sm text-base-content/70">PDFプレビュー</p>
                            <p class="text-xs text-base-content/50 mt-1">{{ number_format(($file->size ?? 0)/1024, 1) }} KB</p>
                            <button class="btn btn-sm btn-outline mt-3" @click="window.open('{{ $previewUrl }}', '_blank')">
                                <i class="fa-solid fa-external-link-alt"></i>
                                新しいタブで開く
                            </button>
                        </div>
                    @else
                        {{-- 実データ: iframe埋め込み --}}
                        <iframe src="{{ $previewUrl }}"
                                class="w-full h-full border-0"
                                title="PDF Preview">
                        </iframe>
                    @endif
                    <div class="absolute top-2 right-2">
                        <button class="btn btn-xs btn-ghost bg-base-100/80 hover:bg-base-100"
                                title="新しいタブで開く"
                                @click="window.open('{{ $previewUrl }}', '_blank')">
                            <i class="fa-solid fa-external-link-alt"></i>
                        </button>
                    </div>
                </div>
            @endif
        </div>
        @endif

        {{-- Tabs - ユーザーシナリオベース --}}
        <div class="flex-1 overflow-y-auto">
            <x-mary-tabs wire:model="selectedTab" class="tabs-boxed m-2">
                {{-- タブ1: 内容確認 (メイン) - ユーザーが最も頻繁に使う --}}
                <x-mary-tab name="content" label="内容" icon="o-document-text">
                    <div class="p-4 space-y-3">
                        @php
                            $hasPreviewText = $file && (
                                (method_exists($file, 'hasPreviewableText') && $file->hasPreviewableText()) ||
                                (!empty($file->mock_preview_text))
                            );
                            $previewText = $file->mock_preview_text ?? ($file->previewable_text ?? null);
                            $confidence = $file->mock_confidence ?? null;
                            $source = $file->mock_source ?? null;
                            $isProcessing = $file && ($file->mock_confidence === 0.0 && $file->mock_source === 'OCR');
                            $isError = $file && ($file->mock_source === null);
                        @endphp

                        @if($isProcessing)
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <div>
                                    <div class="font-bold text-sm">処理中</div>
                                    <div class="text-xs">OCR処理を実行しています...</div>
                                    <progress class="progress progress-warning w-full mt-2" value="65" max="100"></progress>
                                </div>
                            </div>
                        @elseif($isError)
                            <div class="alert alert-error">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <div>
                                    <div class="font-bold text-sm">処理エラー</div>
                                    <div class="text-xs">このファイル形式はテキスト抽出に対応していません。</div>
                                </div>
                            </div>
                        @elseif($hasPreviewText)
                            {{-- 抽出テキスト表示 --}}
                            @if($confidence !== null && $confidence > 0)
                            <div class="flex items-center justify-between p-2 bg-base-200 rounded text-xs">
                                <div class="flex items-center gap-2">
                                    @if($source === 'VLM')
                                        <span class="badge badge-success badge-xs">VLM</span>
                                    @elseif($source === 'OCR')
                                        <span class="badge badge-info badge-xs">OCR</span>
                                    @else
                                        <span class="badge badge-primary badge-xs">Tika</span>
                                    @endif
                                    <span>信頼度 {{ number_format($confidence * 100, 1) }}%</span>
                                </div>
                                @if($confidence >= 0.9)
                                    <i class="fa-solid fa-check-circle text-success"></i>
                                @elseif($confidence >= 0.7)
                                    <i class="fa-solid fa-shield-check text-info"></i>
                                @else
                                    <i class="fa-solid fa-exclamation-triangle text-warning"></i>
                                @endif
                            </div>
                            @endif

                            <textarea class="textarea textarea-bordered w-full h-64 text-xs font-mono" readonly>{{ $previewText }}</textarea>
                            <button class="btn btn-sm btn-outline w-full" x-data="{}" @click="navigator.clipboard.writeText($el.previousElementSibling.value)">
                                <i class="fa-solid fa-copy"></i>
                                コピー
                            </button>
                        @else
                            <div class="alert alert-info">
                                <i class="fa-solid fa-info-circle"></i>
                                <div class="text-xs">テキスト解析結果がありません</div>
                            </div>
                        @endif
                    </div>
                </x-mary-tab>

                {{-- タブ2: 詳細情報 - 必要な時だけ見る --}}
                <x-mary-tab name="details" label="詳細" icon="o-information-circle">
                    <div class="p-4 space-y-4">
                        {{-- ファイル情報 --}}
                        <div>
                            <h3 class="font-semibold text-xs mb-2 text-base-content/70">ファイル情報</h3>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">サイズ</span>
                                    <span>{{ number_format(($file?->size ?? 0)/1024, 1) }} KB</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">形式</span>
                                    <span class="font-mono text-xs">{{ $file?->original_mime_type ?? $file?->mime ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">アップロード</span>
                                    <span>{{ $file?->created_at?->format('Y/m/d H:i') ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">アップロード者</span>
                                    <span>{{ $file?->creator?->name ?? ($file && $file->id >= 1 && $file->id <= 8 ? '山田太郎' : 'N/A') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- 処理ステータス --}}
                        <div>
                            <h3 class="font-semibold text-xs mb-2 text-base-content/70">処理ステータス</h3>
                            <div class="space-y-1 text-xs">
                                @php
                                    $isProcessing = $file && ($file->mock_confidence === 0.0 && $file->mock_source === 'OCR');
                                    $isError = $file && ($file->mock_source === null);
                                @endphp
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">ステータス</span>
                                    @if($isProcessing)
                                        <span class="badge badge-warning badge-xs">処理中</span>
                                    @elseif($isError)
                                        <span class="badge badge-error badge-xs">エラー</span>
                                    @else
                                        <span class="badge badge-success badge-xs">完了</span>
                                    @endif
                                </div>
                                @if($file && $file->mock_source)
                                <div class="flex justify-between py-1">
                                    <span class="text-base-content/60">最終抽出</span>
                                    @if($file->mock_source === 'VLM')
                                        <span class="badge badge-success badge-xs">VLM</span>
                                    @elseif($file->mock_source === 'OCR')
                                        <span class="badge badge-info badge-xs">OCR</span>
                                    @else
                                        <span class="badge badge-primary badge-xs">Tika</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-mary-tab>

                {{-- タブ3: アクセス権限 - 管理者が気にする --}}
                <x-mary-tab name="access" label="権限" icon="o-shield-check">
                    <div class="p-4 space-y-4">
                        {{-- あなたの権限 --}}
                        <div class="p-2 bg-primary/10 rounded border border-primary/30">
                            <h3 class="font-semibold text-xs mb-2 text-primary">あなたの権限</h3>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-1">
                                    <i class="fa-solid fa-eye text-success text-xs"></i>
                                    <span>閲覧</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <i class="fa-solid fa-download text-success text-xs"></i>
                                    <span>DL</span>
                                </div>
                                <div class="flex items-center gap-1 text-base-content/50">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                    <span>編集</span>
                                </div>
                                <div class="flex items-center gap-1 text-base-content/50">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                    <span>削除</span>
                                </div>
                            </div>
                        </div>

                        {{-- 組織別権限 --}}
                        <div>
                            <h3 class="font-semibold text-xs mb-2 text-base-content/70">組織・ロール別設定</h3>
                            <div class="space-y-2 text-xs">
                                <div class="border rounded p-2 bg-base-200/50">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-1">
                                            <i class="fa-solid fa-building text-info text-xs"></i>
                                            <span class="font-medium">総務部</span>
                                        </div>
                                        <span class="badge badge-xs">組織</span>
                                    </div>
                                    <div class="flex gap-1">
                                        <span class="badge badge-success badge-xs">管理</span>
                                        <span class="badge badge-primary badge-xs">編集</span>
                                        <span class="badge badge-info badge-xs">閲覧</span>
                                    </div>
                                </div>

                                <div class="border rounded p-2 bg-base-200/50">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-1">
                                            <i class="fa-solid fa-user-tie text-warning text-xs"></i>
                                            <span class="font-medium">課長</span>
                                        </div>
                                        <span class="badge badge-xs">ロール</span>
                                    </div>
                                    <div class="flex gap-1">
                                        <span class="badge badge-primary badge-xs">編集</span>
                                        <span class="badge badge-info badge-xs">閲覧</span>
                                    </div>
                                </div>

                                <div class="border rounded p-2 bg-base-200/50">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-1">
                                            <i class="fa-solid fa-users text-success text-xs"></i>
                                            <span class="font-medium">一般社員</span>
                                        </div>
                                        <span class="badge badge-xs">ロール</span>
                                    </div>
                                    <div class="flex gap-1">
                                        <span class="badge badge-info badge-xs">閲覧のみ</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-mary-tab>

                {{-- タブ4: 処理履歴 - トラブル時に見る --}}
                <x-mary-tab name="history" label="履歴" icon="o-clock">
                    <div class="p-4 space-y-3">
                        <h3 class="font-semibold text-xs text-base-content/70">処理ログ</h3>
                        <ul class="steps steps-vertical text-xs">
                            <li class="step step-success">
                                <div class="text-left ml-2">
                                    <div class="font-semibold">VLM解析完了</div>
                                    <div class="text-base-content/60">2025-12-13 10:45</div>
                                    <div class="text-base-content/70">信頼度 92.5% | 3.2秒</div>
                                </div>
                            </li>
                            <li class="step step-success">
                                <div class="text-left ml-2">
                                    <div class="font-semibold">OCR処理完了</div>
                                    <div class="text-base-content/60">2025-12-13 10:45</div>
                                    <div class="text-base-content/70">2.8秒</div>
                                </div>
                            </li>
                            <li class="step step-success">
                                <div class="text-left ml-2">
                                    <div class="font-semibold">Tika抽出完了</div>
                                    <div class="text-base-content/60">2025-12-13 10:45</div>
                                    <div class="text-base-content/70">1.5秒</div>
                                </div>
                            </li>
                            <li class="step step-success">
                                <div class="text-left ml-2">
                                    <div class="font-semibold">アップロード</div>
                                    <div class="text-base-content/60">2025-12-13 10:45</div>
                                    <div class="text-base-content/70">山田太郎</div>
                                </div>
                            </li>
                        </ul>

                        <div class="divider text-xs">アクティビティ</div>

                        <div class="space-y-2">
                            <div class="p-2 border rounded bg-base-200/30 text-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-download text-primary"></i>
                                        <span class="font-medium">DL</span>
                                    </div>
                                    <span class="text-base-content/60">11:30</span>
                                </div>
                                <div class="text-base-content/70 mt-1">田中花子</div>
                            </div>

                            <div class="p-2 border rounded bg-base-200/30 text-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-eye text-info"></i>
                                        <span class="font-medium">閲覧</span>
                                    </div>
                                    <span class="text-base-content/60">11:15</span>
                                </div>
                                <div class="text-base-content/70 mt-1">佐藤次郎</div>
                            </div>
                        </div>
                    </div>
                </x-mary-tab>
            </x-mary-tabs>
        </div>

        {{-- Footer - 管理アクション --}}
        <div class="border-t p-3 bg-base-200 shrink-0">
            <div class="flex gap-2 justify-between items-center text-xs">
                <span class="text-base-content/60">
                    ID: {{ $file?->id ?? 0 }}
                </span>
                <div class="flex gap-2">
                    <button class="btn btn-warning btn-xs" title="再処理">
                        <i class="fa-solid fa-refresh"></i>
                    </button>
                    <button class="btn btn-error btn-xs" title="削除">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

