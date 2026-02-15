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

echo "=== テストデータベースリセット開始 ==="
echo ""

# MySQLコンテナ名を確認
CONTAINER_NAME="ledgerleap-mysql-1"
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "エラー: MySQLコンテナ '${CONTAINER_NAME}' が見つかりません。"
    echo "コンテナが起動しているか確認してください: docker ps"
    exit 1
fi

# .env.testingからDB名を取得
if [ ! -f .env.testing ]; then
    echo "エラー: .env.testing ファイルが見つかりません。"
    exit 1
fi

DB_NAME=$(grep "^DB_DATABASE=" .env.testing | cut -d '=' -f2 | tr -d '\r\n')
if [ -z "$DB_NAME" ]; then
    echo "エラー: .env.testing に DB_DATABASE が設定されていません。"
    exit 1
fi

echo "使用するデータベース: ${DB_NAME}"
echo ""

# Step 1: データベースとテナントデータベースを完全に削除・再作成
echo "[1/5] データベースを完全に削除・再作成中..."

echo "  - メインデータベースとテナントデータベースを削除中..."
# テナントデータベースも含めて削除（DB_NAME%で始まるデータベースを全て削除）
TENANT_DBS=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SHOW DATABASES LIKE '${DB_NAME}%';" 2>/dev/null || echo "")
if [ -n "$TENANT_DBS" ]; then
    echo "$TENANT_DBS" | while read db; do
        if [ -n "$db" ]; then
            echo "    削除: ${db}"
            docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS \`${db}\`; SET FOREIGN_KEY_CHECKS = 1;" 2>&1 | grep -v "Warning" > /dev/null
        fi
    done
fi

# メインデータベースを確実に削除（最大10回試行）
echo "  - メインデータベース削除を試行中..."
for attempt in {1..10}; do
    # データベースを削除
    docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS \`${DB_NAME}\`; SET FOREIGN_KEY_CHECKS = 1;" 2>&1 | grep -v "Warning" > /dev/null
    sleep 1

    # 削除されたか確認
    DB_CHECK=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SHOW DATABASES LIKE '${DB_NAME}';" 2>/dev/null || echo "")

    if [ -z "$DB_CHECK" ]; then
        echo "  ✓ データベース削除成功（試行回数: ${attempt}回）"
        break
    fi

    if [ $attempt -lt 10 ]; then
        echo "    リトライ ${attempt}/10: データベースがまだ存在します。2秒後に再試行..."
        sleep 2
    else
        echo "  ✗ データベース削除失敗: 10回試行しましたが削除できませんでした"
        echo "  デバッグ情報: DB_CHECK='${DB_CHECK}'"
        exit 1
    fi
done

echo "  - データベースを作成中..."
OUTPUT=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1)
STATUS=$?

if [ $STATUS -eq 0 ] && ! echo "$OUTPUT" | grep -q "ERROR"; then
    echo "  ✓ データベース作成成功"
elif echo "$OUTPUT" | grep -q "ERROR"; then
    echo "  ✗ データベース作成失敗"
    echo "$OUTPUT" | grep "ERROR"
    exit 1
else
    echo "  ✓ データベース作成成功"
fi

echo "  - データベース確認中..."
if docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SHOW DATABASES LIKE '${DB_NAME}';" 2>/dev/null | grep -q "${DB_NAME}"; then
    echo "  ✓ データベース確認完了"
else
    echo "  ✗ データベース確認失敗"
    exit 1
fi
echo ""

# Step 2: 設定キャッシュをクリア
echo "[2/5] 設定キャッシュをクリア中..."
./vendor/bin/sail artisan config:clear > /dev/null 2>&1 || echo "  ⚠ キャッシュクリア失敗（続行）"
echo "  ✓ キャッシュクリア完了"
echo ""

# Step 3: テナントデータベースの削除確認
echo "[3/5] テナントデータベースの確認中..."
TENANT_DBS=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SHOW DATABASES LIKE '${DB_NAME}%';" 2>/dev/null | grep -v "^${DB_NAME}$" || echo "")
TENANT_COUNT=$(echo "$TENANT_DBS" | grep -c "." || echo "0")
echo "  ✓ テナントデータベース数: ${TENANT_COUNT}"

if [ "$TENANT_COUNT" != "0" ]; then
    echo "  - テナントデータベースを削除中..."
    echo "$TENANT_DBS" | while read tenant_db; do
        if [ -n "$tenant_db" ]; then
            echo "    削除: ${tenant_db}"
            docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "DROP DATABASE IF EXISTS \`${tenant_db}\`;" 2>&1 | grep -v "Warning" > /dev/null
        fi
    done
    echo "  ✓ テナントデータベース削除完了"
fi
echo ""

# Step 4: データベースの状態を確認
echo "[4/5] データベースの状態を確認中..."
TABLE_COUNT=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" 2>/dev/null || echo "0")
echo "  ✓ 現在のテーブル数: ${TABLE_COUNT}"

if [ "$TABLE_COUNT" != "0" ]; then
    echo "  ⚠ 警告: クリーンなデータベースであるべきですが、テーブルが ${TABLE_COUNT} 個存在します"
    echo "  - データベースを再削除・再作成します（最大5回試行）..."

    for retry in {1..5}; do
        # データベースを削除
        docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS \`${DB_NAME}\`; SET FOREIGN_KEY_CHECKS = 1;" 2>&1 | grep -v "Warning" > /dev/null
        sleep 2

        # データベースを再作成
        docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | grep -v "Warning" > /dev/null
        sleep 1

        # 再確認
        TABLE_COUNT=$(docker exec ${CONTAINER_NAME} mysql -uroot -ppassword -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" 2>/dev/null || echo "0")

        if [ "$TABLE_COUNT" = "0" ]; then
            echo "  ✓ データベースクリーン化成功（試行回数: ${retry}回）"
            break
        fi

        if [ $retry -lt 5 ]; then
            echo "    リトライ ${retry}/5: まだテーブルが ${TABLE_COUNT} 個存在します。再試行..."
        else
            echo "  ✗ エラー: 5回試行しましたがテーブルの削除に失敗しました"
            echo "  現在のテーブル数: ${TABLE_COUNT}"
            exit 1
        fi
    done
fi
echo ""

# Step 5: マイグレーション実行
echo "[5/5] マイグレーション実行中..."
echo "  ※ この処理には時間がかかる場合があります..."
echo ""

./vendor/bin/sail artisan migrate --env=testing --force 2>&1 | tee /tmp/migrate_output.log
MIGRATE_STATUS=${PIPESTATUS[0]}

echo ""
if [ $MIGRATE_STATUS -eq 0 ]; then
    echo "  ✓ マイグレーション完了"
else
    echo "  ✗ マイグレーション失敗（終了コード: $MIGRATE_STATUS）"
    echo "  詳細: /tmp/migrate_output.log を確認してください"
    exit 1
fi
echo ""

echo "=== テストデータベースリセット完了 ==="
echo ""
echo "マイグレーション状態:"
./vendor/bin/sail artisan migrate:status --env=testing 2>&1 | tail -15 || echo "ステータス確認失敗"
echo ""
echo "✅ テストDBの準備が完了しました"


