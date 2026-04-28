# [Issue]: 管理者お知らせバナー Sprint 3-1 完了報告

## イシュー種別
改善

## 概要
管理者お知らせの設定画面を Filament の schema ベースで再構成し、実コンポーネントのプレビューと 2 列レイアウトを反映した。

## Sprint 3-1 で完了した内容
- 設定画面を `HasForms` + `InteractsWithForms` の schema ベースに変更した。
- 入力フォームを `Grid` / `Section` / `TextInput` / `Textarea` / `Select` / `Toggle` / `DateTimePicker` で構成した。
- プレビューは [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php) を実際に埋め込む形へ変更した。
- 各フォームラベルに `beforeLabel()` でアイコンを付け、Filament の field slot を使う形に寄せた。
- ワイド画面ではフォームとプレビューが 2 列で並ぶようにした。

## エビデンス
- [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [resources/views/filament/pages/admin-announcement-banner-settings.blade.php](../../../resources/views/filament/pages/admin-announcement-banner-settings.blade.php)
- [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## 完了条件
- [x] 設定画面が schema ベースになった
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] プレビューが実コンポーネントを使って表示される
  - Evidence: [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [x] ラベルにアイコンを追加した
  - Evidence: `beforeLabel()` を各 field に付与
- [x] フォームが 2 列で表示される
  - Evidence: `Grid::make(['xl' => 2])`
- [x] フォーカス領域の feature test が通る
  - Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## GitHub 追跡
- Epic: `#180`
- Sprint 3-1: `#183`

## 関連リンク
- [イシュー本文](../../../.tmp/issue-183-body.md)
- [振り返り](../../../docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_retrospective.md)

## 確認事項
- [x] 完了内容を Sprint 3-1 に限定した
- [x] 実装ファイルとテストファイルを証跡に含めた
- [x] 1 つの feature test で回帰確認した
