# Cycle 2 WBS見直しレポート

**作成日:** 2026-01-10  
**対象:** Ledger Diff Rollback - Cycle 2 (Phase 1)  
**ステータス:** 計画見直し完了

## エグゼクティブサマリー

Cycle 2の検討開始前に、実装状態とWBSの整合性を確認しました。

**主な発見:**
- ✅ **任意2バージョン比較（W4-2.1）**: Cycle 1で既に実装済み
- ✅ **検索ハイライト（W4-2.2）**: SearchHelperを使用して実装済み
- ⚠️ **パフォーマンス測定（W4-2.3）**: 設定ファイルのみ、測定ロジック未実装

**推奨アクション:**
- WBSを更新済み（W3-2.1, W4-2.1, W4-2.2をDoneに）
- 次のステップとして **Option A（パフォーマンス測定を完成させる）** を推奨
- 所要時間: 約1-2日（設計2-3h + 実装4-6h + テスト3-4h + レポート1h）

詳細は本レポートの「7. 次のステップ」を参照してください。

---

## 1. 目的

Cycle 2の検討開始前に、現在の実装状態とWBSの計画を照合し、必要な調整を行う。

## 2. Cycle 2の計画内容（元WBS）

Cycle 2の目的は「比較機能を強化し、ユーザビリティを向上する」であり、以下の成果物が予定されていた：

- 任意2バージョン比較UI
- 検索ハイライト
- パフォーマンス測定機能

### 2.1 設計タスク（W3-2.x）

| タスクID | タスク名 | 内容 | 優先度 | ステータス（計画） |
|---------|---------|------|--------|------------------|
| W3-2.1 | 任意2バージョン比較UI設計 | 履歴テーブルから任意の2バージョンを選択して比較するUI。スライダーUIは単一バージョン表示のみのため、新規UIが必要。W2-1.1でPM承認。 | High | Not started |
| W3-2.2 | パフォーマンス測定機能設計 | FileInspectorの手法に合わせた測定機能の設計。環境変数、設定ファイル、ログ記録、統計分析。W2-1.4で定義。 | Medium | Not started |

### 2.2 実装タスク（W4-2.x）

| タスクID | タスク名 | 内容 | 優先度 | ステータス（計画） |
|---------|---------|------|--------|------------------|
| W4-2.1 | 任意2バージョン比較実装 | 履歴テーブルから任意の2バージョンを選択して比較するUIを実装。W3-2.1の設計を実装。 | High | Done |
| W4-2.2 | 検索ハイライト流用 | 基本情報タブの検索ハイライト実装を更新履歴タブに流用。W2-1.3でPM承認。 | High | Done |
| W4-2.3 | パフォーマンス測定機能実装 | 環境変数、設定ファイル、ログ記録、統計分析の実装。W3-2.2の設計を実装。 | Medium | Not started |

### 2.3 テストタスク（W5-2.x）

| タスクID | タスク名 | 内容 | 優先度 | ステータス（計画） |
|---------|---------|------|--------|------------------|
| W5-2.1 | Featureテスト（Cycle 2） | 任意2バージョン比較、検索ハイライトの動作検証。 | High | Not started |
| W5-2.2 | パフォーマンステスト（Cycle 2） | 任意2バージョン比較の応答時間（800ms以内）、検索ハイライトの応答時間（200ms以内）を測定。 | High | Not started |
| W5-2.3 | アクセシビリティテスト（Cycle 2） | キーボード操作（行間移動、選択、詳細表示）、フォーカス制御、スクリーンリーダー対応を検証。W2-1.4のアクセシビリティ要件を検証。 | Medium | Not started |

## 3. 実装状況の確認結果

### 3.1 任意2バージョン比較（W4-2.1）: ✅ **実装済み**

