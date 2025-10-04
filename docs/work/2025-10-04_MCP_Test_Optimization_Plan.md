# MCP テスト最適化計画

**作成日:** 2025年10月4日  
**目的:** MCPテストの実行速度改善とスキップテストの再実装

## 📊 現状分析

### データベーストレイト使用状況

| テストファイル | 現在のトレイト | モック使用 | 最適化可能性 |
|-------------|-------------|---------|-----------|
| SearchLedgersToolTest | ✅ DatabaseTransactions | LedgerService完全モック | ✅ **完了** (75%高速化) |
| CreateLedgerToolTest | RefreshDatabase | LedgerService + WritableFolderRepository | 🟡 **可能** |
| GetLedgerDefinesToolTest | RefreshDatabase | WritableFolderRepository | 🟡 **可能** |
| GetPendingApprovalsToolTest | RefreshDatabase | WorkflowService | 🟡 **可能** |
| McpToolsAuthenticationTest | RefreshDatabase | LedgerService | 🟡 **可能** |
| AuthenticatedMcpToolTest | RefreshDatabase | なし（実DB使用） | 🔴 **不可** |
| ClaimWorkflowTaskToolTest | DatabaseMigrations | 一部モック | 🟡 **可能** |
| ExecuteApprovalToolTest | DatabaseMigrations | 一部モック | 🟡 **可能** |
| GetActivityLogToolTest | DatabaseMigrations | なし（実DB使用） | 🔴 **不可** |
| GetWorkflowHistoryToolTest | DatabaseMigrations | WritableFolderRepository | 🟡 **可能** |

### スキップされているテスト

| テストファイル | スキップ数 | 理由 | 再実装可能性 |
|-------------|---------|------|------------|
| ClaimWorkflowTaskToolTest | 3テスト | ワークフロー統合テスト複雑 | 🟢 **可能** |
| ExecuteApprovalToolTest | 2テスト | ワークフロー統合テスト複雑 | 🟢 **可能** |

## 🎯 最適化戦略

### 戦略1: DatabaseTransactionsへの移行（高優先度）

**対象:**
- CreateLedgerToolTest
- GetLedgerDefinesToolTest
- GetPendingApprovalsToolTest
- McpToolsAuthenticationTest

**条件:**
- サービス層を完全にモックしている
- データベースは認証トークン検証のみに使用

**効果:**
- 実行時間: 75%削減（SearchLedgersToolTestで実証済み）
- マイグレーション実行回数の大幅削減

**実装方法:**
```php
// Before
use Illuminate\Foundation\Testing\RefreshDatabase;
class SomeTest extends TestCase
{
    use RefreshDatabase;
}

// After  
use Illuminate\Foundation\Testing\DatabaseTransactions;
class SomeTest extends TestCase
{
    use DatabaseTransactions;
}
```

### 戦略2: DatabaseMigrationsの維持（中優先度）

**対象:**
- ClaimWorkflowTaskToolTest
- ExecuteApprovalToolTest
- GetWorkflowHistoryToolTest

**理由:**
- Mroonga全文検索を使用している可能性
- ワークフロー機能のため、複雑なDB状態が必要

**改善案:**
- サービス層のモック化を進める
- 可能な範囲でDatabaseTransactionsに移行

### 戦略3: 実DB使用の維持（低優先度）

**対象:**
- AuthenticatedMcpToolTest
- GetActivityLogToolTest

**理由:**
- 認証トレイトの統合テスト（実DB必須）
- Spatieのactivitylogパッケージ（実DB必須）

**改善案:**
- テスト数を最小限に抑える
- 並列実行時の競合を回避

## 📝 スキップテストの再実装計画

### ClaimWorkflowTaskToolTest

**スキップされているテスト:**
1. `test_claims_inspection_task_successfully`
2. `test_claims_approval_task_successfully`
3. `test_response_includes_proper_fields`

**再実装方針:**
- WorkflowServiceをモックして、正常系のレスポンスを検証
- 既存の`test_handles_service_exceptions`パターンを参考

**実装例:**
```php
#[Test]
public function it_claims_inspection_task_successfully(): void
{
    $mockWorkflowService = \Mockery::mock(WorkflowService::class);
    
    $ledger = Ledger::factory()->make([
        'id' => 123,
        'ledger_define_id' => $this->ledgerDefine->id,
        'status' => WorkflowStatus::PENDING_INSPECTION,
    ]);
    
    $mockWorkflowService->shouldReceive('claimTask')
        ->once()
        ->with($ledger, $this->user, Mockery::type('string'))
        ->andReturn([
            'ledger' => $ledger,
            'new_assignee' => $this->user,
            'task_type' => '点検待ち',
        ]);
    
    $response = $this->tool->handle(
        new \Laravel\Mcp\Request([
            'ledger_id' => 123,
            'comments' => 'テスト引き継ぎ',
        ]),
        $mockWorkflowService
    );
    
    $this->assertFalse($response->isError());
    $responseData = json_decode($response->content(), true);
    
    $this->assertEquals('success', $responseData['type']);
    $this->assertArrayHasKey('__summary__', $responseData);
    $this->assertStringContainsString('点検待ち', $responseData['__summary__']);
}
```

