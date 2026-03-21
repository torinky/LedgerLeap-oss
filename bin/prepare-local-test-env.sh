#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

DB_HOST=$(grep "^DB_HOST=" .env.testing 2>/dev/null | cut -d '=' -f2 | tr -d '\r\n')
DB_NAME=$(grep "^DB_DATABASE=" .env.testing 2>/dev/null | cut -d '=' -f2 | tr -d '\r\n')
DB_HOST=${DB_HOST:-mysql}
DB_NAME=${DB_NAME:-ledgerleap_test}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-password}
WORKER_COUNT=$(nproc 2>/dev/null || echo 8)

mysql_root() {
  mysql -h "$DB_HOST" -uroot -p"$MYSQL_ROOT_PASSWORD" --batch --skip-column-names "$@"
}

drop_database() {
  local database_name="$1"
  mysql_root -e "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS \`${database_name}\`; SET FOREIGN_KEY_CHECKS = 1;"
}

table_count() {
  mysql_root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" 2>/dev/null || echo "0"
}

echo "=== ローカルテスト環境準備開始 ==="
echo "DB_HOST=${DB_HOST}"
echo "DB_NAME=${DB_NAME}"

echo "[1/6] central / worker DB を再作成中..."
EXISTING_DBS=$(mysql_root -e "SHOW DATABASES LIKE '${DB_NAME}%';" 2>/dev/null || true)
if [ -n "$EXISTING_DBS" ]; then
  while IFS= read -r db; do
	[ -z "$db" ] && continue
	drop_database "$db"
  done <<< "$EXISTING_DBS"
fi

for attempt in $(seq 1 5); do
  drop_database "$DB_NAME"
  mysql_root -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  if [ "$(table_count)" = "0" ]; then
	echo "  ✓ データベース再作成成功（試行回数: ${attempt}回）"
	break
  fi

  if [ "$attempt" -lt 5 ]; then
	echo "    リトライ ${attempt}/5: まだテーブルが残っています。再試行..."
	sleep 2
  else
	echo "  ✗ エラー: 5回試行しましたが central DB のクリーン化に失敗しました"
	mysql_root -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = '${DB_NAME}' ORDER BY table_name;"
	exit 1
  fi
done

for i in $(seq 1 "$WORKER_COUNT"); do
  mysql_root -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}_test_${i}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
done
mysql_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO 'sail'@'%';"
mysql_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}_test_%\`.* TO 'sail'@'%';" 2>/dev/null || true
for i in $(seq 1 "$WORKER_COUNT"); do
  mysql_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}_test_${i}\`.* TO 'sail'@'%';"
done
mysql_root -e "GRANT ALL PRIVILEGES ON \`tenant%\`.* TO 'sail'@'%';"
mysql_root -e "FLUSH PRIVILEGES;"

echo "[2/6] Laravel キャッシュをクリア中..."
php artisan optimize:clear >/dev/null
php artisan config:clear >/dev/null

echo "[3/6] Laravel 接続で DB を wipe 中..."
php artisan db:wipe --database=mysql_testing --force >/dev/null

echo "[4/6] central DB を migrate 中..."
php artisan migrate --database=mysql_testing --force

echo "[5/6] shared test tenant を作成 / migrate 中..."
php artisan tinker --execute='
$t = \App\Models\Tenant::firstOrCreate(["id" => "test_tenant_id"]);
tenancy()->initialize($t);
\Artisan::call("tenants:migrate", ["--tenants" => [$t->id], "--force" => true]);
echo "Tenant ready: " . $t->id . PHP_EOL;
'

echo "[6/6] migrate 状態を確認中..."
php artisan migrate:status --database=mysql_testing | tail -15

echo "=== ローカルテスト環境準備完了 ==="

