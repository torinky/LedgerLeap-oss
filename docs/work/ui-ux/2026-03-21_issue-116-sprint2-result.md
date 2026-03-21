# Issue #116 Sprint2 Result: ローディング表示の分離

**作成日**: 2026-03-21  
**関連 Issue**: #116

## 完了内容

### 台帳詳細画面
- `history` / `activity` / `permissions` / `related` タブで、初回表示時のみ skeleton を表示するように整理。
- 再訪時は `x-element.loading-overlay` の spinner 表示のみになるように分離。
- `loadedTabs` は Sprint1 のまま維持し、再訪時の DOM 保持とローディングの役割を分けた。

### ファイルインスペクター
- `details` / `history` / `permissions` タブで、初回表示 skeleton と再訪 spinner を分離。
- `selectedTab` と `loadedTabs` の組み合わせで、初回ロードと再訪ロードを区別できるようにした。
- `content` タブは既存の drawer-level skeleton / source switching loading を維持。

## テスト

- `./vendor/bin/sail test tests/Feature/Livewire/Ledger/ShowAdditionalTest.php tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
- Result: `47 passed (122 assertions) / Duration: 68.44s`

## 備考

- `lazy` / `placeholder()` は初回表示専用、`wire:loading.target` は局所更新専用という役割分担に沿う形で整理した。
- Sprint3 では `Livewire::withoutLazyLoading()` を活用した最終検証を行う。
