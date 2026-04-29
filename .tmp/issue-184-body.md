## イシュー種別
運用改善

## 概要
管理者お知らせを既存通知センターへ統合し、再確認導線と回帰テストを整備する。公開表示で見逃した内容を後から確認できる状態にする。

## 背景 / 目的
主UI は上部表示だが、既存通知センターを裏側統合先として活用すれば再確認・履歴確認に使える。管理者側には別途一覧・作成・公開・停止の導線が必要だが、現行の計画ではそこが抜けていたため再計画する。

## 現状
- 通知センターへの再確認導線は実装済み
- 既存の unread / activity / task 通知は壊していない
- 管理側の一覧画面は実装済みで、作成・編集・公開・停止の運用導線も整った
- 管理側の一覧と公開導線は `AdminAnnouncementResource` ベースに整理済み

## 目標 / 完了状態
- 同じ告知を通知データとして同期できる
- 通知センターで再確認できる
- 管理者は一覧から作成・編集・公開・停止を行える
- 既読や全既読の既存導線と衝突しない
- 公開表示、通知センター、管理側一覧の回帰テストがある

## スコープ / 非スコープ
対象:
- 通知センターへの同期
- 管理側一覧と公開導線
- 再確認導線
- 既読基盤との整合性
- 回帰テスト

対象外:
- 新しい分析基盤
- 複数件積み上げ表示の高度な可視化
- 国際化や多言語配信

## サブスプリント案
### 4-A. 同期方式と payload 仕様を確定する
- 目的: 上部表示の告知をどの経路で database 通知に落とすか決める
- 主な確認点: 既存 `NotificationType` を流用するか、新しい type を追加するか / payload に `announcement_id` と再確認リンクをどう載せるか / tenant 共通か global 共通か
- 成果物: 同期経路、payload 形、idempotency、既読との関係の方針

### 4-B. 通知センターに同じ告知を出す
- 目的: `notifications.index` で再確認できるようにする
- 主な確認点: 既存タブに混ぜるか専用表示を作るか / リンク遷移先 / 通知一覧の表示文言
- 成果物: 再確認導線と表示ルール

### 4-C. 管理側一覧と公開導線を作る
- 目的: 管理者が告知を一覧で把握し、作成・編集・公開・停止まで完結できる画面を用意する
- 主な確認点: 下書き / 公開中 / 停止中の一覧表示、複数件の優先度順、公開期間、編集入口、管理側からの再確認導線
- 成果物: 管理者向けの一覧画面、編集・公開・停止の導線、複数通知の運用単位

### 4-D. 既読と dismiss の責務分離を確認する
- 目的: ユーザーのローカル保存と通知の再確認を混同しないようにする
- 主な確認点: `revision` 更新時の再表示、localStorage key の命名、ユーザーごとの DB 既読を積まない前提、公開期間との整合
- 成果物: 既読・dismiss・公開期間の責務分離ルール

### 4-E. 回帰テストを追加する
- 目的: 管理側一覧、上部バナー、通知センターの 3 画面がそれぞれ壊れていないことを固定する
- 対象: 管理者一覧の表示テスト / 公開・停止テスト / 通知センターの再確認テスト / 既存通知との衝突確認
- 成果物: feature / livewire / filament の回帰テスト一式

## 依存関係
- 4-A の仕様確定が 4-B / 4-C の前提になる
- 4-B と 4-C を固めてから 4-D / 4-E の確認を進める

## 現在の進捗
- 完了: 4-A 同期方式と payload 仕様の確定
- 完了: 4-B 通知センターへの再確認導線
- 完了: 4-C 管理側一覧と公開導線
- 未着手: 4-D 既読と dismiss の責務分離
- 未着手: 4-E 回帰テスト

## 4-A 完了メモ
- 同期先は既存の unread 通知基盤と分離し、管理者お知らせ専用の announcement feed を正本にする
- 1 件 MVP でも最初から list 形で扱い、`priority` / `starts_at` / `ends_at` / `scope` / `status` / `sticky` / `links` / `revision` を持たせる
- 既読相当の保持は `notification_user` ではなく、`announcement_id` + `revision` + tenant を含む localStorage key で行う
- 内容を編集したら `revision` を更新して再表示させる。ユーザーごとの DB 既読を積むより、公開期間とローカル保存を優先する
- 通知センターは同じ feed を履歴・再確認用に読むだけにし、既存の workflow / activity 通知には触れない

