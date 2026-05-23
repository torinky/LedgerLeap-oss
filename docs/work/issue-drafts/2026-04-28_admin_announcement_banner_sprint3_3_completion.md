# [Issue]: 管理者お知らせバナー Sprint 3-3 完了報告

## イシュー種別
改善

## 概要
管理者お知らせの設定画面に、公開状態の表示と公開 / 停止の操作を追加し、公開時の日時バリデーションを入れた。

## Sprint 3-3 で完了した内容
- 公開状態を disabled の status select で表示し、状態と日時の役割を分けた。
- header actions で下書き保存 / 公開 / 停止を分けて実行できるようにした。
- 公開時は starts_at / ends_at / level / scope / CTA の形式を検証するようにした。
- 重大レベルを公開する場合は sticky を維持するようにした。
- feature test で status の切り替えと公開期間の validation を検証した。

## エビデンス
- [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [lang/ja/ledger/ui.php](../../../lang/ja/ledger/ui.php)
- [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
- `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## 完了条件
- [x] 管理者は登録、公開、停止を分けて操作できる
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] 開始日時と終了日時を持てる
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] 状態と日時の役割が分かれている
  - Evidence: [app/Filament/Pages/AdminAnnouncementBannerSettings.php](../../../app/Filament/Pages/AdminAnnouncementBannerSettings.php)
- [x] feature test が通る
  - Evidence: [tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php](../../../tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php)
  - Evidence: `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementBannerSettingsTest.php` ✅

## GitHub 追跡
- Epic: #180
- Sprint 3-3: #183

## 関連リンク
- [イシュー本文](../../../.tmp/issue-183-body.md)
- [Sprint 3-2 完了メモ](../../../docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_2_completion.md)

## 確認事項
- [x] Sprint 3-3 の範囲に限定した
- [x] 公開 / 停止の証跡を含めた
- [x] feature test の成功を含めた