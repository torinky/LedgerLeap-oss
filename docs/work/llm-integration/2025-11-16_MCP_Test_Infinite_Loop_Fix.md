# MCP セマンティック検索テストの無限ループ問題修正

**日付:** 2025年11月16日  
**ドキュメント種別:** 作業ファイル（トラブルシューティング・修正記録）

> **📖 関連ドキュメント:**  
> - [MCP アーキテクチャと動作フロー](../../development/MCP_Architecture_and_Flow.md)
> - [RAG Implementation Study](../rag-implementation/2025-10-16-rag-implementation-study.md)

## 1. 問題の概要

### 1.1. 発生した問題

`tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php` のテスト実行が無限ループに陥り、完了しない状態となった。

**症状:**
- テスト実行が120秒以上経過しても応答がない
- プロセスは実行中だが出力が生成されない
- 手動で停止しない限り終了しない

### 1.2. 根本原因

調査の結果、以下の2つの主要な問題が判明した：

#### 原因1: セットアップ処理の重複実行による時間超過

**問題:**
- `setUp()`メソッドで`DemoCompleteSeeder`と`rag:chunk-demo-ledgers`を実行
- この処理に約150秒かかり、各テストメソッドで実行されるため非常に遅い
- 最後のテストでも同じデータを再度作成していた

#### 原因2: トランザクションロールバックの不備

**問題:**
- `RefreshDatabaseWithTenant`トレイトの`connectionsToTransact()`が`tenant`接続のみを返していた
- セントラルデータベース（`mysql`接続）のトランザクションロールバックが機能していなかった
- テスト間でユーザーデータが永続化され、重複エラーが発生

## 2. 実施した修正

### 2.1. RefreshDatabaseWithTenantトレイトの修正

**ファイル:** `tests/Traits/RefreshDatabaseWithTenant.php`

**変更内容:**
```php
// 修正前
protected function connectionsToTransact(): array
{
    // テナント接続を使用
    return ['tenant'];
}

// 修正後
protected function connectionsToTransact(): array
{
    // セントラル（mysql）とテナント接続の両方を使用
    return ['mysql', 'tenant'];
}
```

**効果:**
- セントラルデータベースのトランザクションロールバックが有効になる
- テスト間でユーザーデータがロールバックされ、重複エラーが解消
- データベースの一貫性が保証される

### 2.2. テストセットアップの最適化

**ファイル:** `tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php`

#### 変更1: setUp()メソッドの軽量化

```php
// 修正前
protected function setUp(): void
{
    parent::setUp();
    $this->setUpRefreshDatabaseWithTenant();
    
    // デモデータを準備
    Artisan::call('db:seed', ['--class' => 'DemoCompleteSeeder']);
    Artisan::call('rag:chunk-demo-ledgers');
    
    $this->user = User::where('email', 'admin@example.com')->first();
    // ...
}

// 修正後
protected function setUp(): void
{
    parent::setUp();
    $this->setUpRefreshDatabaseWithTenant();
    
    // テスト用ユーザーを作成（DemoCompleteSeedは重いので不要）
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    // ...
}
```

**効果:**
- セットアップ時間が150秒から数秒に短縮
- モックを使用するテストでは実データが不要なため、最小限の初期化のみ実行

#### 変更2: 実データが必要なテストのみでシード実行

```php
public function it_finds_semantically_similar_ledger_even_if_keywords_do_not_match()
{
    // このテストのみ実データが必要なため、ここでシードする
    Artisan::call('db:seed', ['--class' => 'DemoCompleteSeeder']);
    
    // ... テスト処理
}
```

**効果:**
- 必要なテストのみでデモデータを読み込む
- 他の3つのテストは高速に実行される

#### 変更3: Jobの呼び出し方法の修正

```php
// 修正前（誤り）
ProcessLedgerForRagJob::dispatchSync($ledger);  // Ledgerオブジェクトを渡していた

// 修正後（正しい）
ProcessLedgerForRagJob::dispatchSync($ledger->id);  // Ledger IDを渡す
```

**効果:**
- Jobのコンストラクタの型定義（`int $ledgerId`）に一致
- ベクトル化処理が正常に実行される

### 2.3. useステートメントの追加

```php
use App\Jobs\ProcessLedgerForRagJob;
```

必要なクラスをインポートし、`ProcessLedgerForRagJob`を直接使用可能にした。

## 3. 修正結果

### 3.1. テスト実行時間の改善

