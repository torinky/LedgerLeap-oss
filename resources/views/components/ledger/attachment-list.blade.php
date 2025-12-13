@props([
    'files' => [],
    'mode' => 'icon-only', // 'full' | 'icon-only'
    'tenantId' => null,
])

@php
    $isIconOnly = $mode === 'icon-only';
@endphp

<div class="{{ $isIconOnly ? 'flex flex-wrap gap-2' : 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4' }}">
    @forelse($files as $file)
        @php
            $mime = $file['mime'] ?? '';
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';
            $isWord = str_contains($mime, 'word');
            $isExcel = str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet');
            $isText = str_contains($mime, 'text');
            $isZip = str_contains($mime, 'zip') || str_contains($mime, 'archive');

            $iconClass = match (true) {
                $isImage => 'fa-solid fa-file-image text-blue-500',
                $isPdf => 'fa-solid fa-file-pdf text-red-500',
                $isWord => 'fa-solid fa-file-word text-blue-600',
                $isExcel => 'fa-solid fa-file-excel text-green-600',
                $isText => 'fa-solid fa-file-lines text-gray-600',
                $isZip => 'fa-solid fa-file-zipper text-purple-600',
                default => 'fa-solid fa-file text-gray-400',
            };
            $status = $file['status'] ?? 'completed';
            $downloadUrl = $file['downloadUrl'] ?? '#';
            $label = $file['filename'] ?? 'file';
        @endphp

        @if($isIconOnly)
            {{-- icon-only モード: 一覧画面用のシンプル表示 --}}
            <div class="relative group">
                {{-- RPA用: 直接ダウンロードリンク --}}
                <a href="{{ $downloadUrl }}"
                   class="direct-download-link absolute inset-0 opacity-0 pointer-events-none z-0"
                   aria-label="Download {{ $label }}"
                   download>
                </a>

                <button type="button"
                        class="btn btn-ghost btn-sm flex flex-col items-center relative z-10 hover:bg-base-200"
                        @click.prevent="window.dispatchEvent(new CustomEvent('open-file-inspector', { detail: { id: {{ (int)($file['id'] ?? 0) }} } }))"
                        aria-label="{{ $label }}">
                    {{-- ステータスインジケータ --}}
                    @if($status === 'processing')
                        <span class="absolute -top-1 -right-1 flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-warning"></span>
                        </span>
                    @elseif($status === 'error')
                        <span class="absolute -top-1 -right-1 flex h-3 w-3 rounded-full bg-error"></span>
                    @else
                        <span class="absolute -top-1 -right-1 flex h-2 w-2 rounded-full bg-success opacity-0 group-hover:opacity-100 transition-opacity"></span>
                    @endif

                    <i class="{{ $iconClass }} text-2xl"></i>
                    <span class="truncate w-16 text-xs mt-1">{{ \Illuminate\Support\Str::limit($label, 10, '...') }}</span>
                </button>

                {{-- ホバー時のダウンロードボタン --}}
                <div class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity z-20 pointer-events-auto">
                    <a href="{{ $downloadUrl }}"
                       class="btn btn-xs btn-primary shadow-lg"
                       title="ダウンロード"
                       download
                       @click.stop>
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
            </div>
        @else
            {{-- full モード: 詳細画面用のカード表示 --}}
            <div class="card bg-base-100 shadow-sm border hover:shadow-md transition-shadow relative">
                {{-- RPA用: 直接ダウンロードリンク --}}
                <a href="{{ $downloadUrl }}"
                   class="direct-download-link absolute inset-0 opacity-0 pointer-events-none z-0"
                   aria-label="Download {{ $label }}"
                   download>
                </a>

                {{-- Header --}}
                <div class="px-3 py-2 flex items-center justify-between border-b bg-base-200">
                    <span class="text-sm font-medium truncate flex-1" title="{{ $label }}">{{ $label }}</span>
                    <a href="{{ $downloadUrl }}"
                       class="btn btn-ghost btn-xs z-10"
                       download
                       @click.stop>
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>

                {{-- Content area --}}
                <div class="aspect-video flex items-center justify-center bg-base-200 cursor-pointer relative z-10"
                     @click="window.dispatchEvent(new CustomEvent('open-file-inspector', { detail: { id: {{ (int)($file['id'] ?? 0) }} } }))">
                    {{-- Status indicator --}}
                    <div class="absolute right-2 top-2">
                        @if($status === 'processing')
                            <i class="fa-solid fa-spinner text-warning animate-spin"></i>
                        @elseif($status === 'error')
                            <i class="fa-solid fa-triangle-exclamation text-error"></i>
                        @else
                            <i class="fa-solid fa-check text-success text-xs"></i>
                        @endif
                    </div>

                    @if($status === 'processing')
                        <div class="flex flex-col items-center gap-2">
                            <i class="fa-solid fa-spinner fa-spin fa-2x text-warning"></i>
                            <span class="text-xs text-base-content/70">処理中...</span>
                        </div>
                    @elseif($status === 'error')
                        <div class="flex flex-col items-center gap-2">
                            <i class="fa-solid fa-exclamation-triangle fa-2x text-error"></i>
                            <span class="text-xs text-base-content/70">エラー</span>
                        </div>
                    @else
                        @if($isImage && isset($file['thumbnailUrl']))
                            {{-- 画像のサムネイル表示 --}}
                            <img src="{{ $file['thumbnailUrl'] }}"
                                 alt="{{ $label }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy">
                        @else
                            {{-- アイコン表示 --}}
                            <i class="{{ $iconClass }} fa-4x"></i>
                        @endif
                    @endif
                </div>

                {{-- Footer actions --}}
                <div class="px-3 py-2 flex items-center justify-end gap-2">
                    <button type="button"
                            @click.prevent="window.dispatchEvent(new CustomEvent('open-file-inspector', { detail: { id: {{ (int)($file['id'] ?? 0) }} } }))"
                            class="btn btn-outline btn-xs z-10">
                        <i class="fa-solid fa-eye"></i>
                        <span class="text-xs">詳細</span>
                    </button>
                </div>
            </div>
        @endif
    @empty
        <div class="text-sm text-base-content/60">{{ __('ledger.empty') }}</div>
    @endforelse
</div>

