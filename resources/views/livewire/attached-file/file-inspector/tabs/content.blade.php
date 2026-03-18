{{-- Content Tab --}}
<div class="space-y-4 relative" x-data="{
    viewMode: 'rendered', // 'rendered' or 'raw' (only relevant for VLM)
    rawPreviewText: null,
    rawPreviewLoading: false,
    switchTab(source, mode = 'rendered') {
        $wire.switchSource(source);
        this.viewMode = mode;
        if (source === 'vlm' && mode === 'raw' && this.rawPreviewText === null) {
            this.rawPreviewLoading = true;
            $wire.requestPreviewText('raw');
        }
    },
    handlePreviewTextReady(event) {
        const { purpose, text } = event.detail || {};
        if (purpose === 'raw') {
            this.rawPreviewText = text || '';
            this.rawPreviewLoading = false;
            this.viewMode = 'raw';
            return;
        }

        if (purpose === 'copy') {
            this.copyToClipboard(text || '');
            this.rawPreviewLoading = false;
            return;
        }

        if (purpose && purpose.startsWith('download:')) {
            const type = purpose.split(':', 2)[1] || 'text';
            this.downloadText(text || '', type);
            this.rawPreviewLoading = false;
        }
    },
    async copyToClipboard(text) {
        if (!text) return;
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    },
    downloadText(text, type = 'text') {
        if (!text) return;
        const blob = new Blob([text], { type: type === 'json' ? 'application/json' : 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = '{{ $file?->original_filename ?? 'extracted' }}' + (type === 'json' ? '.json' : (type === 'markdown' ? '.md' : '.txt'));
        a.click();
        window.URL.revokeObjectURL(url);
    }
}" @preview-text-ready.window="handlePreviewTextReady($event)">
    {{-- Tier 2: Loading overlay for search --}}
    <x-element.loading-overlay tier="2" target="searchKeyword" />

    @php
            $file = $this->file;
        $previewText = $this->getPreviewText();
        $previewTextRaw = null;
        $hasPreviewText = $file && !empty($previewText);

        // 信頼度バッジ情報の取得
        $badge = $file?->getConfidenceBadgeInfo();

        $activeStatus = $this->getSourceStatus($activeSource);
        $isProcessing = $activeStatus === 'processing';
        $isError =
            $activeStatus === 'error' ||
            ($file &&
                empty($mockData) &&
                !$file->finalized_source &&
                $activeStatus === 'missing' &&
                $activeSource === 'vlm');

        $isAllFailed = $this->isAllProcessingFailed();
        $isTimedOut = $this->isProcessingTimedOut();
        $isTikaOnlyFailed = $this->isTikaOnlyFailed();
        $isUnknownMime = $this->isUnknownMimeType();

        $limit = 10000;
        $canExpand = mb_strlen(strip_tags($previewText ?? '')) > $limit;

        // メインコンテンツエリアのID
        $contentAreaId = 'file-inspector-content-' . uniqid('', true);

        $badgeIconClass = null;
        if ($badge) {
            $badgeIconClass = match ($badge['color']) {
                'success' => 'fa-check-circle text-success',
                'warning' => 'fa-shield-halved text-warning',
                default => 'fa-exclamation-circle text-error',
            };
        }

        $downloadType = match ($activeSource) {
            'vlm' => 'markdown',
            'structured' => 'json',
            default => 'text',
        };
    @endphp

    {{-- HEADER: Search & Source Selector (Tabs) --}}
    <div class="sticky top-0 z-20 bg-base-100/95 backdrop-blur-xs py-2 border-b border-base-200">
        <div class="flex flex-col gap-3">
            {{-- Search Box --}}
            <div class="flex items-center gap-2">
                <div class="flex-1 relative">
                    <x-mary-input wire:model.live.debounce.300ms="searchKeyword" icon="o-magnifying-glass"
                        placeholder="{{ __('ledger.file_inspector.search.placeholder') }}"
                        class="input-sm w-full font-normal" clearable />
                </div>

                {{-- Search Hit Badge --}}
                @if (!empty($searchKeyword))
                    <div class="shrink-0 animate-fade-in-right">
                        @if ($this->hasKeywordHit)
                            <span class="badge badge-success badge-sm gap-1 text-white shadow-sm">
                                <i class="fa-solid fa-check"></i>
                                {{ __('ledger.file_inspector.search.hit') }}
                            </span>
                        @else
                            <span class="badge badge-warning badge-sm gap-1 shadow-sm">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                {{ __('ledger.file_inspector.search.no_hit') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Source Selector (Explicit Tabs) --}}
            <div class="flex flex-wrap items-center justify-between gap-y-2 tooltip"
                data-tip="{{ __('ledger.file_inspector.source.tooltip') }}">
                <div class="join bg-base-200 rounded-lg overflow-x-auto max-w-full">
                    {{-- 1. AI Analysis (VLM Rendered) --}}
                    @php
                        $vlmStatus = $this->getSourceStatus('vlm');
                        $isVlmActive = $activeSource === 'vlm';
                        $isVlmProcessing = $vlmStatus === 'processing';
                        $hasVlmContent = $vlmStatus === 'completed';
                    @endphp
                    <button @click="switchTab('vlm', 'rendered')" class="join-item btn btn-xs border-none shrink-0"
                        :class="('{{ $isVlmActive }}' && viewMode === 'rendered') ?
                        'btn-white shadow-sm text-primary font-bold' : 'btn-ghost opacity-70 hover:opacity-100'"
                        {{ !$hasVlmContent && !$isVlmProcessing ? 'disabled' : '' }}>
                        @if ($isVlmProcessing)
                            <span class="loading loading-spinner loading-xs"></span>
                        @endif
                        {{ __('ledger.file_inspector.source.vlm') }}
                    </button>

                    {{-- 2. Markdown (VLM Raw) --}}
                    <button @click="switchTab('vlm', 'raw')" class="join-item btn btn-xs border-none shrink-0"
                        :class="('{{ $isVlmActive }}' && viewMode === 'raw') ?
                        'btn-white shadow-sm text-primary font-bold' : 'btn-ghost opacity-70 hover:opacity-100'"
                        {{ !$hasVlmContent && !$isVlmProcessing ? 'disabled' : '' }}>
                        {{ __('ledger.file_inspector.source.markdown') }}
                    </button>

                    {{-- 3. OCR --}}
                    {{--
                    @php
                        $ocrStatus = $this->getSourceStatus('ocr');
                        $isOcrActive = $activeSource === 'ocr';
                        $isOcrProcessing = $ocrStatus === 'processing';
                        $hasOcrContent = $ocrStatus === 'completed';
                    @endphp
                    <button wire:click="switchSource('ocr')"
                        class="join-item btn btn-xs border-none shrink-0 {{ $isOcrActive ? 'btn-white shadow-sm text-primary font-bold' : 'btn-ghost opacity-70 hover:opacity-100' }}"
                        {{ !$hasOcrContent && !$isOcrProcessing ? 'disabled' : '' }}>
                        @if ($isOcrProcessing)
                            <span class="loading loading-spinner loading-xs"></span>
                        @endif
                        {{ __('ledger.file_inspector.source.ocr') }}
                    </button>
--}}

                    {{-- 4. Tika --}}
                    @php
                        $tikaStatus = $this->getSourceStatus('tika');
                        $isTikaActive = $activeSource === 'tika';
                        $isTikaProcessing = $tikaStatus === 'processing';
                        $hasTikaContent = $tikaStatus === 'completed';
                    @endphp
                    <button wire:click="switchSource('tika')"
                        class="join-item btn btn-xs border-none shrink-0 {{ $isTikaActive ? 'btn-white shadow-sm text-primary font-bold' : 'btn-ghost opacity-70 hover:opacity-100' }}"
                        {{ !$hasTikaContent && !$isTikaProcessing ? 'disabled' : '' }}>
                        @if ($isTikaProcessing)
                            <span class="loading loading-spinner loading-xs"></span>
                        @endif
                        {{ __('ledger.file_inspector.source.tika') }}
                    </button>

                    {{-- 5. JSON --}}
                    @php
                        $jsonStatus = $this->getSourceStatus('structured');
                        $isJsonActive = $activeSource === 'structured';
                        $hasJsonContent = $jsonStatus === 'completed';
                    @endphp
                    <button wire:click="switchSource('structured')"
                        class="join-item btn btn-xs border-none shrink-0 {{ $isJsonActive ? 'btn-white shadow-sm text-primary font-bold' : 'btn-ghost opacity-70 hover:opacity-100' }}"
                        {{ !$hasJsonContent ? 'disabled' : '' }}>
                        JSON
                    </button>
                </div>

                {{-- Confidence Badge --}}
                @if ($badge && $activeSource === 'vlm')
                    <div class="flex items-center gap-1.5 text-xs bg-base-100 border border-base-200 px-2 py-1 rounded-full shadow-xs tooltip tooltip-left cursor-help ml-auto"
                        data-tip="{{ $badge['tooltip'] }}">
                        <i
                            class="fa-solid {{ $badgeIconClass }}"></i>
                        <span class="font-medium opacity-80">{{ $badge['label'] }}</span>
                        @if ($badge['score'])
                            <span class="opacity-60 font-mono text-[10px] ml-0.5">{{ $badge['score'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ALERTS --}}
    <div class="space-y-2">
        @if ($isUnknownMime)
            <x-mary-alert icon="o-question-mark-circle" class="alert-warning shadow-sm">
                <div>
                    <h3 class="font-bold text-sm">{{ __('ledger.file_inspector.status.unsupported_format') }}</h3>
                    <p class="text-xs mt-1">{{ __('ledger.file_inspector.status.unsupported_format_message') }}</p>
                    <a href="{{ route('file.download', ['tenant' => tenant('id'), 'attachedFile' => $file->id]) }}"
                        class="btn btn-xs btn-primary mt-2" download>
                        {{ __('ledger.file_inspector.actions.download') }}
                    </a>
                </div>
            </x-mary-alert>
        @elseif ($isAllFailed)
            <x-mary-alert icon="o-exclamation-triangle" class="alert-error shadow-sm">
                <div>
                    <h3 class="font-bold text-sm">{{ __('ledger.file_inspector.status.all_failed_title') }}</h3>
                    <p class="text-xs mt-1">{{ __('ledger.file_inspector.status.all_failed_message') }}</p>
                    @if ($this->canPerformAction('retry'))
                        <div class="flex gap-2 mt-2">
                            <button class="btn btn-xs btn-outline" wire:click="retryProcessing">
                                {{ __('ledger.file_inspector.actions.retry_all') }}
                            </button>
                            <button class="btn btn-xs btn-ghost text-base-content/70">
                                {{ __('ledger.file_inspector.actions.contact_support') }}
                            </button>
                        </div>
                    @else
                        <button class="btn btn-xs btn-ghost text-base-content/70 mt-2">
                            {{ __('ledger.file_inspector.actions.contact_support') }}
                        </button>
                    @endif
                </div>
            </x-mary-alert>
        @elseif ($isTimedOut)
            <x-mary-alert icon="o-clock" class="alert-warning shadow-sm">
                <div>
                    <h3 class="font-bold text-sm">{{ __('ledger.file_inspector.status.processing_timeout') }}</h3>
                    <p class="text-xs mt-1">{{ __('ledger.file_inspector.status.timeout_message') }}</p>
                </div>
            </x-mary-alert>
        @elseif ($isTikaOnlyFailed)
            <x-mary-alert icon="o-document-text" class="alert-info shadow-sm">
                <div>
                    <span class="font-bold text-sm">{{ __('ledger.file_inspector.status.tika_failed') }}</span>
                    <p class="text-xs mt-1">{{ __('ledger.file_inspector.status.tika_failed_detail') }}</p>
                </div>
            </x-mary-alert>
        @elseif ($isProcessing)
            <x-mary-alert icon="o-arrow-path" class="alert-warning shadow-sm animate-pulse">
                <div>
                    <span class="font-bold text-sm">{{ __('ledger.file_inspector.status.processing') }}</span>
                    <p class="text-xs mt-1">{{ __('ledger.file_inspector.status.processing_message') }}</p>
                </div>
            </x-mary-alert>
        @elseif ($hasPreviewText && $file && ($file->ocr_processed_at ?? false))
            @php
                $isImageFile = str_starts_with($file?->original_mime_type ?? '', 'image/');
                $isPdfFile = ($file?->original_mime_type ?? ($file?->mime ?? '')) === 'application/pdf';
            @endphp
            @if ($isPdfFile)
                <x-mary-alert icon="o-information-circle" class="alert-info shadow-sm bg-base-100/50 border-base-200">
                    <div class="flex flex-col gap-1">
                        <span
                            class="text-xs font-medium">{{ __('ledger.file_inspector.status.ocr_pdf_notice') }}</span>
                        {{-- OCR Optimized Download (Only if exists) --}}
                        @if ($file->ocr_pdf_path ?? false)
                            <a href="{{ route('file.download-ocr-pdf', ['tenant' => tenant('id'), 'attachedFile' => $file->id]) }}"
                                class="btn btn-xs btn-ghost gap-1 self-start" download>
                                <i class="fa-solid fa-file-pdf text-error"></i>
                                {{ __('ledger.file_inspector.actions.download_optimized_pdf') }}
                            </a>
                        @endif
                    </div>
                </x-mary-alert>
            @endif
        @endif
    </div>

    {{-- MAIN CONTENT --}}
    @if (!$isProcessing && !$isError && $hasPreviewText)
        <div class="relative group border border-base-300 rounded-xl bg-base-200/30 overflow-hidden shadow-inner flex flex-col"
            x-data="{
                actionState: {
                    copy: { loading: false, success: false },
                    download: { loading: false, success: false }
                },
            
                async performAction(type, actionFn) {
                    if (this.actionState[type].loading) return;
            
                    this.actionState[type].loading = true;
                    this.actionState[type].success = false;
            
                    try {
                        // Simulate interaction delay for UI feedback consistency
                        await new Promise(r => setTimeout(r, 600));
                        await actionFn();
            
                        this.actionState[type].success = true;
                        // Unified toast message
                        this.notify(
                            type === 'copy' ?
                            '{{ __('ledger.vlm.copied_short') }}' :
                            '{{ __('ledger.file_inspector.actions.download_complete') }}'
                        );
            
                        setTimeout(() => {
                            this.actionState[type].success = false;
                        }, 2000);
            
                    } catch (e) {
                        this.notify('Error', 'error');
                    } finally {
                        this.actionState[type].loading = false;
                    }
                },
            
                copyText(targetId) {
                                this.rawPreviewLoading = true;
                                $wire.requestPreviewText('copy');
                },
            
                fallbackCopy(text) {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                },
            
                downloadFile(type) {
                                this.rawPreviewLoading = true;
                                $wire.requestPreviewText('download:' + type);
                }
            }">
            {{-- Content Body --}}
            <div class="overflow-y-auto min-h-[300px] p-4 text-sm leading-relaxed relative transition-all duration-700 ease-in-out"
                :class="[$wire.isExpanded ? 'max-h-[5000px]' : 'max-h-[500px]']"
                style="scrollbar-width: thin;" id="{{ $contentAreaId }}">

                @if ($activeSource === 'structured')
                    <pre class="bg-base-100 p-4 rounded-lg border border-base-200 overflow-x-auto"><code class="language-json text-xs font-mono">{!! $previewText !!}</code></pre>
                @elseif ($activeSource === 'vlm')
                    @if (!empty($previewText))
                        {{-- Rendered View --}}
                        <div x-show="viewMode === 'rendered'"
                            class="prose prose-sm max-w-none dark:prose-invert prose-headings:font-bold prose-headings:text-base prose-p:my-2 prose-pre:bg-base-300 prose-pre:text-base-content">
                            {!! Str::markdown($previewText) !!}
                        </div>
                        {{-- Raw Markdown View --}}
                                <div x-show="viewMode === 'raw'" x-cloak class="relative">
                                    <template x-if="rawPreviewLoading && rawPreviewText === null">
                                        <div class="py-10 flex justify-center">
                                            <span class="loading loading-spinner loading-md"></span>
                                        </div>
                                    </template>
                                    <pre x-show="!rawPreviewLoading || rawPreviewText !== null"
                                        x-text="rawPreviewText"
                                        class="font-mono whitespace-pre-wrap break-words text-base-content/80 text-xs bg-base-100 p-4 rounded border border-base-300"></pre>
                        </div>
                    @else
                        <div class="text-base-content/50 italic text-center py-10">
                            {{ __('ledger.file_inspector.status.no_text') }}</div>
                    @endif
                @else
                    <pre class="font-mono whitespace-pre-wrap break-words text-base-content/80 text-xs">{!! $previewText !!}</pre>
                @endif

                @if ($this->canExpand && !$isExpanded)
                    <div
                        class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-base-100 via-base-100/90 to-transparent flex items-end justify-center pb-6 z-10">
                        <button wire:click="toggleExpand" class="btn btn-primary shadow-lg gap-2 animate-bounce">
                            <i class="fa-solid fa-arrows-down-to-line"></i>
                            {{ __('ledger.file_inspector.actions.show_all') }}
                        </button>
                    </div>
                @endif
            </div>

            {{-- Actions Footer --}}
            <div class="bg-base-100/50 p-2 border-t border-base-200 flex justify-between items-center backdrop-blur-sm"
                wire:key="actions-footer-{{ $activeSource }}-{{ $this->fileId }}">
                <div class="flex gap-2">
                    @if ($isExpanded)
                        <button wire:click="toggleExpand" class="btn btn-xs btn-ghost gap-1">
                            <i class="fa-solid fa-chevron-up"></i>
                            {{ __('ledger.file_inspector.actions.show_less') }}
                        </button>
                    @endif
                </div>

                <div class="flex gap-2">
                    {{-- Copy Button --}}
                    <button @click="copyText('{{ $activeSource === 'structured' ? 'json' : 'text' }}')"
                        class="btn btn-sm btn-ghost hover:btn-primary gap-2 min-w-[100px]"
                        :disabled="actionState.copy.loading || actionState.download.loading">

                        <span x-show="actionState.copy.loading" class="loading loading-spinner loading-xs"></span>
                        <i x-show="actionState.copy.success" class="fa-solid fa-check text-success"></i>
                        <i x-show="!actionState.copy.loading && !actionState.copy.success"
                            class="fa-solid fa-copy"></i>

                        <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.copy') }}</span>
                    </button>

                    <div class="w-px bg-base-300 h-6"></div>

                    {{-- Download --}}
                    <button
                        @click="downloadFile('{{ $downloadType }}')"
                        class="btn btn-sm btn-ghost hover:btn-primary gap-2 min-w-[100px]"
                        :disabled="actionState.download.loading || actionState.copy.loading">

                        <span x-show="actionState.download.loading" class="loading loading-spinner loading-xs"></span>
                        <i x-show="actionState.download.success" class="fa-solid fa-check text-success"></i>
                        <i x-show="!actionState.download.loading && !actionState.download.success"
                            class="fa-solid fa-download"></i>

                        <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.download') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @elseif (!$isProcessing && !$isError)
        <div class="text-center py-10 opacity-50">
            <i class="fa-solid fa-file-circle-xmark text-4xl mb-2"></i>
            <p>{{ __('ledger.file_inspector.status.no_text') }}</p>
        </div>
    @endif
</div>
