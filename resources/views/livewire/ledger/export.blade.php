{{--
    x-data: keywords/filter の最新値を Alpine.js 側で保持する。
    @refreshChildren.window: RecordsTable が render() 内で dispatch('refreshChildren') すると
      Livewire はそれをブラウザ CustomEvent としても window 上に発火する。
      ここで PHP #[On] を使わず Alpine.js だけで受け取ることで、
      Export コンポーネントへのサーバーラウンドトリップを完全に排除し
      セッションロック起因のリクエスト直列化（数十秒遅延）を防ぐ。
    wire:click="export(localKeywords, localFilter)":
      エクスポートボタン押下時に Alpine.js の最新値を PHP メソッドに渡す。
--}}
<div class="flex flex-wrap items-center gap-3"
     x-data="{
         localKeywords: null,
         localFilter: null,
         init() {
             this.localKeywords = @js($keywords ?? []);
             this.localFilter = @js($filter ?? []);
         }
     }"
     x-on:refresh-children.window="
         if ($event.detail && $event.detail.data) {
             localKeywords = $event.detail.data.keywords ?? localKeywords;
             localFilter   = $event.detail.data.filter   ?? localFilter;
         }
     ">
    @php
        $isExporting = $exporting && !$exportFinished;
        $exportLabel = $isExporting ? __('ledger.exporting') : __('ledger.export_csv');
    @endphp

    @if(!$exportFinished)
        <x-mary-button
            wire:click="export(localKeywords, localFilter)"
            icon="o-arrow-down-tray"
            :label="$exportLabel"
            class="btn-outline btn-secondary w-48 justify-start"
            wire:key="ledger_export_request-{{ $ledgerDefineId }}"
            :disabled="$isExporting"
            wire:loading.attr="disabled"
            wire:target="export"
        />
    @else
        <x-mary-button
            :link="$this->downloadUrl"
            no-wire-navigate
            download="{{ $exportFilename }}"
            icon="o-arrow-down-on-square"
            :label="__('ledger.export_csv_download')"
            class="btn-secondary btn-sm"
            wire:key="ledger_export_download-{{ $ledgerDefineId }}"
        />
    @endif

    @if($isExporting)
        <span wire:poll.1s="updateExportProgress" class="sr-only" wire:key="ledger_export_progress-{{ $ledgerDefineId }}"></span>
    @endif
</div>
