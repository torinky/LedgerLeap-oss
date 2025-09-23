# stancl/tenancy 技術調査レポート Vol. 1 (改訂版)

**日付:** 2025年8月27日

このドキュメントは、`stancl/tenancy` パッケージの主要機能に関する調査結果を記録するものです。

---

## A. URLパスによるテナント識別

### 1. 目的
URL形式を `/{tenant_slug}/...` としてテナントを識別するための、公式に推奨される具体的な設定方法を確立する。

### 2. 結論
`InitializeTenancyByPath` ミドルウェアと `config/tenancy.php` の設定変更を組み合わせることで実現可能。

### 3. 具体的な設定手順

#### 手順1: ミドルウェアの登録
`app/Http/Kernel.php` の `web` ミドルウェアグループ、もしくは個別のルート定義で `InitializeTenancyByPath` ミドルウェアを適用する。

#### 手順2: ルート定義の変更
`routes/web.php` 等で、テナントに属するルートを `{tenant}` パラメータを含むプレフィックスでグループ化する。

```php
// routes/web.php
Route::middleware([\Stancl\Tenancy\Middleware\InitializeTenancyByPath::class])
    ->prefix('/{tenant}')->group(function () {
    // ...
});
```

#### 手順3: 検索キーのカスタマイズ (推奨)
テナントの検索を `id` ではなく `slug` カラムで行うため、`config/tenancy.php` ファイルを編集する。

```php
// config/tenancy.php
'tenant_resolvers' => [
    Stancl\Tenancy\Resolvers\PathTenantResolver::class => [
        'tenant_model_column' => 'slug', // 'id' の代わりに 'slug' を使用
    ],
],
```

### 4. 補足情報
- **テナントが見つからない場合:** デフォルトでは例外が発生するが、`InitializeTenancyByPath::$onFail` プロパティを設定することで、404ページ表示などに動作をカスタマイズできる。
- **`route()` ヘルパー:** テナントルートに対して `route()` を使う場合、毎回 `tenant` パラメータを渡す必要がある点に注意。

---

## B. シングルデータベースモードでのデータ分離

### 1. 目的
単一のデータベース内で、`BelongsToTenant` トレイトを利用してデータをテナントごとに自動的に分離（スコープ）する方法を理解する。

### 2. 結論
モデルに `BelongsToTenant` トレイトを追加し、対象テーブルに `tenant_id` カラムを作成することで、クエリの自動スコープと `tenant_id` の自動設定が有効になる。シングルDBモードでは、`DatabaseTenancyBootstrapper` の無効化が必須。

### 3. 具体的な設定手順

#### 手順1: `DatabaseTenancyBootstrapper` の無効化
シングルDBモードで運用する場合、`config/tenancy.php` でテナントごとのDB接続を管理するブートストラッパーを無効にする。

```php
// config/tenancy.php
'bootstrappers' => [
    // Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class, // この行をコメントアウト
],
```

#### 手順2: マイグレーションの作成
テナントに属するモデルのテーブルに、`tenant_id` を格納するカラムを追加する。

```php
// database/migrations/xxxx_xx_xx_create_posts_table.php
$table->string('tenant_id');
// $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
```

#### 手順3: モデルへのトレイト適用
テナントに属させたい全てのモデルに `BelongsToTenant` トレイトを追加する。

```php
// app/Models/Post.php
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Post extends Model
{
    use BelongsToTenant;
    // ...
}
```

### 4. 機能
- **クエリの自動スコープ:** `Post::all()` のようなクエリは、現在のテナントに属する投稿のみを返すようになる。
- **`tenant_id` の自動設定:** `Post::create([...])` を実行すると、新しいレコードの `tenant_id` カラムに現在のテナントIDが自動的に設定される。
- **テナントなしコンテキスト:** 中央アプリケーションなど、テナントが特定されていないコンテキストでは、このスコープは適用されない。

