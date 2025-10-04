<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * RefreshDatabaseWithTenant トレイト
 *
 * テストクラスの最初のテスト実行前に1回だけマイグレーションとテナント作成を行い、
 * 各テストケースはトランザクション内で実行されます。
 *
 * 特徴:
 * - クラス全体で1回だけマイグレーション実行
 * - テナントも1回だけ作成・初期化
 * - 各テストはトランザクション内で実行（高速）
 * - テナントデータはトランザクション外なので永続化
 *
 * 使用方法:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use RefreshDatabaseWithTenant;
 * }
 * ```
 */
trait RefreshDatabaseWithTenant
{
    /**
     * このテストクラスでデータベースが初期化されたか
     */
    protected static bool $databaseInitialized = false;

    /**
     * 作成されたテナント（クラス全体で共有）
     */
    protected static $sharedTenant = null;

    /**
     * トランケート可能なテーブルのキャッシュ
     */
    protected static ?array $truncatableTablesCache = null;

    /**
     * テストクラスの最初の実行前に1回だけ初期化
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // クラスごとにフラグをリセット
        static::$databaseInitialized = false;
        static::$sharedTenant = null;
        static::$truncatableTablesCache = null;
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
        // このクラスで最初のテストの場合のみマイグレーション実行
        if (! static::$databaseInitialized) {
            $this->refreshDatabase();
            $this->createSharedTenant();

            // テナントを初期化してからテナントデータベースをマイグレーション
            tenancy()->initialize(static::$sharedTenant);
            $this->migrateTenantDatabase();

            // 共有データ（ユーザーなど）を作成
            $this->createSharedData();

            static::$databaseInitialized = true;
        } else {
            // 2回目以降のテストではトランケートしてクリーンアップ
            if (static::$sharedTenant) {
                tenancy()->initialize(static::$sharedTenant);
                $this->truncateTenantTables();
            }
        }
    }

    /**
     * 各テストの後処理
     */
    protected function tearDown(): void
    {
        // トランザクションロールバックは beforeApplicationDestroyed で自動実行される
        parent::tearDown();
    }

    /**
     * データベースのリフレッシュ（最初の1回のみ実行）
     *
     * セントラルデータベース（tenants テーブル等）をリフレッシュ
     */
    protected function refreshDatabase(): void
    {
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
     * テナント初期化後に実行される
     */
    protected function migrateTenantDatabase(): void
    {
        $this->artisan('tenants:migrate', [
            '--tenants' => [static::$sharedTenant->id],
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * 共有テナントの作成（最初の1回のみ実行）
     */
    protected function createSharedTenant(): void
    {
        // テナントを作成（トランザクション外なので永続化される）
        static::$sharedTenant = \App\Models\Tenant::factory()->create();
    }

    /**
     * トランザクションの開始
     */
    protected function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            try {
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

                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->rollBack();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    /**
     * 共有テナントを取得
     */
    protected function getTenant()
    {
        return static::$sharedTenant;
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

        // トランケート可能なテーブルを取得（初回のみチェック）
        if (static::$truncatableTablesCache === null) {
            $tablesToCheck = $this->getTablesToTruncate();
            static::$truncatableTablesCache = [];

            foreach ($tablesToCheck as $table) {
                if ($connection->getSchemaBuilder()->hasTable($table)) {
                    static::$truncatableTablesCache[] = $table;
                }
            }
        }

        // キャッシュされたテーブルをトランケート
        foreach (static::$truncatableTablesCache as $table) {
            $connection->table($table)->truncate();
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
        // モックを多用するテストではほとんど不要
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
     * テナントデータベースに対してトランザクションを開始
     */
    protected function connectionsToTransact(): array
    {
        // プロパティで明示的に指定されている場合はそれを使用
        if (property_exists($this, 'connectionsToTransact')) {
            return $this->connectionsToTransact;
        }

        // テナント接続を使用
        return ['tenant'];
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
