# ProcessLedgerForRagJob 修正実装計画（後方互換性重視版）

## 現状分析

### 既存のテスト依存
以下のテストが現在の実装に依存：

1. **ProcessLedgerForRagJobTest** (11テスト)
   - `new ProcessLedgerForRagJob($ledger)` で直接インスタンス化
   - Jobを同期実行してマークダウン生成やチャンク作成をテスト

2. **LedgerObserverTest** (7テスト)
   - `Queue::fake()` でJobディスパッチをテスト
   - `$job->getLedger()->id` でLedgerモデルを取得

3. **RagSearchServiceTest, VlmRagIntegrationTest, RagPerformanceTest**
   - 同様に直接インスタンス化

### 問題の本質（再確認）
- `SerializesModels` + モデルの直接渡しが tenancy コンテキストを終了させる
- **しかし、これは同期リクエスト内で複数Ledgerを作成する場合のみ発生**
- 既存のテストは問題なく動作している（各テストで1つずつ作成）

## 提案：段階的で安全なアプローチ

### Option A: コンストラクタオーバーロード（推奨）

**目的**: 既存のテストを壊さず、新しい方法も追加

```php
class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    private ?Ledger $ledger = null;
    private ?int $ledgerId = null;
    
    /**
     * 新しいコンストラクタ：IDまたはモデルを受け入れる
     */
    public function __construct(Ledger|int $ledgerOrId)
    {
        if ($ledgerOrId instanceof Ledger) {
            // 既存のテスト用：モデルを直接受け取る
            $this->ledger = $ledgerOrId;
            $this->ledgerId = $ledgerOrId->id;
        } else {
            // 新しい方法：IDのみ受け取る
            $this->ledgerId = $ledgerOrId;
        }
    }
    
    public function getLedger(): Ledger
    {
        // キャッシュされていればそれを返す
        if ($this->ledger) {
            return $this->ledger;
        }
        
        // なければDBから取得（tenancyコンテキスト内）
        $this->ledger = Ledger::findOrFail($this->ledgerId);
        return $this->ledger;
    }
    
    public function handle(EmbeddingService $embeddingService): void
    {
        if (!config('rag.enabled', false)) {
            return;
        }
        
        // getLedger()を使用（統一）
        $ledger = $this->getLedger();
        
        // 以降は既存ロジック
        // ...
    }
}
```

**LedgerObserverの変更**:
```php
public function created(Ledger $ledger): void
{
    if (config('rag.enabled', false)) {
        // IDを渡す（新しい方法）
        ProcessLedgerForRagJob::dispatch($ledger->id);
    }
}
```

**メリット**:
- ✅ 既存のテストコードは変更不要
- ✅ Observer経由の実行では tenancy 問題を解決
- ✅ 段階的な移行が可能

**デメリット**:
- ⚠️ 2つの使い方が混在（将来的に統一が必要）

---

### Option B: SerializesModels削除 + テスト修正（根本的）

**目的**: 公式ベストプラクティスに完全準拠

```php
class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    // SerializesModels を削除
    
    public function __construct(
        public int $ledgerId
    ) {}
    
    public function handle(EmbeddingService $embeddingService): void
    {
        $ledger = Ledger::findOrFail($this->ledgerId);
        // ...
    }
}
```

**全テストを修正**:
```php
// Before
$job = new ProcessLedgerForRagJob($ledger);

// After
$job = new ProcessLedgerForRagJob($ledger->id);
```

**メリット**:
- ✅ 公式推奨に完全準拠
- ✅ コードがシンプル
- ✅ 将来的な問題を回避

**デメリット**:
- ❌ 20箇所以上のテスト修正が必要
- ❌ 修正漏れのリスク
- ❌ 時間がかかる

---

### Option C: Observerのみ修正（最小限）

**目的**: 問題が発生している箇所だけ修正

```php
// LedgerObserver.php
public function created(Ledger $ledger): void
{
    if (config('rag.enabled', false)) {
        // キュー接続がsyncの場合は直接実行
        if (config('queue.default') === 'sync') {
            (new ProcessLedgerForRagJob($ledger))->handle(app(EmbeddingService::class));
        } else {
            // 非同期の場合は通常通りdispatch
            ProcessLedgerForRagJob::dispatch($ledger);
        }
    }
}
```

