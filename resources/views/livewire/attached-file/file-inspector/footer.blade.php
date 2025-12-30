{{-- Footer --}}
<div class="navbar navbar-center bg-base-200 border-t border-base-300 min-h-[2.5rem] px-4 flex-none">
    <div class="navbar-start">
        <span class="text-xs text-base-content/60">ID: {{ $file?->id ?? 0 }}</span>
    </div>
    <div class="navbar-end">
        {{-- アクションボタンはPermissionsタブに統合済み --}}
    </div>
</div>
