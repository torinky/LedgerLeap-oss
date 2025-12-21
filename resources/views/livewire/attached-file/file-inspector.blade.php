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
    x-init="$watch('open', value => { if (value) $nextTick(() => $refs.closeButton.focus()) })"
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
                                        title="{{ $file?->original_filename ?? ($file?->filename ?? __('ledger.file_inspector.title')) }}">
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
                        <div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none">
                            @php
                                $downloadUrl =
                                    $file && $file->id >= 1 && $file->id <= 12
                                        ? '#download-' . $file->id
                                        : route('file.download', [
                                            'tenant' => tenant()?->id,
                                            'attachedFile' => $file?->id ?? 0,
                                        ]);
                            @endphp
                            <a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm flex-1 gap-2" download>
                                <i class="fa-solid fa-download"></i>
                                <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.download') }}</span>
                            </a>
                            <button class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                                data-tip="{{ __('ledger.file_inspector.actions.copy_link') }}" x-data="{}"
                                @click="navigator.clipboard.writeText('{{ $downloadUrl }}').then(() => alert('{{ __('ledger.file_inspector.messages.link_copied') }}'))">
                                <i class="fa-solid fa-link"></i>
                            </button>
                            <a href="{{ $downloadUrl }}"
                                class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                                data-tip="{{ __('ledger.file_inspector.actions.open_new_tab') }}" target="_blank">
                                <i class="fa-solid fa-external-link-alt"></i>
                            </a>
                        </div>

                        {{-- Preview Area --}}
                        @php
                            $mime = $file?->original_mime_type ?? ($file?->mime ?? '');
                            $isImage = str_starts_with($mime, 'image/');
                            $isPdf = $mime === 'application/pdf';
                            $showPreview = $isImage || $isPdf;
                            $previewUrl = null;
                            if ($file && $file->id >= 1 && $file->id <= 12) {
                                if ($isImage) {
                                    $previewUrl =
                                        'https://via.placeholder.com/600x400/4CAF50/FFFFFF?text=' .
                                        urlencode($file->original_filename ?? 'Image');
                                } elseif ($isPdf) {
                                    $previewUrl = '#pdf-preview';
                                }
                            }
                        @endphp

                        @if ($showPreview)
                            <div class="bg-base-200/50 border-b border-base-300 flex-none">
                                @if ($isImage)
                                    <div class="relative aspect-video bg-base-300">
                                        <img src="{{ $previewUrl }}"
                                            alt="{{ $file?->original_filename ?? 'Preview' }}"
                                            class="w-full h-full object-contain" loading="lazy">
                                        <div class="absolute top-2 right-2">
                                            <button
                                                class="btn btn-xs btn-circle btn-ghost bg-base-100/90 hover:bg-base-100 shadow-lg tooltip tooltip-left"
                                                data-tip="{{ __('ledger.file_inspector.actions.zoom') }}"
                                                @click="window.open('{{ $previewUrl }}', '_blank')">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                @elseif($isPdf)
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
                                                    @click="window.open('{{ $previewUrl }}', '_blank')">
                                                    <i class="fa-solid fa-external-link-alt"></i>
                                                    {{ __('ledger.file_inspector.preview.open_new_tab') }}
                                                </button>
                                            </div>
                                        @else
                                            <iframe src="{{ $previewUrl }}" class="w-full h-full border-0"
                                                title="PDF Preview"></iframe>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Tabs & Content --}}
                        <div class="flex-1 overflow-hidden flex flex-col">
                            <x-mary-tabs wire:model="selectedTab"
                                class="tabs tabs-boxed bg-base-200 m-0 p-1 rounded-none border-b border-base-300 flex-none">
                                <x-mary-tab name="content" label="{{ __('ledger.file_inspector.tabs.content') }}"
                                    icon="o-document-text" class="tab-lg gap-2">
                                    {{-- Content Tab --}}
                                    <div class="p-4 space-y-4">
                                        @php
                                            $previewText = $this->getPreviewText(true);
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
                                            <div
                                                class="flex items-center gap-1 p-1 bg-base-300 rounded-lg w-fit shrink-0">
                                                @foreach (['vlm', 'ocr', 'tika'] as $src)
                                                    @php
                                                        $status = $this->getSourceStatus($src);
                                                        $isActive = $activeSource === $src;
                                                        $isDisabled = $status === 'missing' || $status === 'error';
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
                                                        <button
                                                            wire:click="$set('activeSource', '{{ $src }}')"
                                                            @if ($isDisabled || $isProcessingNow) disabled @endif
                                                            class="btn btn-xs {{ $isActive ? 'btn-primary' : 'btn-ghost' }} gap-1">
                                                            @if ($isProcessingNow)
                                                                <i class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                                                            @endif
                                                            {{ __('ledger.file_inspector.source.' . $src) }}
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
                                                    if ($hasOcrProcessed) {
                                                        if ($isImageFile) {
                                                            $ocrPdfUrl = '#ocr-pdf-' . ($file->id ?? 0);
                                                        } elseif ($isPdfFile) {
                                                            $ocrPdfUrl = '#optimized-pdf-' . ($file->id ?? 0);
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
                                                    <div class="alert alert-info shadow-sm py-2 px-4 mb-4">
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
                                                            class="btn btn-xs btn-primary gap-1" download>
                                                            <i class="fa-solid fa-download"></i>
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
                                                    copied: false,
                                                    copyText() {
                                                        const text = this.$refs.rawContent.value;
                                                        if (!text) return;
                                                
                                                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                                            navigator.clipboard.writeText(text)
                                                                .then(() => this.onCopySuccess())
                                                                .catch(() => this.fallbackCopy(text));
                                                        } else {
                                                            this.fallbackCopy(text);
                                                        }
                                                    },
                                                    fallbackCopy(text) {
                                                        const textarea = document.createElement('textarea');
                                                        textarea.value = text;
                                                        textarea.style.position = 'fixed';
                                                        textarea.style.opacity = '0';
                                                        document.body.appendChild(textarea);
                                                        textarea.select();
                                                        try {
                                                            document.execCommand('copy');
                                                            this.onCopySuccess();
                                                        } catch (err) {
                                                            console.error('Copy failed', err);
                                                        }
                                                        document.body.removeChild(textarea);
                                                    },
                                                    onCopySuccess() {
                                                        this.copied = true;
                                                        setTimeout(() => { this.copied = false; }, 2000);
                                                        $dispatch('mary-toast', { type: 'success', title: '{{ __('ledger.file_inspector.messages.text_copied') }}' });
                                                    },
                                                    downloadFile(type) {
                                                        const text = this.$refs.rawContent.value;
                                                        const blob = new Blob([text], { type: 'text/plain' });
                                                        const url = window.URL.createObjectURL(blob);
                                                        const a = document.createElement('a');
                                                        a.href = url;
                                                        a.download = '{{ $file?->original_filename ?? 'extracted' }}' + (type === 'markdown' ? '.md' : '.txt');
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

                                                    <div x-ref="previewContent"
                                                        class="bg-base-200/50 p-4 rounded-lg border border-base-300 overflow-y-auto max-h-[500px] min-h-[256px] relative shadow-inner">
                                                        @if ($activeSource === 'vlm')
                                                            <div class="prose prose-sm max-w-none">
                                                                {!! Str::markdown($previewText ?? '') !!}
                                                            </div>
                                                        @else
                                                            <pre class="text-xs font-mono leading-relaxed whitespace-pre-wrap text-base-content">{!! $previewText !!}</pre>
                                                        @endif

                                                        @if ($canExpand && !$isExpanded)
                                                            <div
                                                                class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-base-100 to-transparent flex items-end justify-center pb-4">
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

                                                    <div class="flex flex-wrap gap-2 mt-4">
                                                        <button @click="copyText()"
                                                            class="btn btn-sm transition-all duration-300"
                                                            :class="copied ? 'btn-success text-white' : 'btn-outline gap-2'">
                                                            <i class="fa-solid"
                                                                :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                                            <span
                                                                x-text="copied ? '{{ __('ledger.vlm.copied_short') }}' : '{{ __('ledger.file_inspector.actions.copy_text') }}'"></span>
                                                        </button>
                                                        <button @click="downloadFile('text')"
                                                            class="btn btn-sm btn-outline gap-2">
                                                            <i class="fa-solid fa-download"></i>
                                                            <span>{{ __('ledger.file_inspector.actions.download_text') }}</span>
                                                        </button>
                                                        @if ($activeSource === 'vlm')
                                                            @if ($tenantId && $file && $file->exists)
                                                                <a href="{{ route('files.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'markdown']) }}"
                                                                    class="btn btn-sm btn-outline gap-2"
                                                                    target="_blank">
                                                                    <i class="fa-brands fa-markdown"></i>
                                                                    <span>Markdown</span>
                                                                </a>
                                                                <a href="{{ route('files.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'json']) }}"
                                                                    class="btn btn-sm btn-outline gap-2"
                                                                    target="_blank">
                                                                    <i class="fa-solid fa-code"></i>
                                                                    <span>JSON</span>
                                                                </a>
                                                            @else
                                                                {{-- モックまたはID未確定時はクライアントサイド生成で代替 --}}
                                                                <button @click="downloadFile('markdown')"
                                                                    class="btn btn-sm btn-outline gap-2">
                                                                    <i class="fa-brands fa-markdown"></i>
                                                                    <span>Markdown</span>
                                                                </button>
                                                            @endif
                                                        @endif
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
                                    <div class="p-4 space-y-4">
                                        <div>
                                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                <i class="fa-solid fa-file-circle-info text-primary"></i>
                                                {{ __('ledger.file_inspector.info.file_info') }}
                                            </h3>
                                            <div class="overflow-x-auto">
                                                <table class="table table-xs">
                                                    <tbody>
                                                        <tr>
                                                            <th class="w-1/3">
                                                                {{ __('ledger.file_inspector.info.size') }}
                                                            </th>
                                                            <td>{{ number_format(($file?->size ?? 0) / 1024, 1) }} KB
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>{{ __('ledger.file_inspector.info.format') }}</th>
                                                            <td>
                                                                <code
                                                                    class="text-xs">{{ $file?->original_mime_type ?? ($file?->mime ?? 'N/A') }}</code>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>{{ __('ledger.file_inspector.info.uploaded') }}</th>
                                                            <td>{{ $file?->created_at?->format('Y/m/d H:i') ?? 'N/A' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>{{ __('ledger.file_inspector.info.uploaded_by') }}</th>
                                                            <td>{{ $file?->creator?->name ?? ($file && $file->id >= 1 && $file->id <= 12 ? '山田太郎' : 'N/A') }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="divider"></div>

                                        {{-- OCR処理後のファイル情報 --}}
                                        @php
                                            $isImageFile = str_starts_with($file?->original_mime_type ?? '', 'image/');
                                            $isPdfFile =
                                                ($file?->original_mime_type ?? ($file?->mime ?? '')) ===
                                                'application/pdf';
                                            $hasOcrProcessed = $file && ($file->ocr_processed_at ?? false);
                                        @endphp

                                        @if ($hasOcrProcessed && ($isImageFile || $isPdfFile))
                                            <div>
                                                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                    <i class="fa-solid fa-file-pdf text-error"></i>
                                                    @if ($isImageFile)
                                                        {{ __('ledger.file_inspector.ocr.converted_pdf') }}
                                                    @else
                                                        {{ __('ledger.file_inspector.ocr.optimized_pdf') }}
                                                    @endif
                                                </h3>
                                                <div class="card bg-base-200 border border-base-300">
                                                    <div class="card-body p-4">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-3">
                                                                <div
                                                                    class="w-12 h-12 bg-error/10 rounded-lg flex items-center justify-center">
                                                                    <i
                                                                        class="fa-solid fa-file-pdf text-2xl text-error"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="font-medium text-sm">
                                                                        @if ($isImageFile)
                                                                            {{ pathinfo($file?->original_filename ?? '', PATHINFO_FILENAME) }}.pdf
                                                                        @else
                                                                            {{ $file?->original_filename ?? 'document.pdf' }}
                                                                        @endif
                                                                    </p>
                                                                    <p
                                                                        class="text-xs text-base-content/60 flex items-center gap-2 mt-1">
                                                                        <span
                                                                            class="badge badge-xs badge-info">OCR済み</span>
                                                                        <span>{{ $file?->ocr_processed_at?->diffForHumans() ?? '' }}</span>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col gap-2">
                                                                @php
                                                                    $ocrPdfUrl = $isImageFile
                                                                        ? '#ocr-pdf-' . ($file->id ?? 0)
                                                                        : '#optimized-pdf-' . ($file->id ?? 0);
                                                                @endphp
                                                                <a href="{{ $ocrPdfUrl }}"
                                                                    class="btn btn-sm btn-primary gap-2" download>
                                                                    <i class="fa-solid fa-download"></i>
                                                                    {{ __('ledger.file_inspector.actions.download') }}
                                                                </a>
                                                                <button class="btn btn-sm btn-ghost gap-2"
                                                                    @click="window.open('{{ $ocrPdfUrl }}', '_blank')">
                                                                    <i class="fa-solid fa-external-link-alt"></i>
                                                                    {{ __('ledger.file_inspector.ocr.preview') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3 pt-3 border-t border-base-300">
                                                            <p class="text-xs text-base-content/70">
                                                                @if ($isImageFile)
                                                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                                                    {{ __('ledger.file_inspector.ocr.image_info') }}
                                                                @else
                                                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                                                    {{ __('ledger.file_inspector.ocr.pdf_info') }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="divider"></div>
                                        @endif

                                        <div>
                                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                <i class="fa-solid fa-microchip text-info"></i>
                                                {{ __('ledger.file_inspector.info.processing_status') }}
                                            </h3>
                                            @php
                                                // ここの $activeStatus は activeSource（現在選択中）のもの
                                                // 全体的な状態を表示したい場合は activeSource ではなく 'tika' や 'vlm' などを固定するか、
                                                // 現在選択中のものの状態を表示する。ここでは「現在選択中の抽出ステータス」として整合性を取る。
                                                $isProcessing = $activeStatus === 'processing';
                                                $isError =
                                                    $activeStatus === 'error' ||
                                                    (empty($mockData) && $file && !$file->finalized_source);
                                            @endphp
                                            <div class="overflow-x-auto">
                                                <table class="table table-xs">
                                                    <tbody>
                                                        <tr>
                                                            <th class="w-1/3">
                                                                {{ __('ledger.file_inspector.info.status') }}
                                                            </th>
                                                            <td>
                                                                @if ($isProcessing)
                                                                    <span class="badge badge-warning badge-sm gap-1">
                                                                        <i class="fa-solid fa-spinner fa-spin"></i>
                                                                        {{ __('ledger.file_inspector.status.processing') }}
                                                                    </span>
                                                                @elseif($isError)
                                                                    <span class="badge badge-error badge-sm gap-1">
                                                                        <i
                                                                            class="fa-solid fa-exclamation-triangle"></i>
                                                                        {{ __('ledger.file_inspector.status.error') }}
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-success badge-sm gap-1">
                                                                        <i class="fa-solid fa-check"></i>
                                                                        {{ __('ledger.file_inspector.status.completed') }}
                                                                    </span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @php
                                                            $currentMockSource = !empty($mockData)
                                                                ? $mockData['mock_source'] ?? null
                                                                : null;
                                                        @endphp
                                                        @if ($currentMockSource)
                                                            <tr>
                                                                <th>{{ __('ledger.file_inspector.info.last_extraction') }}
                                                                </th>
                                                                <td>
                                                                    @if (strtolower($currentMockSource) === 'vlm')
                                                                        <span
                                                                            class="badge badge-success badge-sm">VLM</span>
                                                                    @elseif(strtolower($currentMockSource) === 'ocr')
                                                                        <span
                                                                            class="badge badge-info badge-sm">OCR</span>
                                                                    @else
                                                                        <span
                                                                            class="badge badge-primary badge-sm">Tika</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </x-mary-tab>
                                <x-mary-tab name="access" label="{{ __('ledger.file_inspector.tabs.access') }}"
                                    icon="o-shield-check" class="tab-lg gap-2">
                                    {{-- Access Tab --}}
                                    <div class="p-4 space-y-4">
                                        <div class="card bg-primary/5 border border-primary/20">
                                            <div class="card-body p-4">
                                                <h3 class="card-title text-sm text-primary mb-2">
                                                    <i class="fa-solid fa-user-shield"></i>
                                                    {{ __('ledger.file_inspector.access.your_permissions') }}
                                                </h3>
                                                <div class="grid grid-cols-2 gap-3 text-sm">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-eye text-success"></i>
                                                        <span>{{ __('ledger.file_inspector.access.view') }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-download text-success"></i>
                                                        <span>{{ __('ledger.file_inspector.access.download') }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 opacity-40">
                                                        <i class="fa-solid fa-pen"></i>
                                                        <span>{{ __('ledger.file_inspector.access.edit') }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 opacity-40">
                                                        <i class="fa-solid fa-trash"></i>
                                                        <span>{{ __('ledger.file_inspector.access.delete') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="divider"></div>

                                        <div>
                                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                <i class="fa-solid fa-sitemap text-info"></i>
                                                {{ __('ledger.file_inspector.access.org_role_settings') }}
                                            </h3>
                                            <div class="space-y-2">
                                                <div class="card card-compact bg-base-200">
                                                    <div class="card-body">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <div class="flex items-center gap-2">
                                                                <i class="fa-solid fa-building text-info"></i>
                                                                <span class="font-medium text-sm">総務部</span>
                                                            </div>
                                                            <span
                                                                class="badge badge-sm">{{ __('ledger.file_inspector.access.organization') }}</span>
                                                        </div>
                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                class="badge badge-success badge-sm">{{ __('ledger.file_inspector.access.admin') }}</span>
                                                            <span
                                                                class="badge badge-primary badge-sm">{{ __('ledger.file_inspector.access.write') }}</span>
                                                            <span
                                                                class="badge badge-info badge-sm">{{ __('ledger.file_inspector.access.read') }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                                        <div>
                                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                                <i class="fa-solid fa-list-check text-success"></i>
                                                {{ __('ledger.file_inspector.history.processing_log') }}
                                            </h3>

                                            {{-- スクロール可能なログエリア --}}
                                            <div class="relative">
                                                <div class="overflow-y-auto"
                                                    :class="showAllLogs ? 'max-h-96' : 'max-h-64'"
                                                    style="scrollbar-width: thin;">
                                                    <ul class="steps steps-vertical text-sm">
                                                        <li class="step step-success">
                                                            <div class="text-left ml-3">
                                                                <div class="font-semibold">
                                                                    {{ __('ledger.file_inspector.history.vlm_analysis') }}
                                                                </div>
                                                                <div class="text-xs text-base-content/60">2025-12-13
                                                                    10:45:23
                                                                </div>
                                                                <div class="text-xs text-base-content/70">
                                                                    {{ __('ledger.file_inspector.info.confidence') }}
                                                                    92.5% | 3.2秒
                                                                </div>
                                                            </div>
                                                        </li>
                                                        <li class="step step-success">
                                                            <div class="text-left ml-3">
                                                                <div class="font-semibold">
                                                                    {{ __('ledger.file_inspector.history.ocr_processing') }}
                                                                </div>
                                                                <div class="text-xs text-base-content/60">2025-12-13
                                                                    10:45:20
                                                                </div>
                                                                <div class="text-xs text-base-content/70">2.8秒</div>
                                                            </div>
                                                        </li>
                                                        <li class="step step-success">
                                                            <div class="text-left ml-3">
                                                                <div class="font-semibold">
                                                                    {{ __('ledger.file_inspector.history.tika_extraction') }}
                                                                </div>
                                                                <div class="text-xs text-base-content/60">2025-12-13
                                                                    10:45:17
                                                                </div>
                                                                <div class="text-xs text-base-content/70">1.5秒</div>
                                                            </div>
                                                        </li>
                                                        <li class="step step-success">
                                                            <div class="text-left ml-3">
                                                                <div class="font-semibold">
                                                                    {{ __('ledger.file_inspector.history.uploaded') }}
                                                                </div>
                                                                <div class="text-xs text-base-content/60">2025-12-13
                                                                    10:45:15
                                                                </div>
                                                                <div class="text-xs text-base-content/70">山田太郎</div>
                                                            </div>
                                                        </li>

                                                        {{-- 追加のモックデータ（実際には動的に生成） --}}
                                                        <template x-if="showAllLogs">
                                                            <div>
                                                                <li class="step step-info">
                                                                    <div class="text-left ml-3">
                                                                        <div class="font-semibold">
                                                                            {{ __('ledger.file_inspector.history.reprocess_log') }}
                                                                        </div>
                                                                        <div class="text-xs text-base-content/60">
                                                                            2025-12-12
                                                                            15:30:12</div>
                                                                        <div class="text-xs text-base-content/70">管理者
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="step step-warning">
                                                                    <div class="text-left ml-3">
                                                                        <div class="font-semibold">
                                                                            {{ __('ledger.file_inspector.history.retry') }}
                                                                        </div>
                                                                        <div class="text-xs text-base-content/60">
                                                                            2025-12-12
                                                                            15:29:45</div>
                                                                        <div class="text-xs text-base-content/70">
                                                                            信頼度低下により再実行
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="step step-success">
                                                                    <div class="text-left ml-3">
                                                                        <div class="font-semibold">
                                                                            {{ __('ledger.file_inspector.history.metadata_updated') }}
                                                                        </div>
                                                                        <div class="text-xs text-base-content/60">
                                                                            2025-12-10
                                                                            09:15:33</div>
                                                                        <div class="text-xs text-base-content/70">システム
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                            </div>
                                                        </template>
                                                    </ul>
                                                </div>

                                                {{-- グラデーションオーバーレイ（スクロール可能を示す） --}}
                                                <div class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-base-100 to-transparent pointer-events-none"
                                                    x-show="!showAllLogs"></div>
                                            </div>

                                            {{-- もっと見るボタン --}}
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

                                            {{-- スクロール可能なアクティビティエリア --}}
                                            <div class="relative">
                                                <div class="space-y-2 overflow-y-auto"
                                                    :class="showAllActivity ? 'max-h-96' : 'max-h-64'"
                                                    style="scrollbar-width: thin;">
                                                    <div
                                                        class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                        <div class="card-body">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-2">
                                                                    <i class="fa-solid fa-download text-primary"></i>
                                                                    <span
                                                                        class="font-medium text-sm">{{ __('ledger.file_inspector.history.downloaded') }}</span>
                                                                </div>
                                                                <span class="text-xs text-base-content/60">11:30</span>
                                                            </div>
                                                            <div class="text-xs text-base-content/70 mt-1">田中花子</div>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                        <div class="card-body">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-2">
                                                                    <i class="fa-solid fa-eye text-info"></i>
                                                                    <span
                                                                        class="font-medium text-sm">{{ __('ledger.file_inspector.history.viewed') }}</span>
                                                                </div>
                                                                <span class="text-xs text-base-content/60">11:15</span>
                                                            </div>
                                                            <div class="text-xs text-base-content/70 mt-1">佐藤次郎</div>
                                                        </div>
                                                    </div>

                                                    {{-- 追加のモックアクティビティ --}}
                                                    <template x-if="showAllActivity">
                                                        <div class="space-y-2">
                                                            <div
                                                                class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                                <div class="card-body">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center gap-2">
                                                                            <i
                                                                                class="fa-solid fa-share-nodes text-success"></i>
                                                                            <span
                                                                                class="font-medium text-sm">{{ __('ledger.file_inspector.history.shared') }}</span>
                                                                        </div>
                                                                        <span
                                                                            class="text-xs text-base-content/60">10:45</span>
                                                                    </div>
                                                                    <div class="text-xs text-base-content/70 mt-1">鈴木一郎
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                                <div class="card-body">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center gap-2">
                                                                            <i
                                                                                class="fa-solid fa-download text-primary"></i>
                                                                            <span
                                                                                class="font-medium text-sm">{{ __('ledger.file_inspector.history.downloaded') }}</span>
                                                                        </div>
                                                                        <span
                                                                            class="text-xs text-base-content/60">09:20</span>
                                                                    </div>
                                                                    <div class="text-xs text-base-content/70 mt-1">高橋美咲
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                                <div class="card-body">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center gap-2">
                                                                            <i class="fa-solid fa-eye text-info"></i>
                                                                            <span
                                                                                class="font-medium text-sm">{{ __('ledger.file_inspector.history.viewed') }}</span>
                                                                        </div>
                                                                        <span
                                                                            class="text-xs text-base-content/60">{{ __('ledger.file_inspector.history.yesterday') }}
                                                                            16:45</span>
                                                                    </div>
                                                                    <div class="text-xs text-base-content/70 mt-1">伊藤健太
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                                <div class="card-body">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center gap-2">
                                                                            <i
                                                                                class="fa-solid fa-pen text-warning"></i>
                                                                            <span
                                                                                class="font-medium text-sm">{{ __('ledger.file_inspector.history.edited') }}</span>
                                                                        </div>
                                                                        <span
                                                                            class="text-xs text-base-content/60">{{ __('ledger.file_inspector.history.yesterday') }}
                                                                            14:30</span>
                                                                    </div>
                                                                    <div class="text-xs text-base-content/70 mt-1">渡辺明子
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                                                <div class="card-body">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center gap-2">
                                                                            <i
                                                                                class="fa-solid fa-download text-primary"></i>
                                                                            <span
                                                                                class="font-medium text-sm">{{ __('ledger.file_inspector.history.downloaded') }}</span>
                                                                        </div>
                                                                        <span
                                                                            class="text-xs text-base-content/60">2025-12-13</span>
                                                                    </div>
                                                                    <div class="text-xs text-base-content/70 mt-1">中村春樹
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                {{-- グラデーションオーバーレイ --}}
                                                <div class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-base-100 to-transparent pointer-events-none"
                                                    x-show="!showAllActivity"></div>
                                            </div>

                                            {{-- もっと見るボタン --}}
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
