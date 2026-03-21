{{-- Quick actions bar --}}
<div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none relative z-50 isolate min-w-0 overflow-x-hidden" x-data="{
    downloadingOriginal: false,
    downloadingPdf: false,
    copyingOriginal: false,
    copyingPdf: false,
    handleDownload(type) {
        if (type === 'original') {
            this.downloadingOriginal = true;
            setTimeout(() => { this.downloadingOriginal = false; }, 3000);
        } else {
            this.downloadingPdf = true;
            setTimeout(() => { this.downloadingPdf = false; }, 3000);
        }
    },
    handleCopy(url, type) {
        navigator.clipboard.writeText(url).then(() => {
            if (type === 'original') {
                this.copyingOriginal = true;
                setTimeout(() => { this.copyingOriginal = false; }, 2000);
            } else {
                this.copyingPdf = true;
                setTimeout(() => { this.copyingPdf = false; }, 2000);
            }
            $dispatch('mary-toast', {
                toast: {
                    type: 'success',
                    title: '{{ __('ledger.file_inspector.messages.link_copied') }}',
                    description: '',
                    icon: '',
                    css: 'alert-success'
                }
            });
        });
    }
}">
    @php
        // ファイルタイプ判定
        $isImageFile = $file && str_starts_with($file->original_mime_type ?? ($file->mime ?? ''), 'image/');
        $isPdfFile = $file && ($file->original_mime_type ?? ($file->mime ?? '')) === 'application/pdf';
        $hasOcrProcessed = $file && $file->ocr_processed_at;
        $isMockFile = $file && $file->id >= 1 && $file->id <= 12;

        // オリジナルファイルのダウンロードURL
        $originalUrl = $isMockFile
            ? '#download-original-' . $file->id
            : route(
                'file.download',
                [
                    'tenant' => tenant()?->id,
                    'attachedFile' => $file->id ?? 0,
                    'original' => true,
                ],
                true,
            ); // true for absolute URL

        $downloadUrl = $isMockFile
            ? $originalUrl
            : route(
                'file.download',
                [
                    'tenant' => tenant()?->id,
                    'attachedFile' => $file->id ?? 0,
                    'original' => true,
                ],
                true,
            );

        // OCR PDF（変換/最適化PDF）のダウンロードURL
        $ocrPdfUrl = null;
        if ($hasOcrProcessed && ($isImageFile || $isPdfFile)) {
            $ocrPdfUrl = $isMockFile
                ? '#download-ocr-pdf-' . $file->id
                : route(
                    'file.download-ocr-pdf',
                    [
                        'tenant' => tenant('id'),
                        'attachedFile' => $file->id,
                    ],
                    true,
                ); // true for absolute URL
        }
    @endphp


    {{-- オリジナルファイルダウンロード & リンクコピー --}}
    <div class="join flex-1">
        <a href="{{ $downloadUrl }}"
            class="btn btn-sm join-item gap-2 tooltip tooltip-right z-50 {{ $ocrPdfUrl ? 'btn-ghost flex-1' : 'btn-primary flex-1' }}"
            data-tip="{{ $isImageFile ? __('ledger.file_inspector.actions.download_original_image') : __('ledger.file_inspector.actions.download_original') }}"
            @click="handleDownload('original')" :disabled="downloadingOriginal">
            <span x-show="downloadingOriginal" class="loading loading-spinner loading-xs"></span>
            <i class="fa-solid fa-file-image" x-show="!downloadingOriginal && {{ $isImageFile ? 'true' : 'false' }}"></i>
            <i class="fa-solid fa-file-pdf" x-show="!downloadingOriginal && {{ $isPdfFile ? 'true' : 'false' }}"></i>
            <i class="fa-solid fa-file"
                x-show="!downloadingOriginal && {{ !$isImageFile && !$isPdfFile ? 'true' : 'false' }}"></i>
            <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.original') }}</span>
        </a>
        <button class="btn btn-sm join-item w-10 tooltip tooltip-right z-50 transition-all duration-300"
            :class="copyingOriginal ? 'btn-success text-white' : 'btn-ghost'"
            data-tip="{{ __('ledger.file_inspector.actions.copy_link') }}"
            @click="handleCopy('{{ $downloadUrl }}', 'original')">
            <i class="fa-solid fa-link" x-show="!copyingOriginal"></i>
            <i class="fa-solid fa-check" x-show="copyingOriginal" x-cloak></i>
        </button>
    </div>

    {{-- OCR変換/最適化PDFダウンロード & リンクコピー（ある場合のみ） --}}
    @if ($ocrPdfUrl)
        <div class="join flex-1">
            <a href="{{ $ocrPdfUrl }}" class="btn btn-primary btn-sm join-item flex-1 gap-2 tooltip tooltip-left "
                data-tip="{{ $isImageFile ? __('ledger.file_inspector.actions.download_converted_pdf') : __('ledger.file_inspector.actions.download_optimized_pdf') }}"
                @click="handleDownload('pdf')" :disabled="downloadingPdf">
                <span x-show="downloadingPdf" class="loading loading-spinner loading-xs"></span>
                <i class="fa-solid fa-file-pdf" x-show="!downloadingPdf"></i>
                <span
                    class="hidden sm:inline">{{ $isImageFile ? 'PDF' : __('ledger.file_inspector.actions.optimized') }}</span>
            </a>
            <button
                class="btn btn-primary btn-sm join-item w-10 tooltip tooltip-left border-l-primary-focus/20 transition-all duration-300"
                :class="copyingPdf ? 'btn-success text-white' : ''"
                data-tip="{{ __('ledger.file_inspector.actions.copy_link') }}"
                @click="handleCopy('{{ $ocrPdfUrl }}', 'pdf')">
                <i class="fa-solid fa-link" x-show="!copyingPdf"></i>
                <i class="fa-solid fa-check" x-show="copyingPdf" x-cloak></i>
            </button>
        </div>
    @endif
</div>
