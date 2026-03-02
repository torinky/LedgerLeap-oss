@props([
    'reason'      => 'identifier',
    'matchedKeys' => [],           // string[] — 一致した識別番号値
    'score'       => null,         // float|null — 意味検索コサイン類似度 (0.0–1.0)
])

@php
    // 識別番号が関与する場合のみアイコンを表示
    // semantic のみ → 大スコアバッジで自明なのでアイコン不要
    $showIcon = in_array($reason, ['identifier', 'both']);

    $keysLabel  = ! empty($matchedKeys) ? implode(', ', (array) $matchedKeys) : null;
    $scoreLabel = $score !== null ? number_format($score * 100, 1) . '%' : null;

    $tooltip = match ($reason) {
        'identifier' => $keysLabel
            ? __('ledger.related.identifier_tooltip', ['keys' => $keysLabel])
            : __('ledger.related.reason_identifier'),
        'both'       => collect([
                $keysLabel  ? __('ledger.related.identifier_tooltip', ['keys' => $keysLabel]) : null,
                $scoreLabel ? __('ledger.related.score_tooltip',      ['score' => $scoreLabel]) : null,
            ])->filter()->implode(' / '),
        default      => '',
    };
@endphp

{{-- identifier / both のみ: 識別番号アイコン＋ツールチップ --}}
@if ($showIcon)
    <div class="tooltip tooltip-left" data-tip="{{ $tooltip }}">
        <i class="fas fa-bookmark text-warning"></i>
    </div>
@endif
