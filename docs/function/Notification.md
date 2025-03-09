# 通知機能

## 概要

LedgerLeap
の通知機能は、台帳データやフォルダーの更新時に、適切なユーザーへ通知を送信する機能です。通知は、グローバル通知とフォルダー通知の２つの方法で通知を設定できます。また、通知の配信先や、通知の有無は権限や設定によって決定されます。

## 機能詳細

### 通知のトリガー

通知は、台帳データやフォルダーに対して変更があった場合に発生します。どのような変更に対して通知を行うかは、通知設定によって異なります。

### 通知の配信先

通知の配信先は、以下の要素によって決定されます。

* **権限**: ユーザーが所属するロールや組織に付与された権限。
* **設定**: ユーザー自身が設定した通知設定。
    * 通知の有無
    * 通知のタイプ
* **通知タイプ**:
    * 通知の種類を管理します。
    * `App\Models\NotificationType`で定義します。
    * 通知の内容は、`App\Notifications\GenericNotification`で設定します。
    * `model`, `event`, `route`を設定します。
* **ロールフォルダ権限**:
    * 通知を必要とするユーザーと、その通知先となるフォルダーや通知タイプを紐づけて管理します。
    * `App\Models\RoleFolderPermission`で定義します。
* **グローバル通知**
    * ルートフォルダー（idが1）に紐づく通知です。
    * 全てのユーザーに紐づけが可能です。
        * 通知タイプで管理されます。
        * `App\Filament\Resources\RoleResource`で管理します。
            * `CheckboxList`を利用します。
            * `options`で、選択肢を設定します。
            * `afterStateHydrated`で、初期値を設定します。
            * `dehydrateStateUsing`で保存処理をします。
* **フォルダー通知**
    * フォルダーに紐づく通知です。
    * フォルダー通知は、`App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager`で管理します。
    * `CheckboxList`で通知を設定します。

### 通知の設定

通知の設定は、`App\Models\RoleFolderPermission` モデルと`App\Models\NotificationType`モデルで行います。

* **ロールフォルダ権限**:
    * `role_id`, `folder_id`, `permission`, `notification_type_id` で管理します。
    * `permission`で、`NOTIFY_ON`、`NOTIFY_OFF`で通知を管理します。
    * `notification_type_id`で通知の種類を設定します。
    * グローバル通知は、`folder_id`は、`1`(ルートフォルダー)で管理します。

* **通知タイプ**:
    * 通知の種類（台帳の作成、更新など）を管理します。
    * 通知の種類を定義します。
    * `name` 通知の名称です。
    * `model`  対象のモデルを設定します。
    * `event` イベントを設定します。
    * `route` 関連するルーティングを設定します。
    * `App\Models\NotificationType` で管理します。

* **通知サービス**：
* `App\Services\NotificationService`では、通知を送信するための処理を作成します。
    * `processActivityLog()`を実行します。
    * `getNotifiableRecipients()`で通知先を検索します。
    *     `getUnreadNotificationsForUser()`で未読の通知を取得します。
        *     `markAsRead()`で既読にします。
* **通知**:
* `App\Notifications\GenericNotification`で通知を作成します。
* 通知の未読情報は、`notification_user`テーブルに保存します。

## 関連ファイル

* `App\Models\NotificationType`: 通知のタイプを管理するモデル。
* `App\Models\RoleFolderPermission`: ロールとフォルダーと通知タイプを紐付け管理するモデル。
* `App\Services\NotificationService`: 通知送信を行うサービス。
* `App\Notifications\GenericNotification`:  通知を作成します。
* `App\Filament\Resources\RoleResource`: ロールを管理します。グローバル通知を設定します。
* `App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager`: ロールのフォルダー通知を設定します。
* `resources/views/livewire/notifications/user-notification-list.blade.php`: ユーザーの通知リストを表示します。
* `lang/ja/ledger.php`: 日本語の翻訳ファイル
