@props([
    'files' => [],
    'mode' => 'compact', // 'full' | 'compact' | 'icon-only'
    'tenantId' => null,
    'search' => null,
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
    hoveredFile: null,
    loadingFiles: {},
    errorFiles: {},
    showAll: false,
    displayLimit: {{ $displayLimit }},
    totalCount: {{ $fileCount }},
    search: {{ json_encode($search) }},
    handleFileClick(fileId) {
        // console.log('handleFileClick called with fileId:', fileId);
        this.$dispatch('open-file-inspector', { id: fileId, search: this.search });
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
    class="{{ $isCompact || $isIconOnly ? 'flex flex-wrap items-center gap-1' : 'grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3' }}"
    role="list" aria-label="{{ __('ledger.file_list') }}" id="{{ $componentId }}">

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
                $status === 'completed' && (isset($file['ocr_processed_at']) || isset($file['secondary_download']));

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
        @endphp

        <div x-show="{{ $index }} < displayLimit || showAll" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            style="display: {{ $index < $displayLimit ? 'block' : 'none' }};">

            @if ($isIconOnly)
                {{-- Icon Only モード: 一覧画面用極小表示 --}}
                <div class="relative group inline-flex items-center" role="listitem"
                    x-on:click="handleFileClick({{ $fileId }})" tabindex="0"
                    aria-label="{{ $label }} ({{ $statusLabel }})">
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
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-warning"></span>
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
                    role="listitem" x-on:mouseenter="hoveredFile = {{ $fileId }}"
                    x-on:mouseleave="hoveredFile = null">

                    {{-- RPA用: 透過的ダウンロードリンク --}}
                    <a href="{{ $downloadUrl }}" class="direct-download-link sr-only"
                        aria-label="{{ __('ledger.download') }}: {{ $label }}" tabindex="-1" download></a>

                    {{-- ファイル表示エリア（クリックでドロワー） --}}
                    <div class="indicator tooltip tooltip-bottom flex items-center" data-tip="{{ $fullTooltip }}">
                        @if ($isHit)
                            <span class="indicator-item indicator-start inline-flex items-center justify-center w-5 h-5 rounded-full bg-success/80 text-base-100 mr-1.5 align-middle">
                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                            </span>
                        @endif
                        <button type="button" class="flex items-center gap-2 px-2 py-1 text-left max-w-[200px]"
                            x-on:click="handleFileClick({{ $fileId }})"
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
                                    <span
{{--                                        class="truncate text-sm font-medium {{ $isHit ? 'text-success font-bold' : 'text-base-content/90' }}">{{ $displayLabel }}</span>--}}
                                        class="truncate text-sm font-medium text-base-content/90">{{ $displayLabel }}</span>
                                    <span class="text-[10px] text-base-content/60">{{ $formattedSize }}</span>
                                </div>
                        </button>
                    </div>

                    {{-- ダウンロードボタン --}}
                    <div class="tooltip tooltip-left" data-tip="{{ __('ledger.download') }}">
                        <a href="{{ $downloadUrl }}"
                            class="btn btn-xs btn-circle ml-1 bg-base-100 border border-base-300 text-base-content/60 hover:text-primary hover:border-primary/50 hover:bg-primary/5 shadow-sm transition-all"
                            onclick="event.stopPropagation()" download>
                            <i class="fa-solid fa-download text-[10px]"></i>
                        </a>
                    </div>
                </div>
            @else
                {{-- Full モード: 詳細画面用のカード表示 --}}
                @php
                    $hasSecondary = isset($file['secondary_download']) && $file['secondary_download'];
                    $finalDownloadUrl = $hasSecondary ? $file['secondary_download']['url'] : $downloadUrl;
                    $downloadTooltip = $hasSecondary ? __('ledger.download_optimized') : __('ledger.download_original');
                @endphp
                <div class="tooltip tooltip-bottom text-left indicator" data-tip="{{ $fullTooltip }}">
                        @if ($isHit)
                            <span class="indicator-item indicator-start inline-flex items-center justify-center w-5 h-5 rounded-full bg-success/80 text-base-100 mr-1.5 align-middle">
                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                            </span>
                        @endif
                        @if ($isOptimized)
{{--
                            <span
                                class="indicator-item badge badge-success badge-xs gap-1 shadow-sm opacity-80 mt-2 mr-2">
--}}
                            <span class="indicator-item indicator-middle indicator-center inline-flex items-center justify-center w-5 h-5 rounded-full bg-success/50 text-base-100 mr-1.5 align-middle">
                                <i class="fa-solid fa-check text-[10px]"></i>
                            </span>
                        @endif
                        <div class="card bg-base-100 shadow-sm hover:shadow-xl transition-all duration-300 {{ $isHit ? 'card-bordered border-success ring-1 ring-success bg-success/5 shadow-lg shadow-success/10' : 'card-bordered border-base-200 hover:border-primary/30' }} group cursor-pointer h-full"
                            role="listitem" x-data="{ imageLoading: true, imageError: false }" x-on:click="handleFileClick({{ $fileId }})"
                            tabindex="0" aria-label="{{ $label }} ({{ $statusLabel }})">
                            {{-- RPA用: 透過的ダウンロードリンク --}}
                            <a href="{{ $downloadUrl }}" class="direct-download-link sr-only"
                                aria-label="{{ __('ledger.download') }}: {{ $label }}" tabindex="-1"
                                download></a>

                            <figure
                                class="h-40 bg-base-200/50 flex items-center justify-center relative overflow-hidden group-hover:bg-base-200 transition-colors">
                                @if ($isProcessing)
                                    {{-- Processing --}}
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="loading loading-spinner loading-md text-warning"></span>
                                        <span
                                            class="text-xs text-base-content/60">{{ __('ledger.file_status.processing') }}</span>
                                    </div>
                                @elseif($isError)
                                    {{-- Error --}}
                                    <div class="text-error flex flex-col items-center gap-1">
                                        <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                                        <span class="text-xs font-bold">{{ __('ledger.file_status.error') }}</span>
                                    </div>
                                @else
                                    {{-- Normal --}}
                                    @if (Str::startsWith($mime, 'image/') && isset($file['thumbnailUrl']))
                                        <div x-show="imageLoading"
                                            class="absolute inset-0 flex items-center justify-center bg-base-200">
                                            <span class="loading loading-dots loading-sm text-base-content/30"></span>
                                        </div>
                                        <img src="{{ $file['thumbnailUrl'] }}" alt="{{ $label }}"
                                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy" x-show="!imageError" x-on:load="imageLoading = false"
                                            x-on:error="imageLoading = false; imageError = true">
                                        {{-- Fallback if image fails --}}
                                        <div x-show="imageError"
                                            class="flex flex-col items-center text-base-content/40">
                                            <i class="fa-regular fa-image text-3xl mb-1"></i>
                                            <span class="text-[10px]">No Preview</span>
                                        </div>
                                    @else
                                        <div
                                            class="transform transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                                            <i class="{{ $iconClass }} text-5xl opacity-80"></i>
                                        </div>
                                    @endif
                                @endif
                            </figure>

                            {{-- Footer --}}
                            <div class="px-3 py-2 grow relative">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-semibold text-base-content/90 line-clamp-2 leading-tight mb-1 break-all overflow-hidden"
                                            title="{{ $label }}">
{{--
                                            @if ($isHit)
                                                <span
                                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success/20 text-success mr-1.5 align-middle">
                                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                                                </span>
                                            @endif
--}}
                                            {{ $displayLabel }}
                                        </h3>
                                        <div class="flex items-center gap-2 text-[10px] text-base-content/60">
                                            @if ($formattedSize)
                                                <span>{{ $formattedSize }}</span>
                                            @endif
                                            @if (isset($file['created_at']))
                                                <span>•</span>
                                                <span>{{ \Carbon\Carbon::parse($file['created_at'])->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Download Button (Primary) --}}
                                    <div class="tooltip tooltip-left" data-tip="{{ $downloadTooltip }}">
                                        <a href="{{ $finalDownloadUrl }}"
                                            class="btn btn-circle btn-sm bg-base-100 border border-base-300 text-base-content/60 hover:text-primary hover:border-primary/50 hover:bg-primary/5 shadow-sm transition-all -mt-1 -mr-1"
                                            x-on:click.stop="handleDownload($event, {{ $fileId }}, '{{ $finalDownloadUrl }}')"
                                            download>
                                            <i class="fa-solid fa-download text-xs"></i>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                </div>
            @endif

        </div>{{-- x-show wrapper end --}}

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

    {{-- 「もっと見る」ボタン --}}
    @if ($hiddenCount > 0)
        <div
            class="{{ $isCompact || $isIconOnly ? '' : 'col-span-full' }} flex justify-center {{ $isCompact || $isIconOnly ? 'ml-1' : 'mt-2' }}">

            @if ($isIconOnly)
                {{-- Icon Only モードの「+N」 --}}
                <button type="button" x-on:click="toggleShowAll()"
                    class="badge badge-ghost badge-md cursor-pointer hover:bg-base-300 font-bold"
                    :class="{ '!badge-primary text-primary-content!': showAll }" role="button"
                    aria-label="{{ __('ledger.show_more') }}">
                    <span x-show="!showAll">+{{ $hiddenCount }}</span>
                    <i x-show="showAll" class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>
            @elseif($isCompact)
                <button type="button" x-on:click="toggleShowAll()"
                    class="btn btn-ghost btn-xs text-xs font-normal text-base-content/60 gap-1">
                    <span
                        x-text="showAll ? '{{ __('ledger.collapse') }}' : '+{{ $hiddenCount }} {{ __('ledger.records') }}'"></span>
                </button>
            @else
                {{-- Full モード --}}
                <button type="button" x-on:click="toggleShowAll()" class="btn btn-ghost btn-sm gap-2">
                    <span
                        x-text="showAll ? '{{ __('ledger.collapse') }}' : '{{ __('ledger.show_more') }} (+{{ $hiddenCount }})'"></span>
                    <i class="fa-solid" :class="showAll ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
            @endif
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