## 4-A 追加アイデア
- 並び順は `priority` を主、同値時は `starts_at` と `updated_at` で deterministic に決める
- CTA は `links` 配列で受け、公開バナーでは先頭を primary として扱う
- `scope` は current tenant / all tenants に加え、将来の拡張用に `tenant_id` を明示しておくと、複数テナントの公開を壊しにくい
- dismiss key は本文のハッシュではなく `revision` ベースにして、文言調整で意図せず再表示される事故を避ける
- 既存通知との衝突回避のため、announcement feed の route / payload / blade は専用 namespace に閉じる

## 4-B 完了メモ
- 通知センターは `AdminAnnouncementService` の feed を読むだけにし、既存の unread / activity / task 通知には触れない構成にした
- `NotificationController` から `adminAnnouncements` を渡し、`resources/views/notifications/index.blade.php` の先頭で announcement feed を表示するようにした
- `resources/views/layouts/app.blade.php` と `resources/views/layouts/appWithDrawer.blade.php` は `currentAnnouncement()` を通して同じ供給源を参照するように揃えた
- feed は `priority` 順で並べ、`draft` は notification center では出さないようにした。1 件 MVP でも list 形を崩していない
- 回帰テストで published 2 件 + draft 1 件を流し、通知センター上では published だけが出ることと、既存の通知一覧が引き続き表示されることを確認した

## 4-B 実装証跡
- `app/Services/AdminAnnouncementService.php`
- `resources/views/components/admin/announcement-feed.blade.php`
- `resources/views/notifications/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`
- `tests/Feature/Http/Controllers/NotificationControllerTest.php`
- `./vendor/bin/sail test tests/Feature/Http/Controllers/NotificationControllerTest.php tests/Feature/Views/AdminAnnouncementBannerTest.php tests/Feature/Livewire/Notifications/NotificationListTest.php` ✅

## 4-C 完了メモ
- 管理側一覧は `AdminAnnouncementResource` ベースで稼働している
- 作成・編集・プレビュー・ナビゲーション導線まで resource 側で揃えた
- 4-C の回帰テストは `tests/Feature/Filament/AdminAnnouncementResourceTest.php` で完了している
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementResourceTest.php` で 7 passed / 30 assertions を確認済み
- テスト内の確認対象は次の 6 本
  - `resource_list_page_renders_successfully`
  - `list_page_shows_existing_announcements`
  - `create_page_can_persist_draft_to_list`
  - `edit_page_prefills_existing_values`
  - `edit_page_renders_preview_section`
  - `edit_page_can_save_updates`
- 付随して `dashboard_links_widget_includes_announcement_banner_link` で管理導線のリンクも確認済み
- 通知センター側の feed 確認は 4-B として `tests/Feature/Http/Controllers/NotificationControllerTest.php` で別途担保している

## 4-D 完了メモ
- feed は `announcement-banner` を再利用しつつ `respectDismissed=false` / `dismissible=false` に固定し、通知センターを再確認専用の見え方にした。
- 公開側バナーは `sticky` 設定を尊重し、critical 固定だけに依存しないようにした。
- 有効な複数件の告知を feed / stack で同時に扱い、1 件だけに潰れないようにした。

## 4-D 実装証跡
- `app/Services/AdminAnnouncementService.php`
- `resources/views/components/admin/announcement-banner.blade.php`
- `resources/views/components/admin/announcement-feed.blade.php`
- `resources/views/components/admin/announcement-stack.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`

## 4-E 完了メモ
- `tests/Feature/Views/AdminAnnouncementBannerTest.php` で sticky / dismiss / feed 表示の回帰を押さえた。
- `tests/Feature/Http/Controllers/NotificationControllerTest.php` で通知センターの複数件表示と close 非表示を確認した。
- `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php` で台帳リスト画面の sticky 配置を確認した。
- `tests/Feature/Filament/AdminAnnouncementResourceTest.php` と合わせて管理側導線から通知表示までのつながりを固定した。

## 4-E 実装証跡
- `tests/Feature/Http/Controllers/NotificationControllerTest.php`
- `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- `tests/Feature/Filament/AdminAnnouncementResourceTest.php`

## 現在の進捗
- 完了: 4-A 同期方式と payload 仕様の確定
- 完了: 4-B 通知センターへの再確認導線
- 完了: 4-C 管理側一覧と公開導線
- 完了: 4-D 既読と dismiss の責務分離
- 完了: 4-E 回帰テスト

### GitHub 追跡

- Epic: #180
- Sprint 1: #181
- Sprint 2: #182
- Sprint 3: #183
- Sprint 4: #184

## 関連リンク
- 親 issue: #180
- docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md
- docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint4_completion.md

## 確認事項
- [x] バグ報告ではないことを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した
