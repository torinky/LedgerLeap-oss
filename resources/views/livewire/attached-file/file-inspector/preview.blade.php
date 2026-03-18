{{-- Preview Area --}}
@if ($previewState['showPreview'])
    <div class="bg-base-200/50 border-b border-base-300 flex-none relative z-0">
        {{-- Tier 2: Loading overlay for source switching --}}
        <x-element.loading-overlay tier="2" target="switchSource" />

        @if ($previewState['isImage'])
            <div class="relative aspect-video bg-base-300"
                x-data="{
                    imgLoaded: false,
                    cacheKey: 'img-loaded-{{ $previewState['previewUrl'] ?? 'none' }}',
                    loadStartTime: null,
                    init() {
                        // セッションストレージから読み込み済み状態を復元
                        const cached = sessionStorage.getItem(this.cacheKey);
                        if (cached === 'true') {
                            this.imgLoaded = true;
                            // キャッシュヒット時のログ
                            console.log('[FileInspector Performance] Image preview (cache hit)', {
                                url: '{{ $this->previewUrl }}',
                                cached: true
                            });
                        } else {
                            // 読み込み開始時刻を記録
                            this.loadStartTime = performance.now();
                            console.log('[FileInspector Performance] Image preview started', {
                                url: '{{ $this->previewUrl }}'
                            });
                        }
                    },
                    markLoaded() {
                        const wasLoaded = this.imgLoaded;
                        this.imgLoaded = true;

                        // セッションストレージに保存
                        sessionStorage.setItem(this.cacheKey, 'true');

                        // パフォーマンス測定（初回読み込みのみ）
                        if (!wasLoaded && this.loadStartTime) {
                            const duration = performance.now() - this.loadStartTime;
                            console.log('[FileInspector Performance] Image preview loaded', {
                                duration_ms: duration.toFixed(2),
                                url: '{{ $this->previewUrl }}',
                                cached: false
                            });

                            // Livewireコンポーネントのメソッドを呼び出し
                            $wire.logPerformance('image_preview_load', duration, {
                                url: '{{ $previewState['previewUrl'] }}',
                                from_cache: false
                            });
                        }
                    }
                }"
                wire:key="img-preview-{{ $previewState['previewUrl'] ?? 'none' }}">
                {{-- Loading Spinner --}}
                <div x-show="!imgLoaded" class="absolute inset-0 flex items-center justify-center bg-base-300/50">
                    <span class="loading loading-spinner loading-lg text-primary/40"></span>
                </div>

                @if ($previewState['previewUrl'])
                    <img src="{{ $previewState['previewUrl'] }}" alt="{{ $file?->original_filename ?? 'Preview' }}"
                        class="w-full h-full object-contain transition-opacity duration-500"
                        :class="imgLoaded ? 'opacity-100' : 'opacity-0'"
                        x-on:load="markLoaded()"
                        x-on:error="markLoaded()"
                        x-init="if ($el.complete) markLoaded()"
                        loading="lazy">
                @endif

                <div class="absolute top-2 right-2 flex gap-2">
                    @if ($previewState['shouldUseThumbnail'] && $previewState['thumbnailUrl'])
                        <div class="badge badge-neutral bg-base-100/80 border-none shadow-sm gap-1 pl-1 tooltip tooltip-left"
                            data-tip="{{ __('ledger.file_inspector.preview.thumbnail_hint') ?? '表示を高速化するためにサムネイルを表示しています。拡大ボタンでオリジナルを確認できます。' }}">
                            <i class="fa-solid fa-bolt text-warning text-[10px]"></i>
                            <span class="text-[10px] uppercase font-bold tracking-tighter">THUMBNAIL</span>
                        </div>
                    @endif
                    <button
                        class="btn btn-xs btn-circle btn-ghost bg-base-100/90 hover:bg-base-100 shadow-lg tooltip tooltip-left"
                        data-tip="{{ __('ledger.file_inspector.actions.zoom') }}"
                        @click="window.open('{{ $previewState['originalUrl'] }}', '_blank')">
                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                    </button>
                </div>
            </div>
        @elseif ($previewState['isPdf'])
            <div class="relative aspect-video bg-base-300 flex items-center justify-center">
                @if ($file && $file->id >= 1 && $file->id <= 12)
                    <div class="text-center p-6">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-error/10 mb-4">
                            <i class="fa-solid fa-file-pdf text-4xl text-error"></i>
                        </div>
                        <p class="text-sm font-medium text-base-content mb-1">
                            {{ __('ledger.file_inspector.preview.pdf_preview') }}</p>
                        <p class="text-xs text-base-content/60 mb-4">
                            {{ number_format(($file->size ?? 0) / 1024, 1) }}
                            KB</p>
                        <button class="btn btn-sm btn-outline gap-2"
                            @click="window.open('{{ $previewState['originalUrl'] }}', '_blank')">
                            <i class="fa-solid fa-external-link-alt"></i>
                            {{ __('ledger.file_inspector.preview.open_new_tab') }}
                        </button>
                    </div>
                @else
                    <iframe src="{{ $previewState['previewUrl'] }}" class="w-full h-full border-0" title="PDF Preview"></iframe>
                @endif
            </div>
        @endif
    </div>
@endif


@if($activeSource === 'original')
    {{-- Original Source UI --}}
@else
    {{-- Alternative Source UI --}}
@endif

