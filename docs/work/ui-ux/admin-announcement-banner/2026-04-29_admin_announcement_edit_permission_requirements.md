# システム管理者からの通知の編集権限 要件検討メモ

- date: 2026-04-29
- status: 要件整理 / 実装前
- scope: 管理者通知の変更系権限、閲覧共通化、役割別の許可範囲

## 1. 背景

システム管理者が出す通知は、単なる表示メッセージではなく、

- 通知センターでの閲覧
- 管理画面での作成・編集
- 予約公開 / 即時公開 / 終了
- 複製
- 削除

が混在する。現状は「通知を受け取る権限」と「通知の内容を編集する権限」が明確に分かれておらず、運用責務が曖昧になりやすい。

今回の検討では、**閲覧はユーザー全員共通で権限化しない**。したがって、新しく作る専用権限は変更系だけに絞る。

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

重要なのは、これらは **受信権限** であり、**管理者通知の変更権限ではない** こと。

また、通知一覧や上部バナーの**閲覧そのものは全員共通**とみなし、`view_admin_announcements` のような閲覧専用権限は作らない。

### 2.4 管理者通知の現状実装

- 一覧 / 編集 / 複製 / 削除は `AdminAnnouncementResource` にある
- 通知センターでは `NotificationController` / `NotificationList` が `AdminAnnouncementService` の出力を描画する
- 画面側では `layout.blade.php` / `appWithDrawer.blade.php` に通知バナーが差し込まれる
- ただし、`AdminAnnouncementPolicy` のような専用ポリシーは見当たらない
- 変更系権限の切り分け先は、現時点では Seeder に追加するロール / 権限定義が主な入口になる

参照:

- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Livewire/Notifications/NotificationList.php`
- `app/Services/AdminAnnouncementService.php`

## 3. ペルソナ

### 3.1 通知作成担当ユーザー

- シナリオ検討から導出された「通知を作るユーザー」
- 実装上は `Super Admin` / `Organization Admin` / `Project Manager` / `Editor` のいずれかに割り当てられる候補
- 新規通知の作成、文面修正、不要になった通知の削除を担う

### 3.2 権限管理者

- Seeder で新権限をどのロールに付与するかを決める
- `notify` と変更系権限を分離して運用したい
- 削除をどのロールまで許可するかを管理する

### 3.3 一般ユーザー

- 通知センターでお知らせを読む
- 編集はしない
- 既読 / 非表示などの閲覧操作のみ行う

## 4. ユーザーシナリオ

### 4.1 通知作成担当ユーザーが障害告知を作る

**登場人物**: 通知作成担当ユーザー

**やりたいこと**:

1. タイトルと本文を入力する
2. 重要度を設定する
3. `sticky` を有効にする
4. すぐ公開する
5. 必要なら終了時刻を設定する

**要件候補**:

- 作成・編集・削除の専用権限を持たせる
- 閲覧は権限に依存させず、全ユーザー共通で扱う
- `sticky` や `critical` のような表示制御は、編集内容の一部として扱う

### 4.2 通知作成担当ユーザーが文面を直す

**登場人物**: 通知作成担当ユーザー

**やりたいこと**:

1. 草稿の誤字を直す
2. CTA を差し替える
3. 予約公開時刻は変えない

**要件候補**:

- `update_admin_announcements` で文面修正を許可する
- 公開済みの本文修正を許すかどうかは、運用ルールとして別途決める

### 4.3 通知作成担当ユーザーが不要な通知を削除したい

**登場人物**: 通知作成担当ユーザー / 権限管理者

**やりたいこと**:

1. 誤って作成した通知を削除する
2. 古くなった告知を削除したい
3. 削除権限を強いロールに限定したい

**要件候補**:

- `delete_admin_announcements` は強いロールに限定する
- 削除は論理削除前提にするか、完全削除を許可するかを運用で決める
- テナントや範囲の違いは権限ではなく、データの持つ scope で扱う

### 4.4 一般ユーザーが通知センターで読む

**登場人物**: 一般ユーザー

**やりたいこと**:

1. 通知一覧を開く
2. 管理者通知を確認する
3. 必要なら既読や非表示を行う

**要件候補**:

- 閲覧は権限にしない
- `notify` は通知受信のための既存権限として残す
- ユーザーは編集できなくても閲覧と既読だけはできる

## 5. 現状から読み取れる要件

### 5.1 閲覧は権限化しない

通知センター、上部バナー、一覧再確認は全ユーザー共通の導線として扱う。
したがって、閲覧専用権限は作らず、権限制御は変更系だけに寄せる。

**判断理由**: 閲覧を権限制御すると、全ユーザー共通で見せたい通知まで不要に塞いでしまう。今回は「読めること」より「誰が変更できるか」を管理したいので、閲覧は権限外にした。

### 5.2 変更系権限は作成・編集・削除に分ける

最低でも次を分けて考える。

- 作成
- 編集
- 削除

**判断理由**: 作成・編集・削除は責務と事故リスクが違う。特に削除は影響が大きいため、作成や編集と同じ扱いにしない方が運用しやすい。

### 5.3 `notify` は受信権限として残す

`notify` は、あくまでシステム内通知を受け取るための既存権限として残す。
編集権限に流用しない。

**判断理由**: `notify` を編集権限に流用すると、通知を受けるだけのユーザーまで変更系の判断に巻き込まれる。既存の意味を維持した方が、権限の見通しが崩れない。

### 5.4 Seeder でロールに新権限を付与する

新しい権限は Seeder に追加し、ロールごとに付与する。
閲覧は共通のため、Seeder では変更系権限だけを調整する。

**判断理由**: 新権限を Seeder に集約すると、初期状態と再構築時のロール割当が揃う。手動付与だけにすると、環境ごとの差が出やすい。

### 5.5 予約公開や表示制御は運用ルールとして別途検討する

今回のスコープでは、専用権限は作成・編集・削除に限定する。
公開のタイミングや `sticky` / `critical` の扱いは、更新時の入力ルールまたは運用ルールとして別途詰める。

**判断理由**: 権限設計を先に広げすぎると、作成・編集・削除の基礎設計がぼやける。公開や表示制御は別論点として切り出した方が、要件の境界が分かりやすい。

## 6. 役割別の要件候補

閲覧は全ユーザー共通のため、下表では変更系のみを記載する。

| 役割 | 作成 | 編集 | 削除 | 判断理由 |
|---|---:|---:|---:|---|
| Super Admin | ○ | ○ | ○ | 変更責任を最上位で持つため、全部付与する |
| Organization Admin | ○ | ○ | ○ | 組織内の運用責任者として、変更と削除まで許可する |
| Project Manager | ○ | ○ | × | 作成と編集は任せるが、削除は事故防止のため外す |
| Editor | ○ | ○ | × | 文面調整は任せるが、削除は責務が重いので外す |
| Viewer | × | × | × | 閲覧だけの利用者なので変更系は不要 |
| Folder Manager | × | × | × | フォルダ権限と通知変更は分離する |
| Folder Viewer | × | × | × | 閲覧専用の立場なので変更系は不要 |
| user | × | × | × | 一般利用者は変更系を持たせない |

注:

- `notify` は閲覧権限ではなく受信権限として扱う
- 削除権限は上位ロールに限定する
- `Project Manager` / `Editor` の削除権限は、誤削除リスクを見て与えない前提とする

## 7. 要件候補の結論

### 7.1 必須要件

- 管理者通知の専用権限は、`notify` と独立した変更系権限として扱う — 受信と変更を混ぜないため
- 閲覧は権限化しない — 全ユーザー共通の導線を守るため
- 新規権限は `create_admin_announcements` / `update_admin_announcements` / `delete_admin_announcements` に絞る — スコープを変更系に限定するため
- Seeder でロールに新権限を付与し、どのロールが作成・編集・削除できるかを明示する — 初期状態を揃えて運用差を出さないため

### 7.2 推奨要件

- 専用権限名は `create_admin_announcements` / `update_admin_announcements` / `delete_admin_announcements` を基本にする — 現行の責務を3操作に収めやすいから
- 閲覧専用権限は作らない — 権限が増えるほど、共通閲覧の意図が伝わりにくくなるから
- `publish` / `archive` / `replicate` は今回の権限設計に含めず、必要になった時だけ別途検討する — 変更系の基礎を固めるのが先だから
- 削除は上位ロールのみ、作成・編集は運用担当ロールまで広げる — 誤削除の影響が大きいから

### 7.3 未決事項

- 削除を論理削除のみに限定するか
- `Organization Admin` まで削除を許可するか
- `Project Manager` / `Editor` に削除権限を与えない前提を固定するか
- `create` と `update` を別権限にするか、将来的に分けるか

## 8. 実装に進む前の判断基準

1. **誰が作るか** と **誰が消せるか** を分ける
2. 閲覧は共通、変更は権限で分ける
3. `notify` は受信権限として残す
4. 新権限は、既存の Seeder に無理なく段階的に割り当てる
5. `Organization Admin` 以上にだけ削除を許可するかを先に決める

## 9. 参照ファイル

- `app/Services/PermissionService.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Livewire/Notifications/NotificationList.php`
- `app/Filament/Resources/AdminAnnouncementResource.php`
- `app/Services/AdminAnnouncementService.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`

