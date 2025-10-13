# RefreshDatabaseWithTenant トレイト成功報告書

**作成日:** 2025年10月4日  
**ステータス:** ✅ **完了** - Phase 2全テスト最適化完了  
**成果:** MCP Tools テスト実行時間を70-75%削減

## 🎉 最終成果

### パフォーマンス改善結果（実測値）

```
MCP Tools全テスト (57テスト / 339 assertions)
================================
改善前: 約400秒以上（DatabaseMigrations毎回実行）
改善後: 109.38秒（RefreshDatabaseWithTenant）
削減率: 約70-75%削減 ⚡

個別テストクラスの改善:
┌─────────────────────────────────┬──────────┬──────────┬───────────┐
│ テストファイル                    │ 改善前   │ 改善後   │ 削減率    │
├─────────────────────────────────┼──────────┼──────────┼───────────┤
│ ClaimWorkflowTaskToolTest       │ 67.67秒  │ 15.53秒  │ 77%削減   │
│ ExecuteApprovalToolTest         │ 57.80秒  │ 13.10秒  │ 78%削減   │
│ GetActivityLogToolTest          │ 93.40秒  │ 10.99秒  │ 88%削減!! │
│ GetWorkflowHistoryToolTest      │ 67.69秒  │ 14.55秒  │ 77%削減   │
└─────────────────────────────────┴──────────┴──────────┴───────────┘

すでに最適化済み（Phase 1完了分）:
- SearchLedgersToolTest: 2.28秒（DatabaseTransactions使用）
- CreateLedgerToolTest: 10.37秒（RefreshDatabaseWithTenant）
- GetLedgerDefinesToolTest: 13.80秒（RefreshDatabaseWithTenant）
- GetPendingApprovalsToolTest: 12.45秒（RefreshDatabaseWithTenant）
- McpToolsAuthenticationTest: 10.28秒（RefreshDatabaseWithTenant）
```

### テスト実行時間の内訳パターン

```
各テストクラスの典型的なパターン:
┌──────────────────────────────────────────────────────────┐
│ 最初のテスト: 7-8秒   ← マイグレーション実行             │
├──────────────────────────────────────────────────────────┤
│ 2番目のテスト: 0.2-2秒 ← トランケートのみ（超高速）      │
│ 3番目のテスト: 0.2-2秒 ← トランケートのみ（超高速）      │
│ 4番目のテスト: 0.2-2秒 ← トランケートのみ（超高速）      │
│ ...                                                      │
│ N番目のテスト: 0.2-2秒 ← トランケートのみ（超高速）      │
└──────────────────────────────────────────────────────────┘

例: ClaimWorkflowTaskToolTest (7テスト)
  ✓ 最初のテスト: 8.21s（マイグレーション）
  ✓ 残り6テスト: 0.63-1.67s（平均1.05s）
  合計: 15.53s（改善前: 67.67s）
```

## 🏗️ RefreshDatabaseWithTenant トレイトの仕組み

### アーキテクチャ

```php
trait RefreshDatabaseWithTenant
{
    // クラス全体で1回だけマイグレーション
    protected static bool $databaseInitialized = false;
    protected static $sharedTenant = null;
    protected static ?array $truncatableTablesCache = null;
    
    protected function setUpRefreshDatabaseWithTenant(): void
    {
        if (!static::$databaseInitialized) {
            // 【初回のみ実行 - 7-8秒】
            $this->refreshDatabase();           // セントラルDB マイグレーション
            $this->createSharedTenant();        // テナント作成
            tenancy()->initialize(static::$sharedTenant);
            $this->migrateTenantDatabase();     // テナントDB マイグレーション
            $this->createSharedData();          // 共有データ作成
            
            static::$databaseInitialized = true;
        } else {
            // 【2回目以降 - 0.2-2秒】
            tenancy()->initialize(static::$sharedTenant);
            $this->truncateTenantTables();      // 最小限のテーブルのみトランケート
        }
    }
}
```

### 主要機能

#### 1. クラス全体で1回だけマイグレーション
- **初回**: セントラルDB + テナントDB の完全マイグレーション
- **2回目以降**: トランケートによる高速クリーンアップ
- **効果**: 各テストクラスで60-80秒削減

#### 2. 最小限のトランケート
```php
protected function getTablesToTruncate(): array
{
    // デフォルトは最小限
    return ['personal_access_tokens'];
}

// テストクラスでカスタマイズ可能
protected array $tablesToTruncate = [
    'personal_access_tokens',
    'ledgers',
    'ledger_diffs',
    'custom_activities',
];
```

