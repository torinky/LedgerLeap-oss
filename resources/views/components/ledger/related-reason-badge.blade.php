@props([
    'reason'      => 'identifier',
    'matchedKeys' => [],           // array{value, source, column}[] または string[] (後方互換)
    'score'       => null,         // float|null — 意味検索コサイン類似度 (0.0–1.0)
])

@php
    // 識別番号が関与する場合のみアイコンを表示
    // semantic のみ → 大スコアバッジで自明なのでアイコン不要
    $showIcon = in_array($reason, ['identifier', 'both']);

    // matchedKeys の各要素を「値（ソース）」形式に変換
    // 構造体形式 {value, source, column} と 旧形式 string の両方に対応
    $keyLabels = collect((array) $matchedKeys)->map(function ($key) {
        if (is_array($key) && isset($key['value'], $key['source'])) {
            $sourceLabel = match ($key['source']) {
                'auto_number'  => __('ledger.related.identifier_source_auto_number'),
                'text_column'  => __('ledger.related.identifier_source_text_column'),
                default        => $key['source'],
            };
            return __('ledger.related.identifier_key_with_source', [
                'value'  => $key['value'],
                'source' => $sourceLabel,
            ]);
        }
        // 旧形式（文字列）はそのまま返す
        return (string) $key;
    })->all();

    $keysLabel  = ! empty($keyLabels) ? implode(', ', $keyLabels) : null;
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
