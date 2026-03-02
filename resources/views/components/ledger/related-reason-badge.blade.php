@props([
    'reason'      => 'identifier',
    'matchedKeys' => [],           // string[] — 一致した識別番号値
    'score'       => null,         // float|null — 意味検索コサイン類似度 (0.0–1.0)
])

@php
    // アイコン・色の設定
    $config = match ($reason) {
        'identifier' => ['icon' => 'fa-bookmark',        'colorClass' => 'text-warning'],
        'semantic'   => ['icon' => 'fa-brain',            'colorClass' => 'text-info'],
        'both'       => ['icon' => 'fa-star',             'colorClass' => 'text-success'],
        default      => ['icon' => 'fa-circle',           'colorClass' => 'text-base-content/40'],
    };

    // ツールチップ文字列
    $keysLabel  = ! empty($matchedKeys) ? implode(', ', (array) $matchedKeys) : null;
    $scoreLabel = $score !== null ? number_format($score * 100, 1) . '%' : null;

    $tooltip = match ($reason) {
        'identifier' => $keysLabel
            ? __('ledger.related.identifier_tooltip', ['keys' => $keysLabel])
            : __('ledger.related.reason_identifier'),
        'semantic'   => $scoreLabel
            ? __('ledger.related.score_tooltip', ['score' => $scoreLabel])
            : __('ledger.related.reason_semantic'),
        'both'       => collect([
                $keysLabel  ? __('ledger.related.identifier_tooltip', ['keys' => $keysLabel]) : null,
                $scoreLabel ? __('ledger.related.score_tooltip',      ['score' => $scoreLabel]) : null,
            ])->filter()->implode(' / '),
        default      => $reason,
    };

    // 意味検索スコアに応じたバッジ色（台帳リスト画面の semantic_score と同じ基準）
    $scoreBadgeClass = '';
    if ($score !== null && in_array($reason, ['semantic', 'both'])) {
        $scoreBadgeClass = match (true) {
            $score >= 0.8 => 'badge-success',
            $score >= 0.6 => 'badge-primary',
            $score >= 0.4 => 'badge-info',
            default       => 'badge-ghost',
        };
    }
@endphp

<div class="flex items-center gap-1">
    {{-- 識別理由アイコン（アイコンのみ、ツールチップで詳細表示） --}}
    <div class="tooltip tooltip-right" data-tip="{{ $tooltip }}">
        <i class="fas {{ $config['icon'] }} {{ $config['colorClass'] }}"></i>
    </div>

    {{-- 意味検索スコアバッジ（semantic / both のみ表示 — 台帳リスト画面と同じ表現） --}}
    @if ($score !== null && in_array($reason, ['semantic', 'both']))
        <span class="badge badge-sm {{ $scoreBadgeClass }} flex items-center gap-1">
            <i class="fas fa-brain text-xs"></i>
            {{ number_format($score * 100, 1) }}%
        </span>
    @endif
</div>
