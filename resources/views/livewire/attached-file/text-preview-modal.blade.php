<x-mary-modal wire:model="showModal" box-class="w-11/12 max-w-4xl">
    {{-- ヘッダー --}}
    <x-slot:title class="flex justify-between items-center">
        <span>{{ __('ledger.text_preview.modal_title') }}</span>
    </x-slot:title>

    {{-- ボディ --}}
    @if($file)
        {{-- 品質情報エリア --}}
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <span class="font-bold"><x-heroicon-o-document class="inline w-5 h-5" /> {{ $file->original_filename }}</span>
                @if($badgeInfo)
                    <x-mary-badge :value="$badgeInfo['label']" :class="$badgeInfo['color']" />
                @endif
            </div>
        </div>

        {{-- テキスト表示エリア --}}
        <div class="prose max-w-none overflow-y-auto max-h-[60vh] bg-base-200 p-4 rounded-lg">
            {!! Illuminate\Support\Str::markdown($previewText ?? '') !!}
        </div>
    @endif

    {{-- フッター (アクション) --}}
    <x-slot:actions>
        {{-- クリップボードコピーボタン --}}
        <div x-data="{ 
            textToCopy: @js($file?->previewable_text),
            fallbackCopy(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    $wire.notifyCopySuccess();
                } catch (err) {
                    $wire.notifyCopyFailed();
                }
                document.body.removeChild(textarea);
            }
        }">
            <x-mary-button 
                :label="$isTruncated ? __('ledger.text_preview.copy_full_text_button') : __('ledger.text_preview.copy_button')" 
                icon="o-clipboard" 
                @click="
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(textToCopy)
                            .then(() => $wire.notifyCopySuccess())
                            .catch(() => fallbackCopy(textToCopy));
                    } else {
                        fallbackCopy(textToCopy);
                    }
                " 
                class="btn-primary"
                :disabled="empty($previewText)"
                :tooltip="empty($previewText) ? __('ledger.text_preview.copy_unavailable') : ''"
            />
        </div>

        @php
            $isVlmSource = $file?->finalized_source === 'vlm';
            $downloadMarkdownUrl = $isVlmSource ? route('files.download-vlm', ['tenant' => tenant('id'), 'attachedFile' => $file->id, 'format' => 'markdown']) : '#';
            $downloadJsonUrl = $isVlmSource ? route('files.download-vlm', ['tenant' => tenant('id'), 'attachedFile' => $file->id, 'format' => 'json']) : '#';
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
