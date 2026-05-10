<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\AutoLinkService;

$autoLinkService = app(AutoLinkService::class);

echo "=== マッチ数に対する処理時間の増加 ===\n\n";

$matchCounts = [1, 5, 10, 20, 50, 100, 200];

foreach ($matchCounts as $count) {
    $numbers = array_map(fn ($i) => 'DAILY-'.str_pad($i, 4, '0', STR_PAD_LEFT), range(1, $count));
    $text = implode(' ', $numbers);

    $times = [];
    $iterations = 50;

    // ウォームアップ
    $autoLinkService->convert($text);

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $result = $autoLinkService->convert($text);
        $end = microtime(true);
        $times[] = ($end - $start) * 1000;
    }

    sort($times);
    $median = $times[(int) ($iterations / 2)];
    $mean = array_sum($times) / count($times);
    $max = max($times);

    // マッチ数をカウント
    $matchCount = substr_count($result, '<a href=');

    echo sprintf(
        "matches=%3d  text_len=%5d  med=%7.2fms  mean=%7.2fms  max=%7.2fms  actual_links=%3d\n",
        $count,
        strlen($text),
        $median,
        $mean,
        $max,
        $matchCount
    );
}

echo "\n=== Blade::render() コスト計測 ===\n\n";

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;

$iconName = 'o-link';
$times = [];
$iterations = 1000;

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    Blade::render("<x-mary-icon name='{$iconName}' class='inline-block h-4 w-4 mr-1 -mt-1' />");
    $end = microtime(true);
    $times[] = ($end - $start) * 1000;
}

$mean = array_sum($times) / count($times);
$max = max($times);

echo sprintf("Blade::render() single call: mean=%.4fms  max=%.4fms\n", $mean, $max);

echo "\n=== DOM操作コスト計測 ===\n\n";

$dom = new DOMDocument;
$dom->loadHTML('<div>test</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$textNode = $dom->createTextNode('Hello World');

$times = [];
$iterations = 1000;

for ($i = 0; $i < $iterations; $i++) {
    $fragment = $dom->createDocumentFragment();
    $start = microtime(true);
    @$fragment->appendXML('<span>test</span>');
    $end = microtime(true);
    $times[] = ($end - $start) * 1000;
}

$mean = array_sum($times) / count($times);
echo sprintf("createDocumentFragment + appendXML: mean=%.4fms\n", $mean);

$times = [];
for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    $node = $dom->createTextNode('Hello World');
    $end = microtime(true);
    $times[] = ($end - $start) * 1000;
}

$mean = array_sum($times) / count($times);
echo sprintf("createTextNode: mean=%.4fms\n", $mean);

$times = [];
for ($i = 0; $i < $iterations; $i++) {
    $fragment = $dom->createDocumentFragment();
    $fragment->appendChild($dom->createTextNode('Hello'));
    $fragment->appendChild($dom->createTextNode('World'));
    $start = microtime(true);
    $dom->getElementsByTagName('div')->item(0)->replaceChild($fragment, $textNode);
    $end = microtime(true);
    $times[] = ($end - $start) * 1000;
}

$mean = array_sum($times) / count($times);
echo sprintf("replaceChild: mean=%.4fms\n", $mean);
