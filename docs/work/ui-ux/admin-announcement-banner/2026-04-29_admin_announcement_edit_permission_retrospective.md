# 2026-04-29 システム管理者からの通知の編集権限 振り返り

- date: 2026-04-29
- status: confirmed
- last_confirmed_at: 2026-04-29
- recheck_after: 90d
- recheck_trigger:
  - 変更系権限の再設計が入ったとき
  - issue / sprint 番号の drift が再発したとき
  - 通知センター / 上部バナーの回帰が疑われたとき

## 対象

- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Filament/Resources/AdminAnnouncementResource/Pages/ListAdminAnnouncements.php`
- `tests/Feature/Filament/AdminAnnouncementResourceTest.php`
- `app/Livewire/Notifications/NotificationList.php`
- `tests/Feature/Livewire/Notifications/NotificationListTest.php`
- `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- GitHub issue `#185`

## 事実

- 管理者通知は、閲覧を全員共通の前提にしたまま、作成・編集・削除だけを専用権限に分離した。
- `create_admin_announcements` / `update_admin_announcements` / `delete_admin_announcements` を Seeder に追加し、ロールごとの割当方針を明文化した。
- `AdminAnnouncementResource` は `canViewAny()` / `canCreate()` / `canEdit()` / `canDelete()` / `canDeleteAny()` で変更系だけを判定するように整理した。
- 通知センターと上部バナーの表示は、変更系権限の導入後も権限化せずに維持した。
- 関連テストは `./vendor/bin/sail test tests/Feature/Filament/AdminAnnouncementResourceTest.php tests/Feature/Livewire/Notifications/NotificationListTest.php tests/Feature/Views/AdminAnnouncementBannerTest.php` で通過した。

## 良かったこと

### 技術要素

- 閲覧と変更を分けたことで、権限制御の責務が明確になった。
- create / update / delete を別権限にしたため、ロールごとの運用差をテストで固定しやすかった。
- 通知センターの表示を権限化しなかったので、変更系の導入が表示面に波及しなかった。
- `ListAdminAnnouncements` の作成ボタンを `canCreate()` に連動させたことで、入口の見え方が resource の判定と揃った。

### 仕事の進め方

- まず要件メモを作ってから実装に入ったので、閲覧共通 / 変更系限定という前提がぶれにくかった。
- issue 本文とコメントを証拠として残しながら進めたため、Sprint 1 / Sprint 2 の完了判断を説明しやすかった。
- 関連テストをまとめて Sail で回したことで、通知センターとバナーへの回帰を同時に確認できた。

## 悪かったこと

### 技術要素

- 静的解析で `Permission` の文字列が `Unknown column` と誤認される箇所があり、テストヘルパーの見通しを一時的に悪くした。
- `AdminAnnouncementResource` の責務が増えたことで、メソッド数の警告が出やすくなった。
- 変更系権限の確認はできたが、`publish` / `archive` / `replicate` を同時に扱うと設計が膨らみやすいことが見えた。

### 仕事の進め方

- 途中で issue の本文と完了コメントの整合がずれやすく、本文側を先に更新しないと old wording が残りやすかった。
- 以前のスプリント結果を参照しながら進める場面で、完了済みと未完了の境界が見えにくい瞬間があった。
- テスト結果だけでなく、issue 本文の完了条件まで同時に見ないと「終わったつもり」になりやすかった。

## 上書き指示されたこと

### 技術要素

- 「閲覧専用権限」は作らず、閲覧はユーザー全員共通のまま維持する。
- `notify` は受信権限として残し、編集権限には流用しない。
- `publish` / `archive` / `replicate` は今回の権限設計に含めない。
- 削除権限は強いロールに限定し、作成・編集とは分けて扱う。

### 仕事の進め方

- issue の途中経過ではなく、完了条件そのものを本文へ反映して判断する。
- 上書きされた scope はコメントではなく本文とドキュメントの両方に同時反映する。
- issue 番号や sprint 番号は推測で置かず、GitHub 上の実体に合わせて書き直す。

## 再利用できる結論

### 技術要素

- 権限設計は「閲覧」と「変更」を分け、変更側だけを create / update / delete に切るのが分かりやすい。
- Filament の入口制御は resource の `can*` 系で統一し、一覧ページの header action も同じ判定に寄せると整合が保ちやすい。
- 通知センターや上部バナーのような共通 surface は、変更権限と切り離しておく方が回帰が少ない。

### 仕事の進め方

- 振り返りは `良かったこと` / `悪かったこと` / `上書き指示されたこと` に分け、その後で `技術要素` と `作業の進め方` に再分解すると、次回の判断がぶれにくい。
- issue を閉じる前に、本文の完了条件とテスト結果を同じ証拠セットとして揃える。
- 再利用できる学びは `docs/work` に残し、必要になったものだけを `.github` に昇格する。

## 証拠

- `docs/work/ui-ux/admin-announcement-banner/2026-04-29_admin_announcement_edit_permission_requirements.md`
- `docs/work/ui-ux/README.md`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Filament/Resources/AdminAnnouncementResource/Pages/ListAdminAnnouncements.php`
- `tests/Feature/Filament/AdminAnnouncementResourceTest.php`
- `app/Livewire/Notifications/NotificationList.php`
- `tests/Feature/Livewire/Notifications/NotificationListTest.php`
- `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- GitHub issue `#185`
- Sprint 1 完了コメント: `#issuecomment-4341232676`
- Sprint 2 完了コメント: `#issuecomment-4341361188`

## スキル更新メモ

- この振り返りは `skill-maintenance` の観点では、`docs/work` に残すべき local learning と、`.github` へ昇格できる process learning の両方を含む。
- 次回同種の作業では、先に「完了条件の evidence 」「上書きされた指示」「再利用可否」を書き分ける。
- issue 完了後の retrospective は、技術要素と作業の進め方を別々に書くことを標準にする。

