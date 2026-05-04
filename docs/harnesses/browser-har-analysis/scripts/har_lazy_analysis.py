#!/usr/bin/env python3
"""
LedgerLeap #[Lazy] before/after HAR 比較スクリプト。

Usage:
  python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
      localhost.har localhost2.har localhost3.har

比較観点:
  - フォルダ切替リクエストの構成 (IndexManager と RecordsTable が分離されているか)
  - IndexManager の "interactive time" (最初の UI 反応までの時間)
  - RecordsTable の "content complete time" (コンテンツ完了までの総時間)
  - リクエスト多重度 (refreshChildren カスケードの有無)
  - レスポンスサイズ (HTML 肥大の状況)
"""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


# ---------------------------------------------------------------------------
# Data models
# ---------------------------------------------------------------------------

@dataclass
class LwRequest:
    index: int
    started: str
    time_ms: float
    wait_ms: float
    recv_ms: float
    body_size: int
    components: list[str]   # req コンポーネント名一覧
    resp_components: list[dict[str, Any]] = field(default_factory=list)


def _parse_components(postdata_text: str) -> list[str]:
    names: list[str] = []
    try:
        data = json.loads(postdata_text or '{}')
        for c in data.get('components', []):
            snap = c.get('snapshot', '')
            if isinstance(snap, str):
                try:
                    names.append(json.loads(snap).get('memo', {}).get('name', '?'))
                except Exception:
                    pass
    except Exception:
        pass
    return names


def _parse_resp_components(content: dict[str, Any]) -> list[dict[str, Any]]:
    text = content.get('text', '')
    if not text:
        return []
    try:
        data = json.loads(text)
        result = []
        for c in data.get('components', []):
            snap_str = c.get('snapshot', '{}')
            try:
                snap = json.loads(snap_str)
                name = snap.get('memo', {}).get('name', '?')
            except Exception:
                name = '?'
            html = c.get('effects', {}).get('html', '')
            result.append({'name': name, 'html_len': len(html)})
        return result
    except Exception:
        return []


def is_livewire_update(url: str) -> bool:
    return bool(re.search(r'livewire[^/]*/update', url))


def load_lw_requests(path: Path) -> list[LwRequest]:
    data = json.loads(path.read_text(encoding='utf-8'))
    result: list[LwRequest] = []
    for idx, entry in enumerate(data.get('log', {}).get('entries', [])):
        url = entry.get('request', {}).get('url', '')
        if not is_livewire_update(url):
            continue
        timings = entry.get('timings', {})
        content = entry.get('response', {}).get('content', {})
        postdata = entry.get('request', {}).get('postData', {}).get('text', '') or ''
        result.append(LwRequest(
            index=idx,
            started=entry.get('startedDateTime', '')[:23],
            time_ms=float(entry.get('time', 0) or 0),
            wait_ms=float(timings.get('wait') or 0),
            recv_ms=float(timings.get('receive') or 0),
            body_size=int(content.get('size') or 0),
            components=_parse_components(postdata),
            resp_components=_parse_resp_components(content),
        ))
    return result


# ---------------------------------------------------------------------------
# Analysis helpers
# ---------------------------------------------------------------------------

def has_comp(req: LwRequest, *keywords: str) -> bool:
    return all(any(k in n for n in req.components) for k in keywords)


PLACEHOLDER_BODY_THRESHOLD = 300_000  # bytes: IM+placeholder は本体より大幅に小さい


def folder_switch_sequences(reqs: list[LwRequest]) -> list[dict[str, Any]]:
    """
    フォルダ切替リクエストのシーケンスを抽出する。

    Lazy 前:
      - IndexManager + RecordsTable が 1 req に束ねられ、body が大きい (~500KB〜)

    Lazy 後 (#[Lazy]):
      - パターン1: IM req に records-table が含まれない → 直後の RT 単独 req が Lazy ロード
      - パターン2: IM + RecordsTable(placeholder) が 1 req (body 小、~200KB 未満) → 直後に RT 単独 req
        ※ placeholder は本物の HTML より大幅に小さいため body サイズ閾値で判定
    """
    sequences = []
    i = 0
    while i < len(reqs):
        req = reqs[i]
        if 'ledger.index-manager' in req.components:
            seq: dict[str, Any] = {
                'index_manager_req': req,
                'records_table_req': None,
                'lazy': False,
            }

            rt_in_same = 'ledger.records-table' in req.components
            is_placeholder_bundle = rt_in_same and req.body_size < PLACEHOLDER_BODY_THRESHOLD

            if rt_in_same and not is_placeholder_bundle:
                # Lazy 前: IndexManager + RecordsTable(本物) が同一 req
                seq['lazy'] = False
                seq['records_table_req'] = req
            else:
                # Lazy 後: 直後に RT 単独 req があるか探す
                lookahead = min(i + 3, len(reqs))
                for j in range(i + 1, lookahead):
                    nxt = reqs[j]
                    if (has_comp(nxt, 'records-table')
                            and 'index-manager' not in ' '.join(nxt.components)
                            and len(nxt.components) == nxt.components.count('ledger.records-table')):
                        seq['lazy'] = True
                        seq['records_table_req'] = nxt
                        i = j  # consumed
                        break
                else:
                    if rt_in_same:
                        # placeholder bundle だが後続 RT が見つからなかった
                        seq['lazy'] = True
                        seq['records_table_req'] = None

            sequences.append(seq)
        i += 1
    return sequences


# ---------------------------------------------------------------------------
# Report
# ---------------------------------------------------------------------------

