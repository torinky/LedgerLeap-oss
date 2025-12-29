{{-- Preview Area --}}
@if ($this->showPreview)
    <div class="bg-base-200/50 border-b border-base-300 flex-none">
        @if ($this->isImage)
            <div class="relative aspect-video bg-base-300">
                <img src="{{ $this->previewUrl }}" alt="{{ $file?->original_filename ?? 'Preview' }}"
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
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-error/10 mb-4">
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
                    <iframe src="{{ $this->previewUrl }}" class="w-full h-full border-0" title="PDF Preview"></iframe>
                @endif
            </div>
        @endif
    </div>
@endif