**確認内容:**
- `LedgerHistoryManager.php`の`toggleSelection()`メソッドで任意2バージョンの選択を実装
- 選択された2つのバージョンは`baseDiffId`（新しい方）と`targetDiffId`（古い方）に自動ソート
- UIでは選択状態を視覚的に表示（primary/errorの縦線バッジ）
- `LedgerDiffViewer`に`baseDiffId`と`targetDiffId`を渡して差分表示

**コード証跡:**
```php
// app/Livewire/Ledger/LedgerHistoryManager.php (L79-104)
public function toggleSelection(int $id): void
{
    if ($this->baseDiffId === $id) {
        $this->baseDiffId = null;
    } elseif ($this->targetDiffId === $id) {
        $this->targetDiffId = null;
    } else {
        // 新しく選択する場合
        if ($this->baseDiffId === null) {
            $this->baseDiffId = $id;
        } elseif ($this->targetDiffId === null) {
            $this->targetDiffId = $id;
        } else {
            // 両方埋まっている場合、targetDiffId を追い出して新しく選択
            $this->targetDiffId = $id;
        }
    }

    // ソート処理（常に大きい方を baseDiffId に）
    $ids = collect([$this->baseDiffId, $this->targetDiffId])->filter()->sortDesc()->values();
    $this->baseDiffId = $ids->get(0);
    $this->targetDiffId = $ids->get(1);

    $this->dispatch('versionsSelected', baseId: $this->baseDiffId, targetId: $this->targetDiffId);
}
```

**結論:** W3-2.1の設計タスクは暗黙的に完了しており、W4-2.1は**Done**に更新すべき。

### 3.2 検索ハイライト（W4-2.2）: ✅ **実装済み**

**確認内容:**
- `SearchHelper::highlight()`メソッドが実装済み
- `SearchHelper::extractKeywords()`でMroongaクエリからキーワード抽出
- 既に基本情報タブで使用中（`show.blade.php`でhighlightパラメータ渡し）
- 履歴タブにも`highlight`パラメータが渡されている

**コード証跡:**
```php
// app/Helpers/SearchHelper.php (L61-90)
public static function highlight(?string $text, array $keywords, string $class = 'bg-yellow-200 text-black px-0.5 rounded', bool $shouldEscape = true): string
{
    // ...実装内容...
}
```

```blade
{{-- resources/views/livewire/ledger/show.blade.php (L133) --}}
<livewire:ledger.ledger-history-manager :ledgerId="$ledgerRecord->id" :displayLevel="$displayLevel" :highlight="$highlight"
```

**結論:** W4-2.2は**Done**に更新すべき。

### 3.3 パフォーマンス測定機能（W3-2.2, W4-2.3）: ⚠️ **設定のみ実装、測定ロジック未実装**

**確認内容:**
- `config/ledgerleap.php`に`performance`設定セクションが存在
- 環境変数`PERFORMANCE_MONITORING_ENABLED`で有効化可能
- メトリクスの種類も定義済み（drawer_open, tab_switch, search, etc.）
- ただし、実際の測定ロジック（PerformanceMonitorクラス等）は**未実装**

**設定ファイル証跡:**
```php
// config/ledgerleap.php (L115-154)
'performance' => [
    'enabled' => env('PERFORMANCE_MONITORING_ENABLED', env('APP_ENV') === 'local'),
    'log_destination' => env('PERFORMANCE_LOG_DESTINATION', 'both'),
    'metrics' => [
        'drawer_open' => env('PERFORMANCE_METRIC_DRAWER_OPEN', true),
        'tab_switch' => env('PERFORMANCE_METRIC_TAB_SWITCH', true),
        // ...
    ],
],
```

**検索結果:**
- `class PerformanceMonitor`: 見つからず
- `performance_metric`: 使用箇所なし

**結論:** W3-2.2（設計）は**部分完了**（設定設計のみ）、W4-2.3（実装）は**Not started**が正確。

### 3.4 編集者詳細情報表示（W4-3.1）: ❌ **未実装**

