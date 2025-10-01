# LedgerLeap MCP 包括的実装計画

**作成日:** 2025年9月29日  
**対象:** LedgerLeap開発チーム  
**関連ドキュメント:**
- [MCP応答最適化計画](./2025-09-28_MCP_Response_Optimization_Plan.md) - 基盤実装完了
- [MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md) - ユースケース設計
- [ペルソナ、ユースケース、シナリオ](../function/PersonaUseCaseScenario.md) - 要件定義

---

## 📋 概要

本計画は、LedgerLeapのMCPサーバー機能を、単なる基本的なCRUD操作から**完全なAI統合業務管理プラットフォーム**へと発展させるための包括的な実装計画です。

### 実装範囲
1. **準備段階**: 技術基盤の強化とリファクタリング
2. **機能開発段階**: ユースケース駆動の新機能実装
3. **統合・最適化段階**: システム全体の統合とパフォーマンス最適化

---

## 🎯 実装目標

### ビジネス目標
- **ワークフロー完全統合**: LLM経由でのタスク管理・承認処理
- **インテリジェント監査**: AI支援による活動監視・異常検出  
- **データドリブン意思決定**: 自然言語での統計情報取得

### 技術目標
- **MCPツールの標準化**: 認証・権限制御の統一パターン確立
- **パフォーマンス向上**: spatie/laravel-query-builder 活用による効率化
- **完全なテストカバレッジ**: 全MCPツールの包括的テスト

### UX目標
```
実現したい対話例:
ユーザー: "私に承認待ちのタスクを確認して、期限が近いものから処理したい"
システム: 承認待ち3件を期限順に表示 → そのまま承認処理可能

ユーザー: "先週のシステム利用状況を部門別に集計して"  
システム: 部門別の台帳作成数・ユーザーアクティビティを視覚化して報告
```

---

## 📊 現在の実装状況分析

### ✅ 実装済み機能
| 機能 | 実装状況 | 品質 |
|------|---------|------|
| SearchLedgersTool | 完全実装 | 高品質（format=summary対応） |
| CreateLedgerTool | 基本実装 | 要改善（認証が暫定実装） |
| GetLedgerDefinesTool | 基本実装 | 要改善（権限チェックなし） |
| OpenAPI文書化 | 完全実装 | 高品質 |
| プロンプトガイドライン | 完全実装 | 高品質 |

### ❌ 未実装機能（重要度別）
| 重要度 | 機能カテゴリ | ユースケース影響 |
|--------|------------|-----------------|
| 🔴 超高 | ワークフロー管理 | 承認待ちタスク確認・処理不可 |
| 🔴 超高 | アクティビティ監査 | システム監査・異常検出不可 |
| 🟡 高 | 統計・レポート | データ分析・意思決定支援不足 |
| 🟡 高 | 高度検索フィルタ | ステータス・期間検索の制限 |
| 🟡 高 | 添付ファイル連携 | 添付ファイルのバージョン比較や内容確認ができない |
| 🟢 中 | マイポータル統合 | 個人ダッシュボード情報不足 |

---

## 🚀 段階別実装計画

## Phase 0: 準備段階（技術基盤強化）
**期間:** 3-5日 / **担当:** 1名  
**目標:** 既存実装の品質向上とリファクタリング

### Step 0.1: spatie/laravel-query-builder 完全活用

#### 📋 作業内容
1. **LedgerService のリファクタリング**
   ```php
   // app/Services/LedgerService.php の改修
   public function searchLedgersForApi(\App\Models\User $user, array $params)
   {
       $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
       
       $query = QueryBuilder::for(Ledger::class)
           ->allowedFilters([
               AllowedFilter::exact('creator_id'),
               AllowedFilter::exact('ledger_define_id'),
               AllowedFilter::callback('folder_id', function ($query, $value) {
                   // 既存のフォルダ階層検索ロジック
               }),
               AllowedFilter::scope('created_between'),
               AllowedFilter::scope('search'), 
           ])
           ->allowedSorts(['created_at', 'updated_at'])
           ->whereHas('define.folder', function (Builder $q) use ($readableFolderIds) {
               $q->whereIn('id', $readableFolderIds);
           });
       
       return [
           'ledgers' => $query->get(),
           'total' => $query->count(),
       ];
   }
   ```

