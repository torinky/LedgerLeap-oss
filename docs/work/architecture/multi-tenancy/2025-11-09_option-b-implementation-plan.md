# Tenancy問題 - Option B根本解決実装計画

**作成日**: 2025-11-09  
**対象**: ProcessLedgerForRagJob の SerializesModels 問題  
**関連文書**: [tenancy-issue-resolution.md](./tenancy-issue-resolution.md)  
**ステータス**: 📋 計画中

---

## エグゼクティブサマリー

Option C（Observer修正）により**テスト環境での問題は解決済み**ですが、本番環境での非同期キュー実行時にtenancy問題が再発する可能性があります。本ドキュメントでは、公式ベストプラクティスに完全準拠するOption B（SerializesModels削除 + ID渡しパターン）の詳細実装計画を提示します。

### Option B の選択理由
- **公式推奨**: Stancl Tenancy + Laravel の推奨パターン
- **根本解決**: SerializesModelsによるtenancyコンテキスト喪失を完全回避
- **本番環境対応**: 非同期キュー（Redis/Database）での安定性確保
- **保守性向上**: 明示的なID管理により意図が明確化

---

## 問題の背景

### SerializesModels と Tenancy の競合

```php
// 現在の実装（問題あり）
class ProcessLedgerForRagJob implements ShouldQueue
{
    use SerializesModels; // ← Tenancyコンテキストを失う原因
    
    public function __construct(private Ledger $ledger) {}
}
```

**問題の流れ**:
1. `LedgerObserver::created()` でジョブをディスパッチ
2. `SerializesModels` がモデルをシリアライズ（DB接続情報をクリア）
3. **tenancyコンテキストが終了**（Laravel/Tenancyの仕様）
4. 同一リクエスト内で2つ目以降のLedger作成時、`tenant_id`が設定されない
5. 検索時にデータが見つからない

### 公式ベストプラクティス（2024年最新）

**Laravel + Stancl Tenancy 推奨パターン**:
- ✅ **モデルIDのみを渡す**（SerializesModelsを使わない）
- ✅ Job内でtenancyコンテキスト確立後にモデルを取得
- ✅ QueueTenancyBootstrapperに依存（既に有効化済み）

