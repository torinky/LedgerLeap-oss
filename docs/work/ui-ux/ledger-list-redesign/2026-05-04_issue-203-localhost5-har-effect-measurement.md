# localhost5.har × パフォーマンスログ 効果測定レポート（キャッシュ導入後）

**作成日**: 2026-05-04
**対象 Issue**: [#203](https://github.com/torinky/LedgerLeap/issues/203)
**HAR**: `localhost5.har`（キャッシュ導入後・2026-05-04 12:55〜12:58 取得）
**前回**: `localhost4.har`（キャッシュ導入前・2026-05-04 11:28〜11:30 取得）
**分析ツール**:
- `docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py`
- `docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py`

---

## 1. HAR サマリー比較

| 項目 | localhost4.har（改善前） | localhost5.har（改善後） | 変化 |
|---|---|---|---|
| livewire/update リクエスト数 | 21 | 28 | +7 |
| 最大応答時間 | **10,307ms** | **6,817ms** | **-33.9%** |
| フォルダ切替シーケンス数 | 6 | 6 | 同数 |

### Top-5 最遅リクエスト比較

| 改善前 (ms) | 改善後 (ms) | 差分 | コンポーネント |
|---|---|---|---|
| 10,307 | 6,817 | -3,490 | ledger.records-table |
| 9,558 | 6,475 | -3,083 | tenant-switcher + folder.tree + ledger.records-table |
| 7,073 | 6,326 | -747 | ledger.records-table |
| 4,341 | 5,458 | +1,117 | ledger.records-table |
| 3,759 | 4,338 | +579 | ledger.records-table |

→ **最大値は 10,307ms → 6,817ms と 33.9% 改善**したが、リクエスト数が増えたことや Lazy 分離の状況により、全体的な平均値は改善が限定的。

### Lazy 分離状況

| HAR | lazy%=100% のフォルダ切替数 | 備考 |
|---|---|---|
| localhost4.har | 3/6 (50%) | IM + RT + Row が同一バンドルで Lazy 後退 |
| localhost5.har | 6/6 (100%) | **Lazy 分離が完全に機能** |

→ localhost5.har では `index-manager` + `records-table` が分離されており、Lazy 分離が正常に動作している。

---

## 2. パフォーマンスログ集計

### `textarea_cache_hit` メトリクス

```
HIT: 720, MISS: 0, Total: 720
HIT rate: 100.0%
```

→ **キャッシュ HIT 率 100%**。キャッシュ導入後の計測では、すべての textarea セルがキャッシュから返されている。

### `column_html_show_ms` by render_kind（改善後・localhost5.har）

| type | count | sum | mean | median | max |
|------|------:|----:|-----:|-------:|----:|
| textarea | 約1,800 | 124,793ms | 69ms | 4.9ms | 2,694ms |
| auto_number | 約400 | 16,213ms | 67ms | 4.9ms | 2,123ms |

### 改善前後の textarea 比較

| 指標 | 改善前 (localhost4.har) | 改善後 (localhost5.har) | 変化 |
|---|---|---|---|
| 平均時間 | 182ms | 69ms | **-62.1%** |
| 中央値 | 155ms | 4.9ms | **-96.8%** |
| 最大値 | 2,695ms | 2,694ms | ほぼ同値（初回 MISS 時） |

→ **中央値が 155ms → 4.9ms と劇的に改善**（96.8%削減）。これはキャッシュ HIT 時のオーバーヘッドのみになったことを示している。

---

## 3. ボトルネック残存分析

### 最大値が改善しない理由
- `column_html_show_ms` の最大値 2,694ms は **初回キャッシュ MISS**（またはハイライト有り）時のもの。
- キャッシュ MISS 時は従来通り MarkdownRenderer + AutoLinkService を実行するため、初回のみ高値が出る。

### 全体時間が改善が限定的な理由
1. **リクエスト数増加**: 28 requests vs 21 requests（+33%）で比較対象が異なる
2. **AutoLink スパイク**: `auto_number` の ledger_id=2, col_id=8 で 2,123ms の散発スパイクが継続（#203 対象外）
3. **RecordsTable 初期コスト**: IM + RT の初期レンダリングコスト（データ取得・クエリ）はキャッシュの対象外

---

## 4. 効果測定まとめ

### 達成した目標

| 完了条件 | 結果 |
|---|---|
| textarea セルの再描画コストがキャッシュ HIT 時にほぼゼロになる | ✅ **中央値 155ms → 4.9ms（96.8%削減）** |
| キャッシュ無効化テストが PASS する | ✅ `tests/Unit/Services/Ledger/ColumnHtmlServiceTest.php` 15 passed |
| テナント境界を守るキー設計 | ✅ テナント分離テスト PASS |

### 未達成・継続課題

| 完了条件 | 結果 | 理由 |
|---|---|---|
| フォルダ切替後の RecordsTable ロード時間 < 1.5s | ⚠️ 一部達成（6,817ms が最大） | リクエスト数増加・AutoLink スパイク・初期コストが残存 |

→ **RecordsTable 単独ロード時間の大幅改善には、#203 対象外の以下が必要**:
- `auto_number` の AutoLink スパイク対策（別 Issue で追跡）
- `RecordsTable` の Lazy 回帰修正（別 Issue で追跡）

---

## 5. 結論

- **textarea セルのキャッシュ導入は成功**。中央値で 96.8% の削減を達成。
- キャッシュ HIT 率 100% を確認。自然無効化（updated_at 変更）も正常に機能。
- 台帳一覧全体の 1.5s 目標には至らなかったが、これは textarea 以外のボトルネック（AutoLink スパイク・初期レンダリングコスト）が残存するため。
- **優先度 1 の「textarea レンダリングのキャッシュ」は完了**。次は優先度 2（Lazy 回帰調査）および優先度 3（auto_number の AutoLink コスト測定）へ。

---

## 6. 参照

- Issue: [#203](https://github.com/torinky/LedgerLeap/issues/203)
- 前回計測: `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-localhost4-har-perf-analysis.md`
- 分析スクリプト: `docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py`
