<div
        x-data="{ open: @entangle('open') }"
        @keydown.escape.window="open = false; $wire.close()"
        @open-file-inspector.window="console.log('FileInspector received event:', $event.detail); $wire.openInspector($event.detail.id)"
>
    {{-- DaisyUI Drawer --}}
    <div class="drawer drawer-end z-50">
        <input type="checkbox" id="file-inspector-drawer" class="drawer-toggle" x-model="open"/>

        {{-- Overlay --}}
        <div class="drawer-side">
            <label for="file-inspector-drawer" aria-label="close sidebar" class="drawer-overlay"
                   @click="open = false; $wire.close()"></label>

            {{-- Drawer content --}}
            <div class="min-h-full w-96 md:w-[28rem] lg:w-[32rem] bg-base-100 flex flex-col shadow-2xl">

                {{-- Header --}}
                <div class="navbar bg-base-200 border-b border-base-300 min-h-[4rem] px-4 flex-none">
                    <div class="flex-1">
                        <div class="flex flex-col gap-1">
                            <h2 id="drawer-title" class="text-base font-bold truncate line-clamp-1"
                                title="{{ $file?->original_filename ?? $file?->filename ?? __('ledger.file_inspector.title') }}">
                                <i class="fa-solid fa-file-lines mr-2 text-primary"></i>
                                {{ \Illuminate\Support\Str::limit($file?->original_filename ?? $file?->filename ?? __('ledger.file_inspector.title'), 30) }}
                            </h2>
                            @if($file && ($file->mock_ledger_title ?? $file->ledger ?? null))
                                <div class="text-xs text-base-content/60 flex items-center gap-2">
                                    <i class="fa-solid fa-folder text-warning text-[10px]"></i>
                                    <span class="truncate">{{ \Illuminate\Support\Str::limit($file->mock_folder_path ?? $file->ledger?->folder?->title ?? '', 40) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex-none">
                        <button class="btn btn-ghost btn-sm btn-circle" @click="open = false; $wire.close()"
                                aria-label="{{ __('ledger.file_inspector.close') }}">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Quick actions bar --}}
                <div class="bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none">
                    @php
                        $downloadUrl = $file && $file->id >= 1 && $file->id <= 8
                            ? '#download-' . $file->id
                            : route('file.download', ['tenant' => tenant()?->id, 'attachedFile' => $file?->id ?? 0]);
                    @endphp
                    <a href="{{ $downloadUrl }}"
                       class="btn btn-primary btn-sm flex-1 gap-2"
                       download>
                        <i class="fa-solid fa-download"></i>
                        <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.download') }}</span>
                    </a>
                    <button class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                            data-tip="{{ __('ledger.file_inspector.actions.copy_link') }}"
                            x-data="{}"
                            @click="navigator.clipboard.writeText('{{ $downloadUrl }}').then(() => alert('{{ __('ledger.file_inspector.messages.link_copied') }}'))">
                        <i class="fa-solid fa-link"></i>
                    </button>
                    <a href="{{ $downloadUrl }}"
                       class="btn btn-ghost btn-sm btn-square tooltip tooltip-bottom"
                       data-tip="{{ __('ledger.file_inspector.actions.open_new_tab') }}"
                       target="_blank">
                        <i class="fa-solid fa-external-link-alt"></i>
                    </a>
                </div>

                {{-- Preview Area --}}
                @php
                    $mime = $file?->original_mime_type ?? $file?->mime ?? '';
                    $isImage = str_starts_with($mime, 'image/');
                    $isPdf = $mime === 'application/pdf';
                    $showPreview = $isImage || $isPdf;
                    $previewUrl = null;
                    if ($file && $file->id >= 1 && $file->id <= 8) {
                        if ($isImage) {
                            $previewUrl = 'https://via.placeholder.com/600x400/4CAF50/FFFFFF?text=' . urlencode($file->original_filename ?? 'Image');
                        } elseif ($isPdf) {
                            $previewUrl = '#pdf-preview';
                        }
                    }
                @endphp

                @if($showPreview)
                    <div class="bg-base-200/50 border-b border-base-300 flex-none">
                        @if($isImage)
                            <div class="relative aspect-video bg-base-300">
                                <img src="{{ $previewUrl }}"
                                     alt="{{ $file?->original_filename ?? 'Preview' }}"
                                     class="w-full h-full object-contain"
                                     loading="lazy">
                                <div class="absolute top-2 right-2">
                                    <button class="btn btn-xs btn-circle btn-ghost bg-base-100/90 hover:bg-base-100 shadow-lg tooltip tooltip-left"
                                            data-tip="{{ __('ledger.file_inspector.actions.zoom') }}"
                                            @click="window.open('{{ $previewUrl }}', '_blank')">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    </button>
                                </div>
                            </div>
                        @elseif($isPdf)
                            <div class="relative aspect-video bg-base-300 flex items-center justify-center">
                                @if($file && $file->id >= 1 && $file->id <= 8)
                                    <div class="text-center p-6">
                                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-error/10 mb-4">
                                            <i class="fa-solid fa-file-pdf text-4xl text-error"></i>
                                        </div>
                                        <p class="text-sm font-medium text-base-content mb-1">{{ __('ledger.file_inspector.preview.pdf_preview') }}</p>
                                        <p class="text-xs text-base-content/60 mb-4">{{ number_format(($file->size ?? 0)/1024, 1) }}
                                            KB</p>
                                        <button class="btn btn-sm btn-outline gap-2"
                                                @click="window.open('{{ $previewUrl }}', '_blank')">
                                            <i class="fa-solid fa-external-link-alt"></i>
                                            {{ __('ledger.file_inspector.preview.open_new_tab') }}
                                        </button>
                                    </div>
                                @else
                                    <iframe src="{{ $previewUrl }}" class="w-full h-full border-0"
                                            title="PDF Preview"></iframe>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Tabs & Content --}}
                <div class="flex-1 overflow-hidden flex flex-col">
                    <x-mary-tabs wire:model="selectedTab"
                                 class="tabs tabs-boxed bg-base-200 m-0 p-1 rounded-none border-b border-base-300 flex-none">
                        <x-mary-tab name="content" label="{{ __('ledger.file_inspector.tabs.content') }}"
                                    icon="o-document-text" class="tab-lg gap-2">
                            {{-- Content Tab --}}
                            <div class="p-4 space-y-4">
                                @php
                                    $hasPreviewText = $file && (
                                        (method_exists($file, 'hasPreviewableText') && $file->hasPreviewableText()) ||
                                        (!empty($file->mock_preview_text))
                                    );
                                    $previewText = $file->mock_preview_text ?? ($file->previewable_text ?? null);
                                    $confidence = $file->mock_confidence ?? null;
                                    $source = $file->mock_source ?? null;
                                    $isProcessing = $file && ($file->mock_confidence === 0.0 && $file->mock_source === 'OCR');
                                    $isError = $file && ($file->mock_source === null);
                                @endphp

                                @if($isProcessing)
                                    <div class="alert alert-warning shadow-lg">
                                        <i class="fa-solid fa-spinner fa-spin"></i>
                                        <div>
                                            <div class="font-semibold text-sm">{{ __('ledger.file_inspector.status.processing') }}</div>
                                            <div class="text-xs">{{ __('ledger.file_inspector.status.processing_message') }}</div>
                                            <progress class="progress progress-warning w-full mt-2" value="65"
                                                      max="100"></progress>
                                        </div>
                                    </div>
                                @elseif($isError)
                                    <div class="alert alert-error shadow-lg">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        <div>
                                            <div class="font-semibold text-sm">{{ __('ledger.file_inspector.status.error') }}</div>
                                            <div class="text-xs">{{ __('ledger.file_inspector.status.error_message') }}</div>
                                        </div>
                                    </div>
                                @elseif($hasPreviewText)
                                    @if($confidence !== null && $confidence > 0)
                                        <div class="stats shadow w-full">
                                            <div class="stat p-3">
                                                <div class="stat-title text-xs">{{ __('ledger.file_inspector.info.last_extraction') }}</div>
                                                <div class="stat-value text-lg flex items-center gap-2">
                                                    @if($source === 'VLM')
                                                        <span class="badge badge-success">VLM</span>
                                                    @elseif($source === 'OCR')
                                                        <span class="badge badge-info">OCR</span>
                                                    @else
                                                        <span class="badge badge-primary">Tika</span>
                                                    @endif
                                                    <span class="text-sm">{{ number_format($confidence * 100, 1) }}%</span>
                                                </div>
                                                <div class="stat-desc flex items-center gap-1">
                                                    @if($confidence >= 0.9)
                                                        <i class="fa-solid fa-check-circle text-success"></i>
                                                        <span class="text-success">高信頼度</span>
                                                    @elseif($confidence >= 0.7)
                                                        <i class="fa-solid fa-shield-check text-info"></i>
                                                        <span class="text-info">中信頼度</span>
                                                    @else
                                                        <i class="fa-solid fa-exclamation-triangle text-warning"></i>
                                                        <span class="text-warning">低信頼度</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-semibold">{{ __('ledger.file_inspector.tabs.content') }}</span>
                                        </label>
                                        <textarea id="preview-text-{{ $file?->id ?? 0 }}"
                                                  class="textarea textarea-bordered w-full h-64 text-xs font-mono leading-relaxed"
                                                  readonly>{{ $previewText }}</textarea>
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <button class="btn btn-sm btn-outline flex-1 gap-2"
                                                x-data="{}"
                                                @click="navigator.clipboard.writeText(document.getElementById('preview-text-{{ $file?->id ?? 0 }}').value).then(() => alert('{{ __('ledger.file_inspector.messages.text_copied') }}'))">
                                            <i class="fa-solid fa-copy"></i>
                                            <span class="hidden sm:inline">{{ __('ledger.file_inspector.actions.copy_text') }}</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline flex-1 gap-2"
                                                x-data="{}"
                                                @click="navigator.clipboard.writeText('```\n' + document.getElementById('preview-text-{{ $file?->id ?? 0 }}').value + '\n```').then(() => alert('{{ __('ledger.file_inspector.messages.markdown_copied') }}'))">
                                            <i class="fa-brands fa-markdown"></i>
                                            <span class="hidden sm:inline">Markdown</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline flex-1 gap-2"
                                                x-data="{}"
                                                @click="navigator.clipboard.writeText(JSON.stringify({filename: '{{ $file?->original_filename ?? '' }}', content: document.getElementById('preview-text-{{ $file?->id ?? 0 }}').value, source: '{{ $source }}', confidence: {{ $confidence ?? 0 }}}, null, 2)).then(() => alert('{{ __('ledger.file_inspector.messages.structured_copied') }}'))">
                                            <i class="fa-solid fa-code"></i>
                                            <span class="hidden sm:inline">JSON</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="alert shadow-lg">
                                        <i class="fa-solid fa-info-circle"></i>
                                        <div class="text-sm">{{ __('ledger.file_inspector.status.no_text') }}</div>
                                    </div>
                                @endif
                            </div>

                        </x-mary-tab>
                        <x-mary-tab name="details" label="{{ __('ledger.file_inspector.tabs.details') }}"
                                    icon="o-information-circle" class="tab-lg gap-2">
                            {{-- Details Tab --}}
                            <div class="p-4 space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                        <i class="fa-solid fa-file-circle-info text-primary"></i>
                                        {{ __('ledger.file_inspector.info.file_info') }}
                                    </h3>
                                    <div class="overflow-x-auto">
                                        <table class="table table-xs">
                                            <tbody>
                                            <tr>
                                                <th class="w-1/3">{{ __('ledger.file_inspector.info.size') }}</th>
                                                <td>{{ number_format(($file?->size ?? 0)/1024, 1) }} KB</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('ledger.file_inspector.info.format') }}</th>
                                                <td>
                                                    <code class="text-xs">{{ $file?->original_mime_type ?? $file?->mime ?? 'N/A' }}</code>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('ledger.file_inspector.info.uploaded') }}</th>
                                                <td>{{ $file?->created_at?->format('Y/m/d H:i') ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('ledger.file_inspector.info.uploaded_by') }}</th>
                                                <td>{{ $file?->creator?->name ?? ($file && $file->id >= 1 && $file->id <= 8 ? '山田太郎' : 'N/A') }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="divider"></div>

                                <div>
                                    <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                        <i class="fa-solid fa-microchip text-info"></i>
                                        {{ __('ledger.file_inspector.info.processing_status') }}
                                    </h3>
                                    @php
                                        $isProcessing = $file && ($file->mock_confidence === 0.0 && $file->mock_source === 'OCR');
                                        $isError = $file && ($file->mock_source === null);
                                    @endphp
                                    <div class="overflow-x-auto">
                                        <table class="table table-xs">
                                            <tbody>
                                            <tr>
                                                <th class="w-1/3">{{ __('ledger.file_inspector.info.status') }}</th>
                                                <td>
                                                    @if($isProcessing)
                                                        <span class="badge badge-warning badge-sm gap-1">
                                                                <i class="fa-solid fa-spinner fa-spin"></i>
                                                                {{ __('ledger.file_inspector.status.processing') }}
                                                            </span>
                                                    @elseif($isError)
                                                        <span class="badge badge-error badge-sm gap-1">
                                                                <i class="fa-solid fa-exclamation-triangle"></i>
                                                                {{ __('ledger.file_inspector.status.error') }}
                                                            </span>
                                                    @else
                                                        <span class="badge badge-success badge-sm gap-1">
                                                                <i class="fa-solid fa-check"></i>
                                                                {{ __('ledger.file_inspector.status.completed') }}
                                                            </span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($file && $file->mock_source)
                                                <tr>
                                                    <th>{{ __('ledger.file_inspector.info.last_extraction') }}</th>
                                                    <td>
                                                        @if($file->mock_source === 'VLM')
                                                            <span class="badge badge-success badge-sm">VLM</span>
                                                        @elseif($file->mock_source === 'OCR')
                                                            <span class="badge badge-info badge-sm">OCR</span>
                                                        @else
                                                            <span class="badge badge-primary badge-sm">Tika</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </x-mary-tab>
                        <x-mary-tab name="access" label="{{ __('ledger.file_inspector.tabs.access') }}"
                                    icon="o-shield-check" class="tab-lg gap-2">
                            {{-- Access Tab --}}
                            <div class="p-4 space-y-4">
                                <div class="card bg-primary/5 border border-primary/20">
                                    <div class="card-body p-4">
                                        <h3 class="card-title text-sm text-primary mb-2">
                                            <i class="fa-solid fa-user-shield"></i>
                                            {{ __('ledger.file_inspector.access.your_permissions') }}
                                        </h3>
                                        <div class="grid grid-cols-2 gap-3 text-sm">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-eye text-success"></i>
                                                <span>{{ __('ledger.file_inspector.access.view') }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-download text-success"></i>
                                                <span>{{ __('ledger.file_inspector.access.download') }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 opacity-40">
                                                <i class="fa-solid fa-pen"></i>
                                                <span>{{ __('ledger.file_inspector.access.edit') }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 opacity-40">
                                                <i class="fa-solid fa-trash"></i>
                                                <span>{{ __('ledger.file_inspector.access.delete') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="divider"></div>

                                <div>
                                    <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                        <i class="fa-solid fa-sitemap text-info"></i>
                                        {{ __('ledger.file_inspector.access.org_role_settings') }}
                                    </h3>
                                    <div class="space-y-2">
                                        <div class="card card-compact bg-base-200">
                                            <div class="card-body">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-building text-info"></i>
                                                        <span class="font-medium text-sm">総務部</span>
                                                    </div>
                                                    <span class="badge badge-sm">{{ __('ledger.file_inspector.access.organization') }}</span>
                                                </div>
                                                <div class="flex flex-wrap gap-1">
                                                    <span class="badge badge-success badge-sm">{{ __('ledger.file_inspector.access.admin') }}</span>
                                                    <span class="badge badge-primary badge-sm">{{ __('ledger.file_inspector.access.write') }}</span>
                                                    <span class="badge badge-info badge-sm">{{ __('ledger.file_inspector.access.read') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-mary-tab>
                        <x-mary-tab name="history" label="{{ __('ledger.file_inspector.tabs.history') }}" icon="o-clock"
                                    class="tab-lg gap-2">
                            {{-- History Tab --}}
                            <div class="p-4 space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-success"></i>
                                        {{ __('ledger.file_inspector.history.processing_log') }}
                                    </h3>
                                    <ul class="steps steps-vertical text-sm">
                                        <li class="step step-success">
                                            <div class="text-left ml-3">
                                                <div class="font-semibold">{{ __('ledger.file_inspector.history.vlm_analysis') }}</div>
                                                <div class="text-xs text-base-content/60">2025-12-13 10:45</div>
                                                <div class="text-xs text-base-content/70">{{ __('ledger.file_inspector.info.confidence') }}
                                                    92.5% | 3.2秒
                                                </div>
                                            </div>
                                        </li>
                                        <li class="step step-success">
                                            <div class="text-left ml-3">
                                                <div class="font-semibold">{{ __('ledger.file_inspector.history.ocr_processing') }}</div>
                                                <div class="text-xs text-base-content/60">2025-12-13 10:45</div>
                                                <div class="text-xs text-base-content/70">2.8秒</div>
                                            </div>
                                        </li>
                                        <li class="step step-success">
                                            <div class="text-left ml-3">
                                                <div class="font-semibold">{{ __('ledger.file_inspector.history.tika_extraction') }}</div>
                                                <div class="text-xs text-base-content/60">2025-12-13 10:45</div>
                                                <div class="text-xs text-base-content/70">1.5秒</div>
                                            </div>
                                        </li>
                                        <li class="step step-success">
                                            <div class="text-left ml-3">
                                                <div class="font-semibold">{{ __('ledger.file_inspector.history.uploaded') }}</div>
                                                <div class="text-xs text-base-content/60">2025-12-13 10:45</div>
                                                <div class="text-xs text-base-content/70">山田太郎</div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <div class="divider"></div>

                                <div>
                                    <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                        <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                                        {{ __('ledger.file_inspector.history.activity') }}
                                    </h3>
                                    <div class="space-y-2">
                                        <div class="card card-compact bg-base-200">
                                            <div class="card-body">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-download text-primary"></i>
                                                        <span class="font-medium text-sm">{{ __('ledger.file_inspector.history.downloaded') }}</span>
                                                    </div>
                                                    <span class="text-xs text-base-content/60">11:30</span>
                                                </div>
                                                <div class="text-xs text-base-content/70 mt-1">田中花子</div>
                                            </div>
                                        </div>
                                        <div class="card card-compact bg-base-200">
                                            <div class="card-body">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-eye text-info"></i>
                                                        <span class="font-medium text-sm">{{ __('ledger.file_inspector.history.viewed') }}</span>
                                                    </div>
                                                    <span class="text-xs text-base-content/60">11:15</span>
                                                </div>
                                                <div class="text-xs text-base-content/70 mt-1">佐藤次郎</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-mary-tab>
                    </x-mary-tabs>

                </div>

                {{-- Footer --}}
                <div class="navbar navbar-center bg-base-200 border-t border-base-300 min-h-[3.5rem] px-4 flex-none">
                    <div class="navbar-start">
                        <span class="text-xs text-base-content/60">ID: {{ $file?->id ?? 0 }}</span>
                    </div>
                    <div class="navbar-end gap-2">
                        <button class="btn btn-warning btn-sm btn-square tooltip"
                                data-tip="{{ __('ledger.file_inspector.actions.reprocess') }}">
                            <i class="fa-solid fa-refresh"></i>
                        </button>
                        <button class="btn btn-error btn-sm btn-square tooltip"
                                data-tip="{{ __('ledger.file_inspector.actions.delete') }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