**確認内容:**
- `ledger-history-manager.blade.php`でアバターと名前のみ表示
- クリック/ホバー時のポップオーバーや連絡先コピー機能は未実装
- W4完了レポートの「残存課題」セクションでも指摘されている

**コード証跡:**
```blade
{{-- resources/views/livewire/ledger/ledger-history-manager.blade.php (L56-62) --}}
<div class="flex items-center gap-2 text-xs">
    <div class="avatar placeholder">
        <div class="bg-neutral text-neutral-content rounded-full w-5 h-5">
            <span class="text-[8px]">{{ mb_substr($diff->modifier?->name ?? '?', 0, 1) }}</span>
        </div>
    </div>
    <span class="truncate">{{ $diff->modifier?->name }}</span>
</div>
```

**結論:** Cycle 3のタスクとして残っており、計画通り。

## 4. W4完了レポートで指摘された課題との整合性

W4完了レポートの「8. 残存課題とW2要件とのギャップ」セクションで以下が指摘されている：

1. **スナップショット表示モード（差分OFF）**: Cycle 3の対応範囲（showChangesトグル追加）
2. **編集者詳細情報の表示（所属・連絡先）**: Cycle 3の対応範囲（W4-3.1）
3. **クイックアクションとガイド**: Cycle 3の対応範囲（W4-3.2）

これらはすべてCycle 3タスクとして計画されており、Cycle 2には影響しない。

## 5. WBS更新案

### 5.1 Cycle 2: 設計タスク（W3-2.x）更新

| タスクID | タスク名 | ステータス変更 | 理由 |
|---------|---------|--------------|------|
| W3-2.1 | 任意2バージョン比較UI設計 | Not started → **Done** | Cycle 1実装時に暗黙的に完了（toggleSelection設計） |
| W3-2.2 | パフォーマンス測定機能設計 | Not started → **Partial** | 設定設計のみ完了、測定ロジック設計は未完 |

### 5.2 Cycle 2: 実装タスク（W4-2.x）更新

| タスクID | タスク名 | ステータス変更 | 理由 |
|---------|---------|--------------|------|
| W4-2.1 | 任意2バージョン比較実装 | Done → **Done** ✅ | すでに正しく記録済み |
| W4-2.2 | 検索ハイライト流用 | Done → **Done** ✅ | すでに正しく記録済み |
| W4-2.3 | パフォーマンス測定機能実装 | Not started → **Not started** | 設定ファイルのみ、測定ロジックは未実装 |

### 5.3 Cycle 2の残タスク

**実装が必要なタスク:**
- W4-2.3: パフォーマンス測定機能実装（実測定ロジックの追加）

**テストが必要なタスク:**
- W5-2.1: Featureテスト（任意2バージョン比較、検索ハイライト）
- W5-2.2: パフォーマンステスト（応答時間測定）
- W5-2.3: アクセシビリティテスト（キーボード操作、フォーカス制御）

## 6. 推奨アクション

### 6.1 即座に対応すべき事項

1. **WBSの更新**: 2026-01-03_plan.mdのW3-2.1, W4-2.1, W4-2.2のステータスを更新
2. **W3-2.2の完了**: パフォーマンス測定ロジックの詳細設計（FileInspectorの手法を参考）
3. **W4-2.3の実装**: 測定ロジックの実装（タイマー、ログ出力、統計集計）

### 6.2 Cycle 2の進め方

**Option A: パフォーマンス測定を含めて完了させる（推奨）**
- W3-2.2（設計）を完了 → W4-2.3（実装）→ W5-2.x（テスト）の順で進める
- Cycle 2の当初目的を完全に達成

**Option B: パフォーマンス測定を後回しにする**
- W4-2.3とW5-2.2をスキップまたは別フェーズに延期
- W5-2.1とW5-2.3のみ実施してCycle 2完了とする
- 理由: 既存機能（任意比較、検索ハイライト）の品質保証を優先

### 6.3 FileInspectorのパフォーマンス測定を参考にする

