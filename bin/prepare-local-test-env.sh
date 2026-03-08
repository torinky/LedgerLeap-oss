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

echo "=== ローカルテスト環境準備開始 ==="
echo "DB_HOST=${DB_HOST}"
echo "DB_NAME=${DB_NAME}"

echo "[1/5] central / worker DB を再作成中..."
EXISTING_DBS=$(mysql_root -e "SHOW DATABASES LIKE '${DB_NAME}%';" 2>/dev/null || true)
if [ -n "$EXISTING_DBS" ]; then
  while IFS= read -r db; do
    [ -z "$db" ] && continue
    mysql_root -e "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS \`${db}\`; SET FOREIGN_KEY_CHECKS = 1;"
  done <<< "$EXISTING_DBS"
fi
mysql_root -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
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

echo "[2/5] Laravel キャッシュをクリア中..."
php artisan optimize:clear >/dev/null
php artisan config:clear >/dev/null

echo "[3/5] central DB を migrate 中..."
php artisan migrate --env=testing --force

echo "[4/5] shared test tenant を作成 / migrate 中..."
php artisan tinker --execute='
$t = \App\Models\Tenant::firstOrCreate(["id" => "test_tenant_id"]);
tenancy()->initialize($t);
\Artisan::call("tenants:migrate", ["--tenants" => [$t->id], "--force" => true]);
echo "Tenant ready: " . $t->id . PHP_EOL;
'

echo "[5/5] migrate 状態を確認中..."
php artisan migrate:status --env=testing | tail -15

echo "=== ローカルテスト環境準備完了 ==="

