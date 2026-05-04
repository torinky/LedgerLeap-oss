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

5. **Comparison summary**
   - before / after deltas
   - what disappeared
   - what remains

6. **Next action**
   - whether to keep investigating network/DOM/UI layering
   - whether the bottleneck has moved to HTML, assets, or rerenders

## Guardrails

- Separate **debug noise** from app cost.
- Do not assume a slow page is a single SQL issue; confirm whether the same content is being re-requested.
- If the same script is used repeatedly, move it into the harness script instead of retyping it.
- Keep repo proof in `docs/work/*` and reference it from this skill.
- **`har_summary.py` の livewire/update count が 0 の場合**は URL パターン不一致を疑い `har_lazy_analysis.py` を使う。

## Evidence

- [`docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md`](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)
- [`docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md`](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-04
- recheck_after: 2026-08-04
- recheck_trigger: HAR schema changes, Livewire network payload format changes, livewire URL hash pattern changes, or `har_lazy_analysis.py` being updated
