<div class="contents">
    @if ($canView)
        @if (! empty($files))
            <x-ledger.attachment-list
                :files="$files"
                mode="compact"
                :column-id="$columnId"
                :tenant-id="$currentTenantId"
                :search="$highlightKeyword"
                :selected-file-id="$selectedFileId"
            />
        @else
            <span class="text-neutral/50 inline-flex items-center justify-center w-full">
                <i class="fa-solid fa-cube text-info/50 mr-2"></i>
                <span>{{ __('ledger.empty') }}</span>
            </span>
        @endif
    @else
        <x-ledger.not-authorized-message />
    @endif
</div>