既存の実装を確認して、同様の手法を履歴タブに適用することを推奨：
- `docs/operations/fileinspector-performance-monitoring.md`の内容を参照
- 既存のPerformanceMonitorクラス（存在する場合）を流用
- 存在しない場合は新規作成

## 7. 次のステップ

### 7.1 選択肢の提示

**Option A: パフォーマンス測定を含めて完了させる（推奨）**
- 📋 W3-2.2（設計）を完了 → W4-2.3（実装）→ W5-2.x（テスト）の順で進める
- ✅ Cycle 2の当初目的を完全に達成
- ⏱️ 追加作業時間: 約1-2日
- 📊 運用時のパフォーマンス分析基盤が整う

**Option B: パフォーマンス測定を後回しにする**
- 📋 W5-2.1とW5-2.3のみ実施してCycle 2完了とする
- ✅ 既存機能（任意比較、検索ハイライト）の品質保証を優先
- ⏱️ 追加作業時間: 約0.5-1日
- ⚠️ パフォーマンス測定は別フェーズまたはプロジェクトで実施

### 7.2 Option A を選択した場合の作業内容

#### Step 1: W3-2.2 設計完了（所要時間: 2-3時間）

**成果物:** `2026-01-10_W3-2-2_performance_measurement_design.md`

**内容:**
1. FileInspectorの実装を参考に、履歴タブ用の測定設計を作成
   - 参考実装: `app/Livewire/AttachedFile/FileInspector.php`（logPerformanceメソッド）
   - 参考ドキュメント: `docs/operations/fileinspector-performance-monitoring.md`

2. 測定対象メトリクスの定義
   - `history_tab_load`: 履歴タブ初回表示時間
   - `history_load_more`: 無限スクロールによる追加ロード時間
   - `version_comparison`: 2バージョン比較の差分生成時間
   - `diff_render`: 差分表示のレンダリング時間

3. ログ形式の統一
   - FileInspectorと同じ形式に合わせる
   - Laravel標準ログとJSON統計ファイルの両方に対応

4. 環境変数の追加定義
   ```dotenv
   PERFORMANCE_METRIC_HISTORY_TAB_LOAD=true
   PERFORMANCE_METRIC_HISTORY_LOAD_MORE=true
   PERFORMANCE_METRIC_VERSION_COMPARISON=true
   PERFORMANCE_METRIC_DIFF_RENDER=true
   ```

#### Step 2: W4-2.3 実装（所要時間: 4-6時間）

**実装内容:**

1. `config/ledgerleap.php`に新しいメトリクス追加
   ```php
   'metrics' => [
       // ...existing metrics...
       'history_tab_load' => env('PERFORMANCE_METRIC_HISTORY_TAB_LOAD', true),
       'history_load_more' => env('PERFORMANCE_METRIC_HISTORY_LOAD_MORE', true),
       'version_comparison' => env('PERFORMANCE_METRIC_VERSION_COMPARISON', true),
       'diff_render' => env('PERFORMANCE_METRIC_DIFF_RENDER', true),
   ],
   ```