---

## C. DB分離モードとデータ移行

### 1. 目的
マルチデータベースモードへの移行方法と、その際のデータ移行に関する課題とアプローチを理解する。

### 2. `tenants:migrate` コマンドの役割
このコマンドの主な役割は、各テナントのデータベースに対して、`database/migrations/tenant` ディレクトリに配置された**スキーマ**マイグレーションを実行することである。これはテーブル構造の変更や追加を行うものであり、**データを移行する機能は持たない**。

### 3. データ移行の基本アプローチ
シングルDBからマルチDBへ既存のデータを移行するための専用コマンドは、ライブラリには用意されていない。したがって、**自前でカスタムのArtisanコマンドを作成する**必要がある。このコマンドを `tenants:artisan` コマンド経由で全テナントに対して実行するのが、一般的なアプローチとなる。

### 4. シングルDBからマルチDBへの移行に関する留意事項

カスタムのデータ移行コマンドを作成・実行する際には、以下の点に注意する必要がある。

- **メンテナンスモード:** データ移行中はデータの不整合を防ぐため、アプリケーションを必ずメンテナンスモードにすること。
- **冪等性（べきとうせい）:** 移行スクリプトは、途中で失敗しても安全に再実行できるよう、冪等性を担保した設計にすること。（例: 移行先のデータ有無を確認してから挿入する）
- **外部キー制約:** 関連のあるテーブルを移行する際は、データの投入順序（親テーブル→子テーブル）に注意する。あるいは、一時的に外部キー制約を無効にするアプローチも検討する。
- **パフォーマンス:** 大量のデータを扱う場合は、`chunkById()` などを用いてデータを分割処理し、メモリ消費を抑えること。
- **ファイルストレージ:** データベースの移行と並行して、テナントごとのファイルストレージ分離（パスの変更、ファイルの物理的な移動）も計画・実行する必要がある。
- **テスト:** 作成した移行コマンドが正しく動作することを、少量のテストデータを用いて事前に徹底的にテストすること。
- **切り替え計画:** DB接続情報の変更、`tenancy.php` の設定変更（`DatabaseTenancyBootstrapper` の有効化など）、ルーティングの変更など、本番環境での切り替え手順を詳細に計画しておくこと。

---

## D. テスト手法の確立

### 1. 目的
テナントコンテキストを考慮した、信頼性の高い自動テストを記述する方法を確立する。

### 2. 基本的なテスト手順
テナント固有の機能をテストする場合、テストのライフサイクル内でテナントのコンテキストを能動的に初期化する必要がある。

```php
// tests/Feature/TenantScopeTest.php

use Stancl\Tenancy\Tests\TestCase;

class TenantScopeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // テスト用のテナントを作成し、テナンシーを初期化する
        $tenant = Tenant::create();
        tenancy()->initialize($tenant);
    }

    public function test_a_tenant_can_only_see_their_own_data()
    {
        // ... テストロジック
    }
}
```

### 3. 重要な注意点と制約

過去の実装で問題となった可能性が高い、以下の重要な制約を認識する必要がある。

- **`RefreshDatabase` トレイトは使用不可:** マルチデータベースモード（またはそれを想定したテスト）では、インメモリのSQLite (`:memory:`) や `RefreshDatabase` トレイトは使用できない。テストごとにデータベースをクリーンな状態に保つためには、手動でのクリーンアップ処理や、テスト専用のヘルパーメソッドを実装するなどの代替策が必要となる。

- **`Event::fake()` の影響:** このライブラリは、テナンシーの初期化プロセスなどでイベントを多用している。テスト中に安易に `Event::fake()` を呼び出すと、テナンシーが正しく初期化されず、テストが失敗する原因となる。イベントをモックする場合は、必要なイベントのみを選択的にモックするか、`Event::fakeExcept()` を利用してテナンシー関連のイベントを除外するなどの工夫が必須となる。
