# W3-2.1 ロールバックサービス設計

**最終更新:** 2026-01-24  
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`  
**ステータス:** Draft（要PMレビュー）  
**管理場所:** `docs/work/core-features/ledger-diff-rollback/`

---

## 1. 目的

Phase 2のロールバック機能を支えるバックエンドロジック（`RollbackService`）の詳細仕様を定義する。既存の`WorkflowService`のパターンを流用し、データ整合性と監査証跡を保証するトランザクション構造を設計する。

---

## 2. クラス設計

### 2.1 `App\Services\RollbackService`

ロールバック実行の責務を持つ新規サービスクラス。

#### 主要メソッド

| メソッド名 | 引数 | 戻り値 | 説明 |
|-----------|------|--------|------|
| `execute` | `Ledger $ledger`, `LedgerDiff $targetDiff`, `int $modifierId`, `?string $comments`, `int $expected_current_version` | `array{ledger: Ledger, ledgerDiff: LedgerDiff}` | ロールバック処理の本体。トランザクション内で実行。 |
| `canExecute` | `User $user`, `Ledger $ledger` | `bool` | ユーザーが対象台帳をロールバック可能か判定。 |

---

## 3. 処理詳細設計

### 3.1 `execute` メソッドのアルゴリズム

以下の手順を `DB::transaction` 内で実行する。

1. **事前チェック**:
   - `Ledger::isLocked()` を実行し、ロック済み（`APPROVED`）なら例外。
   - `targetDiff` が同一台帳のものであることを確認。
   - **排他制御**: `Ledger::version` が `$expected_current_version` と異なる場合は `WorkflowConditionException` をスロー。

2. **バージョン確定**:
   - `newVersion = $ledger->version + 1`

3. **新規 `LedgerDiff` レコードの作成**:
   - `content`: `targetDiff->content` を複製
   - `column_define`: `targetDiff->column_define` を複製
   - `status`: 
     - ワークフロー無効台帳の場合: `WorkflowStatus::NONE`
     - ワークフロー有効台帳の場合: `WorkflowStatus::DRAFT`
   - `comments`: `comments` + `"(Ver. {$targetDiff->version} からロールバック)"` を記録
   - `modifier_id`: 実行者ID

4. **`Ledger` レコードの更新**:
   - `content`: `targetDiff->content` を反映
   - `content_attached`: `targetDiff->content_attached` を反映
   - `version`: `newVersion`
   - `latest_diff_id`: 3で作成したレコードのID
   - `status`: 3と同じステータス
   - `modifier_id`: 実行者ID

5. **ワークフローカウンター調整** (ワークフロー有効時のみ):
   - 既存の `WorkflowService::decrementPendingTaskCount` を流用し、現在の担当者（点検者または承認者）のタスク数を減算。

6. **操作ログ記録**:
   - `activity('ledger_rollback')` による監査ログ出力。
   - `properties` に詳細を記録（後述）。

7. **非同期ジョブ投入**:
   - `RecalculateScoringJob`: スコア再計算
   - `UpdateFullTextIndexJob`: 検索インデックス更新

### 3.2 排他制御（不整合防止）

ユーザーがロールバック確認画面を開いている間に、別のユーザーが台帳を更新した場合の「先祖返り後の上書き」を防ぐため、以下の**楽観的ロック**を適用する。

1. **不一致検知**: `Ledger::version` が `expected_current_version` と異なる場合は `WorkflowConditionException` をスローし、処理を中断する。

---

## 4. 権限・バリデーション設計

### 4.1 認可チェック (`canExecute`)

既存の `LedgerPolicy` を拡張または流用。

```php
public function canExecute(User $user, Ledger $ledger): bool
{
    // 1. フォルダ権限 (WRITE以上)
    if (!$this->userService->hasFolderPermission($user, $ledger->define->folder, FolderPermissionType::WRITE)) {
        return false;
    }

    // 2. レコードロックチェック
    if ($ledger->isLocked()) {
        return false;
    }
    
    // 3. ワークフロー有効時の追加制約
    if ($ledger->define->workflow_enabled && $ledger->status->isWorkflowPending()) {
        $latestDiff = $ledger->latestDiff;
        if (!$latestDiff) return false;
        
        // 現在の担当者のみロールバック可能
        if ($ledger->status === WorkflowStatus::PENDING_INSPECTION) {
            return $latestDiff->inspector_id === $user->id;
        } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL) {
            return $latestDiff->approver_id === $user->id;
        }
    }

    return true;
}
```

### 4.2 バリデーションルール

設定ファイル (`config/ledgerleap.php`) から動的に取得。

```php
'rollback' => [
    'validation' => [
        'comments' => [
            'rule' => env('ROLLBACK_COMMENT_RULE', 'nullable|string|max:500'),
        ],
    ],
],
```

### 4.3 イベント・通信設計

ユーザー追記の `W3-2.3` 要件に基づき、以下のパラメータをイベントに含める。

1. **イベント名**: `ledger.rollback.completed` (名前空間化)
2. **対象特定**: `targetComponentId` を含め、不特定のコンポーネントが反応するのを防ぐ。

---

## 5. 非機能・リカバリ設計

### 5.1 ジョブ完了監視

ロールバック後の不整合を防ぐため、以下のリカバリフローを組み込む。

1. **`CheckRollbackJobsCompletion` ジョブ**:
   - 実行から5分後に起動。
   - スコア計算・インデックス更新の完了フラグをチェック。
   - 未完了、または失敗を検知した場合は再試行または管理者アラートを発行。

### 5.2 Mroonga整合性確保

`UpdateFullTextIndexJob` 内で `OPTIMIZE TABLE ledgers` を実行し、インデックスの物理的な更新を促進する。

---

## 6. 監査・トレーサビリティ

### 6.1 ActivityLogのプロパティ拡充

`properties` に以下の情報を追加し、何が起きたかをより詳細に追跡可能にする。

- `target_version_modifier_id`: 復元対象となったバージョンの当時の編集者ID
- `rollback_comments`: ユーザー入力コメント
- `system_context`: `"W2-2 Phase2 Rollback"`
- `expected_version`: 実行時に期待していたバージョン（排他制御用）

---

## 7. 考慮事項

### 7.1 添付ファイルの扱い

ロールバック先のバージョンで保持していた `content_attached`（メタデータ）をそのまま復元する。実ファイルはパスベースで管理されているため、メタデータが正しければ過去の添付ファイルもそのまま閲覧可能となる。
ただし、画像からPDFへのOCR変換が発生している場合は、拡張子の不整合に留意する。

### 7.2 ワークフロー履歴の継続性

ロールバックは「過去の特定の状態をコピーして新しいバージョンとして追加する」アクションであるため、履歴が分岐することはない。一本の線形な履歴として記録される。

---

## 8. 懸念事項と対応策

### 8.1 AsColumnArrayJsonキャストの制約対応

**懸念事項**: OCR処理により画像ファイルのキーが変更される（`.jpg` → `.pdf`）ため、ロールバック時に不整合が発生する可能性。

**対応策**: ファイル参照整合性の検証機能を追加。

```php
private function validateAttachedFilesConsistency(array $contentAttached): bool
{
    foreach ($contentAttached as $columnId => $files) {
        foreach ($files as $hashedBasename => $fileData) {
            // 実ファイルの存在確認
            $filePath = storage_path("app/attachments/{$hashedBasename}");
            if (!file_exists($filePath)) {
                Log::warning("Rollback: Missing file", [
                    'ledger_id' => $this->ledger->id,
                    'file' => $hashedBasename,
                    'column_id' => $columnId
                ]);
                return false;
            }
        }
    }
    return true;
}
```

**理由**: Phase 1-5の添付ファイル機能で、OCR処理後にファイル拡張子が変更される仕様のため。

### 8.2 WorkflowServiceとの整合性確保

**懸念事項**: 既存の`WorkflowService`と処理が重複し、タスクカウンター調整で不整合が発生する可能性。

**対応策**: WorkflowServiceの既存メソッドを流用し、統一的な処理パターンを採用。

```php
private function adjustWorkflowCounters(Ledger $ledger): void
{
    if (!$ledger->define->workflow_enabled || !$ledger->status->isWorkflowPending()) {
        return;
    }
    
    $latestDiff = $ledger->latestDiff;
    if (!$latestDiff) return;
    
    if ($ledger->status === WorkflowStatus::PENDING_INSPECTION && $latestDiff->inspector_id) {
        $this->workflowService->decrementPendingTaskCount($latestDiff->inspector_id, 'inspection');
    } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL && $latestDiff->approver_id) {
        $this->workflowService->decrementPendingTaskCount($latestDiff->approver_id, 'approval');
    }
}
```

**理由**: `WorkflowService::decrementPendingTaskCount`は既に実装済みで、テスト済みの安全な処理パターンのため。

### 8.3 非同期ジョブの実装

**懸念事項**: 設計書で言及されている`RecalculateScoringJob`と`UpdateFullTextIndexJob`が未実装。

**対応策**: 実装フェーズで以下のジョブクラスを新規作成。

```php
// app/Jobs/Ledger/RecalculateLedgerScoringJob.php
class RecalculateLedgerScoringJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(private int $ledgerId) {}
    
    public function handle(ActivityScoreService $activityScoreService, CompositeScoreCalculator $compositeScoreCalculator): void
    {
        $ledger = Ledger::find($this->ledgerId);
        if (!$ledger) return;
        
        $activityScore = $activityScoreService->calculateForLedger($ledger);
        $compositeResult = $compositeScoreCalculator->calculate($ledger);
        
        $ledger->timestamps = false;
        $ledger->activity_score = $activityScore;
        $ledger->composite_score = $compositeResult['composite_score'];
        $ledger->saveQuietly();
    }
}