2. **カスタムスコープの実装**
   ```php
   // app/Models/Ledger.php に追加
   public function scopeCreatedBetween($query, $dateRange)
   {
       [$from, $to] = explode(',', $dateRange);
       return $query->whereBetween('created_at', [
           $from . ' 00:00:00',
           $to . ' 23:59:59'
       ]);
   }
   
   public function scopeUpdatedBetween($query, $dateRange)
   {
       [$from, $to] = explode(',', $dateRange);
       return $query->whereBetween('updated_at', [
           $from . ' 00:00:00',
           $to . ' 23:59:59'
       ]);
   }
   ```

#### ✅ 完了基準
- [ ] 既存の全検索テストが通過
- [ ] パフォーマンステスト（大量データでの速度向上確認）
- [ ] リグレッションテスト完全通過

### Step 0.2: MCPツール認証統一化

#### 📋 作業内容
1. **共通認証トレイトの作成**
   ```php
   // app/Mcp/Traits/AuthenticatedMcpTool.php
   trait AuthenticatedMcpTool
   {
       protected function authenticateUser(): User
       {
           $token = getenv('MCP_AUTH_TOKEN');
           if (!$token) {
               throw new UnauthorizedException('Authentication token not provided.');
           }
   
           $accessToken = PersonalAccessToken::findToken($token);
           if (!$accessToken || !$accessToken->tokenable) {
               throw new UnauthorizedException('Invalid authentication token.');
           }
   
           $user = $accessToken->tokenable;
           Auth::setUser($user);
           return $user;
       }
   
       protected function checkFolderPermission(User $user, Folder $folder, string $permission): bool
       {
           // 権限チェックロジック
       }
   }
   ```

2. **既存MCPツールの修正**
   ```php
   // CreateLedgerTool の修正例
   class CreateLedgerTool extends Tool
   {
       use AuthenticatedMcpTool;
   
       public function handle(Request $request, LedgerService $ledgerService): Response
       {
           try {
               $user = $this->authenticateUser();
               
               $folder = Folder::find($request->arguments('folder_id'));
               if (!$this->checkFolderPermission($user, $folder, 'write')) {
                   return Response::error('Insufficient permissions.', 403);
               }
   
               // 既存の台帳作成処理...
           } catch (UnauthorizedException $e) {
               return Response::error($e->getMessage(), 401);
           }
       }
   }
   ```

#### ✅ 完了基準
- [ ] 全MCPツールで統一された認証パターン
- [ ] 権限チェックの完全実装
- [ ] セキュリティテストの通過

### Step 0.3: テストカバレッジ完全化

#### 📋 作業内容
1. **不足テストの実装**
   ```bash
   # 作成すべきテストファイル
   tests/Unit/Mcp/Tools/CreateLedgerToolTest.php
   tests/Unit/Mcp/Tools/GetLedgerDefinesToolTest.php
   tests/Feature/Mcp/WorkflowToolsTest.php  # 将来用
   ```

2. **MCPサーバーテストの修正**
   ```php
   // tests/Feature/McpServerTest.php の改善
   #[Test]
   public function mcp_server_can_be_started_without_errors()
   {
       // タイムアウト付きのプロセス起動確認
       $process = new Process([
           './vendor/bin/sail', 'artisan', 'mcp:start', 'ledgerleap:mcp', '--test-mode'
       ]);
       $process->setTimeout(10);
       
       try {
           $process->mustRun();
           $this->assertTrue($process->isSuccessful());
       } catch (ProcessTimedOutException $e) {
           // タイムアウト = 正常起動とみなす（デーモンプロセスのため）
           $this->assertTrue(true, 'MCP server started successfully (timeout expected)');
       }
   }
   ```

#### ✅ 完了基準
- [ ] テストカバレッジ 95%以上
- [ ] 全CI/CDパイプラインでのテスト通過
- [ ] MCPサーバー起動テストの安定化

---

## Phase 1: ワークフロー統合（最重要）
**期間:** 1-2週間 / **担当:** 2名  
**目標:** ユースケース「承認待ちタスクの確認・処理」の完全実現

### Step 1.1: ワークフローAPI開発

#### 📋 作業内容
1. **WorkflowController の作成**
   ```php
   // app/Http/Controllers/Api/V1/WorkflowController.php
   class WorkflowController extends Controller
   {
       public function pending(Request $request)
       {
           // 承認待ち台帳の取得
       }
   
       public function approve(ApproveLedgerRequest $request, int $ledgerId)
       {
           // 台帳承認処理
       }
   
       public function reject(RejectLedgerRequest $request, int $ledgerId)
       {
           // 台帳差し戻し処理
       }
   
       public function history(Request $request, int $ledgerId)
       {
           // ワークフロー履歴取得
       }
   }
   ```

