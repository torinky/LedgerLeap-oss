# [Issue]: 管理者お知らせバナー Sprint 4 完了報告

## イシュー種別
改善

## 概要
管理者お知らせの通知センター表示、feed/banner の見え方の分離、台帳リスト画面での sticky 配置を整え、複数件の有効通知を扱えるようにした。

## Sprint 4 で完了した内容
- 管理者お知らせの feed は `announcement-banner` を再利用し、既読状態に関係なく表示するようにした。
- feed 側では close ボタンを出さず、通知センターは確認専用の見え方にした。
- バナー本体は `sticky` 設定を尊重し、critical 固定だけに依存しないようにした。
- 有効な複数件の告知を通知センター / 上部表示で扱えるようにした。
- 台帳リスト画面では通知グループを `IndexManager` 配下へ移し、folder tree の下に潜り込まない配置へ寄せた。

## エビデンス
- [app/Services/AdminAnnouncementService.php](../../../app/Services/AdminAnnouncementService.php)
- [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [resources/views/components/admin/announcement-feed.blade.php](../../../resources/views/components/admin/announcement-feed.blade.php)
- [resources/views/components/admin/announcement-stack.blade.php](../../../resources/views/components/admin/announcement-stack.blade.php)
- [resources/views/livewire/ledger/index-manager.blade.php](../../../resources/views/livewire/ledger/index-manager.blade.php)
- [resources/views/layouts/app.blade.php](../../../resources/views/layouts/app.blade.php)
- [resources/views/layouts/appWithDrawer.blade.php](../../../resources/views/layouts/appWithDrawer.blade.php)
- [tests/Feature/Http/Controllers/NotificationControllerTest.php](../../../tests/Feature/Http/Controllers/NotificationControllerTest.php)
- [tests/Feature/Views/AdminAnnouncementBannerTest.php](../../../tests/Feature/Views/AdminAnnouncementBannerTest.php)
- [tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php](../../../tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php)

## 完了条件
- [x] 複数の有効通知を通知センターに出せる
  - Evidence: [resources/views/components/admin/announcement-feed.blade.php](../../../resources/views/components/admin/announcement-feed.blade.php)
- [x] feed の各通知が banner コンポーネント経由で描画される
  - Evidence: [resources/views/components/admin/announcement-feed.blade.php](../../../resources/views/components/admin/announcement-feed.blade.php)
- [x] feed 側は既読状態に依存しない
  - Evidence: [resources/views/components/admin/announcement-feed.blade.php](../../../resources/views/components/admin/announcement-feed.blade.php)
- [x] feed 側は close ボタンを出さない
  - Evidence: [resources/views/components/admin/announcement-feed.blade.php](../../../resources/views/components/admin/announcement-feed.blade.php)
- [x] 台帳リスト画面の sticky が folder tree の下に潜らない
  - Evidence: [resources/views/livewire/ledger/index-manager.blade.php](../../../resources/views/livewire/ledger/index-manager.blade.php)
- [x] 回帰テストを整備した
  - Evidence: [tests/Feature/Http/Controllers/NotificationControllerTest.php](../../../tests/Feature/Http/Controllers/NotificationControllerTest.php)
  - Evidence: [tests/Feature/Views/AdminAnnouncementBannerTest.php](../../../tests/Feature/Views/AdminAnnouncementBannerTest.php)
  - Evidence: [tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php](../../../tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php)

## GitHub 追跡
- Epic: #180
- Sprint 4: #184

## 関連リンク
- [イシュー本文](../../../.tmp/issue-184-body.md)
- [Sprint 3-4 完了メモ](../../../docs/work/issue-drafts/2026-04-28_admin_announcement_banner_sprint3_4_completion.md)
- [振り返り](../../../docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_retrospective.md)

## 確認事項
- [x] Sprint 4 の範囲に限定した
- [x] 通知センター / feed / ledger 画面の証跡を含めた
- [x] 回帰テストの確認を含めた

