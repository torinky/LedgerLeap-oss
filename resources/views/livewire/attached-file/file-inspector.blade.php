<div x-data="{
    open: @entangle('open'),
    isLoading: @entangle('isLoading')
}" @keydown.escape.window="open = false; $wire.close()"
    @keydown.tab.prevent="
            if (open) {
                let focusable = $el.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex=\'-1\'])');
                let first = focusable[0];
                let last = focusable[focusable.length - 1];
                if ($event.shiftKey) {
                    if (document.activeElement === first) {
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        first.focus();
                    }
                }
            }
        "
    x-init="$watch('open', value => { if (value && $refs.closeButton) $nextTick(() => $refs.closeButton.focus()) })"
    @open-file-inspector.window="open = true; isLoading = true; console.log('FileInspector received event:', $event.detail); $wire.openInspector($event.detail)">
    {{-- DaisyUI Drawer --}}
    <div class="drawer drawer-end z-50" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <input type="checkbox" id="file-inspector-drawer" class="drawer-toggle" x-model="open" />

        {{-- Overlay --}}
        <div class="drawer-side">
            <label for="file-inspector-drawer" aria-label="close sidebar" class="drawer-overlay"
                @click="open = false; $wire.close()"></label>

            {{-- Drawer content --}}
            <div class="min-h-full w-full md:w-[28rem] lg:w-[32rem] bg-base-100 flex flex-col shadow-2xl">
                {{-- Skeleton UI (Alpine controlled) --}}
                <div x-show="isLoading" class="flex flex-col flex-1 h-full">
                    {{-- Skeleton UI Header --}}
                    <div class="navbar bg-base-200 border-b border-base-300 min-h-[4rem] px-4 flex-none animate-pulse">
                        <div class="flex-1">
                            <div class="h-5 bg-base-300 rounded w-3/4 mb-2"></div>
                            <div class="h-3 bg-base-300 rounded w-1/2"></div>
                        </div>
                        <div class="flex-none">
                            <div class="w-8 h-8 bg-base-300 rounded-circle"></div>
                        </div>
                    </div>
                    <div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none animate-pulse">
                        <div class="h-8 bg-base-300 rounded flex-1"></div>
                        <div class="w-8 h-8 bg-base-300 rounded"></div>
                        <div class="w-8 h-8 bg-base-300 rounded"></div>
                    </div>
                    {{-- Skeleton Main Content with Spinner --}}
                    <div class="flex-1 p-4 space-y-4 animate-pulse relative overflow-hidden">
                        {{-- Central Spinner --}}
                        <div class="absolute inset-0 flex items-center justify-center bg-base-100/30 z-10">
                            <div class="flex flex-col items-center gap-2">
                                <span class="loading loading-spinner loading-lg text-primary"></span>
                                <span
                                    class="text-xs font-bold text-base-content/60">{{ __('ledger.file_inspector.loading') }}</span>
                            </div>
                        </div>

                        <div class="h-48 bg-base-200 rounded w-full"></div>
                        <div class="space-y-2">
                            <div class="h-4 bg-base-200 rounded w-full"></div>
                            <div class="h-4 bg-base-200 rounded w-5/6"></div>
                            <div class="h-4 bg-base-200 rounded w-4/6"></div>
                        </div>
                        <div class="h-32 bg-base-200 rounded w-full"></div>
                    </div>
                </div>

                {{-- Actual Content (Alpine controlled) --}}
                <div x-show="!isLoading" class="flex flex-col flex-1 h-full" x-cloak>
                    @if ($file)
                        {{-- Header --}}
                        <div class="navbar bg-base-200 border-b border-base-300 min-h-[4rem] px-4 flex-none">
                            <div class="flex-1">
                                <div class="flex flex-col gap-1">
                                    <h2 id="drawer-title" class="text-base font-bold truncate line-clamp-1"
                                        title="{{ $file->original_filename ?? ($file->filename ?? __('ledger.file_inspector.title')) }}">
                                        <i class="fa-solid fa-file-lines mr-2 text-primary"></i>
                                        {{ \Illuminate\Support\Str::limit($file?->original_filename ?? ($file?->filename ?? __('ledger.file_inspector.title')), 30) }}
                                    </h2>
                                    @php
                                        $mockLedgerTitle = !empty($mockData)
                                            ? $mockData['mock_ledger_title'] ?? null
                                            : null;
                                        $mockFolderPath = !empty($mockData)
                                            ? $mockData['mock_folder_path'] ?? null
                                            : null;
                                    @endphp
                                    @if ($file && ($mockLedgerTitle || ($file->ledger ?? null)))
                                        <div class="text-xs text-base-content/60 flex items-center gap-2">
                                            <i class="fa-solid fa-folder text-warning text-[10px]"></i>
                                            <span
                                                class="truncate">{{ \Illuminate\Support\Str::limit($mockFolderPath ?? ($file->ledger?->folder?->title ?? ''), 40) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-none">
                                <button x-ref="closeButton" class="btn btn-ghost btn-sm btn-circle"
                                    @click="open = false; $wire.close()"
                                    aria-label="{{ __('ledger.file_inspector.close') }}">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Quick actions bar --}}
                        <div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none"
                            x-data="{
                                downloadingOriginal: false,
                                downloadingPdf: false,
                                handleDownload(type) {
                                    if (type === 'original') {
                                        this.downloadingOriginal = true;
                                        setTimeout(() => { this.downloadingOriginal = false; }, 3000);
                                    } else {
                                        this.downloadingPdf = true;
                                        setTimeout(() => { this.downloadingPdf = false; }, 3000);
                                    }
                                }
                            }">
                            @php
                                // ファイルタイプ判定
                                $isImageFile =
                                    $file &&
                                    str_starts_with($file->original_mime_type ?? ($file->mime ?? ''), 'image/');
                                $isPdfFile =
                                    $file && ($file->original_mime_type ?? ($file->mime ?? '')) === 'application/pdf';
                                $hasOcrProcessed = $file && $file->ocr_processed_at;
                                $isMockFile = $file && $file->id >= 1 && $file->id <= 12;

                                // オリジナルファイルのダウンロードURL
                                $originalUrl = $isMockFile
                                    ? '#download-original-' . $file->id
                                    : route('file.download', [
                                        'tenant' => tenant()?->id,
                                        'attachedFile' => $file->id ?? 0,
                                        'original' => true,
                                    ]);

                                // OCR PDF（変換/最適化PDF）のダウンロードURL
                                $ocrPdfUrl = null;
                                if ($hasOcrProcessed && ($isImageFile || $isPdfFile)) {
                                    $ocrPdfUrl = $isMockFile
                                        ? '#download-ocr-pdf-' . $file->id
                                        : route('files.download-ocr-pdf', [
                                            'tenant' => tenant('id'),
                                            'attachedFile' => $file->id,
                                        ]);
                                }
                            @endphp

                            {{-- オリジナルファイルダウンロードボタン --}}
                            <a href="{{ $originalUrl }}"
                                class="btn btn-sm gap-2 tooltip tooltip-bottom {{ $ocrPdfUrl ? 'btn-ghost flex-1' : 'btn-primary flex-1' }}"
                                data-tip="{{ $isImageFile ? __('ledger.file_inspector.actions.download_original_image') : __('ledger.file_inspector.actions.download_original') }}"
                                @click="handleDownload('original')" :disabled="downloadingOriginal">
                                <span x-show="downloadingOriginal" class="loading loading-spinner loading-xs"></span>
                                <i class="fa-solid fa-file-image"
                                    x-show="!downloadingOriginal && {{ $isImageFile ? 'true' : 'false' }}"></i>
                                <i class="fa-solid fa-file-pdf"
                                    x-show="!downloadingOriginal && {{ $isPdfFile ? 'true' : 'false' }}"></i>
                                <i class="fa-solid fa-file"
                                    x-show="!downloadingOriginal && {{ !$isImageFile && !$isPdfFile ? 'true' : 'false' }}"></i>
                                <span
                                    class="hidden sm:inline">{{ __('ledger.file_inspector.actions.original') }}</span>
                            </a>

                            {{-- OCR変換/最適化PDFダウンロードボタン（ある場合のみ） --}}
                            @if ($ocrPdfUrl)
                                <a href="{{ $ocrPdfUrl }}"
                                    class="btn btn-primary btn-sm flex-1 gap-2 tooltip tooltip-bottom"
                                    data-tip="{{ $isImageFile ? __('ledger.file_inspector.actions.download_converted_pdf') : __('ledger.file_inspector.actions.download_optimized_pdf') }}"
                                    @click="handleDownload('pdf')" :disabled="downloadingPdf">
                                    <span x-show="downloadingPdf" class="loading loading-spinner loading-xs"></span>
                                    <i class="fa-solid fa-file-pdf" x-show="!downloadingPdf"></i>
                                    <span
                                        class="hidden sm:inline">{{ $isImageFile ? 'PDF' : __('ledger.file_inspector.actions.optimized') }}</span>
                                </a>
                            @endif

                            {{-- その他のアクションボタン --}}
                            <button class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                                data-tip="{{ __('ledger.file_inspector.actions.copy_link') }}" x-data="{}"
                                @click="navigator.clipboard.writeText('{{ $originalUrl }}').then(() => alert('{{ __('ledger.file_inspector.messages.link_copied') }}'))">
                                <i class="fa-solid fa-link"></i>
                            </button>
                            <a href="{{ $originalUrl }}"
                                class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                                data-tip="{{ __('ledger.file_inspector.actions.open_new_tab') }}" target="_blank">
                                <i class="fa-solid fa-external-link-alt"></i>
                            </a>
                        </div>

                        {{-- Preview Area --}}
                        @if ($this->showPreview)
                            <div class="bg-base-200/50 border-b border-base-300 flex-none">
                                @if ($this->isImage)
                                    <div class="relative aspect-video bg-base-300">
                                        <img src="{{ $this->previewUrl }}"
                                            alt="{{ $file?->original_filename ?? 'Preview' }}"
                                            class="w-full h-full object-contain" loading="lazy">
                                        <div class="absolute top-2 right-2">
                                            <button
                                                class="btn btn-xs btn-circle btn-ghost bg-base-100/90 hover:bg-base-100 shadow-lg tooltip tooltip-left"
                                                data-tip="{{ __('ledger.file_inspector.actions.zoom') }}"
                                                @click="window.open('{{ $this->previewUrl }}', '_blank')">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                @elseif($this->isPdf)
                                    <div class="relative aspect-video bg-base-300 flex items-center justify-center">
                                        @if ($file && $file->id >= 1 && $file->id <= 12)
                                            <div class="text-center p-6">
                                                <div
                                                    class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-error/10 mb-4">
                                                    <i class="fa-solid fa-file-pdf text-4xl text-error"></i>
                                                </div>
                                                <p class="text-sm font-medium text-base-content mb-1">
                                                    {{ __('ledger.file_inspector.preview.pdf_preview') }}</p>
                                                <p class="text-xs text-base-content/60 mb-4">
                                                    {{ number_format(($file->size ?? 0) / 1024, 1) }}
                                                    KB</p>
                                                <button class="btn btn-sm btn-outline gap-2"
                                                    @click="window.open('{{ $this->previewUrl }}', '_blank')">
                                                    <i class="fa-solid fa-external-link-alt"></i>
                                                    {{ __('ledger.file_inspector.preview.open_new_tab') }}
                                                </button>
                                            </div>
                                        @else
                                            <iframe src="{{ $this->previewUrl }}" class="w-full h-full border-0"
                                                title="PDF Preview"></iframe>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Tabs & Content --}}
                        <div class="flex-1 overflow-hidden flex flex-col">
                            <x-mary-tabs wire:model="selectedTab" tabsClass="tabs tabs-lift tabs-xl mt-2">
                                <x-mary-tab name="content" label="{{ __('ledger.file_inspector.tabs.content') }}"
                                    icon="o-document-text" class="tab-lg gap-2">
                                    {{-- Content Tab --}}
                                    <div class="p-4 space-y-4">
                                        @php
                                            $previewText = $this->getPreviewText();
                                            $previewTextRaw = $this->getPreviewText(false);
                                            $hasPreviewText = $file && !empty($previewTextRaw);
                                            $confidence =
                                                (!empty($mockData) ? $mockData['mock_confidence'] ?? null : null) ??
                                                ($file->vlm_confidence ?? null);
                                            $source = $activeSource;
                                            $activeStatus = $this->getSourceStatus($source);
                                            $isProcessing = $activeStatus === 'processing';
                                            $isError =
                                                $activeStatus === 'error' ||
                                                ($file &&
                                                    empty($mockData) &&
                                                    !$file->finalized_source &&
                                                    $activeStatus === 'missing' &&
                                                    $source === 'vlm');
                                            // 補足: 初期表示でソースが全くない場合（ID 7など）も考慮

                                            $limit = 10000;
                                            $canExpand = mb_strlen($previewTextRaw) > $limit;
                                        @endphp

                                        {{-- Search & Source Selector (Always visible if loaded) --}}
                                        <div
                                            class="flex flex-col sm:flex-row gap-2 mb-4 bg-base-200 p-2 rounded-lg border border-base-300">
                                            <div class="flex-1">
                                                <x-mary-input wire:model.live.debounce.300ms="searchKeyword"
                                                    icon="o-magnifying-glass"
                                                    placeholder="{{ __('ledger.file_inspector.search.placeholder') }}"
                                                    class="input-sm" clearable />
                                            </div>
                                            <div class="flex items-center gap-1 p-1 bg-base-300 rounded-lg w-fit shrink-0"
                                                x-data="{ switchingSource: null }"
                                                @source-switched.window="switchingSource = null">
                                                @foreach (['vlm', 'ocr', 'tika', 'structured'] as $src)
                                                    @php
                                                        $status = $this->getSourceStatus($src);
                                                        $isActive = $activeSource === $src;
                                                        $hasContent = $status === 'completed';
                                                        $isProcessingNow = $status === 'processing';

                                                        $tooltip = match ($status) {
                                                            'processing' => __(
                                                                'ledger.file_inspector.status.processing',
                                                            ),
                                                            'missing' => __('ledger.file_inspector.status.no_text'),
                                                            'error' => __('ledger.file_inspector.status.error'),
                                                            default => '',
                                                        };
                                                    @endphp
                                                    <div class="{{ $tooltip ? 'tooltip tooltip-bottom' : '' }}"
                                                        data-tip="{{ $tooltip }}">
                                                        <button wire:click="switchSource('{{ $src }}')"
                                                            @click="switchingSource = '{{ $src }}'"
                                                            class="btn btn-xs {{ $isActive ? 'btn-primary' : 'btn-ghost' }} gap-1 relative"
                                                            @if (!$hasContent || $isProcessingNow) disabled @endif
                                                            x-bind:disabled="switchingSource === '{{ $src }}' ||
                                                                {{ !$hasContent || $isProcessingNow ? 'true' : 'false' }}">
                                                            <span x-show="switchingSource !== '{{ $src }}'">
                                                                @if ($isProcessingNow)
                                                                    <i
                                                                        class="fa-solid fa-spinner fa-spin text-[10px] mr-1"></i>
                                                                @endif
                                                                {{ __('ledger.file_inspector.source.' . $src) }}
                                                            </span>
                                                            <span x-show="switchingSource === '{{ $src }}'"
                                                                x-cloak class="flex items-center gap-1">
                                                                <i class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                                                                {{ __('ledger.file_inspector.source.' . $src) }}
                                                            </span>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if ($isProcessing)
                                            <div class="alert alert-warning shadow-lg">
                                                <i class="fa-solid fa-spinner fa-spin text-xl"></i>
                                                <div>
                                                    <div class="font-semibold text-sm">
                                                        {{ __('ledger.file_inspector.status.processing') }}</div>
                                                    <div class="text-xs">
                                                        {{ __('ledger.file_inspector.status.processing_message') }}
                                                    </div>
                                                    <progress class="progress progress-warning w-full mt-2"
                                                        value="65" max="100"></progress>
                                                </div>
                                            </div>
                                        @elseif($isError)
                                            <div class="alert alert-error shadow-lg">
                                                <i class="fa-solid fa-exclamation-triangle text-xl"></i>
                                                <div>
                                                    <div class="font-semibold text-sm">
                                                        {{ __('ledger.file_inspector.status.error') }}</div>
                                                    <div class="text-xs">
                                                        {{ __('ledger.file_inspector.status.error_message') }}
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            @if ($hasPreviewText)
                                                {{-- OCR処理後のファイルダウンロードUI --}}
                                                @php
                                                    $isImageFile = str_starts_with(
                                                        $file?->original_mime_type ?? '',
                                                        'image/',
                                                    );
                                                    $isPdfFile =
                                                        ($file?->original_mime_type ?? ($file?->mime ?? '')) ===
                                                        'application/pdf';
                                                    $hasOcrProcessed = $file && ($file->ocr_processed_at ?? false);

                                                    $ocrPdfUrl = null;
                                                    if ($hasOcrProcessed && $file) {
                                                        if ($isImageFile || $isPdfFile) {
                                                            $ocrPdfUrl = route('files.download-ocr-pdf', [
                                                                'tenant' => tenant('id'),
                                                                'attachedFile' => $file->id,
                                                            ]);
                                                        }
                                                    }
                                                @endphp

                                                {{-- デバッグ用: 変数の内容を表示 --}}
                                                {{--
                                    @if (config('app.debug'))
                                        <div class="alert alert-warning text-xs">
                                            <div class="font-mono">
                                                <strong>デバッグ情報:</strong><br>
                                                File ID: {{ $file?->id }}<br>
                                                MIME: {{ $file?->original_mime_type ?? $file?->mime }}<br>
                                                isImageFile: {{ $isImageFile ? 'true' : 'false' }}<br>
                                                isPdfFile: {{ $isPdfFile ? 'true' : 'false' }}<br>
                                                ocr_processed_at: {{ $file?->ocr_processed_at ?? 'null' }}<br>
                                                hasOcrProcessed: {{ $hasOcrProcessed ? 'true' : 'false' }}<br>
                                                ocrPdfUrl: {{ $ocrPdfUrl ?? 'null' }}
                                            </div>
                                        </div>
                                    @endif
--}}


                                                @if ($hasOcrProcessed && $ocrPdfUrl)
                                                    <div class="alert alert-info shadow-sm py-2 px-4 mb-4"
                                                        x-data="{
                                                            downloading: false,
                                                            handleDownload() {
                                                                this.downloading = true;
                                                                setTimeout(() => { this.downloading = false; }, 3000);
                                                            }
                                                        }">
                                                        <i class="fa-solid fa-file-pdf text-xl"></i>
                                                        <div class="flex-1">
                                                            <h3 class="font-semibold text-xs">
                                                                @if ($isImageFile)
                                                                    {{ __('ledger.file_inspector.ocr.image_to_pdf_title') }}
                                                                @else
                                                                    {{ __('ledger.file_inspector.ocr.optimized_pdf_title') }}
                                                                @endif
                                                            </h3>
                                                            <p class="text-[10px] opacity-70 leading-tight">
                                                                @if ($isImageFile)
                                                                    {{ __('ledger.file_inspector.ocr.image_info') }}
                                                                @else
                                                                    {{ __('ledger.file_inspector.ocr.pdf_info') }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <a href="{{ $ocrPdfUrl }}"
                                                            class="btn btn-xs btn-primary gap-1"
                                                            @click="handleDownload()" :disabled="downloading">
                                                            <span x-show="downloading"
                                                                class="loading loading-spinner loading-xs"></span>
                                                            <i class="fa-solid fa-download" x-show="!downloading"></i>
                                                            <span>{{ __('ledger.file_inspector.actions.download') }}</span>
                                                        </a>
                                                    </div>
                                                @endif

                                                {{-- Source Selector --}}
                                                {{-- The old source selector was here and has been replaced by the new combined search/source selector above --}}

                                                @php
                                                    $badge = $file?->getConfidenceBadgeInfo();
                                                @endphp
                                                @if ($badge)
                                                    <div class="stats shadow w-full">
                                                        <div class="stat p-3">
                                                            <div class="stat-title text-xs">
                                                                {{ __('ledger.file_inspector.info.last_extraction') }}
                                                            </div>
                                                            <div class="stat-value text-lg flex items-center gap-2">
                                                                <span class="badge badge-{{ $badge['color'] }}">
                                                                    {{ $badge['label'] }}
                                                                </span>
                                                                @if ($badge['score'])
                                                                    <span class="text-sm">{{ $badge['score'] }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="stat-desc flex items-center gap-1">
                                                                @if ($badge['color'] === 'success')
                                                                    <i
                                                                        class="fa-solid fa-check-circle text-success"></i>
                                                                @elseif($badge['color'] === 'warning')
                                                                    <i class="fa-solid fa-shield-check text-info"></i>
                                                                @else
                                                                    <i
                                                                        class="fa-solid fa-exclamation-triangle text-warning"></i>
                                                                @endif
                                                                <span
                                                                    class="text-{{ $badge['color'] }}">{{ $badge['tooltip'] }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="form-control" x-data="{
                                                    copying: null,
                                                    downloading: null,
                                                    copyText() {
                                                        const contentEl = this.$refs.previewContent;
                                                        const text = contentEl?.dataset?.text || '';
                                                
                                                        if (!text) {
                                                            $dispatch('mary-toast', { type: 'error', title: '{{ __('ledger.file_inspector.messages.copy_failed') }}' });
                                                            return;
                                                        }
                                                
                                                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                                            navigator.clipboard.writeText(text)
                                                                .then(() => this.onCopySuccess('text'))
                                                                .catch(() => this.fallbackCopy(text, 'text'));
                                                        } else {
                                                            this.fallbackCopy(text, 'text');
                                                        }
                                                    },
                                                    fallbackCopy(text, id) {
                                                        const textarea = document.createElement('textarea');
                                                        textarea.value = text;
                                                        textarea.style.position = 'fixed';
                                                        textarea.style.opacity = '0';
                                                        document.body.appendChild(textarea);
                                                        textarea.select();
                                                        try {
                                                            document.execCommand('copy');
                                                            this.onCopySuccess(id);
                                                        } catch (err) {
                                                            console.error('Copy failed', err);
                                                            $dispatch('mary-toast', { type: 'error', title: '{{ __('ledger.file_inspector.messages.copy_failed') }}' });
                                                        }
                                                        document.body.removeChild(textarea);
                                                    },
                                                    onCopySuccess(id) {
                                                        this.copying = id;
                                                        setTimeout(() => { this.copying = null; }, 2000);
                                                        const toastTitle = id === 'json' ?
                                                            '{{ __('ledger.file_inspector.messages.json_copied') }}' :
                                                            '{{ __('ledger.file_inspector.messages.text_copied') }}';
                                                        $dispatch('mary-toast', { type: 'success', title: toastTitle });
                                                    },
                                                    copyAsJson() {
                                                        const contentEl = this.$refs.previewContent;
                                                        const text = contentEl?.dataset?.text || '';
                                                
                                                        if (!text) {
                                                            $dispatch('mary-toast', { type: 'error', title: '{{ __('ledger.file_inspector.messages.copy_failed') }}' });
                                                            return;
                                                        }
                                                
                                                        const jsonData = {
                                                            filename: '{{ $file?->original_filename ?? '' }}',
                                                            source: '{{ $activeSource }}',
                                                            content: text,
                                                            confidence: {{ $file?->vlm_confidence ?? 0 }},
                                                            model: '{{ $file?->vlm_model ?? '' }}',
                                                            processed_at: '{{ $file?->vlm_processed_at ?? '' }}'
                                                        };
                                                
                                                        const jsonString = JSON.stringify(jsonData, null, 2);
                                                
                                                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                                            navigator.clipboard.writeText(jsonString)
                                                                .then(() => this.onCopySuccess('json'))
                                                                .catch(() => this.fallbackCopy(jsonString, 'json'));
                                                        } else {
                                                            this.fallbackCopy(jsonString, 'json');
                                                        }
                                                    },
                                                    downloadFile(type) {
                                                        const contentEl = this.$refs.previewContent;
                                                        let text = contentEl?.dataset?.text || '';
                                                
                                                        if (!text) {
                                                            $dispatch('mary-toast', { type: 'error', title: '{{ __('ledger.file_inspector.messages.download_failed') }}' });
                                                            return;
                                                        }
                                                
                                                        if (type === 'json') {
                                                            const jsonData = {
                                                                filename: '{{ $file?->original_filename ?? '' }}',
                                                                source: '{{ $activeSource }}',
                                                                content: text,
                                                                processed_at: new Date().toISOString()
                                                            };
                                                            text = JSON.stringify(jsonData, null, 2);
                                                        }
                                                
                                                        const blob = new Blob([text], { type: type === 'json' ? 'application/json' : 'text/plain' });
                                                        const url = window.URL.createObjectURL(blob);
                                                        const a = document.createElement('a');
                                                        a.href = url;
                                                        const ext = type === 'json' ? '.json' : (type === 'markdown' ? '.md' : '.txt');
                                                        a.download = '{{ $file?->original_filename ?? 'extracted' }}' + ext;
                                                        document.body.appendChild(a);
                                                        a.click();
                                                        window.URL.revokeObjectURL(url);
                                                        document.body.removeChild(a);
                                                    }
                                                }">
                                                    <label class="label">
                                                        <span class="label-text font-semibold flex items-center gap-2">
                                                            <i class="fa-solid fa-align-left text-primary/50"></i>
                                                            {{ __('ledger.file_inspector.tabs.content') }}
                                                        </span>
                                                        @if ($activeSource === 'vlm')
                                                            <span
                                                                class="badge badge-outline badge-xs opacity-50">{{ __('ledger.file_inspector.source.vlm') }}</span>
                                                        @elseif($activeSource === 'ocr')
                                                            <span
                                                                class="badge badge-outline badge-xs opacity-50">{{ __('ledger.file_inspector.source.ocr') }}</span>
                                                        @endif
                                                    </label>

                                                    {{-- Search hit feedback --}}
                                                    @if ($searchKeyword)
                                                        <div class="mb-2 flex items-center gap-2">
                                                            @if ($this->hasKeywordHit)
                                                                <span
                                                                    class="badge badge-success badge-sm gap-1 text-white">
                                                                    <i class="fa-solid fa-check"></i>
                                                                    {{ __('ledger.file_inspector.search.hit') }}
                                                                </span>
                                                            @else
                                                                <span class="badge badge-warning badge-sm gap-1">
                                                                    <i class="fa-solid fa-circle-exclamation"></i>
                                                                    {{ __('ledger.file_inspector.search.no_hit') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @php
                                                        // プレーンテキスト（ハイライトなし）を取得
                                                        $plainText = $this->getPreviewText(false);
                                                    @endphp

                                                    <div x-ref="previewContent" data-text="{{ $plainText }}"
                                                        class="bg-base-200/50 p-4 rounded-lg border border-base-300 overflow-y-auto max-h-[500px] min-h-[256px] relative shadow-inner">
                                                        @if ($activeSource === 'structured')
                                                            {{-- 構造化データ表示 --}}
                                                            <pre class="text-xs overflow-auto bg-base-300 p-3 rounded-lg"><code class="language-json">{!! $this->previewText !!}</code></pre>
                                                        @elseif ($activeSource === 'vlm')
                                                            <div class="prose prose-sm max-w-none">
                                                                {!! Str::markdown($this->previewText ?? '') !!}
                                                            </div>
                                                        @else
                                                            <pre class="text-xs font-mono leading-relaxed whitespace-pre-wrap text-base-content">{!! $this->previewText !!}</pre>
                                                        @endif

                                                        @if ($this->canExpand && !$isExpanded)
                                                            <div
                                                                class="absolute bottom-0 left-0 right-0 h-24 bg-linear-to-t from-base-100 to-transparent flex items-end justify-center pb-4">
                                                                <button wire:click="toggleExpand"
                                                                    class="btn btn-sm btn-primary shadow-lg">
                                                                    <i class="fa-solid fa-arrows-up-down"></i>
                                                                    {{ __('ledger.file_inspector.actions.show_all') }}
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if ($isExpanded)
                                                        <div class="flex justify-center mt-2">
                                                            <button wire:click="toggleExpand"
                                                                class="btn btn-xs btn-ghost gap-1">
                                                                <i class="fa-solid fa-compress"></i>
                                                                {{ __('ledger.file_inspector.actions.show_less') }}
                                                            </button>
                                                        </div>
                                                    @endif

                                                    <div class="flex flex-wrap gap-6 mt-6">
                                                        {{-- Copy Actions Group --}}
                                                        <div class="flex flex-col gap-1.5">
                                                            <span
                                                                class="text-[10px] font-bold opacity-60 px-1 flex items-center gap-1">
                                                                <i
                                                                    class="fa-solid fa-copy"></i>{{ __('ledger.file_inspector.actions.copy') }}
                                                            </span>
                                                            <div class="join border border-base-300">
                                                                <button @click="copyText()"
                                                                    class="btn btn-sm join-item gap-1 tooltip tooltip-bottom transition-all duration-300 min-w-[7.5rem]"
                                                                    :class="copying === 'text' ? 'btn-success text-white' :
                                                                        'btn-outline border-none'"
                                                                    data-tip="{{ __('ledger.file_inspector.actions.copy_text') }}">
                                                                    <i class="fa-solid"
                                                                        :class="copying === 'text' ? 'fa-check' :
                                                                            'fa-file-lines'"></i>
                                                                    <span
                                                                        x-text="copying === 'text' ? '{{ __('ledger.vlm.copied_short') }}' : '{{ __('ledger.file_inspector.actions.text_format') }}'"></span>
                                                                </button>

                                                                @if ($activeSource === 'vlm')
                                                                    <button @click="copyAsJson()"
                                                                        class="btn btn-sm join-item gap-1 tooltip tooltip-bottom transition-all duration-300 min-w-[6.5rem]"
                                                                        :class="copying === 'json' ?
                                                                            'btn-success text-white' :
                                                                            'btn-outline border-none'"
                                                                        data-tip="{{ __('ledger.file_inspector.actions.copy_json') }}">
                                                                        <i class="fa-solid"
                                                                            :class="copying === 'json' ? 'fa-check' :
                                                                                'fa-code'"></i>
                                                                        <span
                                                                            x-text="copying === 'json' ? '{{ __('ledger.vlm.copied_short') }}' : 'JSON'"></span>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        {{-- Download Actions Group --}}
                                                        <div class="flex flex-col gap-1.5">
                                                            <span
                                                                class="text-[10px] font-bold opacity-60 px-1 flex items-center gap-1">
                                                                <i
                                                                    class="fa-solid fa-file-arrow-down"></i>{{ __('ledger.file_inspector.actions.download') }}
                                                            </span>
                                                            <div class="join">
                                                                <button
                                                                    @click="downloading = 'text'; downloadFile('text'); setTimeout(() => downloading = null, 1500)"
                                                                    class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[7.5rem]"
                                                                    data-tip="{{ __('ledger.file_inspector.actions.download_text') }}">
                                                                    <span x-show="downloading === 'text'"
                                                                        class="loading loading-spinner loading-xs"></span>
                                                                    <i x-show="downloading !== 'text'"
                                                                        class="fa-solid fa-file-lines"></i>
                                                                    <span>{{ __('ledger.file_inspector.actions.text_format') }}</span>
                                                                </button>

                                                                @if ($activeSource === 'vlm')
                                                                    @php
                                                                        $isSaved = $tenantId && $file && $file->exists;
                                                                    @endphp
                                                                    @if ($isSaved)
                                                                        <a href="{{ route('files.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'markdown']) }}"
                                                                            class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[7.5rem]"
                                                                            target="_blank"
                                                                            data-attribute-downloading="false"
                                                                            @click="this.dataset.downloading = 'true'; setTimeout(() => this.dataset.downloading = 'false', 2000)"
                                                                            data-tip="{{ __('ledger.file_inspector.actions.download_markdown') }}">
                                                                            <i
                                                                                class="fa-brands fa-markdown opacity-70"></i>
                                                                            <span>{{ __('ledger.file_inspector.actions.markdown_format') }}</span>
                                                                        </a>
                                                                        <a href="{{ route('files.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'json']) }}"
                                                                            class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[5rem]"
                                                                            target="_blank"
                                                                            data-tip="{{ __('ledger.file_inspector.actions.download_json') }}">
                                                                            <i class="fa-solid fa-code opacity-70"></i>
                                                                            <span>JSON</span>
                                                                        </a>
                                                                    @else
                                                                        {{-- Fallback for mock or unsaved files --}}
                                                                        <button
                                                                            @click="downloading = 'markdown'; downloadFile('markdown'); setTimeout(() => downloading = null, 1500)"
                                                                            class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[7.5rem]"
                                                                            data-tip="{{ __('ledger.file_inspector.actions.download_markdown') }}">
                                                                            <span x-show="downloading === 'markdown'"
                                                                                class="loading loading-spinner loading-xs"></span>
                                                                            <i x-show="downloading !== 'markdown'"
                                                                                class="fa-brands fa-markdown opacity-70"></i>
                                                                            <span>{{ __('ledger.file_inspector.actions.markdown_format') }}</span>
                                                                        </button>
                                                                        <button
                                                                            @click="downloading = 'json'; downloadFile('json'); setTimeout(() => downloading = null, 1500)"
                                                                            class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[5.5rem]"
                                                                            data-tip="{{ __('ledger.file_inspector.actions.download_json') }}">
                                                                            <span x-show="downloading === 'json'"
                                                                                class="loading loading-spinner loading-xs"></span>
                                                                            <i x-show="downloading !== 'json'"
                                                                                class="fa-solid fa-code opacity-70"></i>
                                                                            <span>JSON</span>
                                                                        </button>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="alert shadow-lg">
                                                    <i class="fa-solid fa-info-circle"></i>
                                                    <div class="text-sm">
                                                        {{ __('ledger.file_inspector.status.no_text') }}
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                </x-mary-tab>
                                <x-mary-tab name="details" label="{{ __('ledger.file_inspector.tabs.details') }}"
                                    icon="o-information-circle" class="tab-lg gap-2">
                                    {{-- Details Tab --}}
                                    <div class="px-6 py-4 space-y-8 pb-10">
                                        {{-- 1. ファイル基本情報 --}}
                                        <section>
                                            <h3
                                                class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
                                                <i class="fa-solid fa-circle-info text-primary"></i>
                                                {{ __('ledger.file_inspector.info.file_properties') }}
                                            </h3>
                                            <div class="overflow-x-auto">
                                                <table class="table table-xs table-fixed w-full text-base-content">
                                                    <tbody class="whitespace-normal wrap-break-word">
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0 w-32">
                                                                {{ __('ledger.file_inspector.info.filename') }}</th>
                                                            <td class="font-medium text-right break-all border-0 pr-0">
                                                                {{ $file->filename }}</td>
                                                        </tr>
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                {{ __('ledger.file_inspector.info.size') }}</th>
                                                            <td class="font-mono text-right border-0 pr-0">
                                                                {{ \Illuminate\Support\Number::fileSize($file->size ?? 0, precision: 2) }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                {{ __('ledger.file_inspector.info.format') }}</th>
                                                            <td class="text-right border-0 pr-0">
                                                                <span
                                                                    class="font-mono uppercase break-all whitespace-normal">

                                                                    {{ $file->original_mime_type ? \Illuminate\Support\Str::after($file->original_mime_type, '/') : ($file->mime ? \Illuminate\Support\Str::after($file->mime, '/') : '-') }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                {{ __('ledger.file_inspector.info.uploaded_at') }}
                                                            </th>
                                                            <td class="text-right border-0 pr-0 text-[11px]">
                                                                {{ $file->created_at?->format('Y/m/d H:i') ?: '-' }}
                                                            </td>
                                                        </tr>
                                                        @if ($file->metadata_date)
                                                            <tr>
                                                                <th
                                                                    class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                    {{ __('ledger.file_inspector.info.file_creation_date') }}
                                                                </th>
                                                                <td
                                                                    class="text-right border-0 pr-0 text-primary font-medium text-[11px]">
                                                                    {{ $file->metadata_date->format('Y/m/d H:i') }}
                                                                </td>
                                                            </tr>
                                                        @endif
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                {{ __('ledger.file_inspector.info.file_last_updated_at') }}
                                                            </th>
                                                            <td class="text-right border-0 pr-0 text-[11px]">
                                                                {{ $file->updated_at?->format('Y/m/d H:i') ?: '-' }}
                                                            </td>
                                                        </tr>
                                                        {{-- Uploader / Modifier --}}
                                                        <tr>
                                                            <th
                                                                class="opacity-60 whitespace-nowrap font-normal border-0 pl-0 pt-3">
                                                                {{ __('ledger.file_inspector.info.creator') }}</th>
                                                            <td class="text-right border-0 pr-0 pt-3">
                                                                <div
                                                                    class="flex items-center justify-end gap-1.5 font-medium text-[11px]">
                                                                    @if ($mockCreatorName)
                                                                        <x-mary-avatar :title="$mockCreatorName"
                                                                            class="w-4! h-4!" />
                                                                        <span>{{ $mockCreatorName }}</span>
                                                                    @else
                                                                        <x-mary-avatar :title="$file->creator?->name ?: 'System'"
                                                                            class="w-4! h-4!" />
                                                                        <span>{{ $file->creator?->name ?: 'System' }}</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @if (($mockCreatorName && !$file->modifier) || ($file->modifier && $file->modifier_id !== $file->creator_id))
                                                            <tr>
                                                                <th
                                                                    class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                                                    {{ __('ledger.file_inspector.info.modifier') }}
                                                                </th>
                                                                <td class="text-right border-0 pr-0">
                                                                    <div
                                                                        class="flex items-center justify-end gap-1.5 font-medium text-[11px]">
                                                                        @if ($mockCreatorName)
                                                                            <x-mary-avatar :title="$mockCreatorName"
                                                                                class="w-4! h-4!" />
                                                                            <span>{{ $mockCreatorName }}</span>
                                                                        @else
                                                                            <x-mary-avatar :title="$file->modifier->name"
                                                                                class="w-4! h-4!" />
                                                                            <span>{{ $file->modifier->name }}</span>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>

                                        {{-- 2. OCR処理済みPDF (ある場合のみ) --}}
                                        @php
                                            $isImageFile = str_starts_with($file?->original_mime_type ?? '', 'image/');
                                            $isPdfFile =
                                                ($file?->original_mime_type ?? ($file?->mime ?? '')) ===
                                                'application/pdf';
                                            $hasOcrProcessed = $file && ($file->ocr_processed_at ?? false);
                                        @endphp

                                        @if ($hasOcrProcessed && ($isImageFile || $isPdfFile))
                                            <section>
                                                <h3
                                                    class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
                                                    <i class="fa-solid fa-file-pdf text-error"></i>
                                                    @if ($isImageFile)
                                                        {{ __('ledger.file_inspector.ocr.converted_pdf') }}
                                                    @else
                                                        {{ __('ledger.file_inspector.ocr.optimized_pdf') }}
                                                    @endif
                                                </h3>
                                                <div class="card bg-base-200 border border-base-300">
                                                    <div class="card-body p-4" x-data="{
                                                        downloading: false,
                                                        handleDownload() {
                                                            this.downloading = true;
                                                            setTimeout(() => { this.downloading = false; }, 3000);
                                                        }
                                                    }">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-3">
                                                                <div
                                                                    class="w-10 h-10 bg-error/10 rounded-lg flex items-center justify-center">
                                                                    <i
                                                                        class="fa-solid fa-file-pdf text-xl text-error"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="font-medium text-xs">
                                                                        @if ($isImageFile)
                                                                            {{ pathinfo($file?->original_filename ?? '', PATHINFO_FILENAME) }}
                                                                            .pdf
                                                                        @else
                                                                            {{ $file?->original_filename ?? 'document.pdf' }}
                                                                        @endif
                                                                    </p>
                                                                    <p
                                                                        class="text-[10px] text-base-content/60 flex items-center gap-2 mt-0.5">
                                                                        <span
                                                                            class="badge badge-xs badge-info text-[8px] h-3 px-1">OCR完成</span>
                                                                        <span>{{ $file?->ocr_processed_at?->diffForHumans() ?? '' }}</span>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col gap-1">
                                                                <a href="{{ route('files.download-ocr-pdf', ['tenant' => tenant('id'), 'attachedFile' => $file->id]) }}"
                                                                    class="btn btn-xs btn-primary gap-1"
                                                                    @click="handleDownload()" :disabled="downloading">
                                                                    <span x-show="downloading"
                                                                        class="loading loading-spinner loading-xs"></span>
                                                                    <i class="fa-solid fa-download"
                                                                        x-show="!downloading"></i>
                                                                    {{ __('ledger.file_inspector.actions.download') }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>
                                        @endif

                                        {{-- 3. 処理速度ベンチマーク --}}
                                        <section>
                                            <h3
                                                class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
                                                <i class="fa-solid fa-bolt text-info"></i>
                                                {{ __('ledger.file_inspector.info.benchmarks') }}
                                            </h3>
                                            <div class="grid grid-cols-1 gap-2">
                                                @php
                                                    $tikaTime = $file?->calculateProcessingDuration('tika');
                                                    $ocrTime = $file?->calculateProcessingDuration('ocr');
                                                    $vlmTime = $file?->calculateProcessingDuration('vlm');
                                                @endphp

                                                <div
                                                    class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                                                    <span class="flex items-center gap-2 opacity-70">
                                                        <i class="fa-solid fa-file-import w-4"></i>
                                                        {{ __('ledger.file_inspector.source.tika') }}
                                                    </span>
                                                    <span
                                                        class="font-mono">{{ $tikaTime ? number_format($tikaTime / 1000, 2) . 's' : '-' }}</span>
                                                </div>

                                                <div
                                                    class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                                                    <span class="flex items-center gap-2 opacity-70">
                                                        <i class="fa-solid fa-font w-4"></i>
                                                        {{ __('ledger.file_inspector.source.ocr') }}
                                                    </span>
                                                    <span
                                                        class="font-mono">{{ $ocrTime ? number_format($ocrTime / 1000, 2) . 's' : '-' }}</span>
                                                </div>

                                                <div
                                                    class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                                                    <span class="flex items-center gap-2 opacity-70">
                                                        <i class="fa-solid fa-robot w-4"></i>
                                                        {{ __('ledger.file_inspector.source.vlm') }}
                                                    </span>
                                                    <span
                                                        class="font-mono">{{ $vlmTime ? number_format($vlmTime / 1000, 2) . 's' : '-' }}</span>
                                                </div>
                                            </div>
                                        </section>

                                        {{-- 4. 台帳情報 --}}
                                        <section>
                                            <h3
                                                class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
                                                <i class="fa-solid fa-database text-warning"></i>
                                                {{ __('ledger.file_inspector.info.source_ledger') }}
                                            </h3>
                                            <div class="card bg-base-200/50 border border-base-300 shadow-sm">
                                                <div class="card-body p-3">
                                                    <div class="flex items-start gap-3">
                                                        <div
                                                            class="flex-none w-10 h-10 bg-base-300 rounded flex items-center justify-center">
                                                            <i class="fa-solid fa-table-list text-lg opacity-50"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-xs font-bold truncate">
                                                                {{ $mockLedgerTitle ?? ($file->ledger?->define?->title ?? 'N/A') }}
                                                            </p>
                                                            <div class="flex items-center gap-1 mt-0.5">
                                                                <i
                                                                    class="fa-solid fa-folder text-warning text-[10px]"></i>
                                                                <p class="text-[10px] opacity-60 truncate">
                                                                    {{ $mockFolderPath ?? ($file->ledger?->folder?->full_path ?? '-') }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="flex-none">
                                                            @if (!$mockData && $file->ledger_id)
                                                                <x-mary-button icon="o-arrow-top-right-on-square"
                                                                    link="{{ route('ledgersByDefineId', ['tenant' => tenant('id'), 'defineId' => $file->ledger?->define?->id]) }}"
                                                                    class="btn-xs btn-ghost btn-circle"
                                                                    target="_blank" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                </x-mary-tab>

                                <x-mary-tab name="history" label="{{ __('ledger.file_inspector.tabs.history') }}"
                                    icon="o-clock" class="tab-lg gap-2">
                                    {{-- History Tab --}}
                                    <div class="p-4 space-y-4" x-data="{
                                        showAllLogs: false,
                                        showAllActivity: false,
                                        maxInitialLogs: 3,
                                        maxInitialActivity: 5
                                    }">
                                        @if (empty($mockData) && $file && $file->exists)
                                            {{-- DYNAMIC CONTENT --}}
                                            <div>
                                                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                    <i class="fa-solid fa-list-check text-success"></i>
                                                    {{ __('ledger.file_inspector.history.processing_log') }}
                                                </h3>
                                                @php
                                                    $sysEvents = $file->system_timeline ?? collect();
                                                @endphp
                                                @if ($sysEvents->isEmpty())
                                                    <div
                                                        class="alert alert-ghost text-xs shadow-sm border border-base-200">
                                                        <i class="fa-solid fa-circle-info text-info"></i>
                                                        <span>{{ __('ledger.file_inspector.history.no_system_logs') }}</span>
                                                    </div>
                                                @else
                                                    {{-- System Logs List --}}
                                                    <div class="relative">
                                                        <div class="overflow-y-auto"
                                                            :class="showAllLogs ? 'max-h-96' : 'max-h-64'"
                                                            style="scrollbar-width: thin;">
                                                            <ul class="steps steps-vertical text-sm w-full">
                                                                @foreach ($sysEvents as $index => $event)
                                                                    <li class="step step-{{ $event['color'] }} min-h-[4rem]"
                                                                        @if ($index >= 3) x-show="showAllLogs" x-cloak @endif>
                                                                        <div class="text-left ml-3 w-full">
                                                                            <div
                                                                                class="font-semibold flex items-center gap-2">
                                                                                <x-mary-icon
                                                                                    name="{{ $event['icon'] }}"
                                                                                    class="w-4 h-4 opacity-70" />
                                                                                {{ $event['title'] }}
                                                                            </div>
                                                                            <div class="text-xs text-base-content/60">
                                                                                {{ \Carbon\Carbon::parse($event['timestamp'])->format('Y-m-d H:i:s') }}
                                                                            </div>
                                                                            @if ($event['description'])
                                                                                <div
                                                                                    class="text-xs text-base-content/70 mt-0.5">
                                                                                    {{ $event['description'] }}
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                        @if ($sysEvents->count() > 3)
                                                            <div class="absolute bottom-0 left-0 right-0 h-8 bg-linear-to-t from-base-100 to-transparent pointer-events-none"
                                                                x-show="!showAllLogs"></div>
                                                        @endif
                                                    </div>

                                                    @if ($sysEvents->count() > 3)
                                                        <div class="mt-3 text-center">
                                                            <button @click="showAllLogs = !showAllLogs"
                                                                class="btn btn-ghost btn-sm gap-2 text-primary hover:text-primary-focus">
                                                                <template x-if="!showAllLogs">
                                                                    <span>
                                                                        <i class="fa-solid fa-chevron-down"></i>
                                                                        {{ __('ledger.show_more') }}
                                                                    </span>
                                                                </template>
                                                                <template x-if="showAllLogs">
                                                                    <span>
                                                                        <i class="fa-solid fa-chevron-up"></i>
                                                                        {{ __('ledger.show_less') }}
                                                                    </span>
                                                                </template>
                                                            </button>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="divider"></div>

                                            <div>
                                                <div class="flex items-center justify-between mb-3">
                                                    <h3 class="text-sm font-semibold flex items-center gap-2">
                                                        <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                                                        {{ __('ledger.file_inspector.history.activity') }}
                                                    </h3>
                                                    <span
                                                        class="text-xs text-base-content/50">{{ __('ledger.file_inspector.history.recent_30days') }}</span>
                                                </div>
                                                @php
                                                    $usrEvents = $file->user_timeline ?? collect();
                                                @endphp
                                                @if ($usrEvents->isEmpty())
                                                    <div
                                                        class="alert alert-ghost text-xs shadow-sm border border-base-200">
                                                        <i class="fa-solid fa-circle-info text-info"></i>
                                                        <span>{{ __('ledger.file_inspector.history.no_user_activity') }}</span>
                                                    </div>
                                                @else
                                                    <div class="relative">
                                                        <div class="space-y-2 overflow-y-auto"
                                                            :class="showAllActivity ? 'max-h-96' : 'max-h-64'"
                                                            style="scrollbar-width: thin;">
                                                            @foreach ($usrEvents as $index => $activity)
                                                                <div class="card card-compact bg-base-200 hover:bg-base-300 transition-colors"
                                                                    @if ($index >= 5) x-show="showAllActivity" x-cloak @endif>
                                                                    <div class="card-body">
                                                                        <div class="flex items-center justify-between">
                                                                            <div class="flex items-center gap-2">
                                                                                <x-mary-icon
                                                                                    name="o-{{ $activity['icon'] }}"
                                                                                    class="text-{{ $activity['color'] }} w-4 h-4" />
                                                                                <span
                                                                                    class="font-medium text-sm">{{ $activity['title'] }}</span>
                                                                            </div>
                                                                            <div class="text-xs text-base-content/60">
                                                                                {{ \Carbon\Carbon::parse($activity['timestamp'])->format('Y-m-d H:i') }}
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-xs text-base-content/70 mt-1">
                                                                            {{ $activity['user'] }}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        @if ($usrEvents->count() > 5)
                                                            <div class="absolute bottom-0 left-0 right-0 h-8 bg-linear-to-t from-base-100 to-transparent pointer-events-none"
                                                                x-show="!showAllActivity"></div>
                                                        @endif
                                                    </div>

                                                    @if ($usrEvents->count() > 5)
                                                        <div class="mt-3 text-center">
                                                            <button @click="showAllActivity = !showAllActivity"
                                                                class="btn btn-ghost btn-sm gap-2 text-primary hover:text-primary-focus">
                                                                <template x-if="!showAllActivity">
                                                                    <span>
                                                                        <i class="fa-solid fa-chevron-down"></i>
                                                                        {{ __('ledger.show_more') }}
                                                                    </span>
                                                                </template>
                                                                <template x-if="showAllActivity">
                                                                    <span>
                                                                        <i class="fa-solid fa-chevron-up"></i>
                                                                        {{ __('ledger.show_less') }}
                                                                    </span>
                                                                </template>
                                                            </button>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        @else
                                            {{-- MOCK NOTICE --}}
                                            <div class="alert alert-info shadow-sm">
                                                <i class="fa-solid fa-circle-info"></i>
                                                <span>{{ __('ledger.file_inspector.history.mock_notice') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </x-mary-tab>

                                <x-mary-tab name="permissions" label="{{ __('file.inspector.tabs.permissions') }}"
                                    icon="o-shield-check" class="tab-lg gap-2">
                                    {{-- Permissions Tab --}}
                                    <div class="px-6 py-4 space-y-6 pb-10">
                                        {{-- 1. 権限概要 --}}
                                        <section>
                                            <h3
                                                class="text-xs font-bold mb-3 flex items-center gap-2 text-base-content/50 uppercase tracking-wider">
                                                <i class="fa-solid fa-user-shield"></i>
                                                {{ __('file.inspector.permissions.summary_title') }}
                                            </h3>

                                            <div
                                                class="card bg-base-200 border border-base-300 shadow-sm overflow-hidden">
                                                <div
                                                    class="p-4 flex items-center justify-between bg-primary/5 border-b border-primary/10">
                                                    <div class="flex items-center gap-3">
                                                        <div class="avatar placeholder">
                                                            <div
                                                                class="bg-primary text-primary-content rounded-full w-10">
                                                                <span
                                                                    class="text-xs font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <p class="text-sm font-bold">{{ auth()->user()->name }}
                                                            </p>
                                                            <p class="text-[10px] opacity-60">
                                                                {{ auth()->user()->email }}</p>
                                                        </div>
                                                    </div>
                                                    @php
                                                        $highestPerm =
                                                            $this->userPermissions['folder_permission'] ?? 'none';
                                                        $badgeColor = match ($highestPerm) {
                                                            'admin' => 'error',
                                                            'approve' => 'warning',
                                                            'inspect' => 'info',
                                                            'write' => 'primary',
                                                            'read' => 'success',
                                                            default => 'ghost',
                                                        };
                                                    @endphp
                                                    <div class="badge badge-{{ $badgeColor }} font-bold p-3">
                                                        {{ __('file.inspector.permissions.levels.' . $highestPerm) }}
                                                    </div>
                                                </div>

                                                <div class="p-4 bg-base-100">
                                                    <div class="grid grid-cols-2 gap-4">
                                                        @foreach (['read', 'write', 'download', 'delete'] as $perm)
                                                            <div class="flex items-center justify-between">
                                                                <span
                                                                    class="text-xs opacity-70">{{ __('file.inspector.permissions.' . $perm) }}</span>
                                                                @if ($this->userPermissions[$perm])
                                                                    <i
                                                                        class="fa-solid fa-check-circle text-success text-xs"></i>
                                                                @else
                                                                    <i
                                                                        class="fa-solid fa-times-circle text-base-content/20 text-xs"></i>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        {{-- 2. アクションセクション --}}
                                        <section class="mt-8">
                                            <h3
                                                class="text-xs font-bold mb-3 flex items-center gap-2 text-base-content/50 uppercase tracking-wider">
                                                <i class="fa-solid fa-bolt"></i>
                                                {{ __('file.inspector.actions.title') }}
                                            </h3>

                                            <div class="space-y-3">
                                                {{-- 全処理を再実行 --}}
                                                <div class="card bg-base-200 border border-base-300">
                                                    <div class="p-3 flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="w-8 h-8 rounded-lg bg-warning/10 flex items-center justify-center">
                                                                <i class="fa-solid fa-rotate text-warning"></i>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs font-bold">
                                                                    {{ __('file.inspector.actions.retry_all') }}</p>
                                                                <p class="text-[10px] opacity-60">
                                                                    {{ __('file.inspector.actions.retry_all_description') }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <x-mary-button wire:click="retryProcessing"
                                                            wire:confirm="{{ __('file.inspector.messages.retry_confirm') }}"
                                                            class="btn-xs btn-outline btn-warning" :disabled="!$this->userPermissions['retry']">
                                                            {{ __('file.inspector.actions.execute') }}
                                                        </x-mary-button>
                                                    </div>
                                                </div>

                                                {{-- VLM再処理 (管理者) --}}
                                                @if ($this->userPermissions['is_admin'])
                                                    <div class="card bg-base-200 border border-base-300">
                                                        <div class="p-3 flex items-center justify-between">
                                                            <div class="flex items-center gap-3">
                                                                <div
                                                                    class="w-8 h-8 rounded-lg bg-error/10 flex items-center justify-center">
                                                                    <i class="fa-solid fa-robot text-error"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs font-bold">
                                                                        {{ __('file.inspector.actions.vlm_retry') }}
                                                                    </p>
                                                                    <p class="text-[10px] opacity-60">
                                                                        {{ __('file.inspector.actions.vlm_retry_description') }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <x-mary-button wire:click="retryVlmProcessing"
                                                                wire:confirm="{{ __('file.inspector.messages.vlm_retry_confirm') }}"
                                                                class="btn-xs btn-outline btn-error" :disabled="!$this->userPermissions['admin_retry']">
                                                                {{ __('file.inspector.actions.execute') }}
                                                            </x-mary-button>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </section>

                                        {{-- 3. 注意事項 --}}
                                        <div class="alert alert-ghost border border-base-300 bg-base-200/50 p-3 mt-4">
                                            <i class="fa-solid fa-circle-info text-info"></i>
                                            <div class="text-[10px] opacity-70">
                                                {{ __('file.inspector.permissions.delete_notice') }}
                                            </div>
                                        </div>
                                    </div>
                                </x-mary-tab>
                            </x-mary-tabs>

                        </div>

                        {{-- Footer --}}
                        <div
                            class="navbar navbar-center bg-base-200 border-t border-base-300 min-h-[3.5rem] px-4 flex-none">
                            <div class="navbar-start">
                                <span class="text-xs text-base-content/60">ID: {{ $file?->id ?? 0 }}</span>
                            </div>
                            <div class="navbar-end gap-2">
                                <button class="btn btn-warning btn-sm btn-square tooltip"
                                    data-tip="{{ __('ledger.file_inspector.actions.reprocess') }}"
                                    @if (!($file && ($file->id >= 1 && $file->id <= 12))) disabled @endif>
                                    <i class="fa-solid fa-refresh"></i>
                                </button>
                                <button class="btn btn-error btn-sm btn-square tooltip"
                                    data-tip="{{ __('ledger.file_inspector.actions.delete') }}"
                                    @if (!($file && ($file->id >= 1 && $file->id <= 12))) disabled @endif>
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endif
                </div> {{-- Added closing div for x-show="!isLoading" --}}
            </div>
        </div>
    </div>
</div>
