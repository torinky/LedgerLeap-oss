# システム管理者からの通知の編集権限 要件検討メモ

- date: 2026-04-29
- status: 要件整理 / 実装前
- scope: 管理者通知の編集権限、公開操作、閲覧導線、役割別の許可範囲

## 1. 背景

システム管理者が出す通知は、単なる表示メッセージではなく、

- 通知センターでの閲覧
- 管理画面での作成・編集
- 予約公開 / 即時公開 / 終了
- 複製
- 削除

が混在する。現状は「通知を受け取る権限」と「通知の内容を編集する権限」が明確に分かれておらず、運用責務が曖昧になりやすい。

この文書では、ペルソナ、ユーザーシナリオ、現状の権限仕様を踏まえて、編集権限をどう分離すべきかを整理する。

## 2. 現状の権限仕様

### 2.1 二層 ACL の前提

LedgerLeap の権限は次の二層で構成されている。

- `Spatie\Permission` によるグローバル権限
- `WritableFolderRepository` / `RoleFolderPermission` によるフォルダ権限

参照:

- `app/Services/PermissionService.php`
- `.github/skills/permission-model/SKILL.md`

### 2.2 現行ロール

`database/seeders/RolesAndPermissionsSeeder.php` では、少なくとも次のロールがある。

- `Super Admin`
- `Organization Admin`
- `Project Manager`
- `Editor`
- `Viewer`
- `Folder Manager`
- `Folder Viewer`
- `user`

### 2.3 通知関連の既存権限

現時点で通知に近い権限は、主に次のもの。

- `notify`: システム内通知を受け取る
- `receive_workflow_summary_email`: ワークフロー集約メールを受け取る
- `receive_workflow_action_email`: 個別ワークフロー通知メールを受け取る

重要なのは、これらは **受信権限** であり、**管理者通知の編集権限ではない** こと。

### 2.4 管理者通知の現状実装

- 一覧 / 編集 / 複製 / 削除は `AdminAnnouncementResource` にある
- 通知センターでは `NotificationController` / `NotificationList` が `AdminAnnouncementService` の出力を描画する
- 画面側では `layout.blade.php` / `appWithDrawer.blade.php` に通知バナーが差し込まれる
- ただし、`AdminAnnouncementPolicy` のような専用ポリシーは見当たらない

参照:

- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Livewire/Notifications/NotificationList.php`
- `app/Services/AdminAnnouncementService.php`

## 3. ペルソナ

### 3.1 システム管理者

- 全社向けの障害告知、メンテナンス告知、機能案内を出す
- 予約公開や公開終了を管理する
- 必要なら全テナント横断で出す

### 3.2 組織管理者

- 自組織向けの案内を出す
- 内容の修正、公開タイミング調整、終了対応を行う
- 誤配信防止のため、削除や公開権限は慎重に扱う

### 3.3 運用担当 / 編集担当

- 下書き作成や文面修正を行う
- 公開判断は上位者に委ねる場合がある
- 内容修正の責務はあるが、公開は持たせない、という運用もありうる

### 3.4 一般ユーザー

- 通知センターでお知らせを読む
- 編集はしない
- 既読 / 非表示などの閲覧操作のみ行う

## 4. ユーザーシナリオ

### 4.1 障害告知を即時公開したい

**登場人物**: システム管理者

**やりたいこと**:

1. タイトルと本文を入力する
2. 重要度を設定する
3. `sticky` を有効にする
4. すぐ公開する
5. 必要なら終了時刻を設定する

**要件候補**:

- 公開権限は編集権限と分ける
- `sticky` と `critical` は公開時に強制ルールを持たせる
- 予約時は `starts_at` / `ends_at` の整合を強制する

### 4.2 文面だけ直したい

**登場人物**: 編集担当

**やりたいこと**:

1. 草稿の誤字を直す
2. CTA を差し替える
3. 予約公開時刻は変えない

**要件候補**:

- 下書き編集は許可する
- 公開済みの本文修正は、権限を上げるか差し戻しフローを挟む

### 4.3 組織内向け通知を限定公開したい

**登場人物**: Organization Admin

**やりたいこと**:

1. `current_tenant` 向けに通知を作る
2. 予約公開する
3. 組織内だけに見せる

**要件候補**:

- `current_tenant` と `all_tenants` を編集権限で分ける
- `all_tenants` はより強い権限にする

### 4.4 通知センターで読むだけ

**登場人物**: 一般ユーザー

**やりたいこと**:

1. 通知一覧を開く
2. 管理者通知を確認する
3. 必要なら既読や非表示を行う

**要件候補**:

- 閲覧と編集を完全に分ける
- 通知センター閲覧は `notify` とは別のアクセス設計にするか明文化する

## 5. 現状から読み取れる要件

### 5.1 編集権限は受信権限と分離すべき

`notify` は「受け取る権限」であり、編集可否の指標にはならない。
したがって、管理者通知の編集には専用の権限が必要。

### 5.2 操作単位で権限を分けるべき

最低でも次を分けて考える。

- 閲覧
- 作成
- 編集
- 公開
- 終了 / アーカイブ
- 複製
- 削除

### 5.3 テナント境界を明示すべき

通知は `current_tenant` と `all_tenants` の両方を扱うため、

- 自テナントだけ編集できる
- 全テナント配信は上位者のみ

のように境界を分ける必要がある。

### 5.4 予約公開は編集より厳しい扱いにする

公開時刻、終了時刻、`sticky`、`critical` は、単純な文面修正よりも強い責務を持つ。
そのため、下書き編集と公開操作は同一権限にしない方が安全。

## 6. 役割別の要件候補

| 役割 | 閲覧 | 下書き作成 | 下書き編集 | 公開 | 終了 / アーカイブ | 複製 | 削除 | 範囲 |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Super Admin | ○ | ○ | ○ | ○ | ○ | ○ | ○ | 全テナント |
| Organization Admin | ○ | ○ | ○ | ○ | ○ | ○ | △ | 自テナント中心 |
| Project Manager | ○ | ○ | ○ | △ | △ | ○ | × | 自テナント / 自組織 |
| Editor | ○ | ○ | ○ | × | × | ○ | × | 自テナント / 自組織 |
| Viewer | ○ | × | × | × | × | × | × | 閲覧のみ |
| Folder Manager | ○ | × | × | × | × | × | × | 閲覧のみ候補 |
| Folder Viewer | ○ | × | × | × | × | × | × | 閲覧のみ候補 |
| user | ○ | × | × | × | × | × | × | 閲覧のみ候補 |

注:

- `△` は運用判断が必要
- フォルダ権限は現時点では通知編集の直接権限ではない
- `Project Manager` / `Editor` の公開権限は、誤配信リスクを見て再検討する余地がある

## 7. 要件候補の結論

### 7.1 必須要件

- 管理者通知の編集権限は、`notify` と独立した専用権限で扱う
- 公開 / 終了 / 削除は、下書き編集より上位の権限として分ける
- `current_tenant` と `all_tenants` で操作範囲を分ける
- 通知センターの閲覧権限と、管理画面での編集権限を分離する

### 7.2 推奨要件

- 専用権限名を作るなら、少なくとも `view_admin_announcements` / `create_admin_announcements` / `update_admin_announcements` / `publish_admin_announcements` / `archive_admin_announcements` / `delete_admin_announcements` / `replicate_admin_announcements` に分ける
- `critical` や `sticky` の変更は公開権限に含める
- 予約公開の更新には、開始 / 終了日の整合チェックを必須にする
- 複製は原則 `draft` で生成する

### 7.3 未決事項

- 通知センターの閲覧は `notify` を必須にするか
- `Organization Admin` に `all_tenants` の公開権限を与えるか
- `Project Manager` / `Editor` に公開権限を与えるか
- 削除権限を「論理削除のみ」に限定するか

## 8. 実装に進む前の判断基準

1. **誰が作るか** と **誰が公開するか** を分ける
2. **自テナント** と **全テナント** を分ける
3. **下書き編集** と **公開操作** を分ける
4. 現行の `notify` は受信権限として残す
5. 新権限は、既存のロールに無理なく段階的に割り当てる

## 9. 参照ファイル

- `app/Services/PermissionService.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Livewire/Notifications/NotificationList.php`
- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Services/AdminAnnouncementService.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`

