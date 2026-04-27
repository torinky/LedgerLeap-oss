# File Inspector 検索条件の再オープン維持メモ

- **対象**: 台帳一覧 / 台帳詳細の添付ファイル一覧から開く `FileInspector`
- **症状**: 一度閉じて同じファイルを再度開くと、詳細画面で復元される検索キーワードが inspector 側に渡らないことがあった。
- **原因**: 詳細画面の full mode では `components.ledger.attachment-card` が `open-file-inspector` を `id` / `column_id` だけで発火しており、検索語が payload に入っていなかった。
- **修正**:
  - `attachment-card` に `data-search` を付与し、クリック時に親の `handleFileClick(..., $event)` へ渡す
  - `attachment-list` / `FileInspector` の既存 fallback は維持
- **回帰テスト**:
  - `tests/Feature/Components/AttachmentListComponentTest.php`
  - `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
- **確認結果**: `./vendor/bin/sail test` で上記 2 ファイルの関連テストが通過

## 補足

- 一覧の compact / icon-only モードは既に `data-search` を使っていたため、今回の差分は detail の full mode に限定される。
- 再オープン時は URL query fallback に頼り切らず、UI から検索語を payload に載せるほうが安定する。

