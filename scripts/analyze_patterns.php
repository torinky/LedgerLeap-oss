<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AutoNumberPatternService;

$service = app(AutoNumberPatternService::class);
$patterns = $service->getPatterns();

echo "Total patterns: " . $patterns->count() . "\n";
echo "\nFirst 10 patterns:\n";
$patterns->take(10)->each(function ($p, $i) {
    echo "  [$i] {$p['define_title']} / {$p['column_name']}: {$p['pattern']}\n";
});

// ledger_define_id=1, col_id=8 のパターンを検索
$target = $patterns->first(function ($p) {
    return $p['define_id'] === 1 && $p['column_name'] === '日報番号';
});

if ($target) {
    echo "\nTarget pattern (ledger_define_id=1, col_id=8 / 日報番号): {$target['pattern']}\n";
}

// パターンの複雑度を分析
echo "\nPattern complexity analysis:\n";
$complexity = $patterns->map(function ($p) {
    $len = strlen($p['pattern']);
    $hasBacktrack = str_contains($p['pattern'], '.*?');
    return [
        'define_title' => $p['define_title'],
        'column_name' => $p['column_name'],
        'length' => $len,
        'has_backtrack' => $hasBacktrack,
        'pattern' => $p['pattern'],
    ];
});

echo "  Patterns with .*?: " . $complexity->where('has_backtrack', true)->count() . "\n";
echo "  Max pattern length: " . $complexity->max('length') . "\n";
echo "  Avg pattern length: " . round($complexity->avg('length'), 2) . "\n";
