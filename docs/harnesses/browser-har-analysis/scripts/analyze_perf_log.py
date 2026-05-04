#!/usr/bin/env python3
"""
LedgerLeap column_html_show_ms パフォーマンスログ集計スクリプト

Usage:
  python3 docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py \
      storage/logs/laravel-2026-05-04.log
"""
import argparse
import json
import re
from collections import defaultdict
from pathlib import Path
import statistics


def load_perf_entries(path: Path) -> list[dict]:
    data = []
    with path.open(encoding='utf-8', errors='replace') as f:
        for line in f:
            if 'column_html_show_ms' not in line:
                continue
            m = re.search(r'\{.*\}', line)
            if m:
                try:
                    d = json.loads(m.group())
                    data.append(d)
                except Exception:
                    pass
    return data


def summarize(vals: list[float]) -> str:
    if not vals:
        return 'N/A'
    med = statistics.median(vals)
    return f'count={len(vals):4}  sum={sum(vals):8.1f}ms  mean={sum(vals)/len(vals):7.2f}ms  med={med:7.2f}ms  max={max(vals):8.2f}ms'


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument('log_file', type=Path)
    args = p.parse_args()

    data = load_perf_entries(args.log_file)
    if not data:
        print('No column_html_show_ms entries found.')
        return 1

    by_type: dict[str, list[float]] = defaultdict(list)
    by_source: dict[str, list[float]] = defaultdict(list)
    by_ledger: dict[str | int, list[float]] = defaultdict(list)

    for d in data:
        ms = float(d.get('duration_ms', 0))
        by_type[d.get('render_kind', '?')].append(ms)
        by_source[d.get('source', '?')].append(ms)
        by_ledger[d.get('ledger_id', '?')].append(ms)

    print(f'\n=== column_html_show_ms by render_kind  (total entries: {len(data)}) ===')
    print(f'{"type":<20} {"count":>6} {"sum":>10} {"mean":>9} {"median":>9} {"max":>9}')
    for t, vals in sorted(by_type.items(), key=lambda x: -sum(x[1])):
        med = statistics.median(vals)
        print(f'{t:<20} {len(vals):6} {sum(vals):8.1f}ms {sum(vals)/len(vals):8.2f}ms {med:8.2f}ms {max(vals):8.2f}ms')

    print(f'\n=== by source ===')
    for s, vals in sorted(by_source.items(), key=lambda x: -sum(x[1])):
        med = statistics.median(vals)
        print(f'  {s:<30} count={len(vals):4}  total={sum(vals):8.1f}ms  mean={sum(vals)/len(vals):7.2f}ms  med={med:7.2f}ms  max={max(vals):8.2f}ms')

    print(f'\n=== Top 15 ledger_id by total show_ms ===')
    ledger_totals = {k: sum(v) for k, v in by_ledger.items()}
    for lid, total in sorted(ledger_totals.items(), key=lambda x: -x[1])[:15]:
        vals = by_ledger[lid]
        med = statistics.median(vals)
        print(f'  ledger_id={str(lid):>4}: total={total:7.1f}ms  count={len(vals):3}  mean={total/len(vals):6.2f}ms  med={med:6.2f}ms  max={max(vals):7.2f}ms')

    print(f'\nGrand total show_ms: {sum(d.get("duration_ms", 0) for d in data):.1f}ms')
    print(f'Unique ledger_ids: {len(by_ledger)}')

    # 異常スパイク検出 (> 20ms)
    spikes = [d for d in data if d.get('duration_ms', 0) > 20]
    if spikes:
        print(f'\n=== Spikes > 20ms ({len(spikes)} entries) ===')
        for d in sorted(spikes, key=lambda x: -x.get('duration_ms', 0)):
            print(f'  {d.get("duration_ms"):7.2f}ms  ledger={d.get("ledger_id"):>4}  col={d.get("column_id"):>3}  type={d.get("render_kind"):15}  source={d.get("source")}')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())