2. `LedgerHistoryManager.php`に測定ロジック追加
   ```php
   public function mount(int $ledgerId, ...): void
   {
       $startTime = microtime(true);
       
       // ...existing code...
       
       $duration = (microtime(true) - $startTime) * 1000;
       $this->logPerformance('history_tab_load', $duration, [
           'ledger_id' => $ledgerId,
           'diff_count' => $this->ledgerRecord->ledgerDiff()->count(),
       ]);
   }
   
   public function loadMore(): void
   {
       $startTime = microtime(true);
       
       // ...existing code...
       
       $duration = (microtime(true) - $startTime) * 1000;
       $this->logPerformance('history_load_more', $duration, [
           'page' => $this->pageCount,
       ]);
   }
   
   public function toggleSelection(int $id): void
   {
       $startTime = microtime(true);
       
       // ...existing code...
       
       $duration = (microtime(true) - $startTime) * 1000;
       $this->logPerformance('version_comparison', $duration, [
           'base_id' => $this->baseDiffId,
           'target_id' => $this->targetDiffId,
       ]);
   }
   
   private function logPerformance(string $metric, float $duration, array $metadata = []): void
   {
       // FileInspectorと同じロジックをコピー
       if (!config('ledgerleap.performance.enabled', false)) {
           return;
       }
       
       if (!config("ledgerleap.performance.metrics.{$metric}", true)) {
           return;
       }
       
       $logData = array_merge([
           'metric' => $metric,
           'duration_ms' => round($duration, 2),
           'component' => 'LedgerHistoryManager',
       ], $metadata);
       
       $logDestination = config('ledgerleap.performance.log_destination', 'both');
       
       if (in_array($logDestination, ['log', 'both'])) {
           \Illuminate\Support\Facades\Log::info("[HistoryTab Performance] {$metric}", $logData);
       }
       
       if (in_array($logDestination, ['json', 'both'])) {
           $statsFile = storage_path('logs/performance_stats.json');
           $stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
           $stats[] = array_merge($logData, ['timestamp' => now()->toISOString()]);
           file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
       }
   }
   ```

3. フロントエンド測定（Alpine.js）
   - `ledger-history-manager.blade.php`にレンダリング時間測定を追加
   - `window.performance.now()`を使用

#### Step 3: W5-2.x テスト（所要時間: 3-4時間）

1. **W5-2.1: Featureテスト**
   - 既存機能の動作確認（任意比較、検索ハイライト）
   - テストファイル: `tests/Feature/Livewire/Ledger/LedgerHistoryManagerTest.php`

2. **W5-2.2: パフォーマンステスト**
   - 各メトリクスの測定値確認
   - 目標値の検証（800ms以内、200ms以内等）
   - ログファイルの出力確認

3. **W5-2.3: アクセシビリティテスト**
   - キーボード操作の検証
   - フォーカス制御の確認
   - スクリーンリーダー対応（可能な範囲で）

#### Step 4: 完了レポート作成（所要時間: 1時間）

**成果物:** `2026-01-XX_W5-2_Cycle2_completion_report.md`

**内容:**
- 実装内容のサマリー
- テスト結果の報告
- パフォーマンス測定結果の分析
- 残存課題の整理

### 7.3 Option B を選択した場合の作業内容

#### Step 1: W5-2.1 Featureテスト（所要時間: 2時間）

既存機能の動作確認のみ実施。

#### Step 2: W5-2.3 アクセシビリティテスト（所要時間: 2時間）

キーボード操作とフォーカス制御の基本検証。

#### Step 3: W4-2.3/W5-2.2を別タスクとして切り出し

- Phase 1の後続タスクまたは別プロジェクトとして管理
- Cycle 3完了後に優先度を再評価

### 7.4 推奨事項

**Option A を推奨する理由:**
1. ✅ Cycle 2の当初目的を達成できる
2. 📊 早期にパフォーマンス基盤を整備することで、Cycle 3やPhase 2でも活用できる
3. 🔧 FileInspectorの実装が参考になるため、実装コストは低い
4. 📈 運用開始後のパフォーマンス分析に役立つ

**次のアクション:**
1. このレポートをPMと共有し、Option A/Bの選択を確認
2. 選択されたOptionに基づいて作業を開始
3. 完了後、Cycle 3へ進む

## 8. 関連ドキュメント

- [台帳差分表示拡充・ロールバック計画（全体計画）](2026-01-03_plan.md)
- [W4 実施内容報告：Cycle 1](2026-01-04_W4_completion_report.md)
- [W5-1.1 実施内容報告：Cycle 1 Featureテスト](2026-01-05_W5-1-1_completion_report.md)
- [FileInspectorパフォーマンス監視ガイド](../../../operations/fileinspector-performance-monitoring.md)

