# NotificationService

## クラス概要

* **クラス名**: `App\Services\NotificationService`
* **役割**: 通知処理に関するロジックを提供します。
* **依存**: `UserService`

## ユースケース

* モデルが更新された際の通知
* ユーザーへの通知の送信、既読処理、取得
* 通知対象のユーザーやロールの特定

## メソッド

### `processActivityLog(Activity $activity)`

* **役割**: アクティビティログをもとに通知処理を行います。
* **引数**:
    * `$activity` (`Spatie\Activitylog\Models\Activity`): アクティビティログのインスタンス
* **処理内容**:
    1. アクティビティログの `subject_type` と `event` から、`NotificationType` を取得する。
    2. `getNotifiableRecipients()` を呼び出し、通知対象のユーザーを取得する。
    3. `Notification::send()` を使用して、通知を送信する。

### `getNotifiableRecipients(Activity $activity, NotificationType $notificationType)`

* **役割**: 通知対象のユーザーを取得します。
* **引数**:
    * `$activity`(`Spatie\Activitylog\Models\Activity`)：アクティビティログ
    * `$notificationType`(`App\Models\NotificationType`)：NotificationType
* **処理内容**
    1. `subject`からfolderを取得します。
    2. `RoleFolderPermission`を参照して、対象のユーザーを絞り込みます。
    3. `getUsersByRoleIds`を呼び出し、ユーザーを取得します。

### `getUnreadNotificationsForUser(User $user, int $perPage = 10)`

* **役割**: ユーザーの未読通知を取得します。
* **引数**:
    * `$user`: 対象のユーザー
    * `$perPage`: 取得件数
* **戻り値**: `LengthAwarePaginator`

### `getUnreadNotificationCountForUser(User $user)`

* **役割**: ユーザーの未読通知件数を取得します。
* **引数**:
    * `$user`: 対象のユーザー
* **戻り値**: int

### `markAsRead(User $user, $notificationIds = null)`

* **役割**: 通知を既読にします。
* **引数**:
    * `$user`: 対象のユーザー
    * `$notificationIds`: 既読にする通知ID（単数または配列）またはnull（すべての未読通知を既読にする場合）

### `unreadNotificationsForUser(User $user)`

* **役割**: ユーザーの未読通知を取得するためのクエリビルダーを返します。
* **引数**:
    * `$user`: 対象のユーザー
* **戻り値**: `Builder`

## 関連するクラス

* `App\Models\NotificationType`
* `App\Models\RoleFolderPermission`
* `App\Models\User`
* `App\Models\Role`
* `App\Notifications\GenericNotification`
* `App\Services\UserService`
* `Spatie\Activitylog\Models\Activity`
