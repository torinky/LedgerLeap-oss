@props([
    'files' => [],
    'mode' => 'compact', // 'full' | 'compact'
    'tenantId' => null,
])

@php
    $isCompact = $mode === 'compact';
    // Compactモード: 4件、Fullモード: 8件まで初期表示
    $fileCount = count($files);
    $displayLimit = $isCompact ? 4 : 8;
    $hiddenCount = max(0, $fileCount - $displayLimit);
    $componentId = 'attachment-list-' . uniqid('', true);
@endphp

{{-- Alpine.js data for enhanced interactivity --}}
<div x-data="{
    hoveredFile: null,
    loadingFiles: {},
    errorFiles: {},
    showAll: false,
    displayLimit: {{ $displayLimit }},
    totalCount: {{ $fileCount }},
    handleFileClick(fileId) {
        console.log('handleFileClick called with fileId:', fileId);
        this.$dispatch('open-file-inspector', { id: fileId });
    },
    handleDownload(event, fileId, url) {
        event.stopPropagation();
        this.loadingFiles[fileId] = true;
        setTimeout(() => { this.loadingFiles[fileId] = false; }, 1000);
    },
    toggleShowAll() {
        this.showAll = !this.showAll;
        if (this.showAll) {
            // スムーズにスクロール
            this.$nextTick(() => {
                this.$el.querySelector('[x-show]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }
    }
}"
     class="{{ $isCompact ? 'flex flex-wrap items-center gap-1' : 'grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3' }}"
     role="list"
     aria-label="{{ __('File List') }}"
     id="{{ $componentId }}">

    @forelse($files as $index => $file)
        @php
            $mime = $file['mime'] ?? '';
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';
            $isWord = str_contains($mime, 'word');
            $isExcel = str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet');
            $isText = str_contains($mime, 'text');
            $isZip = str_contains($mime, 'zip') || str_contains($mime, 'archive');

            // アイコンとカラー（色覚多様性対応: アイコン形状でも識別可能）
            $iconClass = match (true) {
                $isImage => 'fa-solid fa-file-image text-blue-500',
                $isPdf => 'fa-solid fa-file-pdf text-red-500',
                $isWord => 'fa-solid fa-file-word text-blue-700',
                $isExcel => 'fa-solid fa-file-excel text-green-600',
                $isText => 'fa-solid fa-file-lines text-gray-600',
                $isZip => 'fa-solid fa-file-zipper text-purple-600',
                default => 'fa-solid fa-file text-gray-400',
            };

            $status = $file['status'] ?? 'completed';
            $downloadUrl = $file['downloadUrl'] ?? '#';
            $label = $file['filename'] ?? 'file';
            $fileId = (int)($file['id'] ?? 0);
            $fileSize = $file['size'] ?? null;
            $formattedSize = $fileSize ? number_format($fileSize / 1024, 1) . ' KB' : '';

            // ステータスのアクセシビリティテキスト
            $statusLabel = match($status) {
                'processing' => __('Processing'),
                'error' => __('Error'),
                default => __('Completed'),
            };
        @endphp

        <div x-show="{{ $index }} < displayLimit || showAll"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             style="display: {{ $index < $displayLimit ? 'block' : 'none' }};">

            @if($isCompact)
                {{-- Compact モード: 一覧画面用のシンプル表示 --}}
                <div class="relative group inline-flex items-center"
                     role="listitem"
                     x-on:mouseenter="hoveredFile = {{ $fileId }}"
                     x-on:mouseleave="hoveredFile = null">

                    {{-- RPA用: 透過的ダウンロードリンク --}}
                    <a href="{{ $downloadUrl }}"
                       class="direct-download-link sr-only"
                       aria-label="{{ __('Download') }}: {{ $label }}"
                       tabindex="-1"
                       download></a>

                    {{-- ファイル表示エリア（クリックでドロワー） --}}
                    <button type="button"
                            class="btn btn-ghost btn-xs h-auto min-h-0 px-1.5 py-1 flex items-center gap-1.5 transition-all duration-200 hover:bg-base-200 focus:ring-2 focus:ring-primary focus:outline-none tooltip"
                            x-on:click="handleFileClick({{ $fileId }})"
                            x-bind:class="{ 'bg-base-200': hoveredFile === {{ $fileId }} }"
                            aria-label="{{ $label }} ({{ $statusLabel }})"
                            tabindex="0"
                            data-tip="{{ $label }}"
                    >

                        {{-- ステータスインジケータ（アニメーション付き） --}}
                        <span class="relative">
                        @if($status === 'processing')
                                <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2"
                                      role="status"
                                      aria-label="{{ __('Processing') }}">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-warning"></span>
                            </span>
                            @elseif($status === 'error')
                                <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2 rounded-full bg-error"
                                      role="status"
                                      aria-label="{{ __('Error') }}">
                                <i class="fa-solid fa-exclamation text-white text-[6px]"></i>
                            </span>
                            @endif

                            {{-- ファイルアイコン --}}
                        <i class="{{ $iconClass }} text-base" aria-hidden="true"></i>
                    </span>

                        {{-- ファイル名 --}}
                        <span class="truncate max-w-[80px] text-xs">{{ \Illuminate\Support\Str::limit($label, 12, '...') }}</span>
                    </button>

                    {{-- 直接ダウンロードボタン（常時表示） --}}
                    <a href="{{ $downloadUrl }}"
                       class="btn btn-ghost btn-xs btn-square p-0 text-base-content/50 hover:text-primary"
                       x-on:click="handleDownload($event, {{ $fileId }}, '{{ $downloadUrl }}')"
                       title="{{ __('Download') }} ({{ $label }})"
                       aria-label="{{ __('Download') }}: {{ $label }}"
                       download
                       tabindex="0">
                        <i class="fa-solid fa-download text-xs" aria-hidden="true"></i>
                        <span x-show="loadingFiles[{{ $fileId }}]" class="loading loading-spinner loading-xs"></span>
                    </a>
                </div>

            @else
                {{-- Full モード: 詳細画面用のコンパクトカード表示 --}}
                <div class="card bg-base-100 shadow hover:shadow-lg transition-all duration-300 border border-base-300 hover:border-primary/50 relative group overflow-hidden cursor-pointer"
                     role="listitem"
                     x-data="{ imageLoading: true, imageError: false }"
                     x-on:click="handleFileClick({{ $fileId }})"
                     tabindex="0"
                     aria-label="{{ $label }} ({{ $statusLabel }})"
                >

                    {{-- RPA用: 透過的ダウンロードリンク --}}
                    <a href="{{ $downloadUrl }}"
                       class="direct-download-link sr-only"
                       aria-label="{{ __('Download') }}: {{ $label }}"
                       tabindex="-1"
                       download></a>

                    {{-- ステータスバッジ（右上） --}}
                    @php
                        $badgeClass = match($status) {
                            'processing' => 'badge-warning',
                            'error' => 'badge-error',
                            default => 'badge-success',
                        };
                    @endphp
                    @if($status !== 'processing' && $status !== 'error')
                    <div class="absolute right-2 top-2 z-10 badge badge-xs {{ $badgeClass }} shadow-md"
                         role="status"
                         aria-label="{{ $statusLabel }}">
{{--
                        @if($status === 'processing')
                            <i class="fa-solid fa-spinner animate-spin text-[8px]" aria-hidden="true"></i>
                        @elseif($status === 'error')
                            <i class="fa-solid fa-exclamation-triangle text-[8px]" aria-hidden="true"></i>
                        @else
--}}
                            <i class="fa-solid fa-check text-[8px]" aria-hidden="true"></i>
{{--                        @endif--}}
                    </div>
                    @endif


                    {{-- 直接ダウンロードボタン（常時表示、右下） --}}
                    <a href="{{ $downloadUrl }}"
                       class="absolute left-1/2 bottom-1/4 transform -translate-x-1/2 z-10 btn btn-sm btn-circle btn-ghost text-base-content/50 bg-base-200/70 hover:text-primary hover:bg-base-200 tooltip"
                       x-on:click.stop="handleDownload($event, {{ $fileId }}, '{{ $downloadUrl }}')"
                       title="{{ __('Download') }} ({{ $formattedSize }})"
                       aria-label="{{ __('Download') }}: {{ $label }}"
                       download
                       tabindex="0"
                       data-tip="{{ __('Download') }}"
                    >
                        <i class="fa-solid fa-download text-xs" aria-hidden="true"></i>
                        <span x-show="loadingFiles[{{ $fileId }}]" class="loading loading-spinner loading-xs"></span>
                    </a>

                    {{-- コンテンツエリア --}}
                    <figure class="aspect-4/3 bg-base-200 flex items-center justify-center relative overflow-hidden">
                        @if($status === 'processing')
                            {{-- ローディング状態: スケルトンスクリーン --}}
                            <div class="w-full h-full animate-pulse bg-gradient-to-r from-base-300 via-base-200 to-base-300"
                                 style="background-size: 200% 100%; animation: shimmer 1.5s infinite;"></div>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                                <i class="fa-solid fa-spinner fa-spin text-xl text-warning" aria-hidden="true"></i>
                                <span class="text-xs text-base-content/70">{{ __('Processing') }}...</span>
                            </div>
                        @elseif($status === 'error')
                            {{-- エラー状態 --}}
                            <div class="flex flex-col items-center justify-center gap-2 p-4 text-center">
                                <i class="fa-solid fa-exclamation-triangle text-2xl text-error" aria-hidden="true"></i>
                                <span class="text-xs font-medium text-error">{{ __('Processing Failed') }}</span>
                                <span class="text-[10px] text-base-content/60">{{ $file['error_message'] ?? __('Unknown Error') }}</span>
                            </div>
                        @else
                            @if($isImage && isset($file['thumbnailUrl']))
                                {{-- 画像のサムネイル表示（ローディング＆エラー対応） --}}
                                <div x-show="imageLoading" class="absolute inset-0 animate-pulse bg-base-300"></div>
                                <div x-show="imageError"
                                     class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-base-300">
                                    <i class="fa-solid fa-image-slash text-xl text-base-content/40"
                                       aria-hidden="true"></i>
                                    <span class="text-[10px] text-base-content/60">{{ __('Could Not Load Image') }}</span>
                                </div>
                                <img src="{{ $file['thumbnailUrl'] }}"
                                     alt="{{ $label }}"
                                     class="w-full h-full object-cover"
                                     loading="lazy"
                                     x-on:load="imageLoading = false"
                                     x-on:error="imageLoading = false; imageError = true">
                            @else
                                {{-- アイコン表示 --}}
                                <i class="{{ $iconClass }} fa-3x" aria-hidden="true"></i>
                            @endif
                        @endif
                    </figure>

                    {{-- Footer（ファイル名とサイズのみ） --}}
                    <div class="p-2">
                        <h3 class="text-xs font-medium line-clamp-1" title="{{ $label }}">{{ $label }}</h3>
                        @if($formattedSize)
                            <p class="text-[10px] text-base-content/60 mt-0.5">{{ $formattedSize }}</p>
                        @endif
                    </div>
                </div>
            @endif

        </div>{{-- x-show wrapper end --}}

    @empty
        <div class="col-span-full text-center py-8 text-base-content/60" role="status">
            <i class="fa-solid fa-folder-open fa-2x mb-2 opacity-40" aria-hidden="true"></i>
            <p>@lang('ledger.no_attachments')</p>
        </div>
    @endforelse

    {{-- 「もっと見る」ボタン（4件以上の場合） --}}
    @if($hiddenCount > 0)
        <div class="{{ $isCompact ? '' : 'col-span-full' }} flex justify-center mt-2">
            <button type="button"
                    x-show="!showAll"
                    x-on:click="toggleShowAll()"
                    class="btn btn-ghost btn-sm text-primary hover:bg-primary/10 gap-2"
                    role="button"
                    aria-label="@lang('ledger.show_more'): @lang('ledger.more_files', ['count' => $hiddenCount])">
                <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                <span class="text-sm">@lang('ledger.show_more') (+{{ $hiddenCount }})</span>
            </button>

            <button type="button"
                    x-show="showAll"
                    x-on:click="toggleShowAll()"
                    class="btn btn-ghost btn-sm text-base-content/60 hover:bg-base-200 gap-2"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    role="button"
                    aria-label="@lang('ledger.collapse')">
                <i class="fa-solid fa-chevron-up text-xs" aria-hidden="true"></i>
                <span class="text-sm">@lang('ledger.collapse')</span>
            </button>
        </div>
    @endif
</div>

{{-- CSSアニメーション定義 --}}
@once
    <style>
        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }
    </style>
@endonce
