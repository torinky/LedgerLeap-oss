## イシュー種別
機能追加

## 概要
管理者が管理者お知らせを登録・公開・停止できる UI を実装する。開始日時、終了日時、表示レベル、公開状態、プレビューを扱えるようにする。

## 背景 / 目的
公開表示だけでは運用できないため、管理者が状態を制御できる画面が必要になる。手動停止と期間管理を分けて扱えるようにしたい。

## 現状
- 管理画面の設計は docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md にある
- 管理側一覧、作成、編集、プレビュー、通知センター連携まで実装済み
- DB 変更は migration で反映済みで、一覧と公開導線の土台は resource ベースに寄せている
- システム設定系の入力フォームは Filament に寄せる方針を維持する
- 入口は `http://localhost/app?tenant=demo-tenant` の Filament トップ画面を想定する
- status を採用し、公開 / 停止は status で切り分ける

## 目標 / 完了状態
- 登録フォームがある
- 公開 / 停止ができる
- 開始 / 終了 / レベル / 対象範囲を編集できる
- プレビューで公開表示に近い見え方を確認できる
- DB変更なしでも画面だけ先に立ち上がる
- Filament の画面構成として破綻しない

## 進捗
- 4-C に相当する管理側一覧と公開導線は `AdminAnnouncementResource` で実装済み
- `tests/Feature/Filament/AdminAnnouncementResourceTest.php` で一覧・作成・編集・プレビュー・導線の回帰を確認済み
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementResourceTest.php` は 7 passed / 30 assertions で成功済み
- 管理導線のリンクは `dashboard_links_widget_includes_announcement_banner_link` で確認済み
- 通知センター連携は `tests/Feature/Http/Controllers/NotificationControllerTest.php` で別途確認済み

## スコープ / 非スコープ
対象:
- Filament の管理画面シェル
- 登録フォーム
- 公開 / 停止の状態遷移
- 期間と対象範囲の編集
- プレビュー

対象外:
- 通知センター統合
- データモデル方針の再検討
- 既読の永続保存

## 方針候補 / メモ
1. 単一編集を優先する
2. 一覧編集は後回しにする
3. 状態と日時を別々に扱う
4. Filament 5.6 系の現行 API に合わせて Resource / Page ベースで組む

## スプリント分解
- [x] Sprint 3-1: Filament の画面シェルと入力フォームを作る
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [resources/views/filament/pages/admin-announcement-banner-settings.blade.php](resources/views/filament/pages/admin-announcement-banner-settings.blade.php)
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md)
- [x] Sprint 3-2: プレビューと入力項目の連動を作る
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: [resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php](resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md)
- [x] Sprint 3-3: 公開 / 停止の操作とバリデーションを整える
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [lang/ja/ledger/ui.php](lang/ja/ledger/ui.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_3_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_3_completion.md)
- [x] Sprint 3-4: テストと微調整を入れる
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
	- Evidence: browser preview `http://localhost/__preview/admin-announcement-banner?level=critical` で critical の sticky / close 非表示を確認
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_4_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_4_completion.md)

## エビデンス / 参照先
- docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md
- docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_scope.md
- docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_overview.md
- docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md
- docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md

## 完了条件
- [x] 管理者は登録、公開、停止を分けて操作できる
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] 開始日時と終了日時を持てる
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] 状態と日時の役割が分かれている
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] プレビューで上部表示に近い見た目を確認できる
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
- [x] プレビューを UI からリセットできる
	- Evidence: [resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php](resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php)
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
- [x] まず画面だけ先行しても、進捗がスプリント単位で追える
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md)
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md)
	- Evidence: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_3_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_3_completion.md)
- [x] システム設定系フォームとして Filament の流儀に寄せられている
	- Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](app/Filament/Pages/AdminAnnouncementBannerSettings.php)
	- Evidence: [resources/views/filament/pages/admin-announcement-banner-settings.blade.php](resources/views/filament/pages/admin-announcement-banner-settings.blade.php)
	- Evidence: [resources/views/components/admin/announcement-banner.blade.php](resources/views/components/admin/announcement-banner.blade.php)
	- Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## 関連リンク
- 親 issue: #180
- Sprint 3-1 完了メモ: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md)
- Sprint 3-2 完了メモ: [docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md](docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md)
- docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md

## 確認事項
- [x] バグ報告ではないことを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した