// app/Jobs/Ledger/UpdateFullTextIndexJob.php  
class UpdateFullTextIndexJob implements ShouldQueue
{
    public function __construct(private int $ledgerId) {}
    
    public function handle(): void
    {
        // Mroongaの特性上、個別レコード更新では即座にインデックス反映
        // 必要に応じてOPTIMIZE TABLEで強制更新
        DB::statement('OPTIMIZE TABLE ledgers');
    }
}
```

**理由**: スコアリングシステム（Phase 1実装済み）とMroonga全文検索の整合性を保つため。

### 8.4 権限チェックの包括化

**懸念事項**: ワークフロー有効台帳で現在の担当者以外がロールバックを実行する可能性。

**対応策**: `canExecute`メソッドでワークフロー状態を考慮した詳細権限チェックを実装。

```php
public function canExecute(User $user, Ledger $ledger): bool
{
    // 基本権限チェック（既存実装）
    if (!$this->userService->hasFolderPermission($user, $ledger->define->folder, FolderPermissionType::WRITE)) {
        return false;
    }
    
    if ($ledger->isLocked()) {
        return false;
    }
    
    // ワークフロー有効時の追加制約
    if ($ledger->define->workflow_enabled && $ledger->status->isWorkflowPending()) {
        $latestDiff = $ledger->latestDiff;
        if (!$latestDiff) return false;
        
        // 現在の担当者のみロールバック可能
        if ($ledger->status === WorkflowStatus::PENDING_INSPECTION) {
            return $latestDiff->inspector_id === $user->id;
        } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL) {
            return $latestDiff->approver_id === $user->id;
        }
    }
    
    return true;
}
```

**理由**: Phase 2で対象範囲をワークフロー有効台帳に拡張したため、既存のワークフロー制約に準拠する必要がある。

---

**PM承認後、実装（W4-2.1）に進みます。**
