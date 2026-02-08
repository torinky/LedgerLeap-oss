@props([
    'files' => [],
    'mode' => 'compact', // 'full' | 'compact' | 'icon-only'
    'tenantId' => null,
    'search' => null,
    'columnId' => null, // カラムID
])

@php
    use App\Helpers\MimeTypeHelper;
    use App\Helpers\SearchHelper;
    use Illuminate\Support\Number;
    use Carbon\Carbon;

    $isCompact = $mode === 'compact';
    $isIconOnly = $mode === 'icon-only';

    $fileCount = count($files);

    // 表示件数リミット調整
    $displayLimit = match ($mode) {
        'icon-only' => 5, // アイコンのみの場合は多めに
        'compact' => 4,
        default => 8,
    };

    $hiddenCount = max(0, $fileCount - $displayLimit);
    $componentId = 'attachment-list-' . uniqid('', true);
@endphp

{{-- Alpine.js data for enhanced interactivity --}}
<div x-data="{
    loadingFiles: {},
    successFiles: {},
    showAll: false,
    displayLimit: {{ $displayLimit }},
    totalCount: {{ $fileCount }},
    search: {{ json_encode($search) }},
    columnId: {{ json_encode($columnId) }},
    isIconOnly: {{ $isIconOnly ? 'true' : 'false' }},
    isCompact: {{ $isCompact ? 'true' : 'false' }},
    handleFileClick(fileId, fileColumnId) {
        this.$dispatch('open-file-inspector', {
            id: fileId,
            column_id: fileColumnId || this.columnId,
            search: this.search
        });
    },
    handleDownload(event, fileId, url) {
        event.preventDefault(); // Stop native link
        event.stopPropagation(); // Stop drawer opening

        if (this.loadingFiles[fileId]) return;

        this.loadingFiles[fileId] = true;
        this.successFiles[fileId] = false;

        // Explicitly create and click a link to force download without bubbling issues
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Simulate UI feedback
        setTimeout(() => {
            this.loadingFiles[fileId] = false;
            this.successFiles[fileId] = true;

            setTimeout(() => {
                this.successFiles[fileId] = false;
            }, 2000);
        }, 1000);
    },
    toggleShowAll() {
        this.showAll = !this.showAll;
        if (this.showAll) {
            // スムーズにスクロール（コンテナの下部が見えるように）
            this.$nextTick(() => {
                this.$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }
    }
}" class="not-prose w-full flex flex-col" id="{{ $componentId }}">

    {{-- 高さ制限とフェードを実現するラッパー。ボタンをこの外に出すことで見切れを防止する --}}
    <div class="relative transition-all duration-500 ease-in-out overflow-hidden"
        :class="{
            'max-h-12': !showAll && totalCount > displayLimit && (isIconOnly || isCompact),
            'max-h-[200px]': !showAll && totalCount > displayLimit && !isIconOnly && !isCompact,
            'max-h-[9999px]': showAll || totalCount <= displayLimit
        }">

        <div x-ref="innerContainer"
            class="{{ $isCompact || $isIconOnly ? 'flex flex-wrap items-center gap-1' : 'grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4' }} pb-2"
            role="list" aria-label="{{ __('ledger.file_list') }}">

            @forelse($files as $index => $file)
                @php
                    $mime = $file['mime'] ?? '';
                    $fileInfo = MimeTypeHelper::getInfo($mime);
                    $iconClass = $fileInfo['icon'] . ' ' . $fileInfo['color'];

                    $status = $file['status'] ?? 'completed';

                    // ダウンロードリンク構造への対応 (新: array, 旧: string)
                    $primaryDownload = $file['primary_download'] ?? null;
                    $downloadUrl = '#'; // Default fallback

                    if ($primaryDownload && is_array($primaryDownload)) {
                        $downloadUrl = $primaryDownload['url'];
                    } elseif (isset($file['downloadUrl']) && is_string($file['downloadUrl'])) {
                        $downloadUrl = $file['downloadUrl'];
                    }

                    $label = $file['filename'] ?? 'file';
                    $fileId = (int) ($file['id'] ?? 0);
                    $fileColumnId = $file['column_id'] ?? ($columnId ?? null);
                    $fileSize = $file['size'] ?? null;

                    // ファイルサイズフォーマット (Laravel 10+ Number::fileSize 対応)
                    $formattedSize = '';
                    if ($fileSize) {
                        $formattedSize =
                            class_exists(Number::class) && method_exists(Number::class, 'fileSize')
                                ? Number::fileSize($fileSize, 2)
                                : number_format($fileSize / 1024, 1) . ' KB';
                    }

                    // ステータスのアクセシビリティテキスト
                    $statusLabel = match ($status) {
                        'processing',
                        'pending_initial_processing',
                        'initial_processing',
                        'pending_ocr',
                        'ocr_processing',
                        'pending_vlm',
                        'vlm_processing',
                        'parallel_processing',
                        'optimizing',
                        'extracting',
                        'extracting_and_saved'
                            => __('ledger.file_status.processing'),
                        'error',
                        'tika_failed',
                        'ocr_failed',
                        'vlm_failed',
                        'thumbnail_failed',
                        'processing_failed',
                        'optimize_failed',
                        'extraction_failed'
                            => __('ledger.file_status.error'),
                        default => __('ledger.file_status.completed'),
                    };

                    $isProcessing = in_array($status, [
                        'processing',
                        'pending_initial_processing',
                        'initial_processing',
                        'pending_ocr',
                        'ocr_processing',
                        'pending_vlm',
                        'vlm_processing',
                        'parallel_processing',
                        'optimizing',
                        'extracting',
                        'extracting_and_saved',
                    ]);

                    $isError = in_array($status, [
                        'error',
                        'tika_failed',
                        'ocr_failed',
                        'vlm_failed',
                        'thumbnail_failed',
                        'processing_failed',
                        'optimize_failed',
                        'extraction_failed',
                    ]);

                    $isOptimized =
                        $status === 'completed' &&
                        (isset($file['ocr_processed_at']) || isset($file['secondary_download']));

                    $isHit = $file['is_hit'] ?? false;
                    $hitLabel = $isHit ? ' [SEARCH MATCH]' : '';
                    $fullTooltip = $label . ($formattedSize ? " ($formattedSize)" : '') . $hitLabel;

                    // 検索キーワードの強調（全てのモードで使用）
                    $searchKeywords = SearchHelper::extractKeywords($search);
                    $displayLabel = !empty($searchKeywords)
                        ? new \Illuminate\Support\HtmlString(
                            SearchHelper::highlight(
                                $label,
                                $searchKeywords,
                                'bg-warning/40 text-base-content font-bold px-0.5 rounded',
                            ),
                        )
                        : e($label);

                    $hasSecondary = isset($file['secondary_download']) && $file['secondary_download'];
                    $finalDownloadUrl = $hasSecondary ? $file['secondary_download']['url'] : $downloadUrl;
                    $downloadTooltip = $hasSecondary ? __('ledger.download_optimized') : __('ledger.download_original');
                @endphp

                @if ($isIconOnly)
                    {{-- Icon Only モード: 一覧画面用極小表示 --}}
                    <div class="relative group inline-flex items-center" role="listitem"
                        x-on:click="handleFileClick({{ $fileId }}, {{ json_encode($fileColumnId) }})"
                        tabindex="0" aria-label="{{ $label }} ({{ $statusLabel }})">
                        {{-- RPA用: 透過的ダウンロードリンク --}}
                        <a href="{{ $downloadUrl }}" class="direct-download-link sr-only"
                            aria-label="{{ __('ledger.download') }}: {{ $label }}" tabindex="-1" download></a>

                        <div class="tooltip tooltip-bottom" data-tip="{{ $fullTooltip }}">
                            <button type="button"
                                class="btn btn-ghost btn-xs btn-square h-8 w-8 min-h-0 p-0 flex items-center justify-center border {{ $isHit ? 'border-success bg-success/10' : 'border-base-300 bg-base-100/30' }} hover:bg-base-200 focus:ring-2 focus:ring-primary focus:outline-none transition-all duration-200 shadow-sm"
                                aria-hidden="true">
                                <div class="indicator">
                                    @if ($isProcessing)
                                        <span class="indicator-item">
                                            <span class="flex h-2 w-2">
                                                <span
                                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning opacity-75"></span>
                                                <span
                                                    class="relative inline-flex rounded-full h-2 w-2 bg-warning"></span>
                                            </span>
                                        </span>
                                    @elseif($isError)
                                        <span
                                            class="indicator-item badge badge-error badge-xs p-0 h-2 w-2 border-none"></span>
                                    @elseif($isOptimized && !$isHit)
                                        <span
                                            class="indicator-item badge badge-success badge-xs p-0 h-1.5 w-1.5 opacity-80 border-none"></span>
                                    @elseif($isHit)
                                        <span class="indicator-item flex h-3 w-3 shadow-md">
                                            <span
                                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-40"></span>
                                            <span
                                                class="relative inline-flex rounded-full h-3 w-3 bg-success items-center justify-center border border-base-100">
                                                <i class="fa-solid fa-magnifying-glass text-[6px] text-white"></i>
                                            </span>
                                        </span>
                                    @endif
                                    <i
                                        class="{{ $iconClass }} text-lg {{ $isHit ? 'text-success drop-shadow-[0_0_5px_rgba(34,197,94,0.6)]' : '' }}"></i>
                                </div>
                            </button>
                        </div>
                    </div>
                @elseif($isCompact)
                    {{-- Compact モード: 一覧画面詳細/編集画面リスト表示 --}}
                    <div class="relative group inline-flex items-center p-1 rounded-md border {{ $isHit ? 'border-success bg-success/10 ring-1 ring-success/20' : 'border-transparent hover:border-base-300 hover:bg-base-100' }} transition-all duration-300"
                        role="listitem">

                        {{-- RPA用: 透過的ダウンロードリンク --}}
                        <a href="{{ $downloadUrl }}" class="direct-download-link sr-only"
                            aria-label="{{ __('ledger.download') }}: {{ $label }}" tabindex="-1" download></a>

                        {{-- ファイル表示エリア（クリックでドロワー） --}}
                        <div class="indicator tooltip tooltip-bottom flex items-center" data-tip="{{ $fullTooltip }}">
                            @if ($isHit)
                                <span
                                    class="indicator-item indicator-start inline-flex items-center justify-center w-5 h-5 rounded-full bg-success/80 text-base-100 mr-1.5 align-middle">
                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                                </span>
                            @endif
                            <button type="button" class="flex items-center gap-2 px-2 py-1 text-left max-w-[200px]"
                                x-on:click="handleFileClick({{ $fileId }}, {{ json_encode($fileColumnId) }})"
                                aria-label="{{ $label }} ({{ $statusLabel }})" tabindex="0">
                                {{-- ステータスインジケータ --}}
                                <div class="indicator shrink-0">
                                    @if ($isProcessing)
                                        <span class="indicator-item">
                                            <span class="flex h-2.5 w-2.5">
                                                <span
                                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning opacity-75"></span>
                                                <span
                                                    class="relative inline-flex rounded-full h-2.5 w-2.5 bg-warning border border-base-100"></span>
                                            </span>
                                        </span>
                                    @elseif($isError)
                                        <span
                                            class="indicator-item badge badge-error badge-xs p-0 h-2.5 w-2.5 border-base-100 text-[6px] font-bold">!</span>
                                    @elseif($isOptimized)
                                        <span
                                            class="indicator-item badge badge-success badge-xs p-0 h-2.5 w-2.5 border-base-100 items-center justify-center shadow-sm">
                                            <i class="fa-solid fa-check text-[7px] text-white"></i>
                                        </span>
                                    @endif
                                    <i class="{{ $iconClass }} text-xl"></i>
                                </div>

                                <div class="flex flex-col min-w-0">
                                    <div class="flex items-center gap-1">
                                        <span {{--                                        class="truncate text-sm font-medium {{ $isHit ? 'text-success font-bold' : 'text-base-content/90' }}">{{ $displayLabel }}</span> --}}
                                            class="truncate text-sm font-medium text-base-content/90">{{ $displayLabel }}</span>
                                        <span class="text-[10px] text-base-content/60">{{ $formattedSize }}</span>
                                    </div>
                                </div>
                            </button>
                        </div>

                        {{-- ダウンロードボタン --}}
                        <div class="tooltip tooltip-left" data-tip="{{ $downloadTooltip }}">
                            <a href="{{ $finalDownloadUrl }}"
                                class="btn btn-xs btn-circle ml-1 bg-base-100 border border-base-300 text-base-content/60 hover:text-primary hover:border-primary/50 hover:bg-primary/5 shadow-sm transition-all relative overflow-hidden"
                                x-on:click="handleDownload($event, {{ $fileId }}, '{{ $finalDownloadUrl }}')"
                                download>
                                <span x-show="loadingFiles[{{ $fileId }}]"
                                    class="loading loading-spinner loading-xs text-primary scale-75"></span>
                                <i x-show="successFiles[{{ $fileId }}]"
                                    class="fa-solid fa-check text-[10px] text-success"></i>
                                <i x-show="!loadingFiles[{{ $fileId }}] && !successFiles[{{ $fileId }}]"
                                    class="fa-solid fa-download text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                @else
                    {{-- Full モード: 詳細画面用のカード表示 --}}
                    <x-ledger.attachment-card :file="$file" :index="$index" :displayLimit="$displayLimit" :search="$search" />
                @endif

            @empty
                @if (!$isIconOnly)
                    <div
                        class="col-span-full flex flex-col items-center justify-center py-6 text-base-content/40 border border-dashed border-base-300 rounded-lg">
                        <i class="fa-solid fa-cloud-arrow-up text-2xl mb-2"></i>
                        <p class="text-sm">{{ __('ledger.no_attachments') }}</p>
                    </div>
                @else
                    <span class="text-base-content/30 text-xs">-</span>
                @endif
            @endforelse
        </div>

        {{-- グラデーションマスク（もっと見るへの誘導） --}}
        @if ($hiddenCount > 0)
            <div x-show="!showAll"
                class="absolute bottom-0 left-0 right-0 h-12 bg-linear-to-t from-base-100 via-base-100/80 to-transparent pointer-events-none transition-opacity duration-300">
            </div>
        @endif
    </div>

    {{-- 「もっと見る」ボタンを高さ制限の外側に配置して確実に表示。他カラムのUIに準拠 --}}
    @if ($hiddenCount > 0)
        <div class="mt-1 w-full shrink-0">
            <button type="button" x-on:click="toggleShowAll()"
                class="btn btn-ghost btn-sm w-full gap-2 transition-all duration-300 hover:bg-base-200 shadow-xs"
                :class="{ 'btn-active bg-base-200': showAll }">
                <span
                    x-text="showAll ? '{{ __('ledger.collapse') }}' : '{{ __('ledger.show_more') }} (+{{ $hiddenCount }})'"></span>
                <i class="fa-solid fa-chevron-down transition-transform duration-300"
                    :class="{ 'rotate-180': showAll }"></i>
            </button>
        </div>
    @endif
</div>

{{-- CSSアニメーション --}}
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
