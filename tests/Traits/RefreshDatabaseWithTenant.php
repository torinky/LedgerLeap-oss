<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;

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
     * テストクラスの最初の実行前に1回だけ初期化
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // クラスごとにフラグをリセット
        static::$databaseInitialized = false;
        static::$sharedTenant = null;
    }

    /**
     * 各テストの前処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // このクラスで最初のテストの場合のみマイグレーション実行
        if (! static::$databaseInitialized) {
            $this->refreshDatabase();
            $this->createSharedTenant();
            static::$databaseInitialized = true;
        }

        // テナントを初期化
        if (static::$sharedTenant) {
            tenancy()->initialize(static::$sharedTenant);
        }

        // トランザクション開始（テナント初期化後）
        $this->beginDatabaseTransaction();
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
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
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
