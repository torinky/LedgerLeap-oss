@props([
    'level' => null,
    'scopes' => [],
    'editUrl' => null,
    'label' => null,
    'sourceType' => null,   // 'folder' | 'ledger_define' | null
    'sourceName' => null,
    'sourceId' => null,
    'sourcePath' => null,
    'inherited' => false,
])

@php
$levelLabels = [
    'secret' => __('ledger.confidentiality.level.secret'),
    'confidential' => __('ledger.confidentiality.level.confidential'),
    'internal' => __('ledger.confidentiality.level.internal'),
    'public' => __('ledger.confidentiality.level.public'),
];

$displayLabel = $label ?? ($level ? ($levelLabels[$level] ?? $level) : '');

$scopeText = collect($scopes)->map(fn ($s) => is_array($s) ? ($s['name'] ?? $s) : $s)->implode(', ');

// ツールチップ内容構築（1行で簡潔に）
$tooltipParts = [];

if ($sourcePath) {
    $tooltipParts[] = $inherited
        ? __('ledger.confidentiality.tooltip.inherited_from', ['name' => $sourcePath])
        : __('ledger.confidentiality.tooltip.direct_from', ['name' => $sourcePath]);
} elseif ($sourceType && $sourceName) {
    $sourceLabel = match ($sourceType) {
        'ledger_define' => __('ledger.confidentiality.tooltip.ledger_define_short', ['name' => $sourceName]),
        'folder' => __('ledger.confidentiality.tooltip.folder_short', ['name' => $sourceName]),
        default => $sourceName,
    };
    $tooltipParts[] = $inherited
        ? __('ledger.confidentiality.tooltip.inherited_from', ['name' => $sourceLabel])
        : __('ledger.confidentiality.tooltip.direct_from', ['name' => $sourceLabel]);
}

if ($scopeText) {
    $tooltipParts[] = __('ledger.confidentiality.tooltip.scope_label', ['scopes' => $scopeText]);
} elseif (! $level) {
    $tooltipParts[] = __('ledger.confidentiality.stamp.tooltip_unset');
}

$tooltipText = implode(' | ', $tooltipParts);

// 編集リンクURL構築（仕様書 §6 準拠）
$resolvedEditUrl = $editUrl;
if (! $resolvedEditUrl && $sourceType && $sourceId) {
    try {
        $resolvedEditUrl = match ($sourceType) {
            'ledger_define' => route('ledgerDefine.edit', ['ledgerDefineId' => $sourceId]),
            'folder' => route('folder.edit', ['folder' => $sourceId]),
            default => null,
        };
    } catch (\Throwable $e) {
        $resolvedEditUrl = null;
    }
}
@endphp

@if($level || $label)
    {{-- z-[45]: navbar dropdown z-[30] より上、validation badge / sticky announcement z-[50] より下。
         <a> タグ自体を fixed 配置し、DaisyUI tooltip（::before 擬似要素）を直接持たせる。
         これにより pointer-events-none の副作用（子要素へのイベント伝播失敗）を回避。 --}}
    @if($resolvedEditUrl)
        <a href="{{ $resolvedEditUrl }}"
           class="fixed top-16 right-4 z-[55] block tooltip tooltip-left whitespace-pre-line"
           data-tip="{{ $tooltipText }}"
           wire:ignore>
    @else
        <div class="fixed top-16 right-4 z-[45] tooltip tooltip-left whitespace-pre-line"
             data-tip="{{ $tooltipText }}"
             wire:ignore>
    @endif
        {{-- スタンプ本体: パディング抑えめ・文字大きめ --}}
        <div class="inline-flex items-center px-3 py-1 text-2xl font-black tracking-wider text-red-600 bg-transparent border-[3px] border-red-600 shadow-lg backdrop-blur-sm transform rotate-2 hover:rotate-0 transition-transform duration-200 cursor-pointer whitespace-nowrap">
            {{ $displayLabel }}
            @if($scopeText)
                <span class="mx-1 text-xl">・</span>
                <span class="text-lg font-bold">{{ $scopeText }}</span>
            @endif
        </div>
    @if($resolvedEditUrl)
        </a>
    @else
        </div>
    @endif
@endif