def print_divider(title: str = '') -> None:
    if title:
        print(f"\n{'='*70}")
        print(f"  {title}")
        print('='*70)
    else:
        print('-' * 70)


def report_file(path: Path, reqs: list[LwRequest]) -> None:
    print_divider(f"[{path.name}]  livewire/update: {len(reqs)} requests")

    # --- Timeline ---
    print("\n### Timeline (chronological)")
    print(f"{'started':23}  {'total':>8}  {'wait':>8}  {'body':>9}  n  components")
    for r in reqs:
        label = ', '.join(r.components[:4])
        print(f"{r.started}  {r.time_ms:7.0f}ms  {r.wait_ms:7.0f}ms  {r.body_size:8}B  {len(r.components):2}  {label[:60]}")

    # --- Component frequency ---
    print("\n### Component frequency")
    counter: Counter[str] = Counter()
    for r in reqs:
        counter.update(r.components)
    for name, cnt in counter.most_common(15):
        print(f"  {cnt:4}x  {name}")

    # --- Bundle analysis (multi-component reqs) ---
    bundles = [r for r in reqs if len(r.components) > 1]
    if bundles:
        print(f"\n### Multi-component bundled requests ({len(bundles)} reqs)")
        for r in bundles:
            label = ', '.join(r.components)
            html_total = sum(c['html_len'] for c in r.resp_components)
            print(f"  {r.body_size:8}B  {r.time_ms:7.0f}ms  html={html_total:8}  [{label[:70]}]")

    # --- Folder-switch sequences ---
    seqs = folder_switch_sequences(reqs)
    if seqs:
        print(f"\n### Folder-switch sequences ({len(seqs)} detected)")
        print(f"  {'lazy':5}  {'IM_time':>9}  {'IM_body':>9}  {'RT_time':>9}  {'RT_body':>9}  interactive→content")
        for s in seqs:
            im = s['index_manager_req']
            rt = s['records_table_req']
            lazy = '✅' if s['lazy'] else '❌'
            if rt is im:
                print(f"  {lazy}  {im.time_ms:8.0f}ms  {im.body_size:8}B  (same req as IM)  interactive=content={im.time_ms:.0f}ms")
            elif rt:
                print(f"  {lazy}  {im.time_ms:8.0f}ms  {im.body_size:8}B  {rt.time_ms:8.0f}ms  {rt.body_size:8}B  interactive={im.time_ms:.0f}ms content={im.time_ms+rt.time_ms:.0f}ms")
            else:
                print(f"  {lazy}  {im.time_ms:8.0f}ms  {im.body_size:8}B  (no RT req found)")

    # --- Slow requests ---
    slow = sorted(reqs, key=lambda r: r.time_ms, reverse=True)[:5]
    print(f"\n### Top-5 slowest requests")
    for r in slow:
        label = ', '.join(r.components[:3])
        print(f"  {r.time_ms:8.0f}ms  wait={r.wait_ms:8.0f}ms  body={r.body_size:9}B  [{label}]")


def print_comparison(summaries: list[tuple[Path, list[LwRequest]]]) -> None:
    print_divider("COMPARISON SUMMARY")

    print(f"\n{'file':25}  {'lw_reqs':>8}  {'max_time':>9}  {'med_time':>9}  {'max_body':>9}")
    for path, reqs in summaries:
        if not reqs:
            print(f"  {path.name:23}  {'0':>8}  {'N/A':>9}  {'N/A':>9}  {'N/A':>9}")
            continue
        times = sorted(r.time_ms for r in reqs)
        med = times[len(times) // 2]
        bodies = sorted(r.body_size for r in reqs)
        print(f"  {path.name:23}  {len(reqs):>8}  {max(times):8.0f}ms  {med:8.0f}ms  {max(bodies):9}B")

    # Interactive time comparison (IndexManager separate)
    print(f"\n### Folder-switch interactive time (IndexManager response = first UI reaction)")
    print(f"{'file':25}  {'sequences':>9}  {'lazy%':>7}  {'IM_min':>8}  {'IM_med':>8}  {'IM_max':>8}  {'RT_med':>8}")
    for path, reqs in summaries:
        seqs = folder_switch_sequences(reqs)
        if not seqs:
            print(f"  {path.name:23}  {'0':>9}  {'N/A':>7}  {'N/A':>8}  {'N/A':>8}  {'N/A':>8}  {'N/A':>8}")
            continue
        lazy_count = sum(1 for s in seqs if s['lazy'])
        im_times = [s['index_manager_req'].time_ms for s in seqs]
        rt_times = [s['records_table_req'].time_ms for s in seqs if s['records_table_req'] is not s['index_manager_req']]
        im_times_s = sorted(im_times)
        med_im = im_times_s[len(im_times_s) // 2] if im_times_s else 0
        med_rt = sorted(rt_times)[len(rt_times) // 2] if rt_times else 0
        lazy_pct = f"{100*lazy_count//len(seqs):3}%" if seqs else 'N/A'
        print(f"  {path.name:23}  {len(seqs):>9}  {lazy_pct:>7}  {min(im_times):7.0f}ms  {med_im:7.0f}ms  {max(im_times):7.0f}ms  {med_rt:7.0f}ms")


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description='LedgerLeap #[Lazy] before/after HAR 比較')
    p.add_argument('har_files', nargs='+', type=Path)
    return p.parse_args()


def main() -> int:
    args = parse_args()
    summaries: list[tuple[Path, list[LwRequest]]] = []
    for path in args.har_files:
        reqs = load_lw_requests(path)
        summaries.append((path, reqs))
        report_file(path, reqs)
        print()

    if len(summaries) >= 2:
        print_comparison(summaries)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