**参考資料**:
- [Stancl Tenancy - Queues](https://tenancyforlaravel.com/docs/v3/queues/)
- [Understanding Laravel's SerializesModels](https://ryanc.co/posts/understanding-laravels-serializesmodels)
- [Efficiently Dispatching Jobs with Models](https://sjorso.com/efficiently-dispatching-jobs-with-models-in-laravel)

---

## 実装計画（WBS）

### Phase 1: コア実装（見積: 2-3時間）

#### タスク 1.1: ProcessLedgerForRagJob の修正

**変更ファイル**: `app/Jobs/ProcessLedgerForRagJob.php`

**主な変更点**:
1. `SerializesModels` トレイトを削除
2. コンストラクタを `int $ledgerId` に変更
3. `handle()` 内で `Ledger::find($this->ledgerId)` を実行
4. プライベートメソッドに `Ledger` 引数を追加

**技術的詳細**:
```php
// Before
use SerializesModels;
public function __construct(private Ledger $ledger) {}

// After
// SerializesModels は削除
public function __construct(public int $ledgerId) {}

public function handle(EmbeddingService $embeddingService): void
{
    // QueueTenancyBootstrapperが自動的にtenancyを初期化済み
    $ledger = Ledger::find($this->ledgerId);
    
    if (!$ledger) {
        Log::warning('Ledger not found in job', ['ledger_id' => $this->ledgerId]);
        return;
    }
    
    // 既存ロジックを $this->ledger から $ledger に変更
}
```

**影響するメソッド**:
- `handle()`
- `updateContentAttachedWithVlmResult(Ledger $ledger)`
- `buildMarkdownFromLedger(Ledger $ledger)` （既に引数あり）

**注意点**:
- `getLedger()` メソッドは削除（テストで使用されているため影響調査必要）
- エラーハンドリングとして `Ledger` が見つからない場合のログ出力を追加

#### タスク 1.2: LedgerObserver の修正

**変更ファイル**: `app/Observers/LedgerObserver.php`

**主な変更点**:
```php
// Before
ProcessLedgerForRagJob::dispatch($ledger);

// After
ProcessLedgerForRagJob::dispatch($ledger->id);
```

**修正箇所**:
- `created()` メソッド内の `dispatchRagJob()`
- `updated()` メソッド内の条件分岐

**技術的詳細**:
- `dispatchRagJob()` メソッドの分岐ロジックは維持
- sync/async判定は引き続き機能
- `Queue::fake()` への対応も維持

#### タスク 1.3: 他のジョブクラスの影響調査

**調査対象**:
以下のジョブも `SerializesModels` を使用しているが、tenancy関連の影響を確認:

```
- app/Jobs/Ledger/ExportJob.php
- app/Jobs/Ledger/ProcessAttachedFile.php
- app/Jobs/Ledger/GenerateThumbnail.php
- app/Jobs/Ledger/AttachedFileOcrJob.php
- app/Jobs/Ledger/OcrAndOptimizeFile.php
- app/Jobs/Ledger/ProcessVlmExtraction.php
```

**判断基準**:
- ✅ **同時に修正が必要**: Ledger作成/更新時に自動ディスパッチされるジョブ
- ⚠️ **要検証**: 手動ディスパッチまたは単発実行のジョブ
- ❌ **修正不要**: Tenancy非依存のジョブ

**優先順位**:
1. **高**: `ProcessAttachedFile`, `ProcessVlmExtraction` （自動ディスパッチ）
2. **中**: `AttachedFileOcrJob`, `GenerateThumbnail` （間接的な影響）
3. **低**: `ExportJob`, `OcrAndOptimizeFile` （手動実行）

---

### Phase 2: テスト修正（見積: 3-4時間）

#### タスク 2.1: 既存テストの影響調査

**影響を受けるテストファイル**:
```
tests/Feature/Jobs/ProcessLedgerForRagJobTest.php      (11箇所)
tests/Feature/Observers/LedgerObserverTest.php         (8箇所)
tests/Feature/Rag/VlmRagIntegrationTest.php            (3箇所)
tests/Feature/RagSearchServiceTest.php                 (1箇所)
tests/Feature/RagPerformanceTest.php                   (3箇所)
tests/Feature/Console/FinalizeAttachedFileProcessingTest.php (1箇所)
```

**合計影響箇所**: 約27箇所

#### タスク 2.2: テストパターンの修正

**パターン1: 直接実行テスト**
```php
// Before
$job = new ProcessLedgerForRagJob($ledger);
$job->handle($embeddingServiceMock);

// After
$job = new ProcessLedgerForRagJob($ledger->id);
$job->handle($embeddingServiceMock);
```

**影響箇所**: `ProcessLedgerForRagJobTest.php` (10箇所), `RagSearchServiceTest.php` (1箇所), `RagPerformanceTest.php` (3箇所)

**パターン2: Queue::fake() でのアサーション**
```php
// Before
Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
    return $job->getLedger()->id === $ledger->id;
});

// After
Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
    return $job->ledgerId === $ledger->id;
});
```

**影響箇所**: `LedgerObserverTest.php` (2箇所), `VlmRagIntegrationTest.php` (2箇所)

**パターン3: Bus::fake() でのアサーション**
```php
// Before
Bus::assertDispatched(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
    return $job->getLedger()->id === $ledger->id;
});

// After
Bus::assertDispatched(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
    return $job->ledgerId === $ledger->id;
});
```

**影響箇所**: `VlmRagIntegrationTest.php` (1箇所), `FinalizeAttachedFileProcessingTest.php` (1箇所)

#### タスク 2.3: 新規テストの追加

**目的**: Tenancyコンテキストの正しい動作を保証

**新規テストケース**:
```php
/** @test */
public function job_processes_ledger_in_correct_tenant_context()
{
    config(['rag.enabled' => true]);
    Queue::fake();
    
    // テナント作成と初期化
    $tenant = Tenant::create(['id' => 'test-'.uniqid()]);
    tenancy()->initialize($tenant);
    
    // Ledger作成
    $ledger = Ledger::factory()->create();
    
    // Jobがディスパッチされたことを確認
    Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
        return $job->ledgerId === $ledger->id;
    });
    
    // 実際にJobを実行してtenancyが維持されることを確認
    $job = new ProcessLedgerForRagJob($ledger->id);
    $embeddingService = $this->mock(EmbeddingService::class);
    $embeddingService->shouldReceive('embed')->andReturn([[0.1, 0.2, ...]]);
    
    $job->handle($embeddingService);
    
    // チャンクが作成されたことを確認
    $this->assertDatabaseHas('ledger_chunks', [
        'ledger_id' => $ledger->id,
    ]);
}

/** @test */
public function job_logs_warning_when_ledger_not_found()
{
    config(['rag.enabled' => true]);
    Log::spy();
    
    $job = new ProcessLedgerForRagJob(99999); // 存在しないID
    $job->handle(app(EmbeddingService::class));
    
    Log::shouldHaveReceived('warning')
        ->with('Ledger not found in job', ['ledger_id' => 99999]);
}
```

**追加先**: `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`

---

### Phase 3: 統合テスト（見積: 1-2時間）

#### タスク 3.1: ローカル環境での検証

**手順**:
1. 既存テストスイートの実行
   ```bash
   ./vendor/bin/sail test tests/Feature/Jobs/ProcessLedgerForRagJobTest.php
   ./vendor/bin/sail test tests/Feature/Observers/LedgerObserverTest.php
   ./vendor/bin/sail test tests/Feature/Rag/
   ```

2. 新規テストの実行
   ```bash
   ./vendor/bin/sail test --filter=job_processes_ledger_in_correct_tenant_context
   ```

3. 全テストスイート実行
   ```bash
   ./vendor/bin/sail test
   ```

#### タスク 3.2: 非同期キュー動作確認

**目的**: 本番環境と同等の条件でtenancyが正しく動作することを確認

**手順**:
1. キュー接続を `database` に変更
   ```bash
   # .env
   QUEUE_CONNECTION=database
   ```

2. キューワーカー起動
   ```bash
   ./vendor/bin/sail artisan queue:work --once
   ```

3. Ledger作成とジョブ実行確認
   ```bash
   ./vendor/bin/sail artisan tinker
   # Ledger::factory()->create();
   # DB::table('jobs')->count(); // ジョブが追加されたか確認
   # exit
   
   ./vendor/bin/sail artisan queue:work --once
   # ジョブが正常に処理されたか確認
   ```

4. tenant_id の確認
   ```bash
   ./vendor/bin/sail artisan tinker
   # DB::table('ledger_chunks')->latest()->first();
   # Ledger::latest()->first()->tenant_id;
   ```

#### タスク 3.3: パフォーマンス影響調査

**目的**: モデル渡しからID渡しへの変更による性能影響を測定

**測定項目**:
- ジョブディスパッチ時間
- ジョブ実行時間
- メモリ使用量

**ベンチマーク**:
```bash
# Before（Option C実装状態）
./vendor/bin/sail artisan rag:benchmark --ledgers=10 --content-size=2000 --sync

# After（Option B実装後）
./vendor/bin/sail artisan rag:benchmark --ledgers=10 --content-size=2000 --sync
```

**期待される結果**:
- ディスパッチ時間: 若干改善（モデルシリアライズ不要）
- 実行時間: 微増（Ledger再取得のクエリ1回分）
- メモリ使用量: 改善（シリアライズデータ削減）

---

### Phase 4: ドキュメント整備（見積: 1時間）

#### タスク 4.1: 実装完了報告書作成

**作成ファイル**: `docs/work/architecture/multi-tenancy/2025-11-09_option-b-implementation-report.md`

**記載内容**:
- 実装の背景と目的
- 変更内容の詳細
- テスト結果
- パフォーマンス測定結果
- 発生した問題と解決策
- 今後の保守に関する推奨事項

#### タスク 4.2: 開発ガイドライン更新

**更新ファイル**: `.github/copilot-instructions.md`

**追加内容**:
```markdown
### キュージョブとTenancy
- ✅ **推奨**: モデルIDのみをジョブに渡す
- ❌ **非推奨**: `SerializesModels` + モデルインスタンス渡し
- ✅ Job内で `Ledger::find($this->ledgerId)` を実行
- ✅ `QueueTenancyBootstrapper` が自動的にtenancyを初期化
```

---

## 影響範囲分析

### 変更対象ファイル

| カテゴリ | ファイル数 | 影響度 |
|---------|----------|--------|
| **Jobクラス** | 1 (ProcessLedgerForRagJob) | 高 |
| **Observer** | 1 (LedgerObserver) | 高 |
| **テスト** | 6 | 中 |
| **ドキュメント** | 2 | 低 |
| **合計** | 10 | - |

### リスク評価

#### 高リスク項目
1. **テスト修正漏れ**
   - 影響: 一部テストが失敗し続ける
   - 対策: 全テストファイルの体系的な検索と修正リストの作成

2. **他のジョブクラスでの同様の問題**
   - 影響: 別の箇所でtenancy問題が発生
   - 対策: Phase 1.3 での影響調査を徹底

#### 中リスク項目
1. **パフォーマンスの劣化**
   - 影響: 処理時間の増加
   - 対策: Phase 3.3 でベンチマーク測定
   - 許容範囲: +10%以内

2. **本番環境での予期しない動作**
   - 影響: データ不整合
   - 対策: ステージング環境での十分な検証期間

#### 低リスク項目
1. **ドキュメントの不足**
   - 影響: 将来の保守性低下
   - 対策: Phase 4 での詳細な文書化

---

## ロールバック計画

### ロールバック判断基準

以下のいずれかが発生した場合、Option C実装への即座のロールバックを推奨:
- ✗ テスト通過率が95%未満
- ✗ パフォーマンスが20%以上劣化
- ✗ 本番環境でデータ不整合が発生

### ロールバック手順

1. **Gitブランチの切り戻し**
   ```bash
   git checkout feature/rag-phase1-planning
   git revert <option-b-commit-hash>
   ```

2. **テスト実行**
   ```bash
   ./vendor/bin/sail test
   ```

3. **デプロイ**
   ```bash
   # 通常のデプロイ手順に従う
   ```

### データ整合性の確認

```sql
-- tenant_idが空のLedgerがないか確認
SELECT COUNT(*) FROM ledgers WHERE tenant_id IS NULL OR tenant_id = '';

-- 孤立したchunkがないか確認
SELECT COUNT(*) FROM ledger_chunks lc
LEFT JOIN ledgers l ON lc.ledger_id = l.id
WHERE l.id IS NULL;
```

---

## スケジュール（推奨）

### Week 1: Phase 1 + Phase 2
- **Day 1-2**: コア実装（Task 1.1 - 1.3）
- **Day 3-4**: テスト修正（Task 2.1 - 2.2）
- **Day 5**: 新規テスト追加（Task 2.3）

### Week 2: Phase 3 + Phase 4
- **Day 1**: ローカル環境検証（Task 3.1）
- **Day 2**: 非同期キュー動作確認（Task 3.2）
- **Day 3**: パフォーマンス測定（Task 3.3）
- **Day 4**: ドキュメント整備（Task 4.1 - 4.2）
- **Day 5**: レビューと最終調整

### Week 3: ステージング環境検証
- **Day 1-5**: ステージング環境での動作確認と負荷テスト

### Week 4: 本番デプロイ
- **Day 1**: 本番デプロイ準備
- **Day 2**: 本番デプロイ実施
- **Day 3-5**: 監視とトラブルシューティング

---

## 技術的な詳細

### QueueTenancyBootstrapper の動作

Stancl Tenancy の `QueueTenancyBootstrapper` は以下の処理を自動的に実行:

1. **ディスパッチ時**:
   ```php
   // Jobペイロードに tenant_id を自動追加
   {
     "job": "App\\Jobs\\ProcessLedgerForRagJob",
     "data": {
       "ledgerId": 123,
       "tenant_id": "tenant-uuid" // 自動追加
     }
   }
   ```

2. **実行時**:
   ```php
   // Job実行前に自動的にtenancyを初期化
   tenancy()->initialize($job->data['tenant_id']);
   
   // この時点で以下が true になる
   tenancy()->initialized === true
   ```

3. **完了時**:
   ```php
   // Job完了後に自動的にtenancyをクリア
   tenancy()->end();
   ```

### SerializesModels の問題点（技術的詳細）

**シリアライズ時の処理**:
```php
// SerializesModels::__serialize()
protected function __serialize()
{
    $properties = (new ReflectionClass($this))->getProperties();
    
    foreach ($properties as $property) {
        if ($property->getValue($this) instanceof Model) {
            // モデルを ModelIdentifier に変換
            // この際に DB接続情報がクリアされる
            $values[$property->getName()] = new ModelIdentifier(
                get_class($model),
                $model->getKey(),
                $model->getConnectionName() // ← ここでtenancy情報が失われる
            );
        }
    }
}
```

**デシリアライズ時の処理**:
```php
// ModelIdentifier::restore()
public function restore()
{
    return (new $this->class)->setConnection($this->connection)
                              ->find($this->id);
}
```

**問題**: 
- `$this->connection` には `mysql` が設定される
- しかし、実際には tenant専用のDBに接続すべき
- `tenancy()->initialized === false` の状態でクエリが実行される

### Option B での解決

```php
public function handle(EmbeddingService $embeddingService): void
{
    // QueueTenancyBootstrapperが既にtenancyを初期化済み
    // tenancy()->initialized === true
    
    $ledger = Ledger::find($this->ledgerId); // ← 正しいDB接続で取得
}
```

---

## チェックリスト

### 実装前
- [ ] 現在のブランチのバックアップ作成
- [ ] Option C実装の動作確認（ベースライン）
- [ ] 影響を受けるファイルのリスト作成
- [ ] レビュワーの確保

### Phase 1: コア実装
- [ ] ProcessLedgerForRagJob の修正完了
- [ ] LedgerObserver の修正完了
- [ ] 他のジョブクラスの影響調査完了
- [ ] コンパイルエラーがないことを確認

### Phase 2: テスト修正
- [ ] 全テストファイルの修正箇所リスト作成
- [ ] ProcessLedgerForRagJobTest 修正完了
- [ ] LedgerObserverTest 修正完了
- [ ] VlmRagIntegrationTest 修正完了
- [ ] RagSearchServiceTest 修正完了
- [ ] RagPerformanceTest 修正完了
- [ ] FinalizeAttachedFileProcessingTest 修正完了
- [ ] 新規テストケース追加完了

### Phase 3: 統合テスト
- [ ] 全テストが通過（100%）
- [ ] 非同期キューでの動作確認完了
- [ ] パフォーマンスベンチマーク完了
- [ ] tenant_id の正常性確認完了

### Phase 4: ドキュメント整備
- [ ] 実装完了報告書作成
- [ ] 開発ガイドライン更新
- [ ] README.md 更新（必要に応じて）

### デプロイ前
- [ ] コードレビュー完了
- [ ] ステージング環境での検証完了
- [ ] 本番デプロイ手順書作成
- [ ] ロールバック手順の確認
- [ ] 監視設定の確認

---

## 参考資料

### 公式ドキュメント
- [Stancl Tenancy - Queues](https://tenancyforlaravel.com/docs/v3/queues/)
- [Stancl Tenancy - Queue Tenancy Bootstrapper](https://deepwiki.com/archtechx/tenancy/5.4-queue-tenancy)
- [Laravel Queues - SerializesModels](https://laravel.com/docs/11.x/queues#class-structure)

### 技術記事
- [Understanding Laravel's SerializesModels](https://ryanc.co/posts/understanding-laravels-serializesmodels)
- [Efficiently Dispatching Jobs with Models in Laravel](https://sjorso.com/efficiently-dispatching-jobs-with-models-in-laravel)
- [Improving Laravel's Queue Performance](https://dev.to/lukeskw/improving-laravels-queue-performance-421j)

### コミュニティディスカッション
- [GitHub - Tenancy Issue #181](https://github.com/tenancy/tenancy/issues/181)
- [Stack Overflow - SerializesModels & Relations](https://stackoverflow.com/questions/58456283)
- [Laracasts - Tenant-Aware Jobs](https://laracasts.com/discuss/channels/laravel/help-needed-with-tenant-aware-jobs-in-stancltenancy-multi-database-setup)

### プロジェクト内文書
- [tenancy-issue-resolution.md](./tenancy-issue-resolution.md) - 問題の発見と一時解決
- [tenancy-queue-implementation-plan.md](./tenancy-queue-implementation-plan.md) - 詳細調査結果
- [tenancy-queue-safe-implementation.md](./tenancy-queue-safe-implementation.md) - 安全な実装方針

---

## 結論

Option B は**公式ベストプラクティスに完全準拠した根本解決**であり、本番環境での長期的な安定性を確保するために推奨されます。

### 実装の優先順位
1. **今すぐ**: Option C で運用継続（テスト環境では問題なし）
2. **今週〜来週**: Option B の実装とテスト
3. **来月**: 本番環境へのデプロイ

### 期待される効果
- ✅ Tenancyコンテキストの完全な保証
- ✅ 非同期キューでの安定動作
- ✅ 保守性とコード品質の向上
- ✅ 将来の拡張性確保

**次のステップ**: Phase 1のコア実装から着手し、段階的にOption Bへの移行を進めることを推奨します。
