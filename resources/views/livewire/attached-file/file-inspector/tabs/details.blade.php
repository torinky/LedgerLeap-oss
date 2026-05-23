{{-- Details Tab --}}
<div class="px-6 py-4 space-y-8 pb-10 min-w-0 max-w-full overflow-x-hidden">
    @php
        $tenantId = $this->resolveTenantId($file?->tenant_id);
    @endphp

    {{-- 0. 未最終化ファイル警告 --}}
    @if ($file && !$file->processing_finalized_at)
        <x-mary-alert icon="o-clock" class="alert-warning">
            <span class="font-semibold">{{ __('ledger.file_inspector.status.not_finalized') }}</span>
            <p class="text-sm mt-1">
                {{ __('ledger.file_inspector.status.not_finalized_desc') }}
            </p>
        </x-mary-alert>
    @endif

    {{-- 1. ファイル基本情報 --}}
    <section>
        <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
            <i class="fa-solid fa-circle-info text-primary"></i>
            {{ __('ledger.file_inspector.info.file_properties') }}
        </h3>
        <div class="overflow-x-auto">
            <table class="table table-xs table-fixed w-full text-base-content">
                <tbody class="whitespace-normal wrap-break-word">
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0 w-32">
                            {{ __('ledger.file_inspector.info.filename') }}</th>
                        <td class="font-medium text-right break-all border-0 pr-0">
                            {{ $file->filename }}</td>
                    </tr>
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                            {{ __('ledger.file_inspector.info.size') }}</th>
                        <td class="font-mono text-right border-0 pr-0">
                            {{ \Illuminate\Support\Number::fileSize($file->size ?? 0, precision: 2) }}
                        </td>
                    </tr>
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                            {{ __('ledger.file_inspector.info.format') }}</th>
                        <td class="text-right border-0 pr-0">
                            <span class="font-mono uppercase break-all whitespace-normal">

                                {{ $file->original_mime_type ? \Illuminate\Support\Str::after($file->original_mime_type, '/') : ($file->mime ? \Illuminate\Support\Str::after($file->mime, '/') : '-') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                            {{ __('ledger.file_inspector.info.uploaded_at') }}
                        </th>
                        <td class="text-right border-0 pr-0 text-[11px]">
                            {{ $file->created_at?->format('Y/m/d H:i') ?: '-' }}
                        </td>
                    </tr>
                    @if ($file->metadata_date)
                        <tr>
                            <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                {{ __('ledger.file_inspector.info.file_creation_date') }}
                            </th>
                            <td class="text-right border-0 pr-0 text-primary font-medium text-[11px]">
                                {{ $file->metadata_date->format('Y/m/d H:i') }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                            {{ __('ledger.file_inspector.info.file_last_updated_at') }}
                        </th>
                        <td class="text-right border-0 pr-0 text-[11px]">
                            {{ $file->updated_at?->format('Y/m/d H:i') ?: '-' }}
                        </td>
                    </tr>
                    {{-- Uploader / Modifier --}}
                    <tr>
                        <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0 pt-3">
                            {{ __('ledger.file_inspector.info.creator') }}</th>
                        <td class="text-right border-0 pr-0 pt-3">
                            <div class="flex items-center justify-end gap-1.5 font-medium text-[11px]">
                                @if ($mockCreatorName)
                                    <x-mary-avatar :title="$mockCreatorName" class="w-4! h-4!" />
                                    <span>{{ $mockCreatorName }}</span>
                                @else
                                    <x-mary-avatar :title="$file->creator?->name ?: 'System'" class="w-4! h-4!" />
                                    <span>{{ $file->creator?->name ?: 'System' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if (($mockCreatorName && !$file->modifier) || ($file->modifier && $file->modifier_id !== $file->creator_id))
                        <tr>
                            <th class="opacity-60 whitespace-nowrap font-normal border-0 pl-0">
                                {{ __('ledger.file_inspector.info.modifier') }}
                            </th>
                            <td class="text-right border-0 pr-0">
                                <div class="flex items-center justify-end gap-1.5 font-medium text-[11px]">
                                    @if ($mockCreatorName)
                                        <x-mary-avatar :title="$mockCreatorName" class="w-4! h-4!" />
                                        <span>{{ $mockCreatorName }}</span>
                                    @else
                                        <x-mary-avatar :title="$file->modifier->name" class="w-4! h-4!" />
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
        $isPdfFile = ($file?->original_mime_type ?? ($file?->mime ?? '')) === 'application/pdf';
        $hasOcrProcessed = $file && ($file->ocr_processed_at ?? false);
    @endphp

    @if ($hasOcrProcessed && ($isImageFile || $isPdfFile))
        <section>
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
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
                            <div class="w-10 h-10 bg-error/10 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-file-pdf text-xl text-error"></i>
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
                                <p class="text-[10px] text-base-content/60 flex items-center gap-2 mt-0.5">
                                    <span class="badge badge-xs badge-info text-[8px] h-3 px-1">OCR完成</span>
                                    <span>{{ $file?->ocr_processed_at?->diffForHumans() ?? '' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <a href="{{ route('file.download-ocr-pdf', ['tenant' => $tenantId, 'attachedFile' => $file->id]) }}"
                                class="btn btn-xs btn-primary gap-1" @click="handleDownload()" :disabled="downloading">
                                <span x-show="downloading" class="loading loading-spinner loading-xs"></span>
                                <i class="fa-solid fa-download" x-show="!downloading"></i>
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
        <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
            <i class="fa-solid fa-bolt text-info"></i>
            {{ __('ledger.file_inspector.info.benchmarks') }}
        </h3>
        <div class="grid grid-cols-1 gap-2">
            @php
                $tikaTime = $file?->calculateProcessingDuration('tika');
                $ocrTime = $file?->calculateProcessingDuration('ocr');
                $vlmTime = $file?->calculateProcessingDuration('vlm');
            @endphp

            <div class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                <span class="flex items-center gap-2 opacity-70">
                    <i class="fa-solid fa-file-import w-4"></i>
                    {{ __('ledger.file_inspector.source.tika') }}
                </span>
                <span class="font-mono">{{ $tikaTime ? number_format($tikaTime / 1000, 2) . 's' : '-' }}</span>
            </div>

            <div class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                <span class="flex items-center gap-2 opacity-70">
                    <i class="fa-solid fa-font w-4"></i>
                    {{ __('ledger.file_inspector.source.ocr') }}
                </span>
                <span class="font-mono">{{ $ocrTime ? number_format($ocrTime / 1000, 2) . 's' : '-' }}</span>
            </div>

            <div class="flex items-center justify-between p-2 bg-base-200 rounded text-[10px]">
                <span class="flex items-center gap-2 opacity-70">
                    <i class="fa-solid fa-robot w-4"></i>
                    {{ __('ledger.file_inspector.source.vlm') }}
                </span>
                <span class="font-mono">{{ $vlmTime ? number_format($vlmTime / 1000, 2) . 's' : '-' }}</span>
            </div>
        </div>
    </section>

    {{-- 4. 台帳情報 --}}
    <section>
        <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-base-content/70">
            <i class="fa-solid fa-database text-warning"></i>
            {{ __('ledger.file_inspector.info.source_ledger') }}
        </h3>
        <div class="card bg-base-200/50 border border-base-300 shadow-sm">
            <div class="card-body p-3">
                <div class="flex items-start gap-3">
                    <div class="flex-none w-10 h-10 bg-base-300 rounded flex items-center justify-center">
                        <i class="fa-solid fa-table-list text-lg opacity-50"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold truncate">
                            {{ $mockLedgerTitle ?? ($file->ledger?->define?->title ?? 'N/A') }}
                        </p>
                        <div class="flex items-center gap-1 mt-0.5">
                            <i class="fa-solid fa-folder text-warning text-[10px]"></i>
                            <p class="text-[10px] opacity-60 truncate">
                                {{ $mockFolderPath ?? ($file->ledger?->folder?->full_path ?? '-') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex-none">
                        @if (!$mockData && $file->ledger_id)
                            <x-mary-button icon="o-arrow-top-right-on-square"
                                link="{{ route('ledgersByDefineId', ['tenant' => $tenantId, 'defineId' => $file->ledger?->define?->id]) }}"
                                class="btn-xs btn-ghost btn-circle" target="_blank" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
