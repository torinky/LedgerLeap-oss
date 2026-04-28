# [Issue]: 管理者お知らせバナー Sprint 3-4 完了報告

## イシュー種別
改善

## 概要
管理者お知らせの設定画面について、critical の sticky 強制と close 非表示を回帰テストで固定し、公開側 preview の見た目を確認した。

## Sprint 3-4 で完了した内容
- critical レベルに変更したとき、sticky が自動的に true になることを検証した。
- critical preview で close ボタンが表示されないことを検証した。
- browser preview で critical 表示を確認し、sticky と close 非表示の挙動を目視した。

## エビデンス
- [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅
- browser preview `http://localhost/__preview/admin-announcement-banner?level=critical`

## 完了条件
- [x] critical の sticky 強制が回帰テストで固定されている
  - Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- [x] critical の close 非表示が回帰テストで固定されている
  - Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- [x] browser preview で critical の見え方を確認した
  - Evidence: browser preview `http://localhost/__preview/admin-announcement-banner?level=critical`

## GitHub 追跡
- Epic: #180
- Sprint 3-4: #183

## 関連リンク
- [イシュー本文](../../../.tmp/issue-183-body.md)
- [Sprint 3-3 完了メモ](../../../docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_3_completion.md)

## 確認事項
- [x] Sprint 3-4 の範囲に限定した
- [x] テストと preview 確認を含めた
- [x] feature test の成功を含めた