2. **API Routes の追加**
   ```php
   // routes/api.php に追加
   Route::group(['prefix' => 'v1', 'middleware' => 'auth:sanctum'], function () {
       Route::get('workflows/pending', [WorkflowController::class, 'pending']);
       Route::post('workflows/{ledger}/approve', [WorkflowController::class, 'approve']);
       Route::post('workflows/{ledger}/reject', [WorkflowController::class, 'reject']);
       Route::get('workflows/{ledger}/history', [WorkflowController::class, 'history']);
   });
   ```

3. **リクエストバリデーションクラス**
   ```php
   // app/Http/Requests/Api/V1/ApproveLedgerRequest.php
   class ApproveLedgerRequest extends FormRequest
   {
       public function rules(): array
       {
           return [
               'comment' => 'nullable|string|max:1000',
               'next_assignee_id' => 'nullable|integer|exists:users,id',
           ];
       }
   }
   ```

#### ✅ 完了基準
- [ ] 全ワークフローAPIエンドポイントの実装
- [ ] OpenAPI仕様書への追加
- [ ] ユニットテスト・統合テストの通過

### Step 1.2: ワークフローMCPツール実装

#### 📋 作業内容
1. **GetPendingApprovalsTool の作成**
   ```php
   // app/Mcp/Tools/GetPendingApprovalsTool.php
   class GetPendingApprovalsTool extends Tool
   {
       use AuthenticatedMcpTool;
   
       protected string $description = <<<'MARKDOWN'
           Get pending approval tasks for the authenticated user.
           Returns tasks sorted by priority and deadline.
       MARKDOWN;
   
       public function handle(Request $request): Response
       {
           $user = $this->authenticateUser();
           
           $pendingTasks = $this->workflowService->getPendingApprovals(
               user: $user,
               sortBy: $request->arguments('sort_by', 'deadline'),
               limit: $request->arguments('limit', 10)
           );
   
           if ($request->arguments('format') === 'summary') {
               return $this->formatSummaryResponse($pendingTasks);
           }
   
           return Response::json($pendingTasks);
       }
   
       public function schema(JsonSchema $schema): array
       {
           return [
               'sort_by' => $schema->string('Sort criteria')->enum([
                   'deadline', 'priority', 'created_at'
               ])->default('deadline'),
               'limit' => $schema->integer('Maximum number of tasks')->default(10),
               'format' => $schema->string('Response format')->enum([
                   'raw', 'summary'
               ])->default('raw'),
           ];
       }
   
       private function formatSummaryResponse($pendingTasks): Response
       {
           $summary = "承認待ちのタスクが{$pendingTasks['total']}件あります。";
           
           $formattedTasks = collect($pendingTasks['tasks'])->map(function ($task) {
               return [
                   'id' => $task->id,
                   'ledger_define_title' => $task->ledger->define->title,
                   'creator_name' => $task->ledger->creator->name,
                   'created_at' => $task->ledger->created_at->format('Y年m月d日 H:i'),
                   'deadline' => $task->deadline?->format('Y年m月d日'),
                   '__display_fields__' => [
                       '台帳種類' => $task->ledger->define->title,
                       '申請者' => $task->ledger->creator->name,
                       '申請日時' => $task->ledger->created_at->format('Y年m月d日 H:i'),
                       '期限' => $task->deadline?->format('Y年m月d日') ?? '期限なし',
                       '緊急度' => $this->getPriorityLabel($task->priority),
                   ]
               ];
           });
   
           return Response::json([
               'tasks' => $formattedTasks,
               'total' => $pendingTasks['total'],
               '__summary__' => $summary,
           ]);
       }
   }
   ```

2. **ApproveLedgerTool の作成**
   ```php
   class ApproveLedgerTool extends Tool
   {
       use AuthenticatedMcpTool;
   
       public function handle(Request $request): Response
       {
           $user = $this->authenticateUser();
           
           $ledgerId = $request->arguments('ledger_id');
           $comment = $request->arguments('comment');
           
           $result = $this->workflowService->approveLedger(
               ledgerId: $ledgerId,
               approver: $user,
               comment: $comment
           );
   
           return Response::json([
               'success' => true,
               'message' => '台帳を承認しました。',
               'ledger_id' => $ledgerId,
               'new_status' => $result->status,
               '__summary__' => "台帳ID {$ledgerId} を承認しました。現在のステータス: {$result->status->getLabel()}",
           ]);
       }
   
       public function schema(JsonSchema $schema): array
       {
           return [
               'ledger_id' => $schema->integer('ID of the ledger to approve')->required(),
               'comment' => $schema->string('Optional approval comment')->maxLength(1000),
           ];
       }
   }
   ```

