<?php

namespace Tests\Support;

use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * テストDBグローバル状態の管理クラス
 *
 * RefreshDatabaseWithTenant トレイトが持つ静的プロパティを
 * トレイト外から操作するためのヘルパー。
 *
 * PHP では trait の静的メンバーをトレイトを use していないクラスから
 * 直接呼び出すことは非推奨のため、このクラス自身がトレイトを use して
 * resetState() メソッド経由でリセットを行う。
 *
 * 使用例（setUp() 内で migrate:fresh を実行したテストクラス）:
 *   TestDatabaseState::reset();
 */
class TestDatabaseState
{
    use RefreshDatabaseWithTenant;

    /**
     * RefreshDatabaseWithTenant が管理するグローバル状態を全てリセットする。
     *
     * migrate:fresh を実行した後に呼び出すことで、後続の
     * RefreshDatabaseWithTenant 使用テストが再マイグレーションを正常に実行できる。
     */
    public static function reset(): void
    {
        static::resetState();
    }
}
