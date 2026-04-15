<?php

namespace Tests\Traits;

use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;

/**
 * RefreshDatabaseWithTenant トレイト
 *
 * テスト実行プロセス全体で1回だけ migrate:fresh + tenants:migrate を行い、
 * 各テストケースはトランザクション内で実行されます。
 */
trait RefreshDatabaseWithTenant
{
    /**
     * クラスごとにデータベースが初期化されたかを管理
     *
     * public: TestDatabaseState::reset() からリセットできるよう公開
     */
    public static array $databaseInitializedByClass = [];

    /**
     * プロセス全体で migrate:fresh が実行済みかを管理するグローバルフラグ
     * 全クラスで共有し、1回のみ migrate:fresh を実行する
     *
     * public: TestDatabaseState::reset() からリセットできるよう公開
     */
    public static bool $globalDatabaseMigrated = false;

    /**
     * 作成されたテナント（後方互換のため保持）
     */
    public static $sharedTenant = null;

    /**
     * プロセスごとの migrate 実行状態
     *
     * public: TestDatabaseState::reset() からリセットできるよう公開
     *
     * @var array<string, bool>
     */
    public static array $migratedByProcess = [];

    /**
     * プロセスごとの共有テナント
     *
     * public: TestDatabaseState::reset() からリセットできるよう公開
     *
     * @var array<string, mixed>
     */
    public static array $sharedTenantsByProcess = [];

    /**
     * トランケート可能なテーブルのキャッシュ
     *
     * public: TestDatabaseState::reset() からリセットできるよう公開
     */
    public static ?array $truncatableTablesCache = null;

    /**
     * グローバル状態を全てリセットする
     *
     * TestDatabaseState::reset() から呼び出す。
     * トレイトの静的プロパティへの外部直接アクセスを避けるための窓口。
     *
     * PHP では trait の静的メンバーをトレイトを use していないクラスから
     * 直接参照することは非推奨のため、このメソッド経由でリセットする。
     */
    public static function resetState(): void
    {
        static::$globalDatabaseMigrated = false;
        static::$sharedTenant = null;
        static::$databaseInitializedByClass = [];
        static::$truncatableTablesCache = null;
        static::$migratedByProcess = [];
        static::$sharedTenantsByProcess = [];
    }

