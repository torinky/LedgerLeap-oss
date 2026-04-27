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
  - Evidence: [docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md](../ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md) の「6.6 Sprint 1 完了メモ」
  - Evidence: [docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-sprint1-completion.md](../ui-ux/navigation/2026-04-27_my-portal-folder-handoff-sprint1-completion.md)
  - Evidence: `./vendor/bin/sail test tests/Feature/Livewire/MyPortalTest.php` ✅
- [x] Sprint 2: マイポータル overview と遷移導線の再構成
  - Evidence: `app/Livewire/MyPortal.php`
  - Evidence: `resources/views/livewire/my-portal.blade.php`
  - Evidence: `resources/views/components/folder/tree.blade.php`
  - Evidence: `./vendor/bin/sail test tests/Feature/Livewire/MyPortalTest.php` ✅
- [x] Sprint 3: 台帳一覧の state handoff と回帰テスト
  - Evidence: `app/Livewire/Ledger/IndexManager.php`
  - Evidence: `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
  - Evidence: `tests/Feature/Http/Controllers/NotificationControllerTest.php`
  - Evidence: `tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php`
  - Evidence: `./vendor/bin/sail test tests/Feature/Livewire/MyPortalTest.php tests/Feature/Http/Controllers/NotificationControllerTest.php tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php` ✅

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
- [x] マイポータルの見た目と役割が overview として成立している
  - Evidence: `resources/views/livewire/my-portal.blade.php` の stats ベースの所属 / 役割表示
  - Evidence: `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-sprint1-completion.md`
- [x] フォルダツリーから台帳一覧へ自然に遷移できる
  - Evidence: `resources/views/components/folder/tree.blade.php` の `clickNavigatesToLedgerList`
  - Evidence: `tests/Feature/Livewire/MyPortalTest.php`
- [x] 遷移後に対象フォルダが選択済みとして見える
  - Evidence: `app/Livewire/Ledger/IndexManager.php` の `currentFolderId` / `selectedFolderIds` 初期化
  - Evidence: `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- [x] アイコン / badge / tooltip / CTA の意味が重複していない
  - Evidence: `resources/views/components/folder/tree.blade.php` の `showPermissionTooltip` 切り替え
  - Evidence: `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md` の「6.6 Sprint 1 完了メモ」
- [x] 変更後も tenant 文脈が維持される
  - Evidence: `tests/Feature/Http/Controllers/NotificationControllerTest.php` の global route / tenant id 検証
  - Evidence: `tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php` の `tenant_id` 参照
- [x] 回帰テストが追加されている
  - Evidence: `tests/Feature/Livewire/MyPortalTest.php`
  - Evidence: `tests/Feature/Http/Controllers/NotificationControllerTest.php`
  - Evidence: `tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php`
  - Evidence: `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`

## 関連リンク
- docs: `docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md`
- docs: `docs/function/MyPortal.md`
- Issue / PR: なし

## GitHub 追跡
- Parent Issue: `#176`
- Sprint 1: `#178`
- Sprint 2: `#179`
- Sprint 3: `#177`

## 確認事項
- [x] 改善イシューであることを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した
