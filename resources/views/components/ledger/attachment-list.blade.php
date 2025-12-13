@props([
    // files: [
    //   ['id'=>1,'filename'=>'image.jpg','mime'=>'image/jpeg','status'=>'completed','thumbnailUrl'=>'/storage/...','downloadUrl'=>'...'],
    // ]
    'files' => [],
    'mode' => 'full', // 'full' | 'icon-only'
    'tenantId' => null,
])

@php
    $isIconOnly = $mode === 'icon-only';
@endphp

<div class="{{ $isIconOnly ? 'flex flex-wrap gap-2' : 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4' }}">
    @forelse($files as $file)
        @php
            $isImage = str_starts_with($file['mime'] ?? '', 'image/');
            $isPdf = ($file['mime'] ?? '') === 'application/pdf';
            $iconClass = match (true) {
                $isImage => 'fa-solid fa-file-image',
                $isPdf => 'fa-solid fa-file-pdf',
                default => 'fa-solid fa-file',
            };
            $status = $file['status'] ?? 'completed';
            $downloadUrl = $file['downloadUrl'] ?? '#';
            $thumb = $file['thumbnailUrl'] ?? null;
            $label = $file['filename'] ?? 'file';
        @endphp

        <div class="relative group {{ $isIconOnly ? 'w-20' : 'w-full' }}">
            {{-- RPA互換: 静的ダウンロードリンク（クリック透過） --}}
            <a href="{{ $downloadUrl }}" target="_blank" class="direct-download-link absolute inset-0 opacity-0 pointer-events-none" aria-label="{{ __('file_inspector.actions.download') }}"></a>

            @if(!$isIconOnly)
                <div class="x-mary-card rounded shadow border overflow-hidden" role="region" aria-label="{{ $label }}">
                    {{-- Header with filename label --}}
                    <div class="px-3 py-2 flex items-center justify-between border-b bg-base-100">
                        <span class="text-sm font-medium truncate" title="{{ $label }}">{{ $label }}</span>
                        <a href="{{ $downloadUrl }}" target="_blank" class="btn btn-ghost btn-xs" aria-label="{{ __('file_inspector.actions.download') }}">
                            <i class="fa-solid fa-download"></i>
                        </a>
                    </div>

                    {{-- Status indicator --}}
                    <div class="absolute right-2 top-2 indicator">
                        @if($status === 'processing')
                            <i class="fa-solid fa-spinner text-warning animate-spin"></i>
                        @elseif($status === 'error')
                            <i class="fa-solid fa-triangle-exclamation text-error"></i>
                        @else
                            <i class="fa-solid fa-check text-success"></i>
                        @endif
                    </div>

                    {{-- Thumb / Icon area with loading / error states --}}
                    <div class="aspect-video flex items-center justify-center bg-base-200">
                        @if($status === 'processing')
                            <div class="animate-pulse bg-gray-300 rounded h-40 w-full"></div>
                        @elseif($status === 'error')
                            <div class="bg-gray-200 rounded h-40 w-full flex items-center justify-center">
                                <i class="fa-solid fa-exclamation-triangle fa-2x text-red-500"></i>
                            </div>
                        @else
                            @if($isImage && $thumb)
                                <img src="{{ $thumb }}" alt="{{ $label }}" class="object-cover h-40 w-full">
                            @else
                                <i class="{{ $iconClass }} fa-3x"></i>
                            @endif
                        @endif
                    </div>

                    {{-- Footer: actions --}}
                    <div class="px-3 py-2 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="$dispatch('open-file-inspector', { id: {{ (int)($file['id'] ?? 0) }} })"
                                class="btn btn-outline btn-xs" aria-label="{{ __('ledger.show_details') }}">
                            <i class="fa-solid fa-eye"></i>
                            <span class="ml-1 text-xs">{{ __('ledger.show_details') }}</span>
                        </button>
                        <a href="{{ $downloadUrl }}" target="_blank" class="btn btn-outline btn-xs" aria-label="{{ __('file_inspector.actions.download') }}">
                            <i class="fa-solid fa-download"></i>
                            <span class="ml-1 text-xs">{{ __('file_inspector.actions.download') }}</span>
                        </a>
                    </div>
                </div>
            @else
                {{-- icon-only モード --}}
                <button type="button" class="btn btn-ghost btn-sm flex flex-col items-center"
                        @click.prevent="console.log('Icon clicked, opening inspector for file:', {{ (int)($file['id'] ?? 0) }}); window.dispatchEvent(new CustomEvent('open-file-inspector', { detail: { id: {{ (int)($file['id'] ?? 0) }} } }))"
                        aria-label="{{ $label }}">
                    <i class="{{ $iconClass }} fa-2x"></i>
                    <span class="truncate w-20 text-xs">{{ $label }}</span>
                </button>
            @endif
        </div>
    @empty
        <div class="text-sm text-base-content/60">{{ __('ledger.empty') }}</div>
    @endforelse
</div>
