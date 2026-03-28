# Issue #120 Sprint メモ: タブ切替の段階的 Livewire 化

**作成日**: 2026-03-28  
**関連 Issue**: #120

## 目的

台帳詳細画面のタブ切替で、毎回 Livewire の再描画を起こさず、初回訪問時だけ必要なタブコンテンツをロードする。

## 実施内容

### `Show`
- `notifyTabChange()` で初回訪問タブだけ Livewire 側に通知。
- 既訪問タブの切替は Alpine.js のローカル状態で完結するように変更。
- `selectedTab` / `loadedTabs` / `relatedCount` を Alpine 側へ同期。
- `RelatedLedgers` の `displayLevel` は reactive 依存を外し、`displayLevelUpdated` イベントで同期する構成に変更。

### `show.blade.php`
- タブヘッダーを独自 `tabs-lift` に維持。
- `related` 件数はバッジ表示に変更。
- 初回未訪問タブだけ `notifyTabChange()` を呼び、以後はサーバー往復しない。
- URL は `history.replaceState()` でローカル更新。

### `ledger-history-manager.blade.php`
- 右側の差分ビューアの loading 表示を `toggleSelection` / `historyDisplayLevel` に限定。
- タブ再表示や `loadMore()` によって比較表示がフラッシュしないように調整。
- 関連案件の無限スクロールは `x-intersect.once` にして、タブの再表示で同じ sentinel が再発火しないように変更。

### テスト
- `tests/Feature/Livewire/Ledger/RelatedLedgersTabTest.php`
- `tests/Feature/Livewire/Ledger/ShowAdditionalTest.php`
- `tests/Feature/Livewire/Ledger/ShowTest.php`

## 検証結果

```text
./vendor/bin/sail test tests/Feature/Livewire/Ledger/RelatedLedgersTabTest.php tests/Feature/Livewire/Ledger/ShowAdditionalTest.php
18 passed (46 assertions)

./vendor/bin/sail test tests/Feature/Livewire/Ledger/ShowTest.php
16 passed (47 assertions)
```

## メモ

- 以前の実装では、タブ切替のたびに Livewire を呼び、`RelatedLedgers` の重い描画を繰り返していた。
- 現在は「初回のみ Livewire」「以後は Alpine-only」に分離したため、関連案件タブの往復コストを抑えられる。
- 重い子コンポーネントは `#[Reactive]` を避け、必要な状態だけイベントで同期すると、親タブ切替の巻き込み再描画を避けやすい。

