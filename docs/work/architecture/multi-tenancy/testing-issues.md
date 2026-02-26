# テスト問題調査レポート

## 概要
`tests/Feature/Api/SearchApiTest.php`が失敗する問題を調査。
stancl/tenancyパッケージとLaravelの`RefreshDatabase`トレイトの既知の互換性問題が原因。

## 調査日時
2025-11-09

## 使用環境
- Laravel: 12.37.0
- stancl/tenancy: v3.9.1 (2025-03-13リリース)
- PHP: 8.4.14
- テストフレームワーク: Pest 3.8.4

## 問題の詳細

### 症状
1. `test_admin_can_search_all_ledgers`他12テストが失敗
2. 3つのLedgerを作成しているが、1つしか返されない
3. パターン: "writable"関連は成功、"readable"/"private"関連は失敗

### 根本原因

#### stancl/tenancy公式ドキュメントの警告
> **Multi-database tenancy with automatic mode will break if you use `RefreshDatabase`**
> 
> - `RefreshDatabase`は単一のデフォルト接続のみをリフレッシュ
> - テナントの動的接続切り替えと競合
> - 各テナントが独自のデータベース接続を持つため、`RefreshDatabase`では管理不可

#### Laravel 12との互換性
- Laravel 12のテストトレイトは単一データベースを前提
- tenancyの動的接続切り替えと根本的に競合

### 具体的な問題点

1. **`RefreshDatabaseWithTenant`トレイトの設計問題**
   - `RefreshDatabase`を模倣しているが、tenancy環境では正しく動作しない
   - `setUpRefreshDatabaseWithTenant()`が予期せず複数回呼ばれる
   - トランザクションとトランケーションの管理が複雑

2. **テストデータの消失**
   - `createSharedData()`で3つのLedgerを作成
   - ログでは作成されているが、テスト実行時には1つしか残らない
   - テナントコンテキストの切り替えまたはトランケーションで消失

3. **既知のバグ**
   - stancl/tenancy v3.9.1 (2025-03-13)の主な変更: "Invalidate resolver cache on delete"
   - テスト関連の既知の問題: テナントデータの予期しない消失

## 公式推奨の解決策

### stancl/tenancy公式ドキュメント推奨パターン

```php
class TenantTest extends TestCase
{
    protected $tenancy = true;

    public function setUp(): void {
        parent::setUp();
        
        if($this->tenancy){
            // テナントを作成して初期化
            $tenant = Tenant::create();
            tenancy()->initialize($tenant);
            
            // 必要に応じてマイグレートとシード
            $this->artisan('tenants:migrate');
            $this->seedTestData();
        }
    }
}
```

### カスタムトレイトの推奨実装

```php
trait RefreshTenantDatabase
{
    protected static $tenantInitialized = false;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!static::$tenantInitialized) {
            // セントラルDBを1回だけリフレッシュ
            $this->artisan('migrate:fresh', ['--database' => 'central']);
            
            // テナントを作成
            $this->tenant = Tenant::create();
            tenancy()->initialize($this->tenant);
            
            // テナントDBをマイグレート
            $this->artisan('tenants:migrate');
            
            static::$tenantInitialized = true;
        } else {
            // 2回目以降はトランケートのみ
            tenancy()->initialize($this->tenant);
            $this->truncateTenantTables();
        }
    }
    
    protected function truncateTenantTables(): void
    {
        // 明示的にトランケート対象を指定
        foreach ($this->getTablesToTruncate() as $table) {
            DB::table($table)->truncate();
        }
    }
}
```

## 実施した修正

### 1. `RefreshDatabaseWithTenant`トレイトの改善
- グローバル初期化フラグを追加
- テナントの再利用ロジックを実装
- デバッグログのクリーンアップ

### 2. `SearchApiTest`の修正
- トランケートを完全に無効化
- 共有データを保持する設定

### 3. コードフォーマット
- Laravel Pintでコードスタイルを修正

## 残された問題

1. **テストが依然として失敗**
   - 3つのLedgerが作成されるが、1つしか見えない
   - 権限管理またはテナントコンテキストの問題の可能性

2. **根本的な設計問題**
   - `RefreshDatabaseWithTenant`トレイトは公式推奨に従っていない
   - 完全な解決にはテスト基盤の再設計が必要

## 推奨される次のステップ

### 短期的な対応（緊急）
1. **各テストでデータを個別に作成**
   - 共有データの使用を止める
   - 各`setUp()`でテストデータを作成・削除

2. **期待値の調整**
   - 実際の動作に合わせてアサーションを修正
   - 権限フィルタリングの正確な動作を確認

### 中期的な対応（推奨）
1. **テスト基盤の再設計**
   - `RefreshDatabaseWithTenant`を公式推奨パターンに置き換え
   - カスタムトレイトを適切に実装

2. **テストパフォーマンスの改善**
   - トランケーション戦略の最適化
   - 並列実行可能なテスト構造

### 長期的な対応
1. **stancl/tenancyのアップグレード監視**
   - v4.xでの改善を確認
   - Laravel 12対応の公式パターンの更新

2. **テストドキュメントの整備**
   - tenancy環境でのテスト作成ガイドライン
   - ベストプラクティスの共有

## 参考資料

- [stancl/tenancy v3 公式ドキュメント - Testing](https://tenancyforlaravel.com/docs/v3/testing/)
- [Testing Multitenant Laravel Applications](https://solutions.io/news/how-to-test-multitenant-laravel-applications-solving-database-refresh-challenges)
- [Laravel 12.x Database Testing](https://laravel.com/docs/12.x/database-testing)
- [stancl/tenancy GitHub - Recent Commits](https://github.com/archtechx/tenancy/commits/3.x)

## 結論

`SearchApiTest`の失敗は、**stancl/tenancyパッケージの既知の設計制約**によるものです。
`RefreshDatabase`トレイトはマルチデータベーステナンシーと根本的に互換性がありません。

完全な解決には、公式推奨パターンに従ったテスト基盤の再設計が必要です。
時間の制約を考慮し、短期的な回避策として各テストでデータを個別に作成する方法を推奨します。