### ExecuteApprovalToolTest

**スキップされているテスト:**
1. `test_executes_approve_action`
2. `test_executes_return_to_draft_action`

**再実装方針:**
- WorkflowServiceをモックして、承認処理のレスポンスを検証
- 既存の`test_returns_proper_json_response`パターンを参考

**実装例:**
```php
#[Test]
public function it_executes_approve_action(): void
{
    $mockWorkflowService = \Mockery::mock(WorkflowService::class);
    
    $ledger = Ledger::factory()->make([
        'id' => 123,
        'ledger_define_id' => $this->ledgerDefine->id,
        'status' => WorkflowStatus::PENDING_APPROVAL,
    ]);
    
    $mockWorkflowService->shouldReceive('executeApproval')
        ->once()
        ->with($ledger, $this->user, 'approve', Mockery::any())
        ->andReturn([
            'ledger' => $ledger,
            'action' => 'approve',
            'new_status' => WorkflowStatus::APPROVED,
        ]);
    
    $response = $this->tool->handle(
        new \Laravel\Mcp\Request([
            'ledger_id' => 123,
            'action' => 'approve',
            'comments' => '承認します',
        ]),
        $mockWorkflowService
    );
    
    $this->assertFalse($response->isError());
    $responseData = json_decode($response->content(), true);
    
    $this->assertEquals('success', $responseData['type']);
    $this->assertArrayHasKey('__summary__', $responseData);
}
```

## 🚀 実装ステップ

### Phase 1: 高優先度（即時実施）

1. ✅ **SearchLedgersToolTest**: DatabaseTransactions移行完了
2. **CreateLedgerToolTest**: DatabaseTransactions移行
3. **GetLedgerDefinesToolTest**: DatabaseTransactions移行
4. **GetPendingApprovalsToolTest**: DatabaseTransactions移行
5. **McpToolsAuthenticationTest**: DatabaseTransactions移行

**期待効果:** テスト実行時間 60-75% 削減

### Phase 2: 中優先度（1週間以内）

6. **ClaimWorkflowTaskToolTest**: スキップテスト3件の再実装
7. **ExecuteApprovalToolTest**: スキップテスト2件の再実装
8. **GetWorkflowHistoryToolTest**: DatabaseTransactions移行検討

**期待効果:** テストカバレッジ向上、テスト実行時間さらに削減

### Phase 3: 低優先度（2週間以内）

9. **AuthenticatedMcpToolTest**: 最適化の余地を調査
10. **GetActivityLogToolTest**: 最適化の余地を調査

**期待効果:** 全体的なテスト品質向上

## 📈 期待される成果

### パフォーマンス改善

```
現在の推定実行時間:
- RefreshDatabase使用: 10テストファイル × 平均8秒 = 80秒
- DatabaseMigrations使用: 5テストファイル × 平均10秒 = 50秒
合計: 約130秒

改善後の推定実行時間:
- DatabaseTransactions使用: 10テストファイル × 平均2秒 = 20秒
- DatabaseMigrations使用: 2テストファイル × 平均10秒 = 20秒
合計: 約40秒

改善率: 約70%の時間削減 ⚡
```

### テストカバレッジ改善

```
現在: 
- 実装済みテスト: 約45テスト
- スキップテスト: 5テスト
- カバレッジ: 約90%

改善後:
- 実装済みテスト: 約50テスト
- スキップテスト: 0テスト
- カバレッジ: 約95%

カバレッジ向上: +5% 📊
```

## ⚠️ 注意事項

### DatabaseTransactions使用時の制約

1. **トランザクション内でのテスト:**
   - 各テストはトランザクション内で実行され、自動的にロールバック
   - 並列実行時の競合は発生しにくい

2. **Mroonga全文検索との互換性:**
   - Mroonga使用時は`DatabaseMigrations`が必要な場合がある
   - 該当するテストでは引き続き`DatabaseMigrations`を使用

3. **外部サービスの依存:**
   - Apache Tika、OCRなどの外部サービスを使用するテストは実DB必須

### モック化の注意点

1. **過度なモック化を避ける:**
   - ユニットテストでも適度に実DBを使用
   - 統合テストで実際の動作を検証

2. **レスポンス形式の一貫性:**
   - モックのレスポンスは実際のサービスと同じ形式に

3. **エラーハンドリングの検証:**
   - 正常系だけでなく、異常系も適切にテスト

## 📚 参考資料

- [Laravel Testing: Database Testing](https://laravel.com/docs/11.x/database-testing)
- [PHPUnit Best Practices](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
- SearchLedgersToolTest: 成功事例（75%高速化達成）

---

**次のアクション:**
1. CreateLedgerToolTest から DatabaseTransactions 移行開始
2. スキップテストの再実装着手
3. 各テストの実行時間を計測して効果を検証
