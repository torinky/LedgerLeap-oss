# FileInspector パフォーマンス測定機能

**バージョン:** v1.0.0  
**最終更新:** 2025年12月30日  
**対象:** LedgerLeap v12.0以降

---

## 概要

FileInspectorコンポーネントのパフォーマンスを測定する機能です。環境変数で簡単にON/OFF切り替えが可能です。

### 測定可能なメトリクス

| メトリクス | 説明 | 測定範囲 |
|----------|------|---------|
| **ドロワー開閉時間** (`drawer_open`) | ファイルインスペクターが開いてからコンテンツが表示されるまでの時間 | ドロワーを開くクリック → isLoadingがfalseになるまで |
| **タブ切り替え時間** (`tab_switch`) | Content/Details/History/Permissionsタブを切り替える時間 | タブクリック → レンダリング完了まで |

---

## 設定方法

### 環境変数の設定

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

### 環境別の推奨設定

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

## 動作確認

### 設定値の確認

```bash
# Laravel Tinkerで設定値を確認
./vendor/bin/sail artisan tinker

# 以下を実行
config('ledgerleap.performance.enabled')
// => true または false

config('ledgerleap.performance.metrics')
// => ["drawer_open" => true, "tab_switch" => true]
```

### ログ出力の確認

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

## ログフォーマット

### Laravel標準ログ

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

### JSON統計ファイル

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

## 統計分析

### 平均値の計算

```bash
# ドロワー開閉時間の平均
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "drawer_open") | .duration_ms] | add / length'

# タブ切り替え時間の平均
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch") | .duration_ms] | add / length'
```

### 最大・最小値の確認

```bash
# ドロワー開閉時間の最大値
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "drawer_open") | .duration_ms] | max'

# タブ切り替え時間の最小値
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch") | .duration_ms] | min'
```

### タブ別の統計

```bash
# Contentタブへの切り替え時間
cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "tab_switch" and .to == "content") | .duration_ms]'
```

---

## パフォーマンス測定の無効化

### 完全に無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=false
```

この設定により、以下が実行されなくなります：
- フロントエンドでのPerformance API呼び出し
- バックエンドでのログ記録
- JSON統計ファイルへの書き込み

### 特定のメトリクスのみ無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=false  # タブ切り替え測定を無効化
```

### ログ出力のみ無効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=none  # ブラウザコンソールのみ
```

---

## トラブルシューティング

### ログが出力されない

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

### JSON統計ファイルが生成されない

**原因:** `PERFORMANCE_LOG_DESTINATION` が `log` または `none` に設定されている

**解決策:**
```dotenv
PERFORMANCE_LOG_DESTINATION=both  # または json
```

### パフォーマンスに影響がある

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

## ベンチマーク

### Phase 4.6.5での測定結果

**測定環境:**
- Laravel Sail（ローカル開発環境）
- Chrome 131
- 測定日: 2025年12月30日

**測定結果:**

| メトリクス | 実測値 | 目標値 | 判定 |
|----------|-------|-------|------|
| ドロワー開閉時間 | 平均2033ms | 300ms以内 | ❌ 要改善 |
| タブ切り替え時間 | 平均33ms | 100ms以内 | ✅ 達成 |

**改善提案:**
- キャッシング実装でドロワー開閉時間を1秒以下に短縮
- activitiesの遅延ロードでクエリ数を削減

---

## 技術仕様

### 設定ファイル

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

### Bladeテンプレート

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

### Livewireコンポーネント

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

---

## 関連ドキュメント

- [データベースパフォーマンス監視](./database-performance-monitoring.md)
- [FileInspector実装ガイド](/docs/work/ui-ux/attachment/2025-12-30_phase4-6_implementation_guide.md)（内部資料）
- [パフォーマンス測定レポート](/docs/work/ui-ux/attachment/2025-12-30_phase4-6-5_performance_report.md)（内部資料）

---

**ドキュメントバージョン:** 1.0.0  
**公開日:** 2025年12月30日  
**作成者:** LedgerLeap開発チーム

