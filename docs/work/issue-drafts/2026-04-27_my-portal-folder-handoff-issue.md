# [Issue]: マイポータルから台帳一覧へのフォルダ遷移を改善する

## イシュー種別
改善

## 概要
マイポータルのフォルダツリーから、台帳一覧のフォルダ選択状態へ自然に遷移できる導線を追加したい。
マイポータルは overview としての役割を維持しつつ、担当フォルダを起点に次の作業場所へ迷わず進める構成にしたい。

## 背景 / 目的
- 初度ポータルは、所属・役割・権限・担当範囲を素早く把握する場所として機能している。
- 一方で、フォルダツリーで対象を見つけても、その状態のまま台帳一覧へ移りづらい。
- ポータルを台帳一覧の代替ブラウザにせず、状況把握と遷移導線に役割を分けたい。
- 多くの利用者が直感的に理解しやすい、最大公約数的な UI に寄せたい。

## 現状
- 参照ファイル:
  - `app/Livewire/MyPortal.php`
  - `resources/views/livewire/my-portal.blade.php`
  - `resources/views/components/folder/tree.blade.php`
  - `app/Livewire/Folder/Tree.php`
  - `app/Livewire/Ledger/IndexManager.php`
  - `resources/views/livewire/ledger/index-manager.blade.php`
  - `resources/views/components/ledger/search.blade.php`
  - `docs/function/MyPortal.md`
  - `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md`
- 既存の挙動:
  - マイポータルで役割、権限、担当フォルダ、詳細フォルダツリーを確認できる。
  - 台帳一覧は `currentFolderId` / `selectedFolderIds` / `selectedLedgerDefineIds` を持っている。
  - フォルダ選択状態は一覧側で使えるが、ポータルからその状態へ自然に入る導線が弱い。
- 制約:
  - tenant 文脈を失わないこと。
  - 既存の `currentFolderId` / `selectedFolderIds` の契約を再利用すること。
  - 台帳一覧の検索・表示レベル・ページングは今回の主対象にしないこと。

## 目標 / 完了状態
- マイポータルが overview として成立している。
- フォルダツリーから台帳一覧へ自然に遷移できる。
- 遷移後の台帳一覧で、対象フォルダが選択済みとして見える。
- アイコン、badge、tooltip、CTA の役割分担が整理されている。
- 初見でも理解しやすい、業務画面としての見やすさが確保されている。

## スコープ / 非スコープ
**対象:**
- マイポータルの情報構成と導線整理
- フォルダツリーの遷移導線追加
- 台帳一覧への state handoff
- 必要最小限の文言とデザインの見直し
- 回帰テストとレンダリング確認

**対象外:**
- 台帳一覧の検索・表示レベル・ページングの再設計
- グローバルのテナント選択画面の刷新
- 権限ロジックやフォルダ権限の再実装
- ポータルを台帳一覧ブラウザへ変質させる拡張

## 方針候補 / メモ
1. フォルダ行そのものを台帳一覧への遷移対象にする。
2. フォルダ行に明示的な CTA を置き、overview と action を分離する。
3. アイコンは意味補助に限定し、ラベルと一緒に読める設計にする。
4. badge は状態や件数などの短いメタ情報に限定する。
5. tooltip は補足説明に使い、主メッセージは本文側で読めるようにする。

## スプリント分解
- [x] Sprint 1: 情報設計とデザイン方針の確定
- [ ] Sprint 2: マイポータル overview と遷移導線の再構成
- [ ] Sprint 3: 台帳一覧の state handoff と回帰テスト

## エビデンス / 参照先
- `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md`
- `docs/work/ui-ux/2026-04-26_livewire-folder-tree-current-folder-sync-retrospective.md`
- `docs/work/ui-ux/ledger-list-redesign/2026-03-29_ledger-list-url-normalization-plan.md`
- `docs/work/ui-ux/2026-04-11_ledger-index-manager-ui-plan.md`
- `docs/work/ui-ux/2026-04-11_text-writing-guidance.md`
- `docs/work/ui-ux/2026-04-18_text-icon-size-responsiveness-note.md`
- `docs/work/ui-ux/2026-04-25_permission-display-overview-first-plan.md`
- `app/Livewire/MyPortal.php`
- `resources/views/livewire/my-portal.blade.php`
- `resources/views/components/folder/tree.blade.php`
- `app/Livewire/Folder/Tree.php`
- `app/Livewire/Ledger/IndexManager.php`
- `resources/views/livewire/ledger/index-manager.blade.php`
- `resources/views/components/ledger/search.blade.php`

## 完了条件
- [ ] マイポータルの見た目と役割が overview として成立している
- [ ] フォルダツリーから台帳一覧へ自然に遷移できる
- [ ] 遷移後に対象フォルダが選択済みとして見える
- [ ] アイコン / badge / tooltip / CTA の意味が重複していない
- [ ] 変更後も tenant 文脈が維持される
- [ ] 回帰テストが追加されている

## 関連リンク
- docs: `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md`
- docs: `docs/function/MyPortal.md`
- Issue / PR: なし

## Sprint 1 完了メモ
- ポータルは overview、台帳一覧は workbench と役割分担する方針で確定した
- アイコンは意味補助に限定し、ラベルなしで意味を成立させない方針で確定した
- 所属、役割、権限、担当フォルダは近接させて一塊で読ませる方針で確定した
- badge は短いメタ情報に限定し、tooltip は補足説明に限定する方針で確定した
- 文字サイズとアイコンサイズは、desktop で読める標準寄りのサイズ感を優先する方針で確定した
- フォルダツリーから台帳一覧への遷移は、既存の `currentFolderId` / `selectedFolderIds` 契約を再利用する方針で確定した
- Sprint 2 以降は、この設計方針を前提に view と state handoff を実装する
- 2026-04-27 時点で Sprint 1 は完了済み

## 確認事項
- [x] 改善イシューであることを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した
