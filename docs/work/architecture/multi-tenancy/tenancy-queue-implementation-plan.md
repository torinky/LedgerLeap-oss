# Tenancy対応Job実装計画

## 調査日時
2025-11-09

## 問題の本質

### 発見された問題
`ProcessLedgerForRagJob`がLedger作成時にディスパッチされると、`SerializesModels`トレイトがモデルをシリアライズする際に**tenancyコンテキストが終了**し、2つ目以降のLedgerが正しいtenant_idなしで作成される。

### 根本原因
1. `LedgerObserver::created()`が`ProcessLedgerForRagJob::dispatch($ledger)`を呼び出し
2. `SerializesModels`トレイトがLedgerモデルをシリアライズ
3. シリアライズ時にtenancyコンテキストが終了（これはLaravelの仕様）
4. 同一リクエスト内で作成される2つ目以降のLedgerは`tenancy()->initialized === false`状態で作成される
5. `BelongsToTenant`トレイトがtenant_idを設定せず、結果として空のtenant_idでDBに保存される

## 現在の設定状況

### ✅ 既に設定済み
- `QueueTenancyBootstrapper`は有効（`config/tenancy.php`の42行目）
- キュー接続は`sync`（開発環境）、本番では`redis`または`database`を想定

### ❌ 問題のある実装
```php
// app/Jobs/ProcessLedgerForRagJob.php
class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels; // ← SerializesModels が問題
    
    public function __construct(
        private Ledger $ledger  // ← モデル全体を渡している
    ) {}
}
```

## ベストプラクティス（公式推奨）

### Stancl Tenancy公式ドキュメントより

1. **QueueTenancyBootstrapper を使用**
   - Jobディスパッチ時に自動的にtenant_idをJobペイロードに保存
   - Job実行時に自動的にテナントコンテキストを復元
   - Job完了後に自動的にテナントコンテキストをクリア

2. **SerializesModels は慎重に使用**
   - モデルのシリアライズ時にtenancyコンテキストが失われる
   - Jobディスパッチは非同期なので、tenancyコンテキストの維持が重要

3. **推奨パターン：モデルIDのみを渡す**
   ```php
   // ✅ 良い例
   public function __construct(
       public int $ledgerId,
       public string $tenantId  // QueueTenancyBootstrapperが自動的に保存
   ) {}
   
   public function handle(): void
   {
       // QueueTenancyBootstrapperが自動的にtenancyを初期化
       $ledger = Ledger::find($this->ledgerId);
   }
   ```

4. **中央キューを使用**
   - キューは常にcentral接続を使用
   - テナントごとのキューは使わない

## 実装計画

### Phase 1: 即座の修正（テスト用）
**目的**: テストを通すための最小限の修正

**実装**:
```php
// tests/TestCase.php または tests/Feature/Api/SearchApiTest.php
protected function setUp(): void
{
    parent::setUp();
    
    // テスト環境ではRAGを無効化
    config(['rag.enabled' => false]);
}
```

**メリット**:
- 最小限の変更
- テストが即座に通る

**デメリット**:
- RAG機能自体がテストされない
- 本番環境の問題は解決しない

---

### Phase 2: Jobの正しい実装（推奨）
**目的**: 本番環境でもtenancyが正しく動作するようにする

#### Step 1: ProcessLedgerForRagJobの修正

```php
<?php

namespace App\Jobs;

use App\Models\Ledger;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
// SerializesModels を削除
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    // SerializesModelsトレイトを使わず、IDのみを保存
    
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $ledgerId  // モデルではなくIDを渡す
    ) {}
    
    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        if (!config('rag.enabled', false)) {
            return;
        }
        
        // QueueTenancyBootstrapperが自動的にtenancyを初期化済み
        // tenancy()->initialized === true
        
        $logChannel = config('rag.log_channel', 'stack');
        $startTime = microtime(true);
        
        // Jobハンドラ内でLedgerを取得（tenancyコンテキスト内）
        $ledger = Ledger::find($this->ledgerId);
        
        if (!$ledger) {
            Log::channel($logChannel)->warning('Ledger not found in job', [
                'ledger_id' => $this->ledgerId,
            ]);
            return;
        }
        
        Log::channel($logChannel)->info('Start chunking process for ledger', [
            'ledger_id' => $ledger->id,
            'tenant_id' => $ledger->tenant_id,
        ]);
        
        // 以下、既存のロジックをそのまま使用
        $this->updateContentAttachedWithVlmResult($ledger);
        // ... 残りの処理
    }
    
    // private メソッドの引数にLedgerを追加
    private function updateContentAttachedWithVlmResult(Ledger $ledger): void
    {
        // $this->ledger の代わりに $ledger を使用
        // ... 既存ロジック
    }
    
    private function buildMarkdownFromLedger(Ledger $ledger): string
    {
        // ... 既存ロジック
    }
}
```

#### Step 2: LedgerObserverの修正

```php
<?php

namespace App\Observers;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class LedgerObserver
{
    public function created(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            // モデルではなくIDを渡す
            ProcessLedgerForRagJob::dispatch($ledger->id);
        }
    }
    
    public function updated(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            if ($ledger->wasChanged(['content', 'content_attached'])) {
                // モデルではなくIDを渡す
                ProcessLedgerForRagJob::dispatch($ledger->id);
            }
        }
    }
    
    // deleted, restored, forceDeleted は変更なし
}
```

#### Step 3: キュー設定の確認