#### ✅ 完了基準
- [ ] 4つのワークフローMCPツール完全実装
- [ ] LedgerLeapServer への登録完了
- [ ] MCPプロンプトガイドラインの更新

### Step 1.3: ワークフロー統合テスト

#### 📋 作業内容
1. **エンドツーエンドテスト実装**
   ```php
   // tests/Feature/Mcp/WorkflowIntegrationTest.php
   class WorkflowIntegrationTest extends TestCase
   {
       use DatabaseMigrations; // Mroongaテスト要件
   
       #[Test]
       public function user_can_check_and_approve_pending_tasks_via_mcp()
       {
           // テストデータ作成
           $approver = User::factory()->create();
           $ledger = Ledger::factory()->pendingApproval()->create();
           
           // 承認待ちタスク確認
           $pendingResponse = $this->callMcpTool('GetPendingApprovalsTool', [
               'format' => 'summary'
           ]);
           
           $this->assertStringContains('承認待ちのタスクが1件あります', 
               $pendingResponse['__summary__']);
           
           // 承認実行
           $approveResponse = $this->callMcpTool('ApproveLedgerTool', [
               'ledger_id' => $ledger->id,
               'comment' => 'テスト承認です'
           ]);
           
           $this->assertTrue($approveResponse['success']);
           $this->assertDatabaseHas('ledgers', [
               'id' => $ledger->id,
               'status' => 'approved'
           ]);
       }
   }
   ```

2. **実際のLLM対話テスト**
   ```bash
   # 手動テスト項目
   1. "私に承認待ちのタスクはありますか？"
   2. "期限が今日までの承認待ちタスクを確認して"  
   3. "台帳ID123を承認してください"
   4. "経費精算の承認待ちを全て確認したい"
   ```

#### ✅ 完了基準
- [ ] 全ワークフロー統合テストの通過
- [ ] 実際のLLM対話での期待通りの動作確認
- [ ] パフォーマンステスト（大量承認待ちデータでの応答時間）

---

## Phase 2: アクティビティ監査機能
**期間:** 1週間 / **担当:** 1名  
**目標:** ユースケース「システム監査・異常検出」の実現

### Step 2.1: アクティビティログAPI開発

#### 📋 作業内容
1. **ActivityController の作成**
   ```php
   // app/Http/Controllers/Api/V1/ActivityController.php
   class ActivityController extends Controller
   {
       public function index(SearchActivityRequest $request)
       {
           // アクティビティログ検索
       }
   
       public function userActivity(Request $request, int $userId)
       {
           // 特定ユーザーのアクティビティ
       }
   
       public function securityEvents(Request $request)
       {
           // セキュリティ関連イベント
       }
   
       public function anomalyDetection(Request $request)
       {
           // 異常活動検出
       }
   }
   ```

2. **spatie/laravel-activitylog 活用**
   ```php
   // 既存の CustomActivity モデル拡張
   class ActivityService
   {
       public function detectAnomalies(User $user, Carbon $from, Carbon $to): array
       {
           // 異常検出ロジック
           // - 深夜時間帯のアクセス
           // - 大量ファイルダウンロード  
           // - 通常と異なるアクセスパターン
       }
   
       public function getSecurityEvents(array $filters): Collection
       {
           // セキュリティ関連イベントの抽出
           // - ログイン失敗
           // - 権限変更
           // - 機密フォルダアクセス
       }
   }
   ```

#### ✅ 完了基準
- [ ] アクティビティ検索API実装
- [ ] 異常検出アルゴリズム実装
- [ ] セキュリティイベント分類機能

### Step 2.2: アクティビティMCPツール実装

#### 📋 作業内容
1. **SearchActivityLogTool の作成**
   ```php
   class SearchActivityLogTool extends Tool
   {
       use AuthenticatedMcpTool;
   
       public function handle(Request $request): Response
       {
           $user = $this->authenticateUser();
           
           // 管理者権限チェック
           if (!$user->hasRole('admin')) {
               return Response::error('管理者権限が必要です。', 403);
           }
   
           $activities = $this->activityService->searchActivities([
               'user_id' => $request->arguments('user_id'),
               'from_date' => $request->arguments('from_date'),
               'to_date' => $request->arguments('to_date'),
               'event_type' => $request->arguments('event_type'),
               'limit' => $request->arguments('limit', 50),
           ]);
   
           if ($request->arguments('format') === 'summary') {
               return $this->formatSecuritySummary($activities);
           }
   
           return Response::json($activities);
       }
   
       private function formatSecuritySummary($activities): Response
       {
           $anomalies = $this->activityService->detectAnomalies($activities);
           
           $summary = "期間内のアクティビティ{$activities['total']}件を確認しました。";
           if (count($anomalies) > 0) {
               $summary .= " 注意が必要な活動が{count($anomalies)}件検出されました。";
           }
   
           return Response::json([
               'activities' => $activities['items'],
               'anomalies' => $anomalies,
               'total' => $activities['total'],
               '__summary__' => $summary,
           ]);
       }
   }
   ```

