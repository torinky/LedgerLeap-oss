# NotificationService

## 目的

`NotificationService` は、システム内で発生する様々なイベント（台帳の更新、ワークフローの進捗など）に基づいて、関連するユーザーに通知を送信する機能を提供します。通知の生成、送信、管理（既読化、取得）を一元的に行い、ユーザーが重要な情報を見逃さないようにサポートします。

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

*   目的・機能: アクティビティログのイベントに基づいて、適切な通知を生成し、対象ユーザーに送信します。
*   **役割**: アクティビティログをもとに通知処理を行います。
*   **引数**:
    *   `$activity` (`Spatie\Activitylog\Models\Activity`): アクティビティログのインスタンス
*   **処理内容**:
    1.  アクティビティログの `subject_type` と `event` から、`NotificationType` を取得する。
    2.  `getNotifiableRecipients()` を呼び出し、通知対象のユーザーを取得する。
    3.  `Notification::send()` を使用して、通知を送信する。

### `getNotifiableRecipients(Activity $activity, NotificationType $notificationType)`

*   目的・機能: 指定されたアクティビティと通知タイプに基づき、通知を受け取るべきユーザーのリストを決定します。
*   **役割**: 通知対象のユーザーを取得します。
*   **引数**:
    *   `$activity`(`Spatie\Activitylog\Models\Activity`)：アクティビティログ
    *   `$notificationType`(`App\Models\NotificationType`)：NotificationType
*   **処理内容**
    1.  `subject`からfolderを取得します。
    2.  `RoleFolderPermission`を参照して、対象のユーザーを絞り込みます。
    3.  `getUsersByRoleIds`を呼び出し、ユーザーを取得します。

### `getUnreadNotificationsForUser(User $user, int $perPage = 10)`

*   目的・機能: 特定のユーザーの未読通知をページネーション付きで取得します。
*   **役割**: ユーザーの未読通知を取得します。
*   **引数**:
    *   `$user`: 対象のユーザー
    *   `$perPage`: 取得件数
*   **戻り値**: `LengthAwarePaginator`

### `getUnreadNotificationCountForUser(User $user)`

*   目的・機能: 特定のユーザーの未読通知の総数を取得します。
*   **役割**: ユーザーの未読通知件数を取得します。
*   **引数**:
    *   `$user`: 対象のユーザー
*   **戻り値**: int

### `markAsRead(User $user, $notificationIds = null)`

*   目的・機能: 指定された通知、またはすべての未読通知を既読としてマークします。
*   **役割**: 通知を既読にします。
*   **引数**:
    *   `$user`: 対象のユーザー
    *   `$notificationIds`: 既読にする通知ID（単数または配列）またはnull（すべての未読通知を既読にする場合）

### `unreadNotificationsForUser(User $user)`

*   目的・機能: 特定のユーザーの未読通知を取得するためのEloquentクエリビルダーを返します。
*   **役割**: ユーザーの未読通知を取得するためのクエリビルダーを返します。
*   **引数**:
    *   `$user`: 対象のユーザー
*   **戻り値**: `Builder`

## 関連するクラス

*   `App\Models\NotificationType`
*   `App\Models\RoleFolderPermission`
*   `App\Models\User`
*   `App\Models\Role`
*   `App\Notifications\GenericNotification`
*   `App\Services\UserService`
*   `Spatie\Activitylog\Models\Activity`
