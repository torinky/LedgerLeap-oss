# マルチテナント開発ガイドライン

## 1. はじめに

本ドキュメントは、LedgerLeapプロジェクトにおいて、マルチテナント機能を意識した開発を行う上での規約や注意点をまとめたものです。開発者は、新しい機能を追加・修正する際に、本ガイドラインを遵守してください。

**参照アーキテクチャ:** [マルチテナント・アーキテクチャ](/docs/architecture/multi-tenancy.md)

## 2. データベースマイグレーション

テーブルを新規作成または修正する際は、そのテーブルが「中央テーブル」か「テナントテーブル」かを明確に意識する必要があります。

### 2.1. テナントテーブルの作成

テナントに帰属するデータを格納するテーブル（例: `ledgers`, `folders`）を作成する場合、マイグレーションファイルに以下の2つの要素を**必ず**含めてください。

1.  **`tenant_id` カラムの追加:**
    ```php
    $table->string('tenant_id');
    ```

2.  **`tenants` テーブルへの外部キー制約:**
    ```php
    $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
    ```

これにより、データの整合性を保ちつつ、`stancl/tenancy` の自動スコープ機能が正しく動作します。

### 2.2. 中央テーブルの作成

システム全体で共有されるテーブル（例: `users`, `roles`）には、`tenant_id` カラムは**不要**です。

## 3. Eloquentモデル

### 3.1. テナントモデルへのトレイト適用

テナントテーブルに対応するEloquentモデルには、**必ず** `Stancl\Tenancy\Database\Concerns\BelongsToTenant` トレイトを `use` してください。

```php
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Ledger extends Model
{
    use BelongsToTenant; // これを必ず追加

    // ...
}
```

このトレイトが、クエリに `WHERE tenant_id = ?` を自動的に付与する役割を担います。

## 4. バリデーション

### 4.1. ユニーク制約 (`unique`)

テナントテーブルに対してユニーク制約を検証する場合、現在のテナントのスコープ内でのみユニークであることを保証する必要があります。

`Rule::unique()` を使用する際は、`where()` メソッドを使って `tenant_id` を明示的に指定してください。

```php
use Illuminate\Validation\Rule;

// 例: Ledgersテーブルのtitleカラムのユニーク制約
'title' => [
    'required',
    'string',
    Rule::unique('ledgers')->where('tenant_id', tenant('id'))->ignore($this->ledger->id ?? null),
],
```

本プロジェクトでは、このロジックは `app/Rules/UniqueColumnValue.php` や `app/Rules/UniqueAutoNumber.php` にカプセル化されています。新しいユニーク制約を追加する場合は、これらのカスタムルールクラスの利用を検討してください。

## 5. テナントコンテキストの操作

特定のコンテキストでデータベース操作を行う必要がある場合、`stancl/tenancy` が提供するヘルパを適切に利用してください。

### 5.1. 中央コンテキストでの処理

テナントスコープを一時的に無効にし、中央テーブル（例: `users`）のみを操作したい場合や、全テナントを横断するような処理を行いたい場合は、`tenancy()->central()` を使用します。

```php
use Stancl\Tenancy\Facades\Tenancy;

$users = Tenancy::central(function () {
    return User::all();
});
```

### 5.2. 特定テナントのコンテキストでの処理

特定のテナントのコンテキストに切り替えて処理を行いたい場合は、`$tenant->run()` を使用します。

```php
$tenant = Tenant::find('acme');

$tenant->run(function () {
    // このクロージャ内では、'acme'テナントのコンテキストになる
    $ledgers = Ledger::all(); // acmeテナントの台帳のみが取得される
});
```

## 6. テストコード

フィーチャーテストでテナント関連の機能をテストする場合、テストの実行前にテナントのコンテキストを初期化する必要があります。

### 6.1. テストのセットアップ例

`setUp()` メソッドや各テストメソッドの冒頭で、テスト用のテナントを作成し、`tenancy()->initialize()` で初期化するのが一般的なパターンです。

```php
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. テスト用の中央ユーザーを作成
        $this->user = User::factory()->create();

        // 2. テスト用のテナントを作成
        $this->tenant = Tenant::create([
            'id' => 'test_tenant',
        ]);

        // 3. テナントのコンテキストを初期化
        tenancy()->initialize($this->tenant);

        // 4. ユーザーをテナントのコンテキストで認証
        $this->actingAs($this->user);
    }

    /** @test */
    public function user_can_view_ledgers_in_their_tenant()
    {
        // このテストは 'test_tenant' のコンテキストで実行される
        // ...
    }
}
```
