@props([
    'file' => [],
    'index' => 0,
    'displayLimit' => 8,
    'search' => null,
    'selectedFileId' => null,
])

@php
    use App\Helpers\MimeTypeHelper;
    use App\Helpers\SearchHelper;
    use Illuminate\Support\Number;

    $mime = $file['mime'] ?? '';
    $fileInfo = MimeTypeHelper::getInfo($mime);
    $iconClass = $fileInfo['icon'] . ' ' . $fileInfo['color'];

    $status = $file['status'] ?? 'completed';

    // ダウンロードリンク構造への対応
    $primaryDownload = $file['primary_download'] ?? null;
    $downloadUrl = '#';

    if ($primaryDownload && is_array($primaryDownload)) {
        $downloadUrl = $primaryDownload['url'];
    } elseif (isset($file['downloadUrl']) && is_string($file['downloadUrl'])) {
        $downloadUrl = $file['downloadUrl'];
    }

    $label = $file['filename'] ?? 'file';
    $fileId = (int) ($file['id'] ?? 0);
    $fileColumnId = $file['column_id'] ?? null;
    $isSelectedFile = $selectedFileId !== null && (int) $selectedFileId === $fileId;
    $fileSize = $file['size'] ?? null;

    $formattedSize = '';
    if ($fileSize) {
        $formattedSize =
            class_exists(Number::class) && method_exists(Number::class, 'fileSize')
                ? Number::fileSize($fileSize, 2)
                : number_format($fileSize / 1024, 1) . ' KB';
    }

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

    $isOptimized = $status === 'completed' && (isset($file['ocr_processed_at']) || isset($file['secondary_download']));
    $isHit = $file['is_hit'] ?? false;
    $fullTooltip = $label . ($formattedSize ? " ($formattedSize)" : '');

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

<div class="relative tooltip tooltip-bottom h-full"
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    data-tip="{{ $fullTooltip }}">

    <div class="card bg-base-100 shadow-sm hover:shadow-xl transition-all duration-300 {{ $isHit ? 'card-bordered border-success ring-1 ring-success bg-success/5 shadow-lg shadow-success/10' : 'card-bordered border-base-200 hover:border-primary/30' }} {{ $isSelectedFile ? 'ring-2 ring-primary/60 bg-primary/5 border-primary/40' : '' }} group cursor-pointer h-full flex flex-col"
        role="listitem" x-data="{ imageLoading: true, imageError: false }"
        x-on:click="handleFileClick({{ $fileId }}, {{ json_encode($fileColumnId) }})" tabindex="0"
        aria-label="{{ $label }} ({{ $statusLabel }})">

        {{-- バッジインジケーター --}}
        @if ($isHit)
            <div
                class="absolute top-2 left-2 z-10 inline-flex items-center justify-center w-6 h-6 rounded-full bg-success/90 text-base-100 shadow-lg">
                <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
            </div>
        @endif

        @if ($isOptimized)
            <div
                class="absolute top-2 right-2 z-10 inline-flex items-center justify-center w-6 h-6 rounded-full bg-success/70 text-base-100 shadow-md">
                <i class="fa-solid fa-check text-[10px]"></i>
            </div>
        @endif

        {{-- RPA用: 透過的ダウンロードリンク --}}
        <a href="{{ $downloadUrl }}" class="direct-download-link sr-only"
            aria-label="{{ __('ledger.download') }}: {{ $label }}" tabindex="-1" download></a>

        {{-- 画像/アイコンエリア --}}
        <figure
            class="h-40 shrink-0 bg-base-200/50 flex items-center justify-center relative overflow-hidden group-hover:bg-base-200 transition-colors rounded-t-box">
            @if ($isProcessing)
                <div class="flex flex-col items-center gap-2">
                    <span class="loading loading-spinner loading-md text-warning"></span>
                    <span class="text-xs text-base-content/60">{{ __('ledger.file_status.processing') }}</span>
                </div>
            @elseif($isError)
                <div class="text-error flex flex-col items-center gap-2" @click.stop>
                    <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                    <span class="text-xs font-bold">{{ __('ledger.file_status.error') }}</span>
                    @if ($fileId)
                        <button wire:click="$dispatch('retry-file-processing', { fileId: {{ $fileId }} })"
                            class="btn btn-xs btn-error btn-outline gap-1 mt-1" @click.stop>
                            <i class="fa-solid fa-rotate-right text-[10px]"></i>
                            <span>{{ __('ledger.file_inspector.actions.reprocess') }}</span>
                        </button>
                    @endif
                </div>
            @else
                @if (Str::startsWith($mime, 'image/'))
                    @php
                        // サムネイルURLがない場合は、primary_downloadのURLを使用
                        $imageUrl = $file['thumbnailUrl'] ?? ($file['primary_download']['url'] ?? null);
                    @endphp
                    @if ($imageUrl)
                        <div x-show="imageLoading"
                            class="absolute inset-0 flex items-center justify-center bg-base-200">
                            <span class="loading loading-spinner loading-xs text-primary/40"></span>
                        </div>
                        <img src="{{ $imageUrl }}" alt="{{ $label }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy" x-show="!imageError" x-on:load="imageLoading = false"
                            x-on:error="imageLoading = false; imageError = true">
                        <div x-show="imageError" class="flex flex-col items-center text-base-content/40">
                            <i class="fa-regular fa-image text-3xl mb-1"></i>
                            <span class="text-[10px]">No Preview</span>
                        </div>
                    @else
                        <div
                            class="transform transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                            <i class="{{ $iconClass }} text-5xl opacity-80"></i>
                        </div>
                    @endif
                @else
                    <div
                        class="transform transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                        <i class="{{ $iconClass }} text-5xl opacity-80"></i>
                    </div>
                @endif
            @endif
        </figure>

        {{-- フッター --}}
        <div class="px-3 py-2 flex-1 flex flex-col relative">
            <div class="flex justify-between items-start gap-2 flex-1">
                <div class="min-w-0 flex-1 flex flex-col">
                    <h3 class="text-sm font-semibold text-base-content/90 line-clamp-2 leading-tight mb-1 break-all overflow-hidden"
                        title="{{ $label }}">
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

                {{-- ダウンロードボタン --}}
                <div class="tooltip tooltip-left flex-none" data-tip="{{ $downloadTooltip }}">
                    <a href="{{ $finalDownloadUrl }}"
                        class="btn btn-circle btn-sm bg-base-100 border border-base-300 text-base-content/60 hover:text-primary hover:border-primary/50 hover:bg-primary/5 shadow-sm transition-all -mt-1 -mr-1 relative overflow-hidden"
                        x-on:click="handleDownload($event, {{ $fileId }}, '{{ $finalDownloadUrl }}')" download>
                        <span x-show="loadingFiles[{{ $fileId }}]"
                            class="loading loading-spinner loading-xs text-primary scale-75"></span>
                        <i x-show="successFiles[{{ $fileId }}]"
                            class="fa-solid fa-check text-xs text-success"></i>
                        <i x-show="!loadingFiles[{{ $fileId }}] && !successFiles[{{ $fileId }}]"
                            class="fa-solid fa-download text-xs"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