    /**
     * テストクラスの最初の実行前に1回だけ初期化
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // クラスごとのフラグをリセット（グローバルフラグはリセットしない）
        $className = static::class;
        static::$databaseInitializedByClass[$className] = false;
    }

    /**
     * RefreshDatabaseWithTenant の初期化処理
     *
     * テストクラスのsetUp()から明示的に呼び出す必要があります：
     *
     * protected function setUp(): void
     * {
     *     parent::setUp();
     *     $this->setUpRefreshDatabaseWithTenant();
     *     // ... 残りのセットアップ
     * }
     */
    protected function setUpRefreshDatabaseWithTenant(): void
    {
        $className = static::class;
        $initialized = static::$databaseInitializedByClass[$className] ?? false;

        if (! $initialized) {
            if (! static::hasMigratedForCurrentProcess()) {
                $this->refreshDatabase();
                static::markMigratedForCurrentProcess();
            }

            $tenant = static::getSharedTenantForCurrentProcess();
            if (! $tenant) {
                $this->createSharedTenant();
                $tenant = static::getSharedTenantForCurrentProcess();
                tenancy()->initialize($tenant);
                $this->migrateTenantDatabase();
            } else {
                tenancy()->initialize($tenant);
            }

            $this->createSharedData();
            static::$databaseInitializedByClass[$className] = true;
        } else {
            if ($tenant = static::getSharedTenantForCurrentProcess()) {
                tenancy()->initialize($tenant);
            }
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * 現在のテストプロセスキーを返す
     */
    protected static function currentProcessKey(): string
    {
        return (string) (ParallelTesting::token() ?: 'global');
    }

    protected static function hasMigratedForCurrentProcess(): bool
    {
        return static::$migratedByProcess[static::currentProcessKey()] ?? false;
    }

    protected function currentTestingDatabaseName(): string
    {
        return $this->normalizeTestingDatabaseName(
            $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? 'ledgerleap_test'
        );
    }

    protected function normalizeTestingDatabaseName(string $databaseName): string
    {
        return preg_replace('/(?:_test_\d+)+$/', '', $databaseName) ?: $databaseName;
    }

    protected function currentWorkerDatabaseName(): ?string
    {
        $token = ParallelTesting::token();

        if (! $token) {
            return null;
        }

        return $this->currentTestingDatabaseName().'_test_'.$token;
    }

    protected function isCiEnvironment(): bool
    {
        $value = $_SERVER['CI']
            ?? $_ENV['CI']
            ?? $_SERVER['GITHUB_ACTIONS']
            ?? $_ENV['GITHUB_ACTIONS']
            ?? getenv('CI');

        if ($value === false || $value === null || $value === '') {
            $value = getenv('GITHUB_ACTIONS');
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    protected static function markMigratedForCurrentProcess(): void
    {
        static::$migratedByProcess[static::currentProcessKey()] = true;
        // 後方互換: 既存のフラグを参照するコードに配慮
        static::$globalDatabaseMigrated = true;
    }

    protected static function getSharedTenantForCurrentProcess()
    {
        return static::$sharedTenantsByProcess[static::currentProcessKey()] ?? null;
    }

    protected static function setSharedTenantForCurrentProcess($tenant): void
    {
        static::$sharedTenantsByProcess[static::currentProcessKey()] = $tenant;
        // 後方互換: 既存の参照先を更新
        static::$sharedTenant = $tenant;
    }

    /**
     * 各テストの後処理
     *
     * parent::tearDown() が beforeApplicationDestroyed コールバック（tenant rollBack）を
     * 実行するため、その直前まで tenancy を確実に初期化状態に保つ。
     */
    protected function tearDown(): void
    {
        // beforeApplicationDestroyed の rollBack コールバックが tenant 接続を
        // 使えるよう、parent::tearDown() 前にテナントを再初期化する
        if ($tenant = static::getSharedTenantForCurrentProcess()) {
            if (! tenancy()->initialized) {
                try {
                    tenancy()->initialize($tenant);
                } catch (\Throwable) {
                    // 初期化失敗は無視（接続が切断済みなど）
                }
            }
        }

        parent::tearDown();
    }

    /**
     * データベースのリフレッシュ（プロセス全体で1回のみ実行）
     *
     * - CI環境（CI=true）: migrate:fresh はスキップ。
     *   ワークフローの "Migrate database" ステップで既に実行済みのため。
     * - ローカル並列実行（TEST_TOKEN が設定されている）:
     *   phpunit.xml の $_SERVER['DB_DATABASE']（ベース名）から直接
     *   ワーカーDB名を構築して切り替え、migrate:fresh で初期化する。
     * - ローカル直列実行: 従来通り migrate:fresh で完全リセット。
     */
    protected function refreshDatabase(): void
    {
        $workerDatabase = $this->currentWorkerDatabaseName();

        if ($workerDatabase) {
            $baseDb = $this->currentTestingDatabaseName();

            DB::purge('mysql_testing');
            config()->set('database.connections.mysql_testing.database', $baseDb);
            try {
                DB::connection('mysql_testing')->statement(
                    "CREATE DATABASE IF NOT EXISTS `{$workerDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            } catch (\Throwable $e) {
                // CREATE DATABASE に失敗した場合はワーカーDBが既に存在するとみなして続行
            }

            DB::purge('mysql_testing');
            config()->set('database.connections.mysql_testing.database', $workerDatabase);

            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ]);
            $this->app[Kernel::class]->setArtisan(null);

            return;
        }

        if ($this->isCiEnvironment()) {
            // CI の直列実行: ワークフローで migrate --force 実行済みのため何もしない
            return;
        }

        // ローカル直列実行: 従来通り migrate:fresh
        $this->artisan('migrate:fresh', [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--seed' => $this->shouldSeed(),
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * テナントデータベースのマイグレーション（最初の1回のみ実行）
     *
     * テナント初期化後に実行される。
     * CI環境ではワークフローの "Setup test tenant" ステップで実行済みのためスキップ。
     * ローカル並列実行時はテナントIDがプロセスキーで分離されているため通常通り実行。
     */
    protected function migrateTenantDatabase(): void
    {
        if ($this->isCiEnvironment()) {
            // CI: ワークフローで tenants:migrate 実行済みのため何もしない
            return;
        }

        $tenant = static::getSharedTenantForCurrentProcess();
        $this->artisan('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * 共有テナントの作成（最初の1回のみ実行）
     *
     * CI環境ではワークフローの "Setup test tenant" ステップで ci-test-tenant が
     * 作成済みのため、それを再利用する。
     */
    protected function createSharedTenant(): void
    {
        $processKey = static::currentProcessKey();

        if ($this->isCiEnvironment()) {
            $candidates = $processKey === 'global'
                ? ['ci-test-tenant']
                : ["ci-test-tenant_{$processKey}", 'ci-test-tenant'];

            foreach ($candidates as $candidateId) {
                $existing = Tenant::find($candidateId);
                if ($existing) {
                    static::setSharedTenantForCurrentProcess($existing);

                    return;
                }
            }

            if ($existing = Tenant::first()) {
                static::setSharedTenantForCurrentProcess($existing);

                return;
            }
        }

        $tenantId = $processKey === 'global' ? 'test_tenant' : "test_tenant_{$processKey}";

        $tenant = Tenant::find($tenantId)
            ?? Tenant::factory()->create(['id' => $tenantId]);

        static::setSharedTenantForCurrentProcess($tenant);
    }

    /**
     * トランザクションの開始
     */
    protected function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            try {
                // テナント接続の場合、テナントが初期化されているか確認
                if ($name === 'tenant' && ! tenancy()->initialized) {
                    continue;
                }

                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->beginTransaction();
                $connection->setEventDispatcher($dispatcher);
            } catch (\InvalidArgumentException $e) {
                // テナント接続が設定されていない場合はスキップ
                if ($name === 'tenant') {
                    continue;
                }
                throw $e;
            }
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                // テナント接続の場合、テナントが初期化されているか確認
                if ($name === 'tenant' && ! tenancy()->initialized) {
                    continue;
                }

                try {
                    $connection = $database->connection($name);
                    $dispatcher = $connection->getEventDispatcher();

                    $connection->unsetEventDispatcher();
                    $connection->rollBack();
                    $connection->setEventDispatcher($dispatcher);
                    // disconnect() は呼ばない — 次テストの beginTransaction が
                    // 同一接続を再利用できるよう接続を維持する
                } catch (\InvalidArgumentException $e) {
                    // テナント接続が設定されていない場合はスキップ
                    if ($name === 'tenant') {
                        continue;
                    }
                    throw $e;
                }
            }
        });
    }

    /**
     * 共有テナントを取得
     */
    protected function getTenant()
    {
        return static::getSharedTenantForCurrentProcess();
    }

    /**
     * テナントテーブルをトランケートしてクリーンアップ
     *
     * 各テストの前に呼ばれ、前のテストのデータをクリーンアップします。
     * 共有データ（ユーザーなど）は保持されます。
     */
    protected function truncateTenantTables(): void
    {
        $connection = DB::connection('mysql');

        // 外部キー制約を一時的に無効化してトランケート
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        // トランケート対象のテーブルを取得
        $tablesToCheck = $this->getTablesToTruncate();

        if (empty($tablesToCheck)) {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');

            return;
        }

        foreach ($tablesToCheck as $table) {
            if ($connection->getSchemaBuilder()->hasTable($table)) {
                $connection->table($table)->truncate();
            }
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * トランケート対象のテーブル一覧を取得
     *
     * テストクラスでオーバーライドまたはプロパティで指定できます。
     * デフォルトでは最小限のテーブルのみトランケートします。
     *
     * @return array<string>
     */
    protected function getTablesToTruncate(): array
    {
        // テストクラスでプロパティが定義されている場合はそれを使用
        if (property_exists($this, 'tablesToTruncate')) {
            return $this->tablesToTruncate;
        }

        // デフォルトでは最小限のテーブルのみトランケート
        return [
            'personal_access_tokens', // トークンはクリーンアップ
        ];
    }

    /**
     * 共有データ（各テストで共通して使用するデータ）を作成
     *
     * テストクラスでオーバーライドして、共有データを作成できます。
     * デフォルトでは何も作成しません。
     */
    protected function createSharedData(): void
    {
        // テストクラスで必要に応じてオーバーライド
        // 例: テスト用ユーザーを作成して static::$sharedUser に保存
    }

    /**
     * トランザクション対象の接続名を取得
     *
     * セントラルとテナントの両方のデータベースに対してトランザクションを開始
     */
    protected function connectionsToTransact(): array
    {
        // プロパティで明示的に指定されている場合はそれを使用
        if (property_exists($this, 'connectionsToTransact')) {
            return $this->connectionsToTransact;
        }

        // セントラル（mysql_testing）とテナント接続の両方を使用
        return ['mysql_testing', 'tenant'];
    }

    /**
     * ビューを削除するかどうか
     */
    protected function shouldDropViews(): bool
    {
        return property_exists($this, 'dropViews') ? $this->dropViews : false;
    }

    /**
     * カスタム型を削除するかどうか
     */
    protected function shouldDropTypes(): bool
    {
        return property_exists($this, 'dropTypes') ? $this->dropTypes : false;
    }

    /**
     * シーディングを実行するかどうか
     */
    protected function shouldSeed(): bool
    {
        return property_exists($this, 'seed') ? $this->seed : false;
    }
}
