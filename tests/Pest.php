<?php

$abortReasons=[];

// フレームワーク未ブート時でも安全な環境変数の取得関数
$envVar = static function (string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
};

// 環境名が production なら中止（app() や config() を使わない）
$appEnv = $envVar('APP_ENV');
if ($appEnv === 'production') {
    $abortReasons[] = 'APP_ENV=production';
}

// DB 接続情報も環境変数から直接取得（まだ config は使わない）
$connection = $envVar('DB_CONNECTION', 'mysql');
$dbName    = $envVar('DB_DATABASE');
$dbHost    = $envVar('DB_HOST');

/*$prodLikeHosts = ['prod-db.example.com', '10.0.0.', '192.168.10.10']; // 実態に合わせて
$looksLikeProdHost = $dbHost && collect($prodLikeHosts)->first(fn($h) => str_starts_with((string)$dbHost, $h));

if ($looksLikeProdHost) {
    $abortReasons[] = "DB_HOST looks like production: $dbHost";
}*/

// 明示的に本番DB名をブロック（例）
$blockedDbNames = ['ledgerleap', 'ledgerleap_prod'];
if (in_array($dbName, $blockedDbNames, true)) {
    $abortReasons[] = "DB_DATABASE is blocked: $dbName";
}

if ($abortReasons) {
    fwrite(STDERR, "[ABORT TEST] Refusing to run tests against a production-like database:\n - " . implode("\n - ", $abortReasons) . "\n");
    exit(1);
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
