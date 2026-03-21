# Issue #116 Sprint2 Plan: ローディング表示の分離

**作成日**: 2026-03-21  
**関連 Issue**: #116  
**前提**: Sprint1 で `loadedTabs` 基盤を導入済み

## 目的

Sprint1 で導入した訪問済みタブ追跡を前提に、初回表示の重い skeleton / placeholder と、再訪時の最小ローディングを分離する。

## 反映方針

### 台帳詳細画面
- `details` / `history` / `activity` / `permissions` / `related` の初回表示 skeleton を、`loadedTabs` 未到達時だけに限定する。
- 再訪時は、初回 skeleton ではなく最小限の `wire:loading.target` のみを残す。
- `ledger-diff-viewer` / `related-ledgers` の既存 lazy / placeholder 設計は壊さず、初回のみの役割として整理する。

### ファイルインスペクター
- `content / details / history / permissions` の各タブで、初回表示時と再訪時の見た目を分ける。
- `switchSource` や `searchKeyword` などの操作には `wire:loading.target` を局所化する。
- タブ再訪時には、空白を出しすぎず、既存 DOM を活かした表示へ寄せる。

## 実装候補

1. 初回 skeleton の条件を `loadedTabs` に連動させる
2. 再訪時の loading 表示を `wire:target` 単位で絞る
3. 必要なら tab partial 側に初回表示用の placeholder を分離する

## テスト観点

- 初回表示時のみ skeleton / placeholder が出る
- 再訪時は skeleton が消え、コンテンツを保持したまま表示される
- `switchSource` / `searchKeyword` などの局所更新時に、対象タブだけ loading する
- `close()` 後は `loadedTabs` がリセットされる

## ロールバック

- `loadedTabs` 条件分岐を戻す
- 各タブの skeleton / placeholder の表示条件を元に戻す
- `wire:loading.target` を既存の広めの指定へ戻す

## 次アクション

1. `resources/views/livewire/ledger/show.blade.php` の初回 skeleton 条件を見直す
2. `resources/views/livewire/attached-file/file-inspector.blade.php` の各タブ loading 条件を整理する
3. 追加テストを入れて、再訪時の表示差分を検証する