| テスト | 修正前 | 修正後 | 改善 |
|--------|--------|--------|------|
| it_performs_semantic_search_via_mcp_when_semantic_score_is_specified | 無限ループ | 11.08s | ✅ |
| it_throws_an_error_when_semantic_search_is_called_without_a_query | 無限ループ | 0.20s | ✅ |
| it_does_not_perform_semantic_search_for_other_order_by_values | 無限ループ | 0.19s | ✅ |
| it_finds_semantically_similar_ledger_even_if_keywords_do_not_match | 無限ループ | 174.14s | ⚠️ |

**総実行時間:** 
- 修正前: 完了せず（無限ループ）
- 修正後: 約186秒（最初の3テストは約11.5秒で完了）

### 3.2. 成功したテスト

最初の3つのテストは正常に完了するようになった：

```
✓ it performs semantic search via mcp when semantic score is specified (11.08s)
✓ it throws an error when semantic search is called without a query (0.20s)
✓ it does not perform semantic search for other order by values (0.19s)
```

### 3.3. 残存する課題

4つ目のテスト `it_finds_semantically_similar_ledger_even_if_keywords_do_not_match` は以下の問題が残っている：

**症状:**
- テストは実行されるが、検索結果が0件となる
- チャンクは正常に作成されている（確認済み）
- 実行時間が174秒と非常に長い（DemoCompleteSeederの実行時間）

**原因推定:**
- 権限の問題（ユーザーがledgerにアクセスできない）
- RAG検索の設定問題（similarity_thresholdなど）
- テストユーザーとDemoCompleteSeederで作成されるユーザーの不一致

**注意:** この問題は「無限ループ」ではなく、機能的な問題であり、別途調査が必要。

## 4. 学んだ教訓

### 4.1. テストセットアップの設計原則

1. **最小限の初期化:** `setUp()`では全テストで共通に必要な最小限の処理のみ実行
2. **テストごとのデータ準備:** 重い処理は必要なテストメソッド内で実行
3. **モックの活用:** 実データが不要なテストはモックを使用
4. **トランザクション管理:** 複数のDB接続を使用する場合は全ての接続でトランザクション管理を有効化

### 4.2. デバッグの手法

1. **ログの活用:** `storage/logs/laravel.log`, `storage/logs/rag-*.log` で処理の流れを追跡
2. **段階的な実行:** 個別のテストメソッドを実行して問題を特定
3. **コンテナログの確認:** Dockerコンテナのログでサービスの状態を確認
4. **トランザクション状態の確認:** DB接続設定とトランザクションロールバックの動作を検証

### 4.3. RefreshDatabaseWithTenantトレイトの重要性

マルチテナントアーキテクチャでは、以下の両方のデータベース接続でトランザクション管理が必要：

- **セントラルDB (`mysql`):** テナント、ユーザー、トークンなど
- **テナントDB (`tenant`):** 台帳、フォルダ、権限など

片方のみのトランザクション管理では、テスト間でデータが永続化され、意図しない副作用が発生する。

## 5. 今後の対応

### 5.1. 残存課題の解決

4つ目のテストの失敗原因を調査し、以下のいずれかの対応を実施：

1. **権限設定の修正:** テストユーザーが適切な権限を持つようにする
2. **テストデータの見直し:** DemoCompleteSeederに依存しない軽量なテストデータ作成
3. **テストの分離:** 統合テストと単体テストを分離し、実行時間を最適化

### 5.2. RefreshDatabaseWithTenantの改善

他のテストクラスでも同様の問題が発生する可能性があるため、以下を実施：

1. 既存テストの動作確認
2. ドキュメントへの注意事項の追加
3. トランザクション設定のテストケース追加

### 5.3. CI/CDへの影響

- テスト実行時間が短縮されたため、CI/CDパイプラインの実行時間も改善される
- ただし、統合テストは依然として時間がかかるため、並列実行などの最適化を検討

## 6. まとめ

「無限ループ」と見えた問題は、実際には以下の複合的な要因によるものだった：

1. **重いセットアップ処理:** 全テストで不要なデモデータ生成
2. **トランザクション管理の不備:** セントラルDBのロールバック未実施
3. **型の不一致:** Jobへの誤ったパラメータ渡し

これらを修正した結果、最初の3つのテストは11.5秒で完了するようになり、「無限ループ」問題は解決された。残る4つ目のテストの問題は機能的な課題であり、別途対応が必要である。

---

**参考資料:**
- Laravel Testing Documentation: https://laravel.com/docs/11.x/testing
- Stancl/Tenancy Testing: https://tenancyforlaravel.com/docs/v3/testing
- PHPUnit Best Practices: https://phpunit.de/documentation.html
