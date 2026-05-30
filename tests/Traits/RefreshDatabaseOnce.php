<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;

/**
 * RefreshDatabaseOnce トレイト
 *
 * テストクラスの最初のテスト実行前に1回だけデータベースをリフレッシュし、
 * 各テストケースはトランザクション内で実行されます。
 *
 * 利点:
 * - RefreshDatabase: マイグレーション毎回実行（遅い）
 * - DatabaseTransactions: マイグレーション不要だが初期化が必要
 * - RefreshDatabaseOnce: クラス全体で1回だけマイグレーション、各テストはトランザクション（高速）
 *
 * 使用方法:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use RefreshDatabaseOnce;
 * }
 * ```
 */
trait RefreshDatabaseOnce
{
    /**
     * このテストクラスでデータベースがリフレッシュされたか
     */
    protected static bool $currentClassMigrated = false;

    /**
     * テストクラスの最初の実行前に1回だけデータベースをリフレッシュ
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // クラスごとにフラグをリセット
        static::$currentClassMigrated = false;
    }

    /**
     * 各テストの前処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // このクラスで最初のテストの場合のみマイグレーション実行
        if (! static::$currentClassMigrated) {
            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
                '--seed' => $this->shouldSeed(),
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            static::$currentClassMigrated = true;
        }

        // 各テストはトランザクション内で実行
        $this->beginDatabaseTransaction();
    }

    /**
     * 各テストの後処理
     */
    protected function tearDown(): void
    {
        // トランザクションロールバックは beforeApplicationDestroyed で自動実行
        parent::tearDown();
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
     * トランザクション対象の接続名を取得
     */
    protected function connectionsToTransact(): array
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact
            : [config('database.default')];
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
