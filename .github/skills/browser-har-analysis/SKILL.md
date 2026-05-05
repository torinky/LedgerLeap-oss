---
name: browser-har-analysis
description: Analyze browser HAR files for LedgerLeap to compare repeated requests, Livewire update payloads, and before/after bottlenecks. Use when HAR files are being compared, repeated scripts should be standardized, or the same network capture is reviewed more than once.
compatibility: LedgerLeap (.github/prompts/browser-har-analysis.prompt.md, docs/runbooks/browser-har-analysis-playbook.md, docs/harnesses/browser-har-analysis/README.md)
---

# browser-har-analysis

## Decision Tree

```text
Browser HAR analysis needed?
├─ Need to compare before/after captures? → summarize per file and diff by request type
├─ Need to isolate repeated Livewire requests? → count livewire/update, compare payload size, inspect component names
├─ Need to measure #[Lazy] interactive vs content-complete time? → use har_lazy_analysis.py (see below)
├─ Need to separate app cost from debug noise? → check debugbar/_boost/static assets first
├─ Need a repeatable command recipe? → use the harness script in docs/harnesses/browser-har-analysis/
└─ Need to report a reusable finding? → attach evidence in docs/work and sync via /skill-maintenance
```

## ⚠️ LedgerLeap 固有の注意点

### Livewire URL パターン

LedgerLeap では Livewire の update エンドポイントが **`livewire-HASH/update`** 形式になっている。
`livewire/update` だけでマッチすると **すべてのリクエストが素通りになる**。

```python
# ❌ 旧パターン（マッチしない）
if 'livewire/update' in url:

# ✅ 正しいパターン
import re
if re.search(r'livewire[^/]*/update', url):
```

### #[Lazy] コンポーネントの HAR シグネチャ

`RecordsTable` に `#[Lazy]` を付与した後は、フォルダ切替時のシーケンスが変わる。

| 状態 | 1 本目 req | 2 本目 req |
|------|-----------|-----------|
| Lazy なし | `index-manager + records-table`（500〜900KB、5〜15s） | なし |
| Lazy あり | `index-manager + records-table(placeholder)`（**〜164KB、〜790ms**） | `records-table` 単独（460〜860KB、5〜13s） |

- **1 本目の body が 300KB 未満なら placeholder bundle**（本物の RecordsTable は含まれていない）
- **interactive time** = 1 本目の time_ms（ユーザーがスケルトン UI を見る時点）
- **content complete time** = 1 本目 + 2 本目 time_ms（実コンテンツが揃う時点）

## What to Inspect

- Initial `document` request: status, wait/TTFB, body size
- `livewire/update` requests: count, total time, payload size, repeated component sets
- **Folder-switch sequences**: IndexManager / RecordsTable の分離有無（Lazy 効果確認）
- **interactive time vs content-complete time**: `#[Lazy]` 前後の比較に必須
- Static assets: `app-*.js/css`, `livewire.js`, Vite dev assets, debugbar assets
- Repeated request patterns: same route, same component set, same response size
- Noise sources: debugbar, browser logs, overlay telemetry, dev server refreshes

## Standard Command Recipe

### 基本サマリー（旧スクリプト）
```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py localhost.har localhost2.har
```
> ⚠️ `livewire-HASH/update` URL にマッチしないため livewire/update count が 0 になる既知の問題あり。
> 正確な Livewire 分析には `har_lazy_analysis.py` を使うこと。

### Lazy 分析・before/after 比較（推奨）
```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost.har localhost2.har localhost3.har
```

出力に含まれる情報:
- Timeline（時系列全リクエスト）
- Component frequency（コンポーネント出現頻度）
- Multi-component bundles（バンドルリクエスト）
- **Folder-switch sequences**（lazy フラグ、IM time、RT time、interactive/content）
- **COMPARISON SUMMARY**（ファイル間の比較、interactive time 中央値）

Typical output should answer:
- Which request type dominates?
- How many `livewire/update` requests are large?
- Which components are repeated in the payload?
- Is RecordsTable being lazy-loaded (separate from IndexManager)?
- Did debug noise disappear between captures?

### パフォーマンスログ集計（HAR と組み合わせて使う）
```bash
python3 docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py \
    storage/logs/laravel-YYYY-MM-DD.log
```

