@props([
    'level' => null,
    'scopes' => [],
    'editUrl' => null,
    'tenantId' => null,
    'label' => null,
    'sourceType' => null,
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

$resolvedEditUrl = $editUrl;
$resolvedTenantId = $tenantId ?? request()->route()?->originalParameters()['tenant'] ?? tenant('id');

if (! $resolvedEditUrl && $sourceType && $sourceId) {
    $resolvedEditUrl = match ($sourceType) {
        'ledger_define' => route('ledgerDefine.edit', ['tenant' => $resolvedTenantId, 'ledgerDefineId' => $sourceId]),
        'folder' => route('folder.edit', ['tenant' => $resolvedTenantId, 'folder' => $sourceId]),
        default => null,
    };
}
@endphp

@php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
@endphp

<div wire:ignore
     x-data='{
         label: @json($displayLabel, $jsonFlags),
         scopeText: @json($scopeText, $jsonFlags),
         editUrl: @json($resolvedEditUrl),
         tooltipText: @json($tooltipText, $jsonFlags),
         visible: @json(($level || $label) ? true : false),
         update(data) {
             this.label = data.label;
             this.scopeText = data.scopeText;
             this.editUrl = data.editUrl;
             this.tooltipText = data.tooltipText;
             this.visible = true;
         },
         clear() {
             this.visible = false;
         }
     }'
     @confidentiality-updated.window="update($event.detail)"
     @confidentiality-cleared.window="clear()">

    <div x-show="visible" x-cloak>
        <a x-show="editUrl"
           :href="editUrl"
           class="fixed top-16 right-4 z-[55] block tooltip tooltip-left whitespace-pre-line"
           :data-tip="tooltipText">
            <div class="inline-flex items-center px-3 py-1 text-2xl font-black tracking-wider text-red-600 bg-transparent border-[3px] border-red-600 shadow-lg backdrop-blur-sm transform rotate-2 hover:rotate-0 transition-transform duration-200 cursor-pointer whitespace-nowrap">
                <span x-text="label"></span>
                <span x-show="scopeText" class="contents">
                    <span class="mx-1 text-xl">・</span>
                    <span class="text-lg font-bold" x-text="scopeText"></span>
                </span>
            </div>
        </a>
        <div x-show="!editUrl"
             class="fixed top-16 right-4 z-[45] tooltip tooltip-left whitespace-pre-line"
             :data-tip="tooltipText">
            <div class="inline-flex items-center px-3 py-1 text-2xl font-black tracking-wider text-red-600 bg-transparent border-[3px] border-red-600 shadow-lg backdrop-blur-sm transform rotate-2 hover:rotate-0 transition-transform duration-200 cursor-pointer whitespace-nowrap">
                <span x-text="label"></span>
                <span x-show="scopeText" class="contents">
                    <span class="mx-1 text-xl">・</span>
                    <span class="text-lg font-bold" x-text="scopeText"></span>
                </span>
            </div>
        </div>
    </div>
</div>