**メリット**:
- ✅ テスト修正不要
- ✅ 最小限の変更
- ✅ 既存機能への影響ゼロ

**デメリット**:
- ❌ 根本的な解決ではない
- ❌ sync以外の環境でも問題が起きる可能性

---

## 推奨実装プラン

### Phase 1: 即座の対応（今日）
**Option C を実装** - 最小限の変更でテストを通す

1. `LedgerObserver`の`created()`と`updated()`を修正
2. 既存テストを実行して影響がないことを確認
3. `SearchApiTest`を実行

### Phase 2: 段階的な改善（今週〜来週）
**Option A を実装** - 後方互換性を保ちつつ改善

1. `ProcessLedgerForRagJob`のコンストラクタを変更（Union型対応）
2. `getLedger()`メソッドを追加
3. Observer を ID渡しに変更
4. 新しいテストを追加（IDを渡すパターン）
5. 既存テストは触らない（非推奨警告を追加することは可能）

### Phase 3: 完全移行（将来）
**Option B に移行** - 完全にベストプラクティス準拠

1. 全テストを一括修正
2. `SerializesModels`を削除
3. モデル渡しのサポートを廃止

---

## 実装詳細（Phase 1）

### 変更ファイル
1. `app/Observers/LedgerObserver.php`

### 実装コード

```php
<?php

namespace App\Observers;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;

class LedgerObserver
{
    /**
     * Handle the Ledger "created" event.
     */
    public function created(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            $this->dispatchRagJob($ledger);
        }
    }

    /**
     * Handle the Ledger "updated" event.
     */
    public function updated(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            if ($ledger->wasChanged(['content', 'content_attached'])) {
                $this->dispatchRagJob($ledger);
            }
        }
    }

    /**
     * Dispatch RAG job with tenancy awareness
     * 
     * syncキューの場合は同期実行してtenancyコンテキストを維持
     */
    private function dispatchRagJob(Ledger $ledger): void
    {
        if (config('queue.default') === 'sync') {
            // 同期実行の場合は直接実行（tenancyコンテキストを維持）
            (new ProcessLedgerForRagJob($ledger))->handle(app(EmbeddingService::class));
        } else {
            // 非同期の場合は通常通りdispatch
            // QueueTenancyBootstrapperがtenancyを処理
            ProcessLedgerForRagJob::dispatch($ledger);
        }
    }

    /**
     * Handle the Ledger "deleted" event.
     */
    public function deleted(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->delete();
        }
    }

    /**
     * Handle the Ledger "restored" event.
     */
    public function restored(Ledger $ledger): void
    {
        //
    }

    /**
     * Handle the Ledger "force deleted" event.
     */
    public function forceDeleted(Ledger $ledger): void
    {
        //
    }
}
```

### テスト戦略

1. **既存テストは変更なし**
   - すべて同期実行なので`dispatchRagJob()`の同期パスを通る
   
2. **SearchApiTestの修正**
   - `config(['rag.enabled' => false])`を`setUp()`に追加
   - または、`queue.default`が`sync`なので自動的に同期実行される

3. **新しいテスト追加（オプション）**
   - 非同期キューでの動作テスト

---

## リスク評価

### Phase 1（Option C）
- **リスク**: 極めて低
- **影響範囲**: `LedgerObserver`のみ
- **既存機能**: 影響なし（既存テストすべて通る）
- **工数**: 30分

### 検証手順
1. 変更を適用
2. RAG関連のテストを実行
   ```bash
   ./vendor/bin/sail test tests/Feature/Jobs/ProcessLedgerForRagJobTest.php
   ./vendor/bin/sail test tests/Feature/Observers/LedgerObserverTest.php
   ./vendor/bin/sail test tests/Feature/Api/SearchApiTest.php
   ```
3. すべて通ることを確認

---

## 結論

**即座の実装: Phase 1（Option C）を推奨**

理由:
1. 既存のテストへの影響がゼロ
2. 問題を確実に解決
3. 30分で実装可能
4. 将来の改善の余地を残す

より根本的な解決は、RAG機能が安定した後に段階的に実施。
