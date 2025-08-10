<?php

namespace tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // アプリ未ブートでも安全な環境変数チェック
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: null;
        if ($appEnv === 'production') {
            fwrite(STDERR, "[ABORT TEST] APP_ENV=production\n");
            exit(1);
        }
    }

}
