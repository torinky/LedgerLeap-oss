<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    // use CreatesApplication; // これを削除

    protected bool $tenancy = false;

    protected Tenant $tenant;

    /**
     * true（デフォルト）の場合、setUp() で Queue::fake() を自動実行する。
     * Ledger::factory()->create() → LedgerObserver → ProcessLedgerForRagJob が
     * Embeddingコンテナに接続しないようにするための措置。
     *
     * RAGジョブ/Observerのdispatch自体を検証するテストではfalseに設定すること:
     *   - LedgerObserverTest
     *   - ProcessLedgerForRagJobTest
     *   - VectorizeAttachedFileTest
     *   - FinalizeAttachedFileProcessingTest
     * 実コンテナが必要なテストは #[Group('external')] を付与してCIから除外すること。
     */
    protected bool $fakeQueue = true;

    /**
     * テストは Laravel Sail / Docker interpreter からのみ実行を許可する。
     * ホスト実行では mysql ホスト名解決が壊れやすいため、DB接続前に明示中止する。
     *
     * @return array<int, string>
     */
    public static function getTestRuntimeAbortReasons(): array
    {
        $envVar = static function (string $key, mixed $default = null): mixed {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

            return $value !== false && $value !== null ? $value : $default;
        };

        $abortReasons = [];
        $appEnv = $envVar('APP_ENV');

        if ($appEnv === 'production') {
            $abortReasons[] = 'APP_ENV=production';
        }

        $runningInsideSail = (bool) $envVar('LARAVEL_SAIL') || file_exists('/.dockerenv') || (bool) $envVar('GITHUB_ACTIONS');

        if (! $runningInsideSail) {
            $abortReasons[] = 'Tests must run inside Laravel Sail / Docker interpreter';
        }

        $dbName = $envVar('DB_DATABASE');
        $blockedDbNames = ['ledgerleap', 'ledgerleap_prod'];

        if (in_array($dbName, $blockedDbNames, true)) {
            $abortReasons[] = "DB_DATABASE is blocked: {$dbName}";
        }

        return $abortReasons;
    }

    /**
     * @param  array<int, string>  $abortReasons
     */
    public static function formatTestRuntimeAbortMessage(array $abortReasons): string
    {
        $messageLines = array_map(
            static fn (string $reason): string => " - {$reason}",
            $abortReasons,
        );

        return implode("\n", [
            '[ABORT TEST] Refusing to run tests in the current runtime:',
            ...$messageLines,
            '',
            'Use Laravel Sail or a Docker-based PhpStorm interpreter instead.',
            'Examples:',
            '  ./vendor/bin/sail test',
            '  ./vendor/bin/sail test tests/Feature/Api',
            '  ./vendor/bin/sail pest --testsuite=Feature --exclude-group=external --exclude-group=database-migrations',
            '',
        ]);
    }

    public static function abortIfTestsShouldNotRunInCurrentRuntime(): void
    {
        $abortReasons = static::getTestRuntimeAbortReasons();

        if ($abortReasons !== []) {
            fwrite(STDERR, static::formatTestRuntimeAbortMessage($abortReasons));
            exit(1);
        }
    }

    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Laravel 10+ の新しいブートストラップ方法
        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
            HandleExceptions::class,
            RegisterFacades::class,
            SetRequestForConsole::class,
            RegisterProviders::class,
            BootProviders::class,
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->fakeQueue) {
            Queue::fake();
        }

        if ($this->tenancy) {
            $this->initializeTenancy();
        }
    }

    public static function setUpBeforeClass(): void
    {
        static::abortIfTestsShouldNotRunInCurrentRuntime();

        parent::setUpBeforeClass();
    }

    protected function initializeTenancy(): void // $domain 引数を削除
    {
        // テナントが存在しない場合のみ作成
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->firstOrCreate(['id' => 'test_tenant_id'], ['id' => 'test_tenant_id']);
        $this->tenant = $tenant;
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
