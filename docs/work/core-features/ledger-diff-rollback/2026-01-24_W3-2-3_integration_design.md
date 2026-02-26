# W3-2.3 ロールバック後連携設計

**最終更新:** 2026-01-24  
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`  
**ステータス:** Draft（要PMレビュー）  
**管理場所:** `docs/work/core-features/ledger-diff-rollback/`

---

## 1. 目的

ロールバック処理が完了した直後のユーザー体験（UX）を定義する。適切な画面への遷移、復元内容の視覚的確認、および処理結果の通知をスムーズに統合する。

---

## 2. 画面遷移とステート更新

### 2.1 遷移フロー

1. **ロールバック実行**: `RollbackConfirmModal` が `RollbackService::execute` を呼び出す。
2. **イベント発行**: 完了後、モーダルから全画面に向けて `ledger.rollback.completed` イベントを発行。
3. **親画面 (`Show.php`) の反応**:
   - イベントを捕捉し、以下の状態に更新する。
     - `$selectedTab = 'details'` (基本情報タブへ遷移)
     - `$showChanges = true` (差分表示を有効化)
     - `$targetDiffId = {ロールバック前の最新DiffID}` (直前との比較を表示)
   - 台帳データを再ロード (`$this->mount()`)。

### 2.2 技術実装

**RollbackConfirmModal.php**:
```php
public function execute(): void
{
    $result = $this->rollbackService->execute(...);
    
    $this->dispatch('ledger.rollback.completed', 
        ledgerId: $result['ledger']->id,
        previousDiffId: $this->ledger->latest_diff_id,
        targetComponentId: 'ledger-show-' . $result['ledger']->id
    );
    
    $this->closeModal();
}
```

**Show.php**:
```php
#[On('ledger.rollback.completed')]
public function handleRollbackCompleted(int $ledgerId, int $previousDiffId, string $targetComponentId): void
{
    // 対象コンポーネント確認で誤作動防止
    $expectedComponentId = 'ledger-show-' . $this->ledgerRecord->id;
    if ($targetComponentId !== $expectedComponentId) {
        return;
    }

    $this->selectedTab = 'details';
    $this->showChanges = true;
    $this->targetDiffId = $previousDiffId;
    
    $this->mount($ledgerId);
    
    // ブラウザ履歴管理
    $this->js("
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('tab', 'details');
        newUrl.searchParams.set('showChanges', 'true'); 
        newUrl.searchParams.set('targetDiffId', '{$previousDiffId}');
        window.history.pushState({}, '', newUrl);
    ");

    $this->success(__('ledger.rollback_success', ['version' => $this->ledgerRecord->version]));
}
```

---

## 3. 即時差分表示との連携

### 3.1 視覚的フィードバック

基本情報タブへ遷移した際、既存の `LedgerDiffViewer` が自動的に起動し、以下の比較が行われる。

- **現在 (左/元)**: 新しく作成されたロールバック後のバージョン（内容は過去のコピー）
- **比較対象 (右/先)**: ロールバック実行直前のバージョン（誤更新されていた内容）

---

## 4. 通知とエラーハンドリング

### 4.1 完了通知 (Toast)

MaryUI の `Toast` トレイトを使用し、画面上部に通知を表示する。

- **メッセージ**: 「ロールバックが完了しました。Ver. {N} として復元されました。」
- **タイプ**: `success`

### 4.2 エラーハンドリング

処理中に例外（権限不足、ロック済み等）が発生した場合は、モーダルを閉じずに `x-mary-alert` 等でメッセージを表示する。

---

## 5. 考慮事項

### 5.1 ブラウザの履歴管理

ロールバック実行は重要なアクションのため、`history.pushState` により履歴を適切に管理し、自然な戻る動作を実現する。

### 5.2 検索インデックスの反映ラグ

非同期ジョブの完了には数秒のラグがあるが、詳細画面への反映（DB参照）には影響しない。

---

## 6. 懸念事項と対応策

### 6.1 非同期ジョブの失敗時リカバリー

**懸念事項**: スコア再計算や検索インデックス更新ジョブが失敗した場合のリカバリー戦略が不明確。

**対応策**: ジョブチェーンと失敗時のフォールバック処理を実装。

```php
// Show.php の handleRollback メソッド内
#[On('ledger-rolled-back')]
public function handleRollback(int $ledgerId, int $previousDiffId): void
{
    if ($this->ledgerRecord->id !== $ledgerId) return;

    $this->selectedTab = 'details';
    $this->showChanges = true;
    $this->targetDiffId = $previousDiffId;
    
    $this->mount($ledgerId);
    
    // ジョブ失敗の監視と代替処理
    $this->scheduleJobFailureCheck($ledgerId);
    
    $this->success(__('ledger.rollback_success', ['version' => $this->ledgerRecord->version]));
}

private function scheduleJobFailureCheck(int $ledgerId): void
{
    // 5分後にジョブ完了状況をチェック
    CheckRollbackJobsCompletion::dispatch($ledgerId)
        ->delay(now()->addMinutes(5));
}
```

**理由**: スコアリングと検索インデックスの不整合は、ユーザー体験に直接影響するため。

### 6.2 Livewireイベントの競合回避

**懸念事項**: 複数のLivewireコンポーネント間でイベントが競合し、意図しない動作が発生する可能性。

**対応策**: イベント名の名前空間化と対象コンポーネントの明確化。

```php
// RollbackConfirmModal.php
public function execute(): void
{
    // ...existing code...
    
    // 名前空間化されたイベント名で競合回避
    $this->dispatch('ledger.rollback.completed', 
        ledgerId: $result['ledger']->id,
        previousDiffId: $this->ledger->latest_diff_id,
        targetComponentId: 'ledger-show-' . $result['ledger']->id
    );
}

// Show.php  
#[On('ledger.rollback.completed')]
public function handleRollbackCompleted(int $ledgerId, int $previousDiffId, string $targetComponentId): void
{
    // 対象コンポーネント確認で誤作動防止
    $expectedComponentId = 'ledger-show-' . $this->ledgerRecord->id;
    if ($targetComponentId !== $expectedComponentId) {
        return;
    }
    
    // ...existing code...
}
```

**理由**: LedgerLeapでは複数の台帳詳細画面が同時に開かれる可能性があるため。

### 6.3 ブラウザ履歴との整合性

**懸念事項**: タブ切り替えが`#[Url]`属性で管理されるため、ロールバック後の戻るボタン動作が直感的でない。

**対応策**: ロールバック実行時にブラウザ履歴を適切に管理。

```php
public function handleRollbackCompleted(int $ledgerId, int $previousDiffId, string $targetComponentId): void
{
    // ...existing code...
    
    // ブラウザ履歴への新エントリ追加で自然な戻る動作を実現
    $this->js("
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('tab', 'details');
        newUrl.searchParams.set('showChanges', 'true'); 
        newUrl.searchParams.set('targetDiffId', '{$previousDiffId}');
        newUrl.searchParams.set('rollbackCompleted', 'true');
        window.history.pushState({}, '', newUrl);
    ");
}
```

**理由**: ロールバック実行は重要なアクションのため、ブラウザ履歴に適切に記録すべき。

### 6.4 差分表示の動的更新

**懸念事項**: ロールバック後の即時差分表示で、`LedgerDiffViewer`コンポーネントが正しく更新されない可能性。

**対応策**: 差分表示コンポーネントの強制再レンダリング機能を実装。

```php
public function handleRollbackCompleted(int $ledgerId, int $previousDiffId, string $targetComponentId): void
{
    // ...existing code...
    
    // LedgerDiffViewerコンポーネントの更新を明示的に指示
    $this->dispatch('ledger-diff-viewer.refresh', 
        currentDiffId: $this->ledgerRecord->latest_diff_id,
        targetDiffId: $previousDiffId,
        forceReload: true
    );
}
```

**理由**: Phase 1で実装された差分表示機能の既存APIとの整合性を保つため。

### 6.5 Mroonga全文検索の遅延対応

**懸念事項**: Mroonga全文検索のインデックス更新が遅延し、検索結果に反映されないタイミングが存在する。

**対応策**: 検索結果の整合性を保つための猶予期間設定。

```php
// UpdateFullTextIndexJob.php（新規作成）
class UpdateFullTextIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;
    public $backoff = [30, 60, 120]; // 30秒、1分、2分後にリトライ
    
    public function __construct(private int $ledgerId) {}
    
    public function handle(): void
    {
        // Mroonga特有のインデックス更新処理
        DB::statement('OPTIMIZE TABLE ledgers');
        
        // 更新完了の確認（必要に応じて）
        $ledger = Ledger::find($this->ledgerId);
        if ($ledger) {
            Cache::put("ledger_index_updated_{$this->ledgerId}", true, now()->addMinutes(10));
        }
    }
}
```

**理由**: Mroongaの全文検索インデックスは即座に反映されない特性があるため。

---

**PM承認後、詳細設計（W4-x）および実装フェーズに進みます。**
