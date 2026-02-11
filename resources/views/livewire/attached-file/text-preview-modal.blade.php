<x-mary-modal wire:model="showModal" box-class="w-11/12 max-w-4xl">
    {{-- ヘッダー --}}
    <x-slot:title class="flex justify-between items-center">
        {{ __('ledger.text_preview.modal_title') }}
    </x-slot:title>

    {{-- ボディ --}}
    @if($file)
        {{-- 品質情報エリア --}}
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <span class="font-bold"><x-heroicon-o-document class="inline w-5 h-5" /> {{ $file->original_filename }}</span>
                @if($badgeInfo)
                    <span class="badge badge-{{ $badgeInfo['color'] }}">
                        {{ $badgeInfo['label'] }}
                        @if($badgeInfo['score'])
                            : {{ $badgeInfo['score'] }}
                        @endif
                    </span>
                @endif
            </div>
        </div>

        {{-- テキスト表示エリア --}}
        <div class="prose max-w-none overflow-y-auto max-h-[60vh] bg-base-200 p-4 rounded-lg"
             x-ref="previewContent"
             data-text="{{ $file->previewable_text }}">
            {!! Illuminate\Support\Str::markdown($previewText ?? '') !!}
        </div>
    @endif

    {{-- フッター (アクション) --}}
    <x-slot:actions>
        {{-- クリップボードコピーボタン --}}
        <div class="mb-3" x-data="{
            copied: false,
            copyToClipboard() {
                const contentEl = this.$refs.previewContent;
                const text = contentEl?.dataset?.text || '';

                if (!text) {
                    $wire.call('notifyCopyFailed');
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text)
                        .then(() => {
                            this.copied = true;
                            $wire.call('notifyCopySuccess');
                            setTimeout(() => { this.copied = false; }, 2000);
                        })
                        .catch(() => {
                            this.fallbackCopy(text);
                        });
                } else {
                    this.fallbackCopy(text);
                }
            },
            fallbackCopy(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    this.copied = true;
                    $wire.call('notifyCopySuccess');
                    setTimeout(() => { this.copied = false; }, 2000);
                } catch (err) {
                    $wire.call('notifyCopyFailed');
                }
                document.body.removeChild(textarea);
            }
        }">
            <x-mary-button @click="copyToClipboard()" class="btn btn-primary">
                <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                <span x-text="copied ? '{{ __('ledger.vlm.copied_short') }}' : '{{ __('ledger.text_preview.copy_button') }}'"></span>
            </x-mary-button>
        </div>

        @php
            $isVlmSource = $file?->finalized_source === 'vlm';
            $downloadMarkdownUrl = $isVlmSource && $tenantId ? route('file.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'markdown']) : '#';
            $downloadJsonUrl = $isVlmSource && $tenantId ? route('file.download-vlm', ['tenant' => $tenantId, 'attachedFile' => $file->id, 'format' => 'json']) : '#';
        @endphp

        {{-- ダウンロードボタン --}}
        <x-mary-button 
            label="{{ __('ledger.vlm.download_markdown') }}" 
            link="{{ $downloadMarkdownUrl }}" 
            icon="o-arrow-down-on-square" 
            external="true"
            :disabled="!$isVlmSource"
            :tooltip="!$isVlmSource ? __('ledger.text_preview.download_unavailable_not_vlm') : ''"
        />
        <x-mary-button 
            label="{{ __('ledger.vlm.download_json') }}" 
            link="{{ $downloadJsonUrl }}" 
            icon="o-arrow-down-on-square" 
            external="true"
            :disabled="!$isVlmSource"
            :tooltip="!$isVlmSource ? __('ledger.text_preview.download_unavailable_not_vlm') : ''"
        />

        <x-mary-button label="{{ __('actions.close') }}" @click="$wire.closeModal()" />
    </x-slot:actions>
</x-mary-modal>
