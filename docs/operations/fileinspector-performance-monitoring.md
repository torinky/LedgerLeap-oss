# FileInspector パフォーマンス測定機能

**最終更新:** 2026年1月3日  
**対象:** LedgerLeap v12.0以降  
**Phase 1-5実装完了:** 添付ファイル機能統合（2025年12月-2026年1月）

---

## 1. 概要

FileInspectorコンポーネントのパフォーマンスを測定する機能です。環境変数で簡単にON/OFF切り替えが可能です。

**記載範囲:**
- 運用時のパフォーマンス測定設定
- ログの確認と分析方法
- トラブルシューティング

**記載しない内容:**
- 開発者向け最適化手法 → `docs/development/performance-optimization.md`
- FileInspector実装詳細 → `docs/work/ui-ux/attachment/`

### 1.1. 測定可能なメトリクス

| メトリクス | 説明 | 測定範囲 |
|----------|------|---------|
| **ドロワー開閉時間** (`drawer_open`) | ファイルインスペクターが開いてからコンテンツが表示されるまでの時間 | ドロワーを開くクリック → isLoadingがfalseになるまで |
| **タブ切り替え時間** (`tab_switch`) | Content/Details/History/Permissionsタブを切り替える時間 | タブクリック → レンダリング完了まで |

---

## 2. 設定方法

### 2.1. 環境変数の設定

`.env` ファイルに以下の設定を追加します：

```dotenv
# パフォーマンス測定機能の有効化
# true: 有効, false: 無効（デフォルト: 開発環境のみ有効）
PERFORMANCE_MONITORING_ENABLED=true

# ログ出力先
# log: Laravel標準ログ (storage/logs/laravel-*.log)
# json: JSON統計ファイル (storage/logs/performance_stats.json)
# both: 両方に出力（デフォルト）
# none: ログ出力なし（コンソールのみ）
PERFORMANCE_LOG_DESTINATION=both

# 測定するメトリクスの種類
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true
```

### 2.2. 環境別の推奨設定

#### 開発環境（local）

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=both
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true
```

**推奨理由:**
- パフォーマンス測定・改善のため
- 詳細なログを取得して分析

#### ステージング環境

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=log
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=false
```

**推奨理由:**
- 本番環境前の検証のため
- 重要なメトリクス（ドロワー開閉）のみ測定

#### 本番環境

```dotenv
PERFORMANCE_MONITORING_ENABLED=false
PERFORMANCE_LOG_DESTINATION=log
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=false
```

**推奨理由:**
- オーバーヘッド削減
- 問題発生時のみONにして調査

---

## 3. 動作確認

### 3.1. 設定値の確認

```bash
# Laravel Tinkerで設定値を確認
./vendor/bin/sail artisan tinker

# 以下を実行
config('ledgerleap.performance.enabled')
// => true または false

config('ledgerleap.performance.metrics')
// => ["drawer_open" => true, "tab_switch" => true]
```

### 3.2. ログ出力の確認

#### 1. ブラウザコンソールで確認

1. 台帳詳細画面を開く
2. Chrome DevTools（F12）を開く
3. Consoleタブを開く
4. FileInspectorドロワーを開く
5. 以下のようなログが表示されることを確認:

```
[FileInspector Performance] Drawer open started at: 12345.67
[FileInspector Performance] Drawer open duration: 2033.45 ms
```

#### 2. Laravelログで確認

```bash
./vendor/bin/sail exec laravel-1 tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep "FileInspector Performance"
```