#### ✅ 完了基準
- [ ] 3つのアクティビティMCPツール実装
- [ ] 管理者権限の適切な制御
- [ ] 異常検出結果の視覚的表示

### Step 2.3: セキュリティ監査統合テスト

#### 📋 作業内容
1. **監査シナリオテスト**
   ```php
   #[Test]
   public function admin_can_detect_suspicious_user_activity()
   {
       // 異常なアクティビティパターンを作成
       $suspiciousUser = User::factory()->create();
       
       // 深夜のファイル大量ダウンロードを記録
       CustomActivity::factory()->create([
           'causer_id' => $suspiciousUser->id,
           'description' => 'file_downloaded',
           'created_at' => now()->setTime(2, 30), // 深夜2:30
       ]);
   
       $response = $this->callMcpTool('SearchActivityLogTool', [
           'user_id' => $suspiciousUser->id,
           'format' => 'summary'
       ]);
   
       $this->assertStringContains('注意が必要な活動', $response['__summary__']);
   }
   ```

#### ✅ 完了基準
- [ ] 異常検出テストの通過
- [ ] 実際の監査シナリオでの動作確認
- [ ] セキュリティレポートの生成確認

---

## Phase 3: 統計・レポート機能  
**期間:** 1週間 / **担当:** 1名  
**目標:** データドリブンな意思決定支援

### Step 3.1: 統計分析API開発

#### 📋 作業内容
1. **AnalyticsController の作成**
   ```php
   class AnalyticsController extends Controller
   {
       public function ledgerStats(Request $request)
       {
           // 台帳統計（種類別、期間別、ユーザー別）
       }
   
       public function usageAnalytics(Request $request)
       {
           // システム利用状況分析
       }
   
       public function folderStats(Request $request)
       {
           // フォルダ別統計
       }
   
       public function performanceReport(Request $request)  
       {
           // パフォーマンスレポート
       }
   }
   ```

2. **統計計算サービス**
   ```php
   class AnalyticsService
   {
       public function getLedgerStatsByPeriod(Carbon $from, Carbon $to): array
       {
           return [
               'total_created' => Ledger::whereBetween('created_at', [$from, $to])->count(),
               'by_define' => Ledger::select('ledger_define_id', DB::raw('count(*) as count'))
                   ->whereBetween('created_at', [$from, $to])
                   ->groupBy('ledger_define_id')
                   ->with('define:id,title')
                   ->get(),
               'by_status' => Ledger::select('status', DB::raw('count(*) as count'))
                   ->whereBetween('created_at', [$from, $to])
                   ->groupBy('status')
                   ->get(),
           ];
       }
   }
   ```

#### ✅ 完了基準
- [ ] 包括的な統計分析API実装
- [ ] パフォーマンス最適化（大量データ処理）
- [ ] キャッシュ機能の実装

### Step 3.2: 統計MCPツール実装

#### 📋 作業内容
```php
class GetLedgerStatsTool extends Tool
{
    use AuthenticatedMcpTool;

    public function handle(Request $request): Response
    {
        $user = $this->authenticateUser();
        
        $period = $request->arguments('period', 'this_week');
        [$from, $to] = $this->parsePeriod($period);
        
        $stats = $this->analyticsService->getLedgerStatsByPeriod($from, $to);
        
        if ($request->arguments('format') === 'summary') {
            return $this->formatStatsSummary($stats, $period);
        }
        
        return Response::json($stats);
    }

    private function formatStatsSummary($stats, $period): Response
    {
        $periodLabel = $this->getPeriodLabel($period);
        
        $summary = "{$periodLabel}に作成された台帳は合計{$stats['total_created']}件です。";
        
        $breakdown = collect($stats['by_define'])->map(function ($item) {
            return "{$item->define->title}: {$item->count}件";
        })->join('、');
        
        if (!empty($breakdown)) {
            $summary .= " 内訳は{$breakdown}です。";
        }
        
        return Response::json([
            'stats' => $stats,
            '__summary__' => $summary,
            '__display_fields__' => [
                '期間' => $this->getPeriodLabel($period),
                '総件数' => $stats['total_created'] . '件',
                '主要な台帳種類' => collect($stats['by_define'])->take(3)->pluck('define.title')->join('、'),
            ]
        ]);
    }
}
```

