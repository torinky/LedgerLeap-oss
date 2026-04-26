# Livewire フォルダーツリー currentFolder 同期 レトロスペクティブ

**作成日**: 2026-04-26  
**対象**: `app/Livewire/Ledger/IndexManager.php` / `app/Livewire/Folder/Tree.php` / 関連テスト

## 1. 何を直したか

フォルダーをクリックして `currentFolderId` を切り替えたあと、URL クエリは変わるのにツリー表示が追従しない問題を修正した。

対応は次の 2 点。

- 親コンポーネントでフォルダー切り替え後に `currentFolderChangedByMain` を明示的に dispatch する
- ツリーコンポーネントでそのイベントを受けて `standaloneFolderId` を常に同期する

追加した回帰テストは次の 2 つ。

- `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- `tests/Feature/Livewire/Folder/TreeTest.php`

## 2. 良かったこと

- 原因候補を広げすぎず、まず「親の状態更新」と「子の受信経路」を分けて確認できた。
- URL クエリだけで判断せず、実際にツリーの描画条件がどこから来るかを見たことで、再読み込み依存の見落としを防げた。
- 修正後すぐに、親の dispatch と子の同期の両方をテストで固定できた。

## 3. 悪かったこと

- 最初の見立てでは、`currentFolderId` の更新だけで再描画が追従する前提に寄っていた。
- ツリー側の状態が `#[Reactive]` とイベント同期の両方に依存しているのに、そこを分離した回帰テストが足りなかった。
- 画面上の症状が「URL は変わるが見た目が変わらない」だったため、Livewire の event bridge を疑うのが少し遅れた。

## 4. 次回に残す判断基準

- URL が変わっただけでは成功扱いにしない。
- 親の state 更新と子の受信イベントを別々に確認する。
- `currentFolderId` の変更で見た目が変わるはずの場面では、`dispatch()` の有無を最初に点検する。
- 親子同期の問題は、`assertSet` だけでなく `assertDispatched` と子コンポーネントの状態確認まで入れる。

## 5. Skill review

`skill-maintenance` を振り返った結果、今回の学びは **まだ feature-local な Livewire 同期パターン** の範囲に留まると判断した。

- 再利用価値はあるが、今はフォルダーーツリーの event bridge に強く依存している
- `livewire-tenant-context` や `livewire-loading-ui` に近い一般化済みパターンではない
- したがって `.github/skills/*` へ昇格せず、この `docs/work` 記録を再参照先として残す

## 6. 参照先

- 実装: `app/Livewire/Ledger/IndexManager.php`
- 実装: `app/Livewire/Folder/Tree.php`
- テスト: `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- テスト: `tests/Feature/Livewire/Folder/TreeTest.php`

## 7. Freshness

- status: confirmed
- last_confirmed_at: 2026-04-26
- recheck_after: 次回の Livewire 親子同期、`#[Reactive]`、または current folder 切り替え表示の修正時
- recheck_trigger: URL は変わるのに表示が追従しない再発、または `currentFolderChangedByMain` / `currentFolderChangeRequested` の経路変更
