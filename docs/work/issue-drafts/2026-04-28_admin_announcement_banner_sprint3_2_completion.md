# [Issue]: 管理者お知らせバナー Sprint 3-2 完了報告

## イシュー種別
改善

## 概要
管理者お知らせの設定画面で、入力内容が preview に即時反映されるようにし、公開側バナーの見え方をフォーム操作と連動させた。

## Sprint 3-2 で完了した内容
- 設定画面の入力項目を live 化し、preview が入力変更に追従するようにした。
- preview 側で `scope` と `sticky` を表示し、入力内容との対応が分かるようにした。
- CTA の入力が preview のリンク表示に反映されることを確認した。
- dismiss key を入力状態に連動させ、preview を閉じても別の入力状態で再表示できるようにした。
- preview をリセットするボタンを追加し、閉じた状態を UI から明示的に戻せるようにした。
- preview の root に `wire:key` を付け、reset 時に Alpine state を再マウントできるようにした。
- feature test で title / body / scope / sticky / CTA の表示反映を検証した。

## エビデンス
- [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## 完了条件
- [x] 入力変更が preview に反映される
  - Evidence: `->live()` / `->live(onBlur: true)` を設定
- [x] preview で scope と sticky が確認できる
  - Evidence: [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [x] CTA のリンク先が preview に反映される
  - Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- [x] preview を閉じても入力変更で再表示できる
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] preview を UI からリセットできる
  - Evidence: [resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php](../../../resources/views/filament/pages/admin-announcement-banner-preview-reset.blade.php)
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
  - Evidence: [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [x] feature test が通る
  - Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## GitHub 追跡
- Epic: `#180`
- Sprint 3-2: `#183`

## 関連リンク
- [イシュー本文](../../../.tmp/issue-183-body.md)
- [Sprint 3-1 完了メモ](../../../docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_1_completion.md)

## 確認事項
- [x] Sprint 3-2 の範囲に限定した
- [x] preview / input 連動の証跡を含めた
- [x] feature test の成功を含めた
