# Issue #192 / フォルダ切替後の描画遅延調査 振り返り

**作成日**: 2026-05-03  
**対象 Issue**: [#192](https://github.com/torinky/LedgerLeap/issues/192)

---

## 1. 何を調べたか

Issue #192 では、フォルダツリーでフォルダを切り替えた後に発生する台帳一覧の描画遅延を、既存の計測資産だけを使って切り分けた。

今回の調査では、新しいログ機構や新規テストは追加していない。既存の `storage/logs/laravel-2026-05-03.log` と `storage/logs/performance_stats.json`、および `IndexManager` / `RecordsTable` / `Folder\Tree` / `folder-and-ledger-panels` のコード読解をベースにした。

---

## 2. Sprint 1 で固定したこと

### 2.1 再現条件

- `ledger_change_current_folder` は 50 サンプルあり、中央値 **36.04ms** / 平均 **47.11ms** / 最大 **408.75ms** を確認した
- 遅延は `current_folder_id=3` / `11`、`display_level=1|2|3`、`has_workflow_enabled=true|false` などの条件でばらついた
- したがって、単一の固定条件ではなく、フォルダ状態と表示状態の組み合わせで遅延の出方が変わる

### 2.2 観測方法

- サーバー側: 既存の performance ログを再利用
- ブラウザ側: DevTools の Network / Performance で再描画と scripting を確認
- DB 側: 既存のクエリ時間出力で確認

### 2.3 ベースライン

- `ledger_index_mount`
- `ledger_records_mount`
- `ledger_records_render`
- `ledger_change_current_folder`

以上の既存観測点がそろっており、追加の計測導入は不要だった。

---

## 3. Sprint 2 で絞れたこと

### 3.1 主因

主因は `changeCurrentFolder()` 単体ではなく、`RecordsTable::render()` を中心とした Livewire の再描画パスだった。

### 3.2 補助因子

- `Folder\Tree` も `#[Reactive]` により頻繁に再描画されていた
- 親子コンポーネントの再評価が体感遅延を押し上げていた

### 3.3 N+1 の扱い

`folder-and-ledger-panels` に `ledgers()->count()` の fallback は残るが、`IndexManager` と `RecordsTable` の両経路で `withCount(['ledgers'])` / `withCount(['ledgerDefines'])` が付いていたため、今回の画面の主因とは見なしにくいと判断した。

---

## 4. 既存ログから読めたこと

- `ledger_change_current_folder`: **51 件**
  - 中央値 **36.04ms**
  - 平均 **47.11ms**
  - 最大 **408.75ms**
- `ledger_records_render`: **24 件**
  - 中央値 **109.64ms**
  - 平均 **117.26ms**
  - 最大 **182.08ms**
- `IndexManager render`: **1406 件**
- `[Folder\Tree] rendering`: **1468 件**

この分布から、フォルダ切替の遅延はサーバー単発処理よりも、再描画と再評価の積み上がりが支配的だと読めた。

---

## 5. 学び

### 5.1 `changeCurrentFolder()` だけ見ても全体像は掴めない

フォルダ切替の入口処理が速くても、`RecordsTable::render()` と `Folder\Tree` の再評価が重いと、ユーザー体感は遅くなる。

そのため、今後同系統の遅延を見るときは、入口メソッドだけでなく、再描画される Livewire 子コンポーネント全体を一緒に見る。

### 5.2 `#[Reactive]` は便利だが再描画コストも伴う

`#[Reactive]` は親子同期を簡潔にする一方、更新の波及範囲が広いと再描画コストが増える。

表示の正しさだけでなく、「どこまで再評価されるか」を観測対象に含める必要がある。

### 5.3 既存の観測点を先に使う方が速い

今回は新規ログや新規テストを足さず、既存の観測点だけで十分に切り分けできた。

遅延調査では、まず既存の `performance_stats.json` とログを再集計し、必要な場合だけ追加計測に進む方が無駄が少ない。

---

## 6. docs/work / .github の扱い

今回の学びは、特定機能に閉じた調査結果として `docs/work` に留める。

- `.github` 側の新しい skill 化はしない
- 再利用する場合でも、まずはこの振り返りと Issue #192 を参照する
- 次に同系統の遅延が出たときに、再描画パスと reactive propagation を優先して確認する

---

## 7. 参照先

- Issue: [#192](https://github.com/torinky/LedgerLeap/issues/192)
- 既存ログ: `storage/logs/laravel-2026-05-03.log`
- 既存集計: `storage/logs/performance_stats.json`
- 対象コード: `app/Livewire/Ledger/IndexManager.php`
- 対象コード: `app/Livewire/Ledger/RecordsTable.php`
- 対象コード: `app/Livewire/Folder/Tree.php`
- 対象コード: `resources/views/components/folder/folder-and-ledger-panels.blade.php`
- 先行振り返り: `docs/work/core-features/confidentiality-classification/2026-05-03_retrospective_issue191.md`

---

## 8. Freshness

- status: confirmed
- last_confirmed_at: 2026-05-03
- recheck_after: 次回 `IndexManager` / `RecordsTable` / `Folder\Tree` の再描画構成、または `folder-and-ledger-panels` のカウント処理を変更するとき
- recheck_trigger: フォルダ切替後の遅延が再発する、または Livewire の再描画対象を変更する

