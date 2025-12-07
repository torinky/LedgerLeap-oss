<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // use CreatesApplication; // これを削除

    protected bool $tenancy = false;

    protected Tenant $tenant;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Laravel 10+ の新しいブートストラップ方法
        $app->bootstrapWith([
            \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
            \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
            \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
            \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
            \Illuminate\Foundation\Bootstrap\BootProviders::class,
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->tenancy) {
            $this->initializeTenancy();
        }
    }

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

    protected function initializeTenancy(): void // $domain 引数を削除
    {
        // テナントが存在しない場合のみ作成
        $this->tenant = \App\Models\Tenant::firstOrCreate(['id' => 'test_tenant_id'], ['id' => 'test_tenant_id']);
        // ドメイン関連の行を削除

        tenancy()->initialize($this->tenant);

        // テストリクエストのホスト設定は不要（パスベースのため）
        // $this->withServerVariables(['HTTP_HOST' => $this->tenant->domains()->first()->domain]); // 削除
    }

    protected function tearDown(): void
    {
        // テスト後にテナントとそのデータベースをクリーンアップ (デバッグのため一時的にコメントアウト)
        // config([
        //     'tenancy.queue_database_deletion' => false,
        //     'tenancy.delete_database_after_tenant_deletion' => true,
        // ]);

        // Tenancy コンテキストを終了して central DB に戻す (デバッグのため一時的にコメントアウト)
        // tenancy()->end();

        // central 側のテナントを削除（DB も設定に従い削除される） (デバッグのため一時的にコメントアウト)
        // \App\Models\Tenant::query()->get()->each->delete();

        parent::tearDown();
    }
}