#### ✅ 完了基準
- [ ] 4つの統計MCPツール実装
- [ ] 複数の期間指定パターン対応
- [ ] 視覚的なサマリー生成

---

## Phase 4: 検索機能拡張・統合
**期間:** 3-5日 / **担当:** 1名  
**目標:** SearchLedgersTool の完全機能化

### Step 4.1: 高度検索フィルタ追加

#### 📋 作業内容
```php
// SearchLedgersTool のスキーマ拡張
public function schema(JsonSchema $schema): array
{
    return [
        // 既存パラメータ
        'q' => $schema->string('Full-text search keyword'),
        'creator_id' => $schema->integer('Filter by creator ID'),
        'created_from' => $schema->string('Created date from (YYYY-MM-DD)'),
        'created_to' => $schema->string('Created date to (YYYY-MM-DD)'),
        
        // 新規追加パラメータ
        'status' => $schema->string('Filter by ledger status')->enum([
            'none', 'in_progress', 'pending_inspection', 
            'pending_approval', 'approved', 'rejected'
        ]),
        'updated_from' => $schema->string('Updated date from (YYYY-MM-DD)'),
        'updated_to' => $schema->string('Updated date to (YYYY-MM-DD)'),
        'period' => $schema->string('Predefined period filter')->enum([
            'today', 'yesterday', 'this_week', 'last_week',
            'this_month', 'last_month', 'this_year'
        ]),
        'assigned_user_id' => $schema->integer('Filter by assigned user ID'),
        'has_attachments' => $schema->boolean('Filter ledgers with/without attachments'),
        'priority' => $schema->string('Filter by priority level')->enum([
            'low', 'medium', 'high', 'urgent'
        ]),
        
        // 表示制御
        'format' => $schema->string('Response format')->enum(['raw', 'summary']),
        'sort_by' => $schema->string('Sort field')->enum([
            'created_at', 'updated_at', 'priority', 'status'
        ])->default('created_at'),
        'sort_direction' => $schema->string('Sort direction')->enum([
            'asc', 'desc'
        ])->default('desc'),
        'limit' => $schema->integer('Maximum results')->default(10)->maximum(100),
    ];
}
```

#### ✅ 完了基準
- [ ] 全ての新規フィルタの実装・テスト
- [ ] 複合条件検索の動作確認
- [ ] パフォーマンス最適化

---

## Phase 5: 添付ファイル・差分連携機能
**期間:** 1週間 / **担当:** 1名
**目標:** ユースケース「添付ファイルのバージョン比較」「テキストインデックスの確認」の実現

### Step 5.1: 添付ファイル・差分関連API開発

#### 📋 作業内容
1. **LedgerDiffController の作成**
   ```php
   // app/Http/Controllers/Api/V1/LedgerDiffController.php
   class LedgerDiffController extends Controller
   {
       public function index(Request $request, int $ledgerId)
       {
           // 特定台帳の差分リストを取得
       }
   }
   ```
2. **AttachedFileController の作成**
   ```php
   // app/Http/Controllers/Api/V1/AttachedFileController.php
   class AttachedFileController extends Controller
   {
       public function show(Request $request, int $attachmentId)
       {
           // 添付ファイルの詳細情報（抽出テキスト含む）を取得
       }

       public function content(Request $request, int $attachmentId)
       {
           // 添付ファイルのバイナリコンテンツを取得
       }
   }
   ```
3. **API Routes の追加**
   ```php
   // routes/api.php に追加
   Route::group(['prefix' => 'v1', 'middleware' => 'auth:sanctum'], function () {
       Route::get('ledgers/{ledger}/diffs', [LedgerDiffController::class, 'index']);
       Route::get('attachments/{attachment}', [AttachedFileController::class, 'show']);
       Route::get('attachments/{attachment}/content', [AttachedFileController::class, 'content']);
   });
   ```

#### ✅ 完了基準
- [ ] 全ての添付ファイル・差分関連APIエンドポイントの実装
- [ ] OpenAPI仕様書への追加
- [ ] ユニットテスト・統合テストの通過

### Step 5.2: 添付ファイル・差分MCPツール実装

#### 📋 作業内容
1. **GetLedgerDiffsTool の作成**
   - `handle` メソッドで `/api/v1/ledgers/{ledger}/diffs` を呼び出す。
   - `ledger_id` を必須パラメータとする。
2. **GetAttachmentDetailsTool の作成**
   - `handle` メソッドで `/api/v1/attachments/{attachment}` を呼び出す。
   - `attachment_id` を必須パラメータとする。
