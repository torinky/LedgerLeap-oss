#!/usr/bin/env python3
"""Summarize LedgerLeap browser HAR files.

Usage:
  python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py path/to/a.har [path/to/b.har ...]

The script prints a compact markdown summary that highlights:
- total request count
- top slow requests
- livewire/update request count and payload size
- component breakdown for each livewire/update response
- a simple before/after comparison when multiple HAR files are passed
"""

from __future__ import annotations

import argparse
import base64
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable


@dataclass
class HarRequest:
    index: int
    url: str
    method: str
    status: int | None
    time_ms: float
    wait_ms: float | None
    receive_ms: float | None
    body_size: int | None
    content_size: int | None
    mime_type: str | None


@dataclass
class LivewireUpdate:
    index: int
    time_ms: float
    wait_ms: float | None
    response_size: int | None
    components: list[dict[str, Any]]


@dataclass
class HarSummary:
    path: Path
    total_entries: int
    requests: list[HarRequest]
    livewire_updates: list[LivewireUpdate]

    @property
    def top_slow_request(self) -> HarRequest | None:
        return max(self.requests, key=lambda r: r.time_ms, default=None)

    @property
    def total_livewire_update_size(self) -> int:
        return sum(update.response_size or 0 for update in self.livewire_updates)


def decode_content_text(content: dict[str, Any]) -> str:
    text = content.get('text', '')
    if not text:
        return ''
    if content.get('encoding') == 'base64':
        return base64.b64decode(text).decode('utf-8', errors='replace')
    return text


def load_har(path: Path) -> HarSummary:
    data = json.loads(path.read_text(encoding='utf-8'))
    entries = data.get('log', {}).get('entries', [])

    requests: list[HarRequest] = []
    livewire_updates: list[LivewireUpdate] = []

    for index, entry in enumerate(entries):
        request = entry.get('request', {})
        response = entry.get('response', {})
        timings = entry.get('timings', {})
        content = response.get('content', {})

        requests.append(
            HarRequest(
                index=index,
                url=request.get('url', ''),
                method=request.get('method', ''),
                status=response.get('status'),
                time_ms=float(entry.get('time', 0) or 0),
                wait_ms=timings.get('wait'),
                receive_ms=timings.get('receive'),
                body_size=response.get('bodySize'),
                content_size=content.get('size'),
                mime_type=content.get('mimeType'),
            )
        )

        if 'livewire/update' in request.get('url', ''):
            response_text = decode_content_text(content)
            components: list[dict[str, Any]] = []
            if response_text:
                try:
                    response_json = json.loads(response_text)
                    components = response_json.get('components', [])
                except json.JSONDecodeError:
                    components = []
            livewire_updates.append(
                LivewireUpdate(
                    index=index,
                    time_ms=float(entry.get('time', 0) or 0),
                    wait_ms=timings.get('wait'),
                    response_size=content.get('size'),
                    components=components,
                )
            )

    return HarSummary(
        path=path,
        total_entries=len(entries),
        requests=requests,
        livewire_updates=livewire_updates,
    )


def format_request(row: HarRequest) -> str:
    return (
        f"{row.time_ms:7.1f}ms | {row.method:<4} | {row.status or '-':>3} | "
        f"body={row.body_size if row.body_size is not None else '-':>9} | "
        f"content={row.content_size if row.content_size is not None else '-':>9} | "
        f"wait={row.wait_ms if row.wait_ms is not None else '-':>8} | {row.url}"
    )


def component_name(component: dict[str, Any]) -> str:
    try:
        snapshot = json.loads(component.get('snapshot', '{}'))
        return snapshot.get('memo', {}).get('name', '<unknown>')
    except json.JSONDecodeError:
        return '<unknown>'


def component_lengths(component: dict[str, Any]) -> tuple[int, int]:
    html = component.get('effects', {}).get('html', '')
    snapshot = component.get('snapshot', '')
    return len(html), len(snapshot)


def print_summary(summary: HarSummary) -> None:
    print(f"## {summary.path.name}")
    print(f"- total entries: {summary.total_entries}")
    print(f"- livewire/update count: {len(summary.livewire_updates)}")
    print(f"- livewire/update total size: {summary.total_livewire_update_size}")

    top = summary.top_slow_request
    if top is not None:
        print(f"- slowest request: {top.time_ms:.1f}ms {top.method} {top.url}")

    print("\n### Top slow requests")
    for row in sorted(summary.requests, key=lambda r: r.time_ms, reverse=True)[:10]:
        print(f"- {format_request(row)}")

    print("\n### livewire/update breakdown")
    if not summary.livewire_updates:
        print("- (none)")
        return

    for update in summary.livewire_updates:
        print(
            f"- idx={update.index} time={update.time_ms:.1f}ms "
            f"wait={update.wait_ms if update.wait_ms is not None else '-'} "
            f"size={update.response_size if update.response_size is not None else '-'}"
        )
        for component in update.components:
            name = component_name(component)
            html_len, snapshot_len = component_lengths(component)
            print(f"  - {name}: html={html_len} snapshot={snapshot_len}")


def print_comparison(first: HarSummary, second: HarSummary) -> None:
    print("\n## Comparison")
    print(f"- {first.path.name}: livewire/update={len(first.livewire_updates)}, total_size={first.total_livewire_update_size}")
    print(f"- {second.path.name}: livewire/update={len(second.livewire_updates)}, total_size={second.total_livewire_update_size}")
    print(
        f"- delta updates: {len(second.livewire_updates) - len(first.livewire_updates)}, "
        f"delta size: {second.total_livewire_update_size - first.total_livewire_update_size}"
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='Summarize LedgerLeap browser HAR files.')
    parser.add_argument('har_files', nargs='+', type=Path, help='One or more HAR files to summarize')
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    summaries = [load_har(path) for path in args.har_files]

    for summary in summaries:
        print_summary(summary)
        print()

    if len(summaries) >= 2:
        print_comparison(summaries[0], summaries[1])

    return 0


if __name__ == '__main__':
    raise SystemExit(main())

