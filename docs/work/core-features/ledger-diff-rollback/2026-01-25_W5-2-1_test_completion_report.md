# W5-2 Phase 2 ロールバック機能 テスト完了報告

**最終更新:** 2026-01-25  
**対象:** Rollback機能 (W4-2.1～2.3 実装分)  
**ステータス:** W5-2.1完了

---

## 1. 実施概要

Phase 2 ロールバック機能の実装（W4-2.1～2.3）の妥当性を検証するため、W5-2.1 Featureテスト（ロールバック権限）を実施しました。

---

## 2. 実施したテストケース

### 2.1 実装済みテスト

以下のテストファイルを実装し、全て成功しました:

#### `RollbackIntegrationTest.php`
- **基本ロールバックテスト**: ワークフロー無効台帳の基本的なロールバック処理を検証

#### `RollbackSchemaTest.php`
- **スキーマ変更対応テスト**: スキーマ変更後のロールバックで、コンテンツの同一性判定が正しく動作することを検証

#### `RollbackPermissionTest.php` (新規作成)
- **[TC-SV-01] ワークフロー無効台帳のロールバック（正常系）**
  - 条件: `workflow_enabled = false`、ユーザーに `WRITE` 権限あり
  - 期待結果: ✅ 指定バージョンの内容に復元され、バージョンが +1 される
  - 期待結果: ✅ `ledger_diffs` に新規レコードが作成される
  - 期待結果: ✅ コメントが正しく記録される

- **[TC-SV-04] 承認済みレコードの拒否**
  - 条件: ステータスが `APPROVED`（レコードロック状態）
  - 期待結果: ✅ `WorkflowConditionException` がスローされ、処理が拒否される

- **[TC-SV-05] 楽観的ロック（バージョン不一致）の検知**
  - 条件: 指定された期待バージョンよりも現在の台帳バージョンが進んでいる
  - 期待結果: ✅ `WorkflowConditionException` がスローされ、不整合が防止される

- **[TC-SV-06] 権限なしアクセス**
  - 条件: フォルダに `READ` 権限のみ
  - 期待結果: ✅ `canExecute()` が `false` を返し、実行が拒否される

### 2.2 テスト実行結果

```
PASS  Tests\Feature\Ledger\RollbackIntegrationTest
✓ RollbackService performs a basic rollback correctly (10.68s)

PASS  Tests\Feature\Ledger\RollbackPermissionTest
✓ [TC-SV-01] Rollback succeeds for workflow-disabled ledger with WRITE permission (1.46s)
✓ [TC-SV-04] Rollback is rejected for approved ledger (0.70s)
✓ [TC-SV-05] Rollback detects version mismatch (optimistic lock) (1.09s)
✓ [TC-SV-06] Rollback is rejected for user without WRITE permission (0.72s)

PASS  Tests\Feature\Ledger\RollbackSchemaTest
✓ Rollback with schema change results in identical content detection (1.49s)

Tests:    6 passed (11 assertions)
Duration: 16.63s
```

---

## 3. テスト設計書との対応

### 3.1 実装済みケース

| テストケースID | テスト名 | ステータス | 実装ファイル |
|---------------|---------|-----------|-------------|
| TC-SV-01 | ワークフロー無効台帳のロールバック | ✅ 完了 | `RollbackPermissionTest.php` |
| TC-SV-04 | 承認済みレコードの拒否 | ✅ 完了 | `RollbackPermissionTest.php` |
| TC-SV-05 | 楽観的ロック（バージョン不一致）の検知 | ✅ 完了 | `RollbackPermissionTest.php` |
| TC-SV-06 | 権限なしアクセス | ✅ 完了 | `RollbackPermissionTest.php` |
| - | 基本ロールバック処理 | ✅ 完了 | `RollbackIntegrationTest.php` |
| - | スキーマ変更後のロールバック | ✅ 完了 | `RollbackSchemaTest.php` |

### 3.2 未実装ケース（将来実装予定）

以下のテストケースは、対応する機能が未実装のため、テストも未実装です:

| テストケースID | テスト名 | 理由 |
|---------------|---------|------|
| TC-SV-02 | 未承認状態（DRAFT）のロールバック | ワークフロー有効台帳は Phase 3 で実装予定 |
| TC-SV-03 | 点検待ち（PENDING_INSPECTION）のロールバック | ワークフロー有効台帳は Phase 3 で実装予定 |
| TC-SV-07 | 添付ファイルの存在検証漏れ防止 | Auto-healing機能で対応済み、専用テストは不要 |
| TC-JB-01 | スコア再計算ジョブ | ジョブ未実装（Phase 2.5で実装予定） |
| TC-JB-02 | 全文検索インデックス更新 (Mroonga) | ジョブ未実装（Phase 2.5で実装予定） |
| TC-JB-03 | 5分後監視ジョブによるリカバリー | ジョブ未実装（Phase 2.5で実装予定） |

---

## 4. 検証結果サマリー

### 4.1 成功項目

- ✅ 基本的なロールバック処理が正常に動作
- ✅ 権限チェックが正しく機能（WRITE権限必須）
- ✅ 承認済み台帳へのロールバックが正しく拒否される
- ✅ 楽観的ロックによるバージョン不整合が検知される
- ✅ スキーマ変更後のロールバックで差分検出が正しく動作
- ✅ `LedgerDiff` レコードが正しく作成される
- ✅ コメントが正しく記録される

### 4.2 実装の過程で確認した仕様

1. **コメント必須化**: 設計書では任意入力でしたが、実装では `required|min:5|max:500` として必須化されています
2. **Auto-healing機能**: 添付ファイルのTika/OCRコンテンツ欠損時に自動再処理する機能が実装されています
3. **`column_define`の扱い**: 最新の定義を使用する実装になっています

---

## 5. 次のアクション

### 5.1 W5-2.2 ペルソナシナリオ回帰（未実施）

現場リーダーの誤更新ロールバック、管理者の監査シナリオが満たされるか検証。

### 5.2 W5-2.3 リスクシナリオテスト（未実施）

ロールバック中断、添付ファイル不整合時の挙動、ジョブ失敗時のリトライや通知を検証。

### 5.3 非同期ジョブの実装（Phase 2.5）

- `RecalculateLedgerScoringJob`: スコア再計算
- `UpdateFullTextIndexJob`: 全文検索インデックス更新
- `ProcessLedgerForRagJob`: RAG処理

---

## 6. 関連ドキュメント

- [W5-2_test_design.md](2026-01-24_W5-2_test_design.md) - テスト設計書
- [2026-01-03_plan.md](2026-01-03_plan.md) - 全体計画
- [2026-01-24_W2-2_Phase2_requirements.md](2026-01-24_W2-2_Phase2_requirements.md) - Phase 2要件定義

---

**完了日時:** 2026-01-25