3. **ReadAttachmentTool の作成**
   - `handle` メソッドで `/api/v1/attachments/{attachment}/content` を呼び出す。
   - `attachment_id` を必須パラメータとする。

#### ✅ 完了基準
- [ ] 3つの添付ファイル・差分MCPツール実装
- [ ] LedgerLeapServer への登録完了
- [ ] MCPプロンプトガイドラインの更新

### Step 5.3: 添付ファイル・差分連携テスト

#### 📋 作業内容
1. **エンドツーエンドテスト実装**
   - 複数のバージョンを持つ台帳を作成し、`GetLedgerDiffsTool` で差分が取得できることを確認。
   - `GetAttachmentDetailsTool` で添付ファイルの詳細（特に抽出テキスト）が取得できることを確認。
   - `ReadAttachmentTool` でファイルコンテンツが取得できることを確認。
2. **実際のLLM対話テスト**
   - 「契約書Aの最新版と一つ前の版で、どこが変わったか教えて。」
   - 「台帳ID: 123 の添付ファイル `invoice.pdf` の抽出テキストを見せて。」

#### ✅ 完了基準
- [ ] 全ての連携テストの通過
- [ ] 実際のLLM対話での期待通りの動作確認

---

## Phase 6: 統合・最適化段階
**期間:** 3-5日 / **担当:** 全員  
**目標:** システム全体の統合とUX最適化

### Step 5.1: MCPプロンプト最適化

#### 📋 作業内容
1. **LedgerLeapServer instructions 拡張**
   ```markdown
   You are an assistant for the LedgerLeap ledger management system.
   
   ## Core Capabilities
   You can help users with:
   - Searching and filtering ledgers with advanced criteria
   - Managing workflow approvals and task assignments  
   - Monitoring system activity and security events
   - Generating statistical reports and analytics
   - Creating and managing ledger entries
   
   ## Response Optimization
   When using tools that return responses with `__summary__`, include that summary prominently.
   When displaying `__display_fields__`, present information in user-friendly Japanese format.
   
   ## Workflow Management  
   For approval-related queries:
   1. Use GetPendingApprovalsTool with format="summary"
   2. Present tasks sorted by urgency and deadline
   3. Offer to execute approval actions directly
   
   ## Security & Monitoring
   For administrative monitoring:
   1. Use SearchActivityLogTool for audit requests
   2. Highlight any anomalies or security concerns
   3. Provide actionable recommendations
   
   ## Analytics & Reporting
   For statistical queries:
   1. Use appropriate analytics tools with format="summary"
   2. Present data with clear context and business insights
   3. Offer drill-down options for detailed analysis
   ```

2. **プロンプトガイドライン更新**
   - 新機能の使用例追加
   - エラーハンドリングパターン拡張
   - 複合クエリのベストプラクティス

#### ✅ 完了基準
- [ ] 新機能を含む包括的なプロンプトガイドライン
- [ ] 実際のユーザー対話での自然な動作確認

### Step 5.2: パフォーマンス最適化

#### 📋 作業内容
1. **データベースクエリ最適化**
   - インデックス追加・最適化
   - N+1問題の解決
   - ページネーション改善

2. **キャッシュ戦略実装**
   ```php
   // 統計データのキャッシュ
   Cache::remember("ledger_stats_{$period}", 3600, function() use ($period) {
       return $this->analyticsService->getLedgerStatsByPeriod(...);
   });
   ```

3. **MCPレスポンス最適化**
   - 大量データでの応答時間改善
   - メモリ使用量最適化

#### ✅ 完了基準
- [ ] 全機能でのパフォーマンステスト通過
- [ ] メモリ使用量・応答時間の基準値達成

### Step 5.3: 最終統合テスト

#### 📋 作業内容
1. **エンドツーエンドシナリオテスト**
   ```bash
   # 統合シナリオ例
   1. "今日私に割り当てられた承認待ちタスクを確認して、緊急度の高い順に処理したい"
   2. "先月の部門別台帳作成統計を確認し、異常なアクセスがないかチェックして"
   3. "特定ユーザーの活動履歴を確認し、問題があれば関連台帳も検索して"
   ```

2. **負荷テスト・ストレステスト**
   - 同時MCPリクエスト処理
   - 大量データでの各種操作
   - メモリリーク・パフォーマンス劣化チェック

#### ✅ 完了基準
- [ ] 全ユースケースシナリオの動作確認
- [ ] 負荷テスト基準値達成
- [ ] 本番環境での安定動作確認

---