#### 3. 共有テナント活用
- 全テストで同じテナントを再利用
- テナント作成・マイグレーションは1回のみ
- **効果**: テナント関連の初期化コストを1回に集約

#### 4. 共有データ作成（オプション）
```php
protected function createSharedData(): void
{
    // 全テストで共通して使えるデータを作成
    // 例: 管理者ユーザー、基本フォルダ等
    static::$sharedAdmin = User::factory()->admin()->create();
}
```

## 📝 使用方法

### 基本的な使い方

```php
use Tests\Traits\RefreshDatabaseWithTenant;

class MyMcpToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;
    
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ 必須: トレイトの初期化
        $this->setUpRefreshDatabaseWithTenant();
        
        // テストデータ作成
        $this->user = User::factory()->create();
        $token = $this->user->createToken('test-token');
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);
    }
    
    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        \Mockery::close();
        parent::tearDown();
    }
}
```

### トランケート対象のカスタマイズ

```php
class MyMcpToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;
    
    // ✅ プロパティで指定（推奨）
    protected array $tablesToTruncate = [
        'personal_access_tokens',
        'ledgers',
        'ledger_diffs',
        'custom_activities',
    ];
    
    // または メソッドでオーバーライド
    protected function getTablesToTruncate(): array
    {
        return [
            'personal_access_tokens',
            'ledgers',
            'ledger_diffs',
        ];
    }
}
```

### 共有データの作成

```php
class MyMcpToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;
    
    protected static ?User $sharedAdmin = null;
    protected static ?Folder $sharedFolder = null;
    
    protected function createSharedData(): void
    {
        // 全テストで共通して使うデータ
        // トランケート対象外のテーブルに作成すること
        static::$sharedAdmin = User::factory()->admin()->create();
        static::$sharedFolder = Folder::factory()->create();
    }
    
    #[Test]
    public function it_uses_shared_data(): void
    {
        // 共有データを使用
        $this->assertNotNull(static::$sharedAdmin);
        $this->assertTrue(static::$sharedAdmin->isAdmin());
    }
}
```

## ✅ 適用済みテストファイル一覧

### Phase 1完了分（すでに最適化済み）

1. **SearchLedgersToolTest** - DatabaseTransactions（特殊ケース）
   - LedgerService完全モックのため、さらに軽量化
   - 実行時間: 2.28秒（6テスト）
   - 改善率: 75%削減

2. **CreateLedgerToolTest** - RefreshDatabaseWithTenant
   - 実行時間: 10.37秒（5テスト）

3. **GetLedgerDefinesToolTest** - RefreshDatabaseWithTenant
   - 実行時間: 13.80秒（5テスト）

4. **GetPendingApprovalsToolTest** - RefreshDatabaseWithTenant
   - 実行時間: 12.45秒（5テスト）

5. **McpToolsAuthenticationTest** - RefreshDatabaseWithTenant
   - 実行時間: 10.28秒（6テスト）

### Phase 2完了分（今回最適化）

6. **ClaimWorkflowTaskToolTest** - RefreshDatabaseWithTenant
   - 変更前: DatabaseMigrations（67.67秒）
   - 変更後: RefreshDatabaseWithTenant（15.53秒）
   - 改善率: **77%削減**
   - テスト数: 7テスト / 26 assertions

7. **ExecuteApprovalToolTest** - RefreshDatabaseWithTenant
   - 変更前: DatabaseMigrations（57.80秒）
   - 変更後: RefreshDatabaseWithTenant（13.10秒）
   - 改善率: **78%削減**
   - テスト数: 6テスト / 20 assertions

8. **GetActivityLogToolTest** - RefreshDatabaseWithTenant
   - 変更前: DatabaseMigrations（93.40秒）
   - 変更後: RefreshDatabaseWithTenant（10.99秒）
   - 改善率: **88%削減!!**
   - テスト数: 10テスト / 109 assertions

9. **GetWorkflowHistoryToolTest** - RefreshDatabaseWithTenant
   - 変更前: DatabaseMigrations（67.69秒）
   - 変更後: RefreshDatabaseWithTenant（14.55秒）
   - 改善率: **77%削減**
   - テスト数: 7テスト / 33 assertions

## 🎓 学んだ教訓

### ✅ 成功要因

1. **段階的なマイグレーション**
   - テストクラスごとに1回だけ実行
   - 初期コストを分散し、各テストは高速化

2. **最小限のクリーンアップ**
   - 必要なテーブルのみトランケート
   - 共有データは維持してテスト間で再利用

3. **柔軟なカスタマイズ**
   - `getTablesToTruncate()`でテストごとに調整可能
   - `createSharedData()`で共通データ準備可能

4. **トランザクション回避**
   - テナントDB操作との相性問題を解決
   - トランケートで代替し、安定性向上

