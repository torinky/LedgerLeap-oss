# テスト環境マイグレーション実行の確立された手順

**日付:** 2026年2月11日  
**ステータス:** 完了 ✅

---

## 問題の根本原因

1. **環境変数の優先順位**: `--env=testing`を指定しても`.env`の設定が優先され、本番DBに接続してしまう
2. **migrationsテーブルの不整合**: テーブル個別削除では履歴が残り、途中からマイグレーションが実行される
3. **デッドロック**: 大量データ存在時の`migrate:refresh`はテーブルロックで競合

---

## 解決策

### 方法1: 専用スクリプト（推奨）

```bash
./bin/reset-test-db.sh
```

### 方法2: 手動実行

```bash
# Step 1: データベース全体を削除・再作成
cat > /tmp/recreate_db.sql << 'EOF'
DROP DATABASE IF EXISTS ledgerleap_test;
CREATE DATABASE ledgerleap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF
cat /tmp/recreate_db.sql | ./vendor/bin/sail mysql

# Step 2: 設定キャッシュをクリア
./vendor/bin/sail artisan config:clear

# Step 3: マイグレーション実行
./vendor/bin/sail artisan migrate --env=testing
```

---

## 必須設定

### 1. `.env.testing`

```dotenv
DB_CONNECTION=mysql_testing
DB_HOST=mysql
DB_PORT=3306
DB_USERNAME=sail
DB_PASSWORD=password
DB_DATABASE=ledgerleap_test
```

### 2. `config/database.php`

```php
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => 'ledgerleap_test', // ハードコード
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

### 3. `phpunit.xml`

```xml
<server name="DB_CONNECTION" value="mysql_testing"/>
<server name="DB_DATABASE" value="ledgerleap_test"/>
```

---

## マイグレーションファイルの冪等性（必須）

### カラム追加

```php
public function up(): void
{
    Schema::table('table_name', function (Blueprint $table) {
        // 依存カラムの動的決定
        $afterColumn = Schema::hasColumn('table_name', 'preferred_column') 
            ? 'preferred_column' 
            : 'fallback_column';
        
        // 存在チェック
        if (! Schema::hasColumn('table_name', 'new_column')) {
            $table->timestamp('new_column')->nullable()
                ->comment('説明')
                ->after($afterColumn);
        }
    });
}
```

### インデックス追加

```php
if (! Schema::hasIndex('table_name', 'idx_name')) {
    $table->index('column_name', 'idx_name');
}
```

### 安全な削除

```php
public function down(): void
{
    Schema::table('table_name', function (Blueprint $table) {
        // インデックス削除
        if (Schema::hasIndex('table_name', 'idx_name')) {
            $table->dropIndex('idx_name');
        }
        
        // カラム削除
        $columnsToDelete = [];
        foreach (['col1', 'col2', 'col3'] as $column) {
            if (Schema::hasColumn('table_name', $column)) {
                $columnsToDelete[] = $column;
            }
        }
        
        if (! empty($columnsToDelete)) {
            $table->dropColumn($columnsToDelete);
        }
    });
}
```

---

## 注意事項

- ⚠️ `migrate:refresh`はデッドロックリスクあり（使用非推奨）
- ⚠️ `--env=testing`だけでは不十分、`.env.testing`と専用接続が必須
- ⚠️ 全マイグレーションファイルで`hasColumn()`/`hasIndex()`による存在チェックを実施
- ⚠️ `after()`句は動的に決定し、依存カラムが存在しない場合に備える

---

## 参考資料

- `database/migrations/2025_11_03_014829_add_vlm_columns_to_attached_files_table.php` - 冪等性の実装例
- `docs/development/Testing-Best-Practices.md` - テスト全般のベストプラクティス
- `docs/database/schema.md` - データベーススキーマ概要

