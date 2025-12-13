<div
    x-data="{ open: @entangle('open') }"
    @keydown.escape.window="open = false; $wire.close()"
    @open-file-inspector.window="console.log('FileInspector received event:', $event.detail); $wire.openInspector($event.detail.id)"
>
    {{-- Overlay --}}
    <div x-show="open" class="fixed inset-0 bg-black/50 z-40" @click="open = false; $wire.close()"></div>

    {{-- Drawer --}}
    <div class="fixed right-0 top-0 h-full w-96 md:w-1/3 bg-base-100 shadow-xl z-40 transform transition-transform flex flex-col"
         :class="open ? 'translate-x-0' : 'translate-x-full'"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title"
    >
        {{-- Header with actions --}}
        <div class="p-4 flex flex-col border-b bg-base-200 flex-shrink-0">
            <div class="flex items-center justify-between mb-3">
                <h2 id="drawer-title" class="text-lg font-bold truncate flex-1 line-clamp-2" title="{{ $file?->original_filename ?? $file?->filename ?? 'ファイル' }}">
                    {{ $file?->original_filename ?? $file?->filename ?? 'ファイル' }}
                </h2>
                <button class="btn btn-square btn-ghost btn-sm ml-2" @click="open = false; $wire.close()" aria-label="閉じる">
                    <i class="fa-solid fa-xmark fa-lg"></i>
                </button>
            </div>

            {{-- Action buttons --}}
            <div class="flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" title="ダウンロード">
                    <i class="fa-solid fa-download"></i>
                    <span class="text-xs">DL</span>
                </button>
                <button class="btn btn-outline btn-sm" title="新しいタブで開く">
                    <i class="fa-solid fa-external-link-alt"></i>
                    <span class="text-xs">外部</span>
                </button>
                <button class="btn btn-outline btn-sm" title="リンクをコピー">
                    <i class="fa-solid fa-link"></i>
                    <span class="text-xs">リンク</span>
                </button>
            </div>
        </div>

        {{-- Content with tabs (scrollable) --}}
        <div class="flex-1 overflow-y-auto p-4">
            <x-mary-tabs wire:model="selectedTab" class="tabs-boxed mb-4">
                {{-- 解析タブ --}}
