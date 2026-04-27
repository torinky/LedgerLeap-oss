# 2026-04-27 issue 176 周辺作業の振り返りとスキル更新メモ

## 対象

- GitHub issue 176: マイポータルから台帳一覧へのフォルダ遷移を改善する
- 関連作業: issue 178 / issue 179 の再整理、通知の global route 化、portal hover ちらつき対策、issue 番号の整合修正

## 何が良かったか

- 既存の `currentFolderId` / `selectedFolderIds` 契約を再利用し、新しい state を増やさずに遷移導線を固定できた。
- グローバル通知ページでは `tenant_id` を明示的に持たせ、`tenant()` だけに依存しない route 生成へ寄せられた。
- portal のフォルダツリーでは、`wire:ignore` に頼り切らず、静的な `title` 表示へ切り替えて hover のちらつきを抑えられた。
- issue body、plan、completion report、テストを揃えて evidence を残し、後から見ても何を固定したか追いやすくできた。
- 途中で役割や scope が変わった箇所は、旧文言を残したまま足すのではなく、正しい番号と意図に合わせて書き直せた。

## 何が悪かったか

- ドキュメントと実際の GitHub issue 番号がずれ、Sprint 番号と issue 番号の対応が誤解されやすくなった。
- 最初の hover フラッシュ対策として `wire:ignore` を試したが、根本原因は tooltip / CSS / overflow の見え方で、Livewire の再描画だけではなかった。
- issue 176 と周辺 issue の役割分担が途中で混線し、番号だけでなく「どの sprint が何を担うか」を明示する必要があった。
- issue body の更新と doc の更新を別々に扱うと、どちらか一方に古い表記が残りやすかった。

## 上書き指示されたこと

- 途中で scope が変わったら、旧案を並べて残さず、今の正規ルートだけを見えるようにする。
- 直接的な見た目の症状が残るときは、まず局所的な応急処置を積み増すのではなく、直近の semantic anchor から書き直す。
- issue 番号の不一致は後でメモするのではなく、issue 本体と計画書の両方へ同じ対応表を戻してから進める。

## 普遍化して skills に落とすこと

### 1. issue 番号と sprint の対応は、GitHub を正とする

- GitHub issue の title / number / comments を先に確認し、ローカルの計画書やファイル名は補助情報として扱う。
- 計画書や issue 本文には `GitHub 追跡` のような対応表を置き、Sprint 番号と issue 番号のずれを残さない。
- issue 176 のように後から番号の見え方がずれた場合は、本文・計画・完了報告を同じタイミングで修正する。

### 2. Livewire の hover ちらつきは、再描画だけを疑わない

- `wire:ignore` で改善しない場合は、CSS、tooltip、overflow、z-index、hover 疑似要素の組み合わせを先に見る。
- 静的な一覧やツリーでは、補足情報を hover tooltip だけに置かず、`title` / `aria-label` / 補助テキストへ逃がす。
- hover に依存する表現は、accordion や横スクロール領域のような不安定なレイアウトでは先に外す。

### 3. tenant 文脈が消える global 画面では、モデル由来の tenant_id を使う

- `tenant()` が使えない global 画面では、route 生成やリンク表示を `tenant_id` で補強する。
- Livewire の更新リクエストで route パラメータが欠ける前提を置き、render 時点で最終 fallback を持つ。

### 4. 上書き指示は、その場で新しい正規ルートに書き戻す

- ユーザーが scope や表示方針を上書きしたら、旧方針をコメントで並べるのではなく、issue / doc / plan を同時に更新する。
- superseded な案は残さず、1 本の正規ルートに統合してから次へ進む。

## 証拠

- [docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-plan.md](navigation/2026-04-27_my-portal-folder-handoff-plan.md)
- [docs/work/ui-ux/navigation/2026-04-27_my-portal-folder-handoff-sprint1-completion.md](navigation/2026-04-27_my-portal-folder-handoff-sprint1-completion.md)
- [docs/work/issue-drafts/2026-04-27_my-portal-folder-handoff-issue.md](../issue-drafts/2026-04-27_my-portal-folder-handoff-issue.md)
- [tests/Feature/Livewire/MyPortalTest.php](../../../tests/Feature/Livewire/MyPortalTest.php)
- [tests/Feature/Http/Controllers/NotificationControllerTest.php](../../../tests/Feature/Http/Controllers/NotificationControllerTest.php)
- [tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php](../../../tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php)
- [tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php](../../../tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php)

## 検証

- `./vendor/bin/sail test tests/Feature/Livewire/MyPortalTest.php tests/Feature/Http/Controllers/NotificationControllerTest.php tests/Feature/Livewire/Workflow/OtherRelatedTasksListAdditionalTest.php tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- 25 tests passed を確認した。

## 次に skill へ反映すること

- issue を扱う skill には、GitHub の実 issue 番号と Sprint 番号の対応を先に確定するガードレールを入れる。
- Livewire / UI の skill には、hover ちらつきが残るときに `wire:ignore` だけで終えず、tooltip と CSS を先に点検するガードレールを入れる。
- どの skill でも、上書き指示があったら旧文言を残さず正規ルートへ書き戻す前提を強める。