`column_html_show_ms` エントリを集計する。HAR の wait time と照合することでサーバサイドのボトルネックを特定できる。

出力に含まれる情報:
- **render_kind 別集計**（count / sum / mean / median / max）
- **source 別集計**（table-row / ledger-detail-table など呼び出し元別）
- **ledger_id 別 Top 15**（特定台帳でコストが集中していないかの確認）
- **20ms 超スパイク一覧**（column型・ledger・column_id 付きで降順）

#### 確認パターン

| 観察 | 示唆 |
|------|------|
| `textarea` が sum の 80% 超 | `MarkdownRenderer` + `AutoLinkService` がボトルネック → キャッシュ検討 |
| `auto_number` に 100ms 超スパイク | `AutoLinkService` の正規表現が特定 ledger で遅い可能性 |
| `select` / `chk` が遅い | ColumnDefine の eager load 漏れを疑う |
| wait ≈ total（HAR） | 全遅延がサーバサイド PHP 処理 |
| wait << total（HAR） | ネットワーク受信が遅い（レスポンスボディが大きい）|

## Output Contract

When reporting results, include:

1. **Capture context**
   - HAR filename
   - debug mode on/off
   - browser / page flow if known

2. **Top-level metrics**
   - total requests
   - largest `document`
   - `livewire/update` count and sizes
   - obvious static asset outliers

3. **Component breakdown**
   - repeated Livewire components
   - response sizes per component
   - whether the same heavy component appears more than once

4. **Folder-switch analysis（#[Lazy] 導入後はこれが最重要）**
   - `lazy%`: Lazy 分離されているか
   - `IM_med`: interactive time 中央値（フォルダクリック → スケルトン表示）
   - `RT_med`: RecordsTable 単独ロード時間
   - content complete = IM + RT

5. **パフォーマンスログ照合（analyze_perf_log.py）**
   - `textarea` の sum が全体の何 % か
   - 20ms 超スパイクの render_kind は何か
   - wait ≈ total であればサーバサイドが主因

6. **Comparison summary**
   - before / after deltas
   - what disappeared
   - what remains

7. **Next action**
   - whether to keep investigating network/DOM/UI layering
   - whether the bottleneck has moved to HTML, assets, or rerenders

## Guardrails

- Separate **debug noise** from app cost.
- Do not assume a slow page is a single SQL issue; confirm whether the same content is being re-requested.
- If the same script is used repeatedly, move it into the harness script instead of retyping it.
- Keep repo proof in `docs/work/*` and reference it from this skill.
- **`har_summary.py` の livewire/update count が 0 の場合**は URL パターン不一致を疑い `har_lazy_analysis.py` を使う。

## Advanced Patterns

### `lazyLoaded` フィールドの解釈

Livewire v3 の HAR payload には `components[0].snapshot.data.lazyLoaded` フィールドが存在する。

| 値 | 意味 | 判断 |
|---|---|---|
| `0` | `#[Lazy]` が有効で、まだ実コンテンツをロードしていない | lazy ✅ |
| `None` / `null` | `#[Lazy]` が認識されていない、または既にマウント済み | lazy ❌ |

**確認方法**: `python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py` の出力 `lazy%` を確認。`lazy=✅` の割合が期待値より低い場合、`lazyLoaded` フィールドを個別に確認する。

### `$commit` NO UPDATES の診断

`updates` が空の `$commit` リクエストが定期的に発生する場合：

```
[ 15] 9072ms ['$commit'] (+10.3s) ← NO UPDATES
[ 17]  897ms ['$commit'] (+9.4s)  ← NO UPDATES
```

**診断フロー**:
1. `wire:poll` コンポーネント単位検索: `grep -r 'wire:poll' resources/views/livewire/ledger/`
2. Alpine.js `x-data` + `$nextTick` + `IntersectionObserver` の追跡
3. `#[Reactive]` プロパティの影響確認（子コンポーネント単独で dirty 判定が走る可能性）
4. Livewire DevTools などデバッグツールの干渉確認

### `wire:key` 動的変更による `#[Lazy]` 強制再マウント

同一ページ内で子コンポーネントを再利用したい場合、`wire:key` を動的に変更する。

```php
// 親コンポーネント (IndexManager)
public $recordsTableMountKey = 0;

public function changeCurrentFolder($folderId)
{
    $this->currentFolderId = $folderId;
    $this->recordsTableMountKey++;
    $this->dispatch('folderChanged', folderId: $folderId);
}
```

