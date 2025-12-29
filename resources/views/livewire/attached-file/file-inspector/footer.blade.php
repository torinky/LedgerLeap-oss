{{-- Footer --}}
<div class="navbar navbar-center bg-base-200 border-t border-base-300 min-h-[3.5rem] px-4 flex-none">
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
