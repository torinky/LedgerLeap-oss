# Issue #116 Sprint1 メモ: タブキャッシュ基盤

**作成日**: 2026-03-21  
**関連 Issue**: #116

## 目的

台帳詳細画面とファイルインスペクターで、タブを一度開いたあとに別タブへ戻っても、既読タブを再初期化しないための基盤を追加した。

## 実施内容

### `Show`
- `loadedTabs` を追加し、初回訪問済みタブを保持。
- `navigateToTab()` / `switchToHistoryTab()` / `updatedSelectedTab()` で訪問済みタブを記録。
- `mount()` では、未初期化時のみ現在タブを初期登録。

### `FileInspector`
- `loadedTabs` を追加し、開いたタブを追跡。
- `openInspector()` で `content` タブに初期化。
- `close()` でキャッシュを破棄し、別ファイルを開く境界を明確化。
- `updatedSelectedTab()` で訪問済みタブを記録。

### Blade
- `ledger/show.blade.php` と `attached-file/file-inspector.blade.php` を、訪問済みタブのみ child component / partial を保持する条件に変更。

## テスト

- `tests/Feature/Livewire/Ledger/ShowAdditionalTest.php`
  - `it_tracks_loaded_tabs_across_tab_changes_and_refreshes`
- `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
  - `it_tracks_loaded_tabs_and_resets_them_on_close`

## 検証結果

```text
./vendor/bin/sail test tests/Feature/Livewire/Ledger/ShowAdditionalTest.php tests/Feature/Livewire/AttachedFile/FileInspectorTest.php
47 passed (122 assertions) / Duration: 107.07s
```

## 次の Sprint への接続

Sprint2 では、ここで導入した `loadedTabs` 基盤の上に、
- 初回表示の skeleton / placeholder
- `wire:loading.target` の局所化
- ファイルインスペクターのタブ別ローディング

を重ねる。
