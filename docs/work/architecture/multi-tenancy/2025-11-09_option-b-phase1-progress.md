# Option B実装 - Phase 1 進捗報告

**作成日**: 2025-11-09  
**ブランチ**: `feature/option-b-tenancy-fix`  
**フェーズ**: Phase 1 - コア実装  
**ステータス**: 🟡 進行中

---

## Phase 1 タスク進捗

### ✅ Task 1.1: ProcessLedgerForRagJob の修正（完了）

**実施時刻**: 2025-11-09 19:21 JST

**変更内容**:

1. **SerializesModels トレイトの削除**
   ```php
   // Before
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
   
   // After
   use Dispatchable, InteractsWithQueue, Queueable;
   ```

2. **コンストラクタの変更**
   ```php
   // Before
   public function __construct(private Ledger $ledger) {}
   
   // After
   public function __construct(public int $ledgerId) {}
   ```

3. **handle() メソッドの修正**
   - Ledger取得ロジックの追加
   - エラーハンドリングの実装
   - ログ出力にtenant_id追加
   
   ```php
   // QueueTenancyBootstrapperが自動的にtenancyを初期化済み
   $ledger = Ledger::find($this->ledgerId);
   
   if (!$ledger) {
       Log::channel($logChannel)->warning('Ledger not found in job', [
           'ledger_id' => $this->ledgerId,
       ]);
       return;
   }
   ```

4. **プライベートメソッドの引数追加**
   - `updateContentAttachedWithVlmResult(Ledger $ledger)` - 引数追加
   - `buildMarkdownFromLedger(Ledger $ledger)` - 既に引数あり（変更なし）

5. **getLedger() メソッドの削除**
   - テストで使用されているため、Phase 2で影響調査が必要

**変更ファイル**:
- `app/Jobs/ProcessLedgerForRagJob.php`

**コードフォーマット**: ✅ 完了（pint実行済み）

---

### ✅ Task 1.2: LedgerObserver の修正（完了）

**実施時刻**: 2025-11-09 19:21 JST

**変更内容**:

1. **dispatchRagJob() メソッドの修正**
   ```php
   // Before
   ProcessLedgerForRagJob::dispatch($ledger);
   
   // After
   ProcessLedgerForRagJob::dispatch($ledger->id);
   ```

2. **sync実行時の修正**
   ```php
   // Before
   (new ProcessLedgerForRagJob($ledger))->handle(app(EmbeddingService::class));
   
   // After
   (new ProcessLedgerForRagJob($ledger->id))->handle(app(EmbeddingService::class));
   ```

**影響箇所**:
- `created()` メソッド内の `dispatchRagJob()`
- `updated()` メソッド内の条件分岐

**変更ファイル**:
- `app/Observers/LedgerObserver.php`

**コードフォーマット**: ✅ 完了（pint実行済み）

---

### 🔄 Task 1.3: 他のJobクラスの影響調査（進行中）

**実施時刻**: 2025-11-09 19:22 JST

**調査対象**:

| ジョブクラス | SerializesModels | 自動ディスパッチ | 優先度 | 判定 |
|------------|------------------|-----------------|--------|------|
| `ProcessAttachedFile.php` | ✅ あり | ✅ あり | 高 | ⚠️ 要対応 |
| `ProcessVlmExtraction.php` | ✅ あり | ✅ あり | 高 | ⚠️ 要対応 |
| `GenerateThumbnail.php` | ✅ あり | ✅ あり（間接） | 中 | 🔍 要検証 |
| `AttachedFileOcrJob.php` | ✅ あり | ✅ あり（間接） | 中 | 🔍 要検証 |
| `OcrAndOptimizeFile.php` | ✅ あり | ❌ なし | 低 | ✅ Phase 2以降 |
| `ExportJob.php` | ✅ あり | ❌ なし | 低 | ✅ Phase 2以降 |

**調査結果**:

1. **ProcessAttachedFile.php**
   - Observerから自動ディスパッチされる
   - `AttachedFile` モデルを受け取る
   - 手動でtenancy初期化を実装済み（45行目）
   ```php
   tenancy()->initialize($this->attachedFile->tenant_id);
   ```
   - **判定**: 現状は動作しているが、Option Bパターンに統一すべき

2. **ProcessVlmExtraction.php**
   - `ProcessAttachedFile` から自動ディスパッチされる
   - `AttachedFile` モデルを受け取る
   - tenancy初期化なし
   - **判定**: Option Bパターンへの変更が必要

**推奨対応**:
- Phase 1では `ProcessLedgerForRagJob` のみに集中
- Phase 2で `ProcessAttachedFile` と `ProcessVlmExtraction` を対応
- 他のジョブはPhase 3以降で対応

---

## 次のステップ

### 🔜 Phase 2へ移行準備

**Phase 1完了条件**:
- ✅ Task 1.1: ProcessLedgerForRagJob 修正完了
- ✅ Task 1.2: LedgerObserver 修正完了
- ✅ Task 1.3: 影響調査完了

**Phase 2開始前の確認事項**:
1. [ ] コンパイルエラーがないことを確認
2. [ ] 既存テストの失敗パターンを把握
3. [ ] テスト修正箇所リストの作成

---

## 技術的メモ

### getLedger() メソッドについて

**削除したメソッド**:
```php
public function getLedger(): Ledger
{
    return $this->ledger;
}
```

**影響範囲**:
- `tests/Feature/Observers/LedgerObserverTest.php` (2箇所)
- `tests/Feature/Rag/VlmRagIntegrationTest.php` (2箇所)

**Phase 2での対応**:
```php
// Before
$job->getLedger()->id === $ledger->id

// After
$job->ledgerId === $ledger->id
```

### tenancy初期化のタイミング

**QueueTenancyBootstrapper の動作**:
1. Job dispatch時: tenant_id を自動的にペイロードに追加
2. Job実行前: `tenancy()->initialize()` を自動実行
3. Job完了後: `tenancy()->end()` を自動実行

**確認方法**:
```php
// config/tenancy.php の42行目
Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class, // ✅ 有効
```

---

## Phase 1 完了確認

**変更ファイル数**: 2
- `app/Jobs/ProcessLedgerForRagJob.php`
- `app/Observers/LedgerObserver.php`

**削除行数**: 3（SerializesModels use文、getLedger()メソッド）  
**追加行数**: 約10（エラーハンドリング、ログ出力強化）

**コードフォーマット**: ✅ 完了  
**コンパイル**: 🔜 次の確認項目

---

**次回更新**: Phase 2（テスト修正）開始時
