<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AutoLinkService;
use App\Services\HtmlProcessorService;

$autoLinkService = app(AutoLinkService::class);

// テストケース: 異なるテキスト長とパターン
$testCases = [
    'short_no_match' => 'Hello World',
    'short_match' => 'DAILY-0002',
    'medium_no_match' => str_repeat('Hello World ', 50),
    'medium_match' => str_repeat('Hello World ', 50) . ' DAILY-0002 ' . str_repeat('Hello World ', 50),
    'long_no_match' => str_repeat('Hello World ', 500),
    'long_match' => str_repeat('Hello World ', 500) . ' DAILY-0002 ' . str_repeat('Hello World ', 500),
    'many_numbers' => implode(' ', array_map(fn($i) => "DAILY-".str_pad($i, 4, '0', STR_PAD_LEFT), range(1, 100))),
    'markdown_text' => "# 見出し\n\n本文です。DAILY-0002 の案件について。\n\n- 項目1\n- 項目2\n\n[DAILY-0003](https://example.com)",
];

echo "=== AutoLinkService::convert() ベンチマーク ===\n\n";

foreach ($testCases as $name => $text) {
    $times = [];
    $iterations = 100;
    
    // ウォームアップ
    $autoLinkService->convert($text);
    
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $result = $autoLinkService->convert($text);
        $end = microtime(true);
        $times[] = ($end - $start) * 1000;
    }
    
    sort($times);
    $median = $times[(int)($iterations / 2)];
    $mean = array_sum($times) / count($times);
    $max = max($times);
    $min = min($times);
    
    $textLen = strlen($text);
    $resultLen = strlen($result);
    
    echo sprintf(
        "%-20s len=%5d  med=%6.2fms  mean=%6.2fms  min=%6.2fms  max=%6.2fms  result_len=%5d\n",
        $name,
        $textLen,
        $median,
        $mean,
        $min,
        $max,
        $resultLen
    );
}

echo "\n=== 正規表現単体計測 ===\n\n";

$patterns = [
    'DAILY' => '/(DAILY\-\d{4,}.*?)/u',
    'EXP' => '/(EXP\-\d{4,})(?![0-9])/u',
    'INSP' => '/(INSP\-\d{4,}.*?)/u',
    'WR' => '/(WR\-\d{4,}.*?)/u',
];

$testTexts = [
    'no_match' => 'Hello World Hello World',
    'single_match' => 'DAILY-0002',
    'many_match' => implode(' ', array_map(fn($i) => "DAILY-".str_pad($i, 4, '0', STR_PAD_LEFT), range(1, 100))),
];

foreach ($patterns as $pName => $pattern) {
    foreach ($testTexts as $tName => $text) {
        $times = [];
        $iterations = 1000;
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            preg_match($pattern, $text);
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
        }
        
        $mean = array_sum($times) / count($times);
        $max = max($times);
        
        echo sprintf(
            "%-10s / %-12s  mean=%8.4fms  max=%8.4fms\n",
            $pName,
            $tName,
            $mean,
            $max
        );
    }
    echo "\n";
}