<x-mary-tab name="analysis" label="解析" icon="o-document-text">
                    <div class="space-y-3">
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
                            {{-- 処理中状態 --}}
                            <div class="alert alert-warning shadow-sm">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <div class="flex-1">
                                    <h3 class="font-bold text-sm">処理中</h3>
                                    <div class="text-xs mt-1">OCR処理を実行しています。しばらくお待ちください...</div>
                                    <div class="mt-2">
                                        <progress class="progress progress-warning w-full" value="65" max="100"></progress>
                                        <div class="text-xs text-base-content/70 mt-1">進行状況: 65%</div>
                                    </div>
                                </div>
                            </div>
                        @elseif($isError)
                            {{-- エラー状態 --}}
                            <div class="alert alert-error shadow-sm">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <div class="flex-1">
                                    <h3 class="font-bold text-sm">処理エラー</h3>
                                    <div class="text-xs mt-1">このファイル形式はテキスト抽出に対応していません。</div>
                                    <div class="mt-2 text-xs">
                                        <strong>対応形式:</strong> PDF, 画像(JPG/PNG), Office文書(Word/Excel), テキストファイル
                                    </div>
                                </div>
                            </div>
                        @elseif($hasPreviewText)
                            {{-- 信頼度スコア表示 --}}
                            @if($confidence !== null && $confidence > 0)
                            <div class="p-3 bg-base-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold">信頼度スコア</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-lg">{{ number_format($confidence * 100, 1) }}%</span>
                                        @if($confidence >= 0.9)
                                            <i class="fa-solid fa-check-circle text-success"></i>
                                        @elseif($confidence >= 0.7)
                                            <i class="fa-solid fa-shield-check text-info"></i>
                                        @else
                                            <i class="fa-solid fa-exclamation-triangle text-warning"></i>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    @if($source === 'VLM')
                                        <div class="badge badge-success badge-sm">VLM抽出</div>
                                        <span class="text-xs text-base-content/70">高精度AI解析</span>
                                    @elseif($source === 'OCR')
                                        <div class="badge badge-info badge-sm">OCR抽出</div>
                                        <span class="text-xs text-base-content/70">画像文字認識</span>
                                    @elseif($source === 'Tika')
                                        <div class="badge badge-primary badge-sm">Tika抽出</div>
                                        <span class="text-xs text-base-content/70">ドキュメント解析</span>
                                    @endif
                                </div>
                            </div>
                            @endif

                            <div>
                                <label class="label">
                                    <span class="label-text font-semibold text-xs">抽出されたテキスト</span>
                                </label>
                                <textarea class="textarea textarea-bordered w-full h-48 text-sm font-mono" readonly>{{ $previewText }}</textarea>
                                <div class="mt-2 flex gap-2">
                                    <button class="btn btn-sm btn-outline" x-data="{}" @click="navigator.clipboard.writeText($el.previousElementSibling.previousElementSibling.value)">
                                        <i class="fa-solid fa-copy"></i>
                                        コピー
                                    </button>
                                    <button class="btn btn-sm btn-outline">
                                        <i class="fa-solid fa-download"></i>
                                        テキスト保存
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info shadow-sm">
                                <i class="fa-solid fa-info-circle"></i>
                                <div class="flex-1">
                                    <h3 class="font-bold text-sm">テキストプレビュー不可</h3>
                                    <div class="text-xs mt-1">このファイルにはテキスト解析結果がありません。</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-mary-tab>

                {{-- 情報タブ --}}
                <x-mary-tab name="info" label="情報" icon="o-information-circle">
                    <div class="space-y-4">
                        {{-- 基本情報 --}}
                        <div>
                            <h3 class="font-bold text-sm mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-file"></i>
                                基本情報
                            </h3>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">ファイル名</span>
                                    <span class="font-medium text-right">{{ $file?->original_filename ?? $file?->filename ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">MIMEタイプ</span>
                                    <span class="font-mono text-xs">{{ $file?->original_mime_type ?? $file?->mime ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">サイズ</span>
                                    <span class="font-medium">{{ number_format(($file?->size ?? 0)/1024, 1) }} KB</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">アップロード日時</span>
                                    <span class="font-medium">{{ $file?->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">アップロード者</span>
                                    <span class="font-medium">{{ $file?->creator?->name ?? ($file && $file->id === 0 ? '山田太郎' : 'N/A') }}</span>
                                </div>
                                @if($file && $file->updated_at)
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">最終更新</span>
                                    <span class="font-medium">{{ $file->updated_at->format('Y-m-d H:i') }}</span>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- 処理情報 --}}
                        <div>
                            <h3 class="font-bold text-sm mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-cogs"></i>
                                処理情報
                            </h3>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">ステータス</span>
                                    @php
                                        $isProcessing = $file && ($file->mock_confidence === 0.0 && $file->mock_source === 'OCR');
                                        $isError = $file && ($file->mock_source === null);
                                    @endphp
                                    @if($isProcessing)
                                        <span class="badge badge-warning badge-sm flex items-center gap-1">
                                            <i class="fa-solid fa-spinner fa-spin text-xs"></i>
                                            処理中
                                        </span>
                                    @elseif($isError)
                                        <span class="badge badge-error badge-sm">エラー</span>
                                    @else
                                        <span class="badge badge-success badge-sm">完了</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">最終抽出ソース</span>
                                    @if($file && $file->mock_source)
                                        @if($file->mock_source === 'VLM')
                                            <span class="badge badge-success badge-sm">VLM</span>
                                        @elseif($file->mock_source === 'OCR')
                                            <span class="badge badge-info badge-sm">OCR</span>
                                        @elseif($file->mock_source === 'Tika')
                                            <span class="badge badge-primary badge-sm">Tika</span>
                                        @endif
                                    @else
                                        <span class="font-medium text-base-content/50">未処理</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">Tika抽出</span>
                                    @if($file && in_array($file->mock_source, ['Tika', 'OCR', 'VLM']))
                                        <span class="font-medium flex items-center gap-1">
                                            <i class="fa-solid fa-check text-success text-xs"></i>
                                            完了
                                        </span>
                                    @else
                                        <span class="font-medium text-base-content/50">-</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">OCR処理</span>
                                    @if($file && in_array($file->mock_source, ['OCR', 'VLM']))
                                        @if($isProcessing)
                                            <span class="font-medium text-warning">進行中</span>
                                        @else
                                            <span class="font-medium flex items-center gap-1">
                                                <i class="fa-solid fa-check text-success text-xs"></i>
                                                完了
                                            </span>
                                        @endif
                                    @else
                                        <span class="font-medium text-base-content/50">スキップ</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">VLM処理</span>
                                    @if($file && $file->mock_source === 'VLM')
                                        <span class="font-medium flex items-center gap-1">
                                            <i class="fa-solid fa-check text-success text-xs"></i>
                                            完了
                                        </span>
                                    @else
                                        <span class="font-medium text-base-content/50">未実施</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">処理時間</span>
                                    @if($isProcessing)
                                        <span class="font-medium text-warning">計測中...</span>
                                    @elseif($isError)
                                        <span class="font-medium text-base-content/50">-</span>
                                    @else
                                        <span class="font-medium">{{ rand(3, 12) }}.{{ rand(0, 9) }}秒</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 権限情報 --}}
                        <div>
                            <h3 class="font-bold text-sm mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-shield-halved"></i>
                                アクセス権限
                            </h3>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">閲覧権限</span>
                                    <span class="badge badge-success badge-sm">あり</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">ダウンロード</span>
                                    <span class="badge badge-success badge-sm">可能</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">削除権限</span>
                                    <span class="badge badge-warning badge-sm">制限</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-base-content/70">再処理権限</span>
                                    <span class="badge badge-warning badge-sm">管理者のみ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-mary-tab>

                {{-- 履歴タブ --}}
                <x-mary-tab name="history" label="履歴" icon="o-clock">
                    <div class="space-y-4">
                        {{-- 処理ログタイムライン --}}
                        <div>
                            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-list-check"></i>
                                処理ログ
                            </h3>
                            <ul class="steps steps-vertical text-xs">
                                <li class="step step-success">
                                    <div class="text-left ml-3">
                                        <div class="font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-robot text-success"></i>
                                            VLM解析完了
                                        </div>
                                        <div class="text-base-content/60">2025-12-13 10:45:32</div>
                                        <div class="text-base-content/70 mt-1">信頼度: 92.5% | 所要時間: 3.2秒</div>
                                    </div>
                                </li>
                                <li class="step step-success">
                                    <div class="text-left ml-3">
                                        <div class="font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-text-width text-info"></i>
                                            OCR処理完了
                                        </div>
                                        <div class="text-base-content/60">2025-12-13 10:45:28</div>
                                        <div class="text-base-content/70 mt-1">所要時間: 2.8秒</div>
                                    </div>
                                </li>
                                <li class="step step-success">
                                    <div class="text-left ml-3">
                                        <div class="font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-file-alt text-info"></i>
                                            Tika抽出完了
                                        </div>
                                        <div class="text-base-content/60">2025-12-13 10:45:25</div>
                                        <div class="text-base-content/70 mt-1">所要時間: 1.5秒</div>
                                    </div>
                                </li>
                                <li class="step step-success">
                                    <div class="text-left ml-3">
                                        <div class="font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-upload"></i>
                                            ファイルアップロード
                                        </div>
                                        <div class="text-base-content/60">2025-12-13 10:45:20</div>
                                        <div class="text-base-content/70 mt-1">アップロード者: 山田太郎</div>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        {{-- アクティビティログ --}}
                        <div class="border-t pt-4">
                            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-user-clock"></i>
                                アクティビティログ
                            </h3>
                            <div class="space-y-2">
                                <div class="p-2 border rounded bg-base-200/50">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-download text-primary"></i>
                                            <span class="font-semibold">ダウンロード</span>
                                        </div>
                                        <div class="badge badge-xs">2025-12-13 11:30</div>
                                    </div>
                                    <div class="text-xs text-base-content/70 mt-1 ml-5">田中花子</div>
                                </div>
                                <div class="p-2 border rounded bg-base-200/50">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-eye text-info"></i>
                                            <span class="font-semibold">閲覧</span>
                                        </div>
                                        <div class="badge badge-xs">2025-12-13 11:15</div>
                                    </div>
                                    <div class="text-xs text-base-content/70 mt-1 ml-5">佐藤次郎</div>
                                </div>
                                <div class="p-2 border rounded bg-base-200/50">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-download text-primary"></i>
                                            <span class="font-semibold">ダウンロード</span>
                                        </div>
                                        <div class="badge badge-xs">2025-12-13 10:50</div>
                                    </div>
                                    <div class="text-xs text-base-content/70 mt-1 ml-5">山田太郎</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-mary-tab>
            </x-mary-tabs>
        </div>

        {{-- Footer with additional actions --}}
        <div class="border-t p-3 bg-base-200 flex-shrink-0">
            <div class="flex gap-2 flex-wrap justify-between items-center">
                <div class="text-xs text-base-content/70">
                    <i class="fa-solid fa-info-circle"></i>
                    ID: {{ $file?->id ?? 0 }}
                </div>
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