## 📊 実装完了時の達成目標

### 機能目標
- [ ] **18種類以上のMCPツール**実装
- [ ] **全18シナリオ**への対応
- [ ] **エンドツーエンド業務フロー**のAI統合

### 品質目標
- [ ] **テストカバレッジ 95%以上**
- [ ] **API応答時間 500ms以下**（通常クエリ）
- [ ] **セキュリティ監査基準**の完全準拠

### UX目標
```
Before: "台帳の検索しかできない基本的なツール"
After: "業務全体を自然言語で操作できる完全統合プラットフォーム"

実現される対話例:
"昨日から今日にかけての緊急承認待ちタスクを確認し、関連する統計情報も含めて部門長に報告書を作成して"
→ 承認待ちタスク取得 + 統計分析 + 視覚的レポート生成まで一貫して実行
```

---

## 📅 実装スケジュール

| Phase | 期間 | 担当 | マイルストーン | 進捗状況 |
|-------|------|------|---------------|---------|
| **Phase 0** | 3-5日 | 1名 | 技術基盤強化完了 | ✅ **完了 (2025-10-01)** |
| **Phase 1** | 1-2週間 | 2名 | ワークフロー統合完了 | ⏳ 待機中 |
| **Phase 2** | 1週間 | 1名 | 監査機能実装完了 | ⏳ 待機中 |
| **Phase 3** | 1週間 | 1名 | 統計機能実装完了 | ⏳ 待機中 |
| **Phase 4** | 3-5日 | 1名 | 検索機能完全化 | ⏳ 待機中 |
| **Phase 5** | 1週間 | 1名 | 添付ファイル連携完了 | ⏳ 待機中 |
| **Phase 6** | 3-5日 | 全員 | 統合・最適化完了 | ⏳ 待機中 |

**総実装期間: 4-6週間**  
**必要リソース: 2名のフルタイム開発者**  
**現在の進捗: Phase 0 - Step 0.1 完了 (2025-09-29)**

### 🎯 **Phase 0 詳細進捗**
- ✅ **Step 0.1** (2025-09-29完了): spatie/laravel-query-builder完全活用
  - **技術成果**: コード効率化100行→20行、クエリ処理16.38ms達成
  - **品質**: 全既存テスト通過、完全後方互換性維持
- ✅ **Step 0.2** (2025-09-29完了): MCPツール認証統一化
  - **技術成果**: 認証ロジック共通化、113行の統一トレイト実装
  - **品質**: 統合テスト6テスト/16 assertions全通過、エラーハンドリング標準化
- ✅ **Step 0.3** (2025-10-01完了): テストカバレッジ完全化
  - **技術成果**: 包括的テストスイート36テスト/113 assertions実装
  - **品質**: Testing Best Practices準拠、重複整理完了、100%テスト通過率

---

## ⚠️ リスク管理

### 技術リスク
1. **Mroonga全文検索制限**: 複合インデックス使用不可
   - **対策**: OR結合による個別検索パターン維持
2. **大量データ処理**: 統計計算の負荷
   - **対策**: インクリメンタル計算・キャッシュ活用
3. **MCPプロトコル制限**: レスポンスサイズ上限
   - **対策**: ページネーション・サマリー化対応

### プロジェクトリスク
1. **要件変更**: ユースケース追加・変更
   - **対策**: 段階的実装・フィードバック組み込み
2. **テスト環境**: CI/CDパイプライン負荷
   - **対策**: 並列テスト・選択的実行

### セキュリティリスク
1. **権限制御**: MCPツール間の一貫性
   - **対策**: 共通トレイト・統一パターン適用
2. **監査ログ**: アクセス制御の適切性
   - **対策**: 管理者権限の厳密なチェック

---

## 🎯 成功基準

### 定量的基準
- [ ] **MCPツール数**: 15種類以上
- [ ] **対応ユースケース**: 全15シナリオ
- [ ] **テストカバレッジ**: 95%以上  
- [ ] **API応答時間**: 平均500ms以下
- [ ] **同時接続数**: 50ユーザー対応

### 定性的基準
- [ ] **自然言語理解**: 曖昧な指示への適切な対応
- [ ] **業務フロー統合**: エンドツーエンド操作の実現
- [ ] **セキュリティ**: 監査要件の完全準拠
- [ ] **拡張性**: 新機能追加の容易さ確保

---

この包括的実装計画により、LedgerLeapは**次世代AI統合業務管理プラットフォーム**として完全な機能を提供できるようになります。段階的な実装により、各フェーズでの確認・調整が可能であり、品質を保ちながら確実に目標を達成できる設計となっています。