#### 3. JSON統計ファイルで確認

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.'
```

---

## 4. ログフォーマット

### 4.1. Laravel標準ログ

**場所:** `storage/logs/laravel-YYYY-MM-DD.log`

**フォーマット:**
```
[2025-12-30 08:35:59] local.INFO: [FileInspector Performance] drawer_open {"metric":"drawer_open","duration_ms":2033.0,"file_id":16,"tab":"content"}
```

**項目説明:**

| 項目 | 説明 |
|-----|------|
| `metric` | メトリクス種別（drawer_open / tab_switch） |
| `duration_ms` | 所要時間（ミリ秒） |
| `file_id` | ファイルID |
| `tab` | 現在のタブ |
| `from` | タブ切り替えの場合、元のタブ |
| `to` | タブ切り替えの場合、切り替え先のタブ |

### 4.2. JSON統計ファイル

**場所:** `storage/logs/performance_stats.json`

**フォーマット:**
```json
[
    {
        "metric": "drawer_open",
        "duration_ms": 2033,
        "file_id": 16,
        "tab": "content",
        "timestamp": "2025-12-30T08:35:59.977491Z"
    },
    {
        "metric": "tab_switch",
        "duration_ms": 22,
        "file_id": 16,
        "tab": "history",
        "from": "content",
        "to": "history",
        "timestamp": "2025-12-30T08:36:23.608030Z"
    }
]
```

---

## 5. 統計分析

### 5.1. 平均値の計算

```bash
# ドロワー開閉時間の平均
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "drawer_open") | .duration_ms] | add / length'

# タブ切り替え時間の平均
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch") | .duration_ms] | add / length'
```

### 5.2. 最大・最小値の確認

```bash
# ドロワー開閉時間の最大値
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "drawer_open") | .duration_ms] | max'

# タブ切り替え時間の最小値
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch") | .duration_ms] | min'
```

### 5.3. タブ別の統計

```bash
# Contentタブへの切り替え時間
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch" and .to == "content") | .duration_ms]'
```

---

## 6. パフォーマンス測定の無効化

### 6.1. 完全に無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=false
```

この設定により、以下が実行されなくなります：
- フロントエンドでのPerformance API呼び出し
- バックエンドでのログ記録
- JSON統計ファイルへの書き込み

### 6.2. 特定のメトリクスのみ無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=false  # タブ切り替え測定を無効化
```

### 6.3. ログ出力のみ無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=none  # ブラウザコンソールのみ
```

---

## 7. トラブルシューティング

### 7.1. ログが出力されない

#### 原因1: 環境変数が反映されていない

**解決策:**
```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan config:cache
```

#### 原因2: 測定機能が無効化されている

**確認方法:**
```bash
# .envファイルを確認
grep PERFORMANCE .env
```

**解決策:**
```dotenv
PERFORMANCE_MONITORING_ENABLED=true
```

#### 原因3: ログディレクトリの権限不足

**解決策:**
```bash
./vendor/bin/sail exec laravel-1 chmod -R 777 storage/logs/
```

### 7.2. JSON統計ファイルが生成されない

**原因:** `PERFORMANCE_LOG_DESTINATION` が `log` または `none` に設定されている

**解決策:**
```dotenv
PERFORMANCE_LOG_DESTINATION=both  # または json
```

### 7.3. パフォーマンスに影響がある

**症状:**
- ドロワーの開閉が遅くなった
- ブラウザが重くなった

**解決策:**

