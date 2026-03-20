# Livewire update 重複リクエストの解消メモ

- 日付: 2026-03-20
- 対象 issue: #111, #113
- 関連ハーネス: `storage/logs/localhost2.har`, `storage/logs/localhost3.har`, `storage/logs/localhost4.har`, `storage/logs/localhost5.har`

## 結論
台帳一覧の初期表示遅延は、単一の SQL だけではなく、**複数の Livewire update リクエスト** が同じ重いコンポーネント群を再取得していたことが主因だった。

最終的に以下の連鎖を遮断することで、再取得の重複を解消できた。

- `RecordsTable::render()` からの件数同期を `recordsUpdated` 依存から切り離し
- 件数表示を `ledger-records-count-updated` + Alpine のクライアント状態に寄せる
- 初期オーバーレイの timing telemetry を `$wire.logPerformance(...)` から外し、Livewire 更新を増やさないようにする

## 主要な証拠

### 1. `localhost4.har` の状況
- `livewire/update` が **2 回** の大きい往復として残っていた
- どちらも **841,483 bytes** の response
- 含まれるコンポーネント:
  - `ledger.records-table`: **519,962 bytes**
  - `folder.tree`: **113,815 bytes**
  - `ledger.index-manager`: **76,746 bytes**
  - `ledger.export`: **340 bytes**

### 2. `localhost5.har` の状況
- 大きい `livewire/update` は **1 回** に整理された
- 残った `livewire/update` は **1,035 bytes**
- 返しているのは `ledger.export` のみ
- `ledger.records-table` / `folder.tree` / `ledger.index-manager` の同時再取得は消失

### 3. 補助メトリクス
- `ledger_records_query_count_ms` はキャッシュで **0ms** まで低下
- ただし最終的な体感改善は、件数キャッシュよりも **再レンダー連鎖の遮断** による寄与が大きかった

## 実装で行った変更
- `app/Livewire/Ledger/RecordsTable.php`
  - `ledger-records-count-updated` を dispatch
  - `totalRecords` の更新を最小限に整理
- `resources/views/livewire/ledger/index-manager.blade.php`
  - 件数バッジを Alpine 状態で表示
  - `wire:ignore` を付与して親 rerender の巻き戻りを防止
  - `ledger-init-overlay:timing` は Livewire ではなくブラウザ側で処理
- `app/Livewire/Ledger/IndexManager.php`
  - `recordsUpdated` に依存しない件数同期へ整理

## 再発防止のポイント
- 計測イベントが「計測のための Livewire action」を発火していないか確認する
- 件数表示や軽い UI 更新は、必要ならブラウザイベント / Alpine で完結させる
- `refreshChildren` / `recordsUpdated` のような再レンダー連鎖を作るイベントは、発火点を限定する

## 関連 issue コメント
- #111: `https://github.com/torinky/LedgerLeap/issues/111`
- #113: `https://github.com/torinky/LedgerLeap/issues/113`