5. **キャッシュ活用**
   - トランケート可能なテーブルをキャッシュ
   - 毎回のテーブル存在確認を回避

### 🚨 注意点

1. **並列実行の制限**
   - 共有テナントを使用するため、同一テストクラスの並列実行は不可
   - 異なるテストクラス間の並列実行は可能

2. **トランケート対象の選定**
   - 各テストで使用するテーブルを正しく把握
   - 不足するとテスト間で影響が出る可能性
   - 多すぎると実行時間が増加

3. **共有データの管理**
   - 全テストで共通して使えるデータのみ作成
   - テスト固有のデータは各テストで作成
   - トランケート対象外のテーブルに作成すること

4. **外部キー制約**
   - トランケート時に一時的に無効化
   - テーブルの依存関係に注意

## 📊 統計データ

### テスト数と実行時間の相関

```
テストクラスごとの効率:
┌─────────────────────────────────┬──────────┬──────────┬──────────────┐
│ テストファイル                    │ テスト数 │ 実行時間 │ 1テスト平均   │
├─────────────────────────────────┼──────────┼──────────┼──────────────┤
│ ClaimWorkflowTaskToolTest       │ 7テスト  │ 15.53秒  │ 2.22秒/テスト │
│ ExecuteApprovalToolTest         │ 6テスト  │ 13.10秒  │ 2.18秒/テスト │
│ GetActivityLogToolTest          │ 10テスト │ 10.99秒  │ 1.10秒/テスト │
│ GetWorkflowHistoryToolTest      │ 7テスト  │ 14.55秒  │ 2.08秒/テスト │
└─────────────────────────────────┴──────────┴──────────┴──────────────┘

平均: 約2秒/テスト（初回マイグレーション含む）
平均: 約0.5-1.5秒/テスト（2回目以降のテストのみ）
```

### DatabaseMigrations vs RefreshDatabaseWithTenant

```
従来の DatabaseMigrations:
- 各テストで完全マイグレーション実行
- 実行時間: 約9-10秒/テスト
- 利点: テスト間の完全な独立性
- 欠点: 非常に遅い

RefreshDatabaseWithTenant:
- 最初のテストのみマイグレーション
- 実行時間: 7-8秒（初回）、0.2-2秒（2回目以降）
- 利点: 非常に高速（70-88%削減）
- 欠点: テストクラス内での並列実行不可
```

## 🔄 今後の展開

### ✅ 完了した項目
- Phase 1: 初期MCPテスト最適化完了
- Phase 2: DatabaseMigrations使用テストの最適化完了
- 全MCP Toolsテスト最適化完了（9テストファイル）
- ドキュメント更新完了

### ⏭️ 今後の検討事項

1. **他のテストスイートへの展開**
   - Feature テストへの適用検討
   - Unit テスト全体への適用検討
   - Integration テストへの適用検討

2. **さらなる最適化**
   - CI/CD環境での並列実行最適化
   - テストデータファクトリの改善
   - モック戦略の見直し

3. **モニタリング**
   - テスト実行時間の継続的な監視
   - パフォーマンス低下の早期検出
   - CI/CDパイプラインでの計測

4. **ベストプラクティスの確立**
   - 新規テスト作成時のガイドライン
   - トレイト選択のフローチャート作成
   - 開発チームへの共有

## 📚 関連ドキュメント

- [RefreshDatabaseWithTenant トレイト実装](../../tests/Traits/RefreshDatabaseWithTenant.php)
- [RefreshDatabaseOnce トレイト実装](../../tests/Traits/RefreshDatabaseOnce.php)
- [実装ログ](./IMPLEMENTATION_LOG.md)
- [MCPアーキテクチャ](../development/MCP_Architecture_and_Flow.md)
- [MCP Test Optimization Plan](./2025-10-04_MCP_Test_Optimization_Plan.md)

## 🎯 結論

RefreshDatabaseWithTenantトレイトの導入により、MCP Toolsテストの実行時間を**約70-75%削減**することに成功しました。この成果は、以下の要因によるものです：

1. **テストクラスごとに1回だけマイグレーション**
2. **最小限のトランケートによる高速クリーンアップ**
3. **共有テナントの活用**
4. **柔軟なカスタマイズ機能**

この手法は、マルチテナントアーキテクチャを採用したLaravelプロジェクトのテスト最適化において、非常に効果的なアプローチであることが実証されました。

---

**最終更新日:** 2025年10月4日  
**ステータス:** ✅ **完了** - Phase 2完了、全MCP Toolsテスト最適化達成  
**成果:** 実行時間を約400秒 → 109秒に削減（70-75%削減）