#### 本番環境では無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=false
```

#### タブ切り替え測定のみ無効化

```dotenv
PERFORMANCE_METRIC_TAB_SWITCH=false
```

#### ログ出力を最小化

```dotenv
PERFORMANCE_LOG_DESTINATION=none
```

---

## 8. ベンチマーク

### 8.1. WBS 5.2での測定結果（Phase 1-5）

**測定環境:**
- Laravel Sail（ローカル開発環境）
- Chrome 131
- 測定日: 2025年12月30日-31日

**測定結果:**

| メトリクス | 実測値 | 目標値 | 判定 |
|----------|-------|-------|------|
| ドロワー開閉時間 | 平均1600-2500ms | 300ms以内 | ❌ 要改善 |
| タブ切り替え時間 | 平均7-140ms | 100ms以内 | ✅ 達成 |

**改善実施済み（WBS 5.2.1）:**
- `npm run build`による劇的な改善
- フォーカス遅延: 完全解消
- 画像プレビュー: 143msに改善
- UIブロック: 完全解消

**今後の改善課題:**
- Livewireレンダリングの最適化（キーワード検索の1500ms遅延）
- activitiesの遅延ロード実装

詳細は `docs/work/ui-ux/attachment/wbs5.2-performance-improvement/` を参照

---

## 9. 技術仕様

### 9.1. 設定ファイル

**場所:** `config/ledgerleap.php`

```php
'performance' => [
    'enabled' => env('PERFORMANCE_MONITORING_ENABLED', env('APP_ENV') === 'local'),
    'log_destination' => env('PERFORMANCE_LOG_DESTINATION', 'both'),
    'metrics' => [
        'drawer_open' => env('PERFORMANCE_METRIC_DRAWER_OPEN', true),
        'tab_switch' => env('PERFORMANCE_METRIC_TAB_SWITCH', true),
    ],
],
```

### 9.2. Bladeテンプレート

**場所:** `resources/views/livewire/attached-file/file-inspector.blade.php`

```blade
@php
    $performanceEnabled = config('ledgerleap.performance.enabled', false);
    $drawerOpenMetricEnabled = config('ledgerleap.performance.metrics.drawer_open', true);
    $tabSwitchMetricEnabled = config('ledgerleap.performance.metrics.tab_switch', true);
@endphp

@if($performanceEnabled && $drawerOpenMetricEnabled)
    {{-- ドロワー開閉測定コード --}}
@endif
```

### 9.3. Livewireコンポーネント

**場所:** `app/Livewire/AttachedFile/FileInspector.php`

```php
public function logPerformance(string $metric, float $duration, array $metadata = []): void
{
    if (! config('ledgerleap.performance.enabled', false)) {
        return;  // 測定無効時は何もしない
    }
    
    if (! config("ledgerleap.performance.metrics.{$metric}", true)) {
        return;  // メトリクスが無効時は何もしない
    }
    
    // ログ記録処理...
}
```

### 9.4. メトリクス追加方法

新しいメトリクスを追加する場合の手順：

1. **環境変数の追加** (`.env`)
```dotenv
PERFORMANCE_METRIC_NEW_METRIC=true
```

2. **設定ファイルの更新** (`config/ledgerleap.php`)
```php
'metrics' => [
    // ...existing code...
    'new_metric' => env('PERFORMANCE_METRIC_NEW_METRIC', true),
],
```

3. **Bladeテンプレートで測定**
```blade
@if(config('ledgerleap.performance.metrics.new_metric'))
<div x-data="{
    logNewMetric() {
        const start = performance.now();
        // 測定対象の処理
        $wire.logPerformance('new_metric', performance.now() - start);
    }
}">
@endif
```

---

## 10. 関連ドキュメント

### 運用ガイド
- **[データベースパフォーマンス監視](./database-performance-monitoring.md)** - データベース監視設定

### 開発ガイド
- **[パフォーマンス最適化ガイド](../development/performance-optimization.md)** - 開発者向け最適化手法

### アーキテクチャ
- **[非同期処理](../architecture/QueueProcessing.md)** - キューワーカーとジョブ設計
- **[ファイル処理フロー](../architecture/file-processing-flow.md)** - VLM/OCR/Tika並列処理

### 作業ドキュメント（内部資料）
- `docs/work/ui-ux/attachment/wbs5.2-performance-improvement/` - WBS 5.2パフォーマンス改善の詳細
- `docs/work/ui-ux/attachment/2025-12-30_phase4-6-5_performance_report.md` - Phase 4.6.5測定レポート

---

**最終更新:** 2026年1月3日  
**主な測定実施:** Phase 1-5（添付ファイル機能統合）、WBS 5.2（パフォーマンス改善）

