# テスト基本原則・環境設定

**最終更新:** 2026-02-28
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## 🎯 基本原則

### 1. テスト設計の原則
- **1テストメソッド = 1HTTPリクエスト**を厳守
- **Single Responsibility**: 各テストは1つの機能・条件のみテスト
- **明確な命名**: テスト名でテスト内容を完全に理解できること

---

## 🔧 テスト環境の設定

### `.env.testing` ファイルの設定

```dotenv
# .env.testing
DB_CONNECTION=mysql_testing
DB_HOST=mysql
DB_PORT=3306
DB_USERNAME=sail
DB_PASSWORD=password
DB_DATABASE=ledgerleap_test
```

### `config/database.php` でテスト専用接続を定義

```php
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => 'ledgerleap_test', // ハードコード
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    // ...
],
```

**理由:**
- `--env=testing` を指定しても `.env` の `DB_DATABASE` が優先される場合がある
- 専用接続により本番DBへの誤操作リスクを回避

---

## 🚫 避けるべきアンチパターン

### 1. 複数 HTTP リクエストテスト

```php
// ❌ 避けるべきパターン
public function test_multiple_operations()
{
    $response1 = $this->getJson('/api/v1/resource?param1=value1');
    $response2 = $this->getJson('/api/v1/resource?param2=value2'); // BadRequestException発生リスク
}

// ✅ 推奨パターン
public function test_operation_with_param1()
{
    $response = $this->getJson('/api/v1/resource?param1=value1');
}

public function test_operation_with_param2()
{
    $response = $this->getJson('/api/v1/resource?param2=value2');
}
```

### 2. 重複するテスト責任

```php
// ❌ 避けるべき重複
// LedgerControllerTest.php
public function test_search_ledgers() { /* 検索テスト */ }
// SearchApiTest.php
public function test_search_functionality() { /* 同じ検索テスト */ }

// ✅ 推奨する責任分担
// LedgerControllerTest.php - CRUD操作のみ
public function test_create_ledger() { }
// SearchApiTest.php - 検索機能のみ
public function test_search_by_keyword() { }
```

---

## 🏭 ファクトリベストプラクティス

### 1. 軽量ファクトリの実装

```php
// ✅ 推奨: 最小限のデータ
class LedgerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content' => [0 => 'Test Content'],
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }

    public function withComplexContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $this->generateComplexContent(),
        ]);
    }
}
```

### 2. ファクトリ使用例

```php
$ledger = Ledger::factory()->create();                          // デフォルト（軽量）
$ledger = Ledger::factory()->minimal()->create();               // 最小構成を明示
$ledger = Ledger::factory()->withComplexContent()->create();    // 必要な場合のみ
```

> ⚠️ `Ledger::factory()->create()` は LedgerObserver 経由で ProcessLedgerForRagJob を dispatch する。
> **Queue::fake() が必須。** 詳細は [03-external-dependency-isolation.md](./03-external-dependency-isolation.md) を参照。

---

## 🏗️ モデルイベント & Sail 環境の注意点

### `touch()` vs `update()` の選択

Laravel Sail (Docker) 環境において、`$model->touch()` では `updated` イベントが安定して発火しないケースがある。

```php
// ❌ 不安定（Sail環境）
$model->touch();

// ✅ 安定して発火する
$model->update(['column' => 'value']);
```

### トレースログによるイベントフローの可視化

Observer や `booted()` 内のクロージャが動作しているか不明な場合：

```php
// 開発中の一時デバッグ（確認後は必ず削除）
\Log::info('=== Observer fired ===', ['ledger_id' => $ledger->id]);
```

---

## 🛠 よくある問題

### Q: テストで `wire:loading` が確認できない
`wire:loading` はフロントエンドの挙動。`assertSeeHtml` で属性の存在確認、または Livewire 内部状態の確認で代替する。

### Q: ファイルアップロードのテストが通らない
`Livewire::test()->set('file', UploadedFile::fake()->image('test.jpg'))` を使用。
`AsColumnArrayJson` 等のキャストが介在する場合は [04-ledger-content-structure.md](./04-ledger-content-structure.md) を参照。

