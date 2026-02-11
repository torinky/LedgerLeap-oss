#!/bin/bash
#
# テストデータベースを完全にリセットしてマイグレーションを実行するスクリプト
#
# 使用方法:
#   ./bin/reset-test-db.sh
#
# 前提条件:
#   - .env.testing で DB_CONNECTION=mysql_testing が設定されていること
#   - config/database.php に mysql_testing 接続が定義されていること
#

set -e

echo "=== テストデータベースリセット開始 ==="
echo ""

# Step 1: データベースを削除・再作成
echo "[1/4] データベースを削除・再作成中..."

# データベース削除（複数回試行）
echo "  - データベース削除中..."
docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "DROP DATABASE IF EXISTS ledgerleap_test;" 2>/dev/null || true
sleep 1
docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "DROP DATABASE IF EXISTS ledgerleap_test;" 2>/dev/null || true
sleep 1

# データベースを作成
echo "  - データベース作成中..."
CREATE_OUTPUT=$(docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "CREATE DATABASE ledgerleap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1)
CREATE_STATUS=$?

if [ $CREATE_STATUS -eq 0 ]; then
    echo "  ✓ データベース作成成功"
else
    echo "  ✗ データベース作成失敗"
    echo "  エラー: $CREATE_OUTPUT"
    exit 1
fi

# 作成されたことを確認
echo "  - データベース確認中..."
RESULT=$(docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -N -e "SHOW DATABASES LIKE 'ledgerleap_test';" 2>/dev/null)

if [ "$RESULT" = "ledgerleap_test" ]; then
    echo "  ✓ データベース再作成完了"
else
    echo "  ✗ データベース確認失敗"
    exit 1
fi
echo ""

# Step 2: 設定キャッシュをクリア
echo "[2/4] 設定キャッシュをクリア中..."
./vendor/bin/sail artisan config:clear > /dev/null 2>&1
echo "  ✓ キャッシュクリア完了"
echo ""

# Step 3: テーブル一覧を確認（デバッグ用）
echo "[3/4] データベースの状態を確認中..."
TABLE_COUNT=$(docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ledgerleap_test';" 2>/dev/null)
echo "  ✓ 現在のテーブル数: ${TABLE_COUNT}"

# migrationsテーブルをクリア（マイグレーション履歴をリセット）
if [ "$TABLE_COUNT" != "0" ]; then
    echo "  - マイグレーション履歴をクリア中..."
    docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "TRUNCATE TABLE ledgerleap_test.migrations;" 2>/dev/null || true
    echo "  ✓ マイグレーション履歴クリア完了"
fi
echo ""

# Step 4: マイグレーション実行
echo "[4/4] マイグレーション実行中..."
./vendor/bin/sail artisan migrate --env=testing
echo ""

echo "=== テストデータベースリセット完了 ==="
echo ""
echo "マイグレーション状態を確認:"
./vendor/bin/sail artisan migrate:status --env=testing | tail -10

