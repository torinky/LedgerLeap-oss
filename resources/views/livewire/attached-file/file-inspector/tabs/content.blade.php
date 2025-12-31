{{-- Content Tab --}}
<div class="p-4 space-y-4">
    @php
        $previewText = $this->getPreviewText();
        $previewTextRaw = $this->getPreviewText(false);
        $hasPreviewText = $file && !empty($previewTextRaw);
        $confidence =
            (!empty($mockData) ? $mockData['mock_confidence'] ?? null : null) ?? ($file->vlm_confidence ?? null);
        $source = $activeSource;
        $activeStatus = $this->getSourceStatus($source);
        $isProcessing = $activeStatus === 'processing';
        $isError =
            $activeStatus === 'error' ||
            ($file && empty($mockData) && !$file->finalized_source && $activeStatus === 'missing' && $source === 'vlm');
        // 補足: 初期表示でソースが全くない場合（ID 7など）も考慮

        $isAllFailed = $this->isAllProcessingFailed();
        $isTimedOut = $this->isProcessingTimedOut();
        $isTikaOnlyFailed = $this->isTikaOnlyFailed();
        $isUnknownMime = $this->isUnknownMimeType();

        $limit = 10000;
        $canExpand = mb_strlen($previewTextRaw) > $limit;
    @endphp

    {{-- MIMEタイプ不明/非対応（最優先） --}}
    @if ($isUnknownMime)
        <div class="alert alert-warning">
            <div class="flex items-start gap-3 w-full">
                <x-mary-icon name="o-question-mark-circle" class="w-5 h-5 shrink-0" />
                <div class="flex-1">
                    <h3 class="font-bold">{{ __('ledger.file_inspector.status.unsupported_format') }}</h3>
                    <p class="text-sm mt-1">{{ __('ledger.file_inspector.status.unsupported_format_message') }}</p>
                    <div class="mt-2">
                        <a href="{{ route('file.download', ['tenant' => tenant('id'), 'attachedFile' => $file->id]) }}"
                           class="btn btn-sm btn-primary" download>
                            <x-mary-icon name="o-arrow-down-tray" class="w-4 h-4" />
                            {{ __('ledger.file_inspector.actions.download') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    {{-- 全処理失敗の警告 --}}
    @elseif ($isAllFailed)
        <div class="alert alert-error">
            <div class="flex items-start gap-3 w-full">
                <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 shrink-0" />
                <div class="flex-1">
                    <h3 class="font-bold">{{ __('ledger.file_inspector.status.all_failed_title') }}</h3>
                    <p class="text-sm mt-1">{{ __('ledger.file_inspector.status.all_failed_message') }}</p>
                    @if (!empty($mockData) && !empty($mockData['mock_error_message']))
                        <p class="text-xs mt-2 opacity-80">{{ $mockData['mock_error_message'] }}</p>
                    @endif
                    <div class="mt-3 flex gap-2">
                        @if ($this->canPerformAction('retry'))
                            <button class="btn btn-sm btn-outline" wire:click="retryProcessing">
                                <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
                                {{ __('ledger.file_inspector.actions.retry_all') }}
                            </button>
                        @endif
                        <a href="mailto:{{ config('mail.support_address', 'support@example.com') }}"
                           class="btn btn-sm btn-ghost">
                            <x-mary-icon name="o-envelope" class="w-4 h-4" />
                            {{ __('ledger.file_inspector.actions.contact_support') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($isTimedOut)
        {{-- タイムアウト警告 --}}
        <div class="alert alert-warning">
            <div class="flex items-start gap-3 w-full">
                <x-mary-icon name="o-clock" class="w-6 h-6 shrink-0" />
                <div class="flex-1">
                    <h3 class="font-bold">{{ __('ledger.file_inspector.status.processing_timeout') }}</h3>
                    <p class="text-sm mt-1">{{ __('ledger.file_inspector.status.timeout_suggestion') }}</p>
                    @if (!empty($mockData) && !empty($mockData['mock_error_message']))
                        <p class="text-xs mt-2 opacity-80">{{ $mockData['mock_error_message'] }}</p>
                    @endif
                    <div class="mt-3 flex gap-2">
                        @if ($this->canPerformAction('retry'))
                            <button class="btn btn-sm btn-warning btn-outline" wire:click="retryProcessing">
                                <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
                                {{ __('ledger.file_inspector.actions.retry') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @elseif ($isTikaOnlyFailed)
        {{-- Tika単独失敗の情報通知 --}}
        <div class="alert alert-info">
            <div class="flex items-start gap-3 w-full">
                <x-mary-icon name="o-information-circle" class="w-5 h-5 shrink-0" />
                <div class="flex-1">
                    <p class="text-sm">{{ __('ledger.file_inspector.status.tika_only_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Search & Source Selector (Always visible if loaded) --}}
    <div class="flex flex-col sm:flex-row gap-2 mb-4 bg-base-200 p-2 rounded-lg border border-base-300">
        <div class="flex-1">
            <x-mary-input wire:model.change="searchKeyword" icon="o-magnifying-glass"
                placeholder="{{ __('ledger.file_inspector.search.placeholder') }}" class="input-sm" clearable />
            {{-- ローディングインジケーター --}}
            <div x-show="searching" x-cloak class="absolute right-10 top-1/2 -translate-y-1/2">
                <span class="loading loading-spinner loading-xs text-primary"></span>
            </div>
        </div>
        <div class="flex items-center gap-1 p-1 bg-base-300 rounded-lg w-fit shrink-0" x-data="{ switchingSource: null }"
            @source-switched.window="switchingSource = null">
            @foreach (['vlm', 'ocr', 'tika', 'structured'] as $src)
                @php
                    $status = $this->getSourceStatus($src);
                    $isActive = $activeSource === $src;
                    $hasContent = $status === 'completed';
                    $isProcessingNow = $status === 'processing';

                    $tooltip = match ($status) {
                        'processing' => __('ledger.file_inspector.status.processing'),
                        'missing' => __('ledger.file_inspector.status.no_text'),
                        'error' => __('ledger.file_inspector.status.error'),
                        default => '',
                    };
                @endphp
                <div class="{{ $tooltip ? 'tooltip tooltip-bottom' : '' }}" data-tip="{{ $tooltip }}">
                    <button wire:click="switchSource('{{ $src }}')"
                        @click="switchingSource = '{{ $src }}'"
                        class="btn btn-xs {{ $isActive ? 'btn-primary' : 'btn-ghost' }} gap-1 relative"
                        @if (!$hasContent || $isProcessingNow) disabled @endif
                        x-bind:disabled="switchingSource === '{{ $src }}' ||
                            {{ !$hasContent || $isProcessingNow ? 'true' : 'false' }}">
                        <span x-show="switchingSource !== '{{ $src }}'">
                            @if ($isProcessingNow)
                                <i class="fa-solid fa-spinner fa-spin text-[10px] mr-1"></i>
                            @endif
                            {{ __('ledger.file_inspector.source.' . $src) }}
                        </span>
                        <span x-show="switchingSource === '{{ $src }}'" x-cloak
                            class="flex items-center gap-1">
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
                <progress class="progress progress-warning w-full mt-2" value="65" max="100"></progress>
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
                $isImageFile = str_starts_with($file?->original_mime_type ?? '', 'image/');
                $isPdfFile = ($file?->original_mime_type ?? ($file?->mime ?? '')) === 'application/pdf';
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

            @if ($hasOcrProcessed && $ocrPdfUrl)
                <div class="alert alert-info shadow-sm py-2 px-4 mb-4" x-data="{
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
                    <a href="{{ $ocrPdfUrl }}" class="btn btn-xs btn-primary gap-1" @click="handleDownload()"
                        :disabled="downloading">
                        <span x-show="downloading" class="loading loading-spinner loading-xs"></span>
                        <i class="fa-solid fa-download" x-show="!downloading"></i>
                        <span>{{ __('ledger.file_inspector.actions.download') }}</span>
                    </a>
                </div>
            @endif

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
                                <i class="fa-solid fa-check-circle text-success"></i>
                            @elseif($badge['color'] === 'warning')
                                <i class="fa-solid fa-shield-check text-info"></i>
                            @else
                                <i class="fa-solid fa-exclamation-triangle text-warning"></i>
                            @endif
                            <span class="text-{{ $badge['color'] }}">{{ $badge['tooltip'] }}</span>
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
                        this.notify('{{ __('ledger.file_inspector.messages.copy_failed') }}', 'error');
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
                        this.notify('{{ __('ledger.file_inspector.messages.copy_failed') }}', 'error');
                    }
                    document.body.removeChild(textarea);
                },
                onCopySuccess(id) {
                    this.copying = id;
                    setTimeout(() => { this.copying = null; }, 2000);
                    const toastTitle = id === 'json' ?
                        '{{ __('ledger.file_inspector.messages.json_copied') }}' :
                        '{{ __('ledger.file_inspector.messages.text_copied') }}';
                    this.notify(toastTitle);
                },
                copyAsJson() {
                    const contentEl = this.$refs.previewContent;
                    const text = contentEl?.dataset?.text || '';
            
                    if (!text) {
                        this.notify('{{ __('ledger.file_inspector.messages.copy_failed') }}', 'error');
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
                        this.notify('{{ __('ledger.file_inspector.messages.download_failed') }}', 'error');
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
            
                    const blob = new Blob([text], {
                        type: type === 'json' ?
                            'application/json' : 'text/plain'
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    const ext = type === 'json' ? '.json' : (type === 'markdown' ?
                        '.md' : '.txt');
                    a.download = '{{ $file?->original_filename ?? 'extracted' }}' +
                        ext;
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
                            <span class="badge badge-success badge-sm gap-1 text-white">
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
                    $highlightedText = $this->previewText; // ハイライト済み（サーバー側）
                @endphp

                <div wire:ignore
                    x-data="{
                        plainText: @js($plainText),
                        highlightedTextServer: @js($highlightedText),
                        keyword: @entangle('searchKeyword'),
                        keywords: [],
                        activeSource: @entangle('activeSource'),

                        init() {
                            this.$watch('keyword', (value) => {
                                this.keywords = value.trim().split(/\s+/).filter(k => k);
                            });
                        },

                        get displayText() {
                            // キーワードがない場合はサーバー側のハイライトをそのまま使用
                            if (!this.keywords.length) {
                                return this.highlightedTextServer;
                            }

                            // 外部関数でハイライト処理
                            return window.highlightKeywords(this.plainText, this.keywords);
                        }
                    }"
                    x-ref="previewContent"
                    class="bg-base-200/50 p-4 rounded-lg border border-base-300 overflow-y-auto max-h-[500px] min-h-[256px] relative shadow-inner">
                    <template x-if="activeSource === 'structured'">
                        <pre class="text-xs overflow-auto bg-base-300 p-3 rounded-lg"><code class="language-json" x-html="displayText"></code></pre>
                    </template>
                    <template x-if="activeSource === 'vlm'">
                        <div class="prose prose-sm max-w-none" x-html="displayText"></div>
                    </template>
                    <template x-if="activeSource !== 'structured' && activeSource !== 'vlm'">
                        <pre class="text-xs font-mono leading-relaxed whitespace-pre-wrap text-base-content" x-html="displayText"></pre>
                    </template>

                    @if ($this->canExpand && !$isExpanded)
                        <div
                            class="absolute bottom-0 left-0 right-0 h-24 bg-linear-to-t from-base-100 to-transparent flex items-end justify-center pb-4">
                            <button wire:click="toggleExpand" class="btn btn-sm btn-primary shadow-lg">
                                <i class="fa-solid fa-arrows-up-down"></i>
                                {{ __('ledger.file_inspector.actions.show_all') }}
                            </button>
                        </div>
                    @endif
                </div>

                @if ($isExpanded)
                    <div class="flex justify-center mt-2">
                        <button wire:click="toggleExpand" class="btn btn-xs btn-ghost gap-1">
                            <i class="fa-solid fa-compress"></i>
                            {{ __('ledger.file_inspector.actions.show_less') }}
                        </button>
                    </div>
                @endif

                <div class="flex flex-wrap gap-6 mt-6">
                    {{-- Copy Actions Group --}}
                    <div class="flex flex-col gap-1.5">
                        <span class="text-[10px] font-bold opacity-60 px-1 flex items-center gap-1">
                            <i class="fa-solid fa-copy"></i>{{ __('ledger.file_inspector.actions.copy') }}
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
                        <span class="text-[10px] font-bold opacity-60 px-1 flex items-center gap-1">
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
                                <i x-show="downloading !== 'text'" class="fa-solid fa-file-lines"></i>
                                <span>{{ __('ledger.file_inspector.actions.text_format') }}</span>
                            </button>

                            @if ($activeSource === 'vlm')
                                @php
                                    $isSaved = $tenantId && $file && $file->exists;
                                @endphp
                                @if ($isSaved)
                                    <a href="{{ route('files.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'markdown']) }}"
                                        class="btn btn-sm btn-primary join-item gap-1 tooltip tooltip-bottom min-w-[7.5rem]"
                                        target="_blank" data-attribute-downloading="false"
                                        @click="this.dataset.downloading = 'true'; setTimeout(() => this.dataset.downloading = 'false', 2000)"
                                        data-tip="{{ __('ledger.file_inspector.actions.download_markdown') }}">
                                        <i class="fa-brands fa-markdown opacity-70"></i>
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
                                        <i x-show="downloading !== 'json'" class="fa-solid fa-code opacity-70"></i>
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