```blade
<livewire:ledger.records-table
    wire:key="ledger-records-table-mount-{{ $recordsTableMountKey }}"
    :keywords="$keywords"
    ... />
```

**効果**: `wire:key` が変化すると Livewire は既存コンポーネントを破棄して新規マウントするため、`#[Lazy]` の placeholder → 実コンテンツのライフサイクルが再現される。

### Alpine.js `x-data` + `IntersectionObserver` と Livewire の相互作用

`render()` ごとに `dispatch('ledger-sections-rendered')` → Alpine.js `setupObserver()` → `IntersectionObserver` 再設定 という連鎖が発生する場合：

- `$nextTick` で DOM クエリ → Alpine.js 内部の reactive 依存関係が変化 → dirty フラグが立つ可能性
- **対策**: observer の生存期間を `init()` / `destroy()` で明示的に管理し、`setupObserver()` に変更検知（DOM 構造が変わった場合のみ再設定）を入れる

### Blade Component Render Spike Pattern

`column_html_show_ms` で `auto_number` や `text` タイプに散発スパイクがある場合、正規表現マッチ後の **Blade コンポーネントレンダリング**を疑う。

**診断フロー**:
1. `analyze_perf_log.py` でスパイクの render_kind と ledger_id/col_id を特定
2. DB で該当カラムの実データ（テキスト長、マッチ数）を確認
3. `AutoLinkService::convert()` 内で `Blade::render()` をループ呼び出ししていないか確認
4. ベンチマークスクリプトでマッチ数別の処理時間を計測

**対策例**:
```php
// Before: マッチごとに Blade::render() → 線形増加
$iconHtml = Blade::render("<x-mary-icon ... />");

// After: リクエスト内キャッシュで定数コスト化
private static array $iconHtmlCache = [];
private function getCachedIconHtml(string $iconName): string
{
    if (! isset(self::$iconHtmlCache[$iconName])) {
        self::$iconHtmlCache[$iconName] = Blade::render("<x-mary-icon ... />");
    }
    return self::$iconHtmlCache[$iconName];
}
```

→ 100マッチで 130ms → 13ms（90%削減）

**証跡**: [docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md](../../../docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md)

### `Cache::remember()` の落とし穴と対処

```text
Cache::remember() のクロージャが正しく保存されない?
├─ Cache::get() + Cache::put() に変更する
└─ リクエスト内インメモリキャッシュで Redis 往復を回避
```

**パターン**:
```php
private static array $requestCache = [];

public function show(...)
{
    $cacheKey = "...";
    
    // リクエスト内キャッシュ（~0.01ms）
    if (isset(self::$requestCache[$cacheKey])) {
        return self::$requestCache[$cacheKey];
    }
    
    // Redis キャッシュ（~5ms）
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        self::$requestCache[$cacheKey] = $cached;
        return $cached;
    }
    
    // 生成 → 両方に保存
    $html = $this->generateHtml(...);
    Cache::put($cacheKey, $html, $ttl);
    self::$requestCache[$cacheKey] = $html;
    return $html;
}
```

**効果**:
- `Cache::get()` Redis 往復: ~5ms
- リクエスト内キャッシュ: ~0.01ms
- セル数が多い場合（数百セル）に顕著

**証跡**: [Issue #200 コメント](https://github.com/torinky/LedgerLeap/issues/200#issuecomment-4376836885)

## Evidence

- [`docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md`](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)
- [`docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md`](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md)
- [`docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-localhost4-har-perf-analysis.md`](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-localhost4-har-perf-analysis.md)
- [`docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md`](../../../docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md)
- [Issue #200: 状態ベースキャッシュで派生結果を再利用する](https://github.com/torinky/LedgerLeap/issues/200)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-05
- recheck_after: 2026-08-05
- recheck_trigger: HAR schema changes, Livewire network payload format changes, livewire URL hash pattern changes, `har_lazy_analysis.py` being updated, `analyze_perf_log.py` being updated, `#[Lazy]` lifecycle changes, Alpine.js / Livewire dirty-check behavior changes, new Blade component render spikes in `column_html_show_ms`, or Cache driver changes (Redis → array/file)
