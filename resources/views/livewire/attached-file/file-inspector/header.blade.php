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
                $mockLedgerTitle = !empty($mockData) ? $mockData['mock_ledger_title'] ?? null : null;
                $mockFolderPath = !empty($mockData) ? $mockData['mock_folder_path'] ?? null : null;
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
        <button x-ref="closeButton" class="btn btn-ghost btn-sm btn-circle" @click="open = false; $wire.close()"
            aria-label="{{ __('ledger.file_inspector.close') }}">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>
</div>