```php
// config/queue.php

'connections' => [
    // ... 他の接続
    
    'database' => [
        'driver' => 'database',
        'connection' => env('DB_CONNECTION', 'mysql'), // ← central接続を使用
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

**注意**: `'connection'`が明示的に設定されていない場合、デフォルトのDB接続を使用します。本プロジェクトでは単一DBなので問題ありません。

---

### Phase 3: テストの改善

#### テストでRAGを有効にしてtenancyをテスト

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessLedgerForRagJobTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_job_processes_ledger_in_correct_tenant_context()
    {
        // RAGを有効化
        config(['rag.enabled' => true]);
        
        // Fake queue to prevent actual execution
        Queue::fake();
        
        // テナント作成と初期化
        $tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid()]);
        tenancy()->initialize($tenant);
        
        // Ledger作成
        $ledger = Ledger::factory()->create();
        
        // Jobがディスパッチされたことを確認
        Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
            return $job->ledgerId === $ledger->id;
        });
        
        // 実際にJobを実行してtenancyが維持されることを確認
        $job = new ProcessLedgerForRagJob($ledger->id);
        $job->handle(app(\App\Services\EmbeddingService::class));
        
        // チャンクが作成されたことを確認
        $this->assertDatabaseHas('ledger_chunks', [
            'ledger_id' => $ledger->id,
        ]);
    }
}
```

---

## マイグレーション計画

### ステップ1: テスト環境で検証（低リスク）
1. Phase 2の実装を適用
2. 既存のテストを実行
3. 新しいテスト（Phase 3）を追加
4. すべてのテストが通ることを確認

### ステップ2: 開発環境で検証（中リスク）
1. `QUEUE_CONNECTION=database`に変更
2. キューワーカーを起動: `php artisan queue:work`
3. Ledgerを作成してJobが正しく実行されることを確認
4. tenant_idが正しく設定されることを確認

### ステップ3: 本番環境にデプロイ（要注意）
1. デプロイ前にメンテナンスモードを有効化
2. キューワーカーを停止
3. コードをデプロイ
4. キューワーカーを再起動
5. メンテナンスモードを解除
6. モニタリングを強化

---

## リスク評価

### Phase 1（テストでRAG無効化）
- **リスク**: 低
- **影響範囲**: テスト環境のみ
- **工数**: 1行の変更
- **推奨**: ✅ 即座に実施可能

### Phase 2（Job修正）
- **リスク**: 中
- **影響範囲**: RAG機能全体
- **工数**: 2-3時間
- **推奨**: ✅ 推奨（根本的な解決）

### Phase 3（テスト追加）
- **リスク**: 低
- **影響範囲**: テストのみ
- **工数**: 1-2時間
- **推奨**: ✅ Phase 2と合わせて実施

---

## 代替案の検討

### 代替案1: Observer内でtenancyを再初期化
```php
public function created(Ledger $ledger): void
{
    if (config('rag.enabled', false)) {
        $tenantId = $ledger->tenant_id;
        
        // Job内でtenancyを再初期化
        ProcessLedgerForRagJob::dispatch($ledger)
            ->afterResponse()  // レスポンス後に実行
            ->onQueue('default');
    }
}
```

**評価**: ❌ 非推奨
- `SerializesModels`問題は解決しない
- より複雑になる

### 代替案2: 同期実行（syncキュー）
```php
config(['queue.default' => 'sync']);
```

**評価**: ⚠️ 条件付きで可
- 開発環境では問題ない
- 本番環境ではパフォーマンス問題
- tenancy終了問題は解決しない

---

## 結論と推奨

### 即座の対応（今日中）
**Phase 1を実施**: テストでRAGを無効化
```php
config(['rag.enabled' => false]);
```

### 根本的な解決（今週中）
**Phase 2を実施**: Jobの正しい実装
- `SerializesModels`を削除
- モデルではなくIDを渡す
- Job内でLedgerを取得

### 品質保証（来週）
**Phase 3を実施**: tenancy対応のテストを追加

---

## 参考資料

- [Stancl Tenancy - Queues](https://tenancyforlaravel.com/docs/v3/queues/)
- [Laravel SerializesModels解説](https://ryanc.co/posts/understanding-laravels-serializesmodels)
- [Stancl Tenancy - QueueTenancyBootstrapper](https://deepwiki.com/archtechx/tenancy/5.4-queue-tenancy)

---

## チェックリスト

### Phase 1（即座）
- [ ] `SearchApiPoCTest`に`config(['rag.enabled' => false])`を追加
- [ ] `SearchApiTest`に同様の設定を追加
- [ ] すべてのテストが通ることを確認

### Phase 2（今週）
- [ ] `ProcessLedgerForRagJob`から`SerializesModels`を削除
- [ ] コンストラクタを`int $ledgerId`に変更
- [ ] `handle()`内でLedgerを取得
- [ ] プライベートメソッドにLedger引数を追加
- [ ] `LedgerObserver`を`$ledger->id`を渡すように修正
- [ ] ローカルでテスト実行

### Phase 3（来週）
- [ ] `ProcessLedgerForRagJobTest`を作成
- [ ] tenancyコンテキストのテストを追加
- [ ] CI/CDパイプラインでテスト実行

### デプロイ前
- [ ] すべてのテストがパス
- [ ] コードレビュー完了
- [ ] ステージング環境で検証
- [ ] ロールバック手順の確認
- [ ] モニタリング設定の確認
