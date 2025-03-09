# ユーザー管理機能

## 概要

ユーザー管理機能は、LedgerLeap にアクセスするユーザーを管理する機能です。この機能により、ユーザーの登録、編集、削除、パスワード変更、ロールの付与など、ユーザーに関する一連の操作を管理することができます。

## 機能詳細

### ユーザーの追加

* `App\Models\User` に新しいレコードを作成することで、ユーザーを追加できます。
* `App\Filament\Resources\UserResource`でユーザーを登録します。
* ユーザーの`name`、`email`、`password`、`organization`などを登録します。

### ユーザーの編集

* `App\Models\User` の既存レコードを更新することで、ユーザー情報を編集できます。
* `App\Filament\Resources\UserResource`でユーザー情報を編集します。
* ユーザーの`name`、`email`、`password`、`organization`などを編集します。
* ユーザーは、複数の`organization`に所属することができます。
* ユーザーは、複数の`Role`に所属することができます。

### ユーザーの削除

* `App\Models\User` の既存レコードを削除することで、ユーザーを削除できます。
* `App\Filament\Resources\UserResource`でユーザーを削除します。
* 削除したユーザーは、システムにアクセスできなくなります。

### パスワードの変更

* `App\Models\User`の`password`を更新します。
* ユーザーは自分のパスワードを変更することができます。
* 管理者は他のユーザーのパスワードを変更することができます。

### ロールの付与

* `App\Models\Role`を`App\Models\User`に紐づけて、ロールを付与します。
* ユーザーは複数のロールを付与されます。
* `App\Filament\Resources\UserResource`で管理します。

## 関連ファイル

* `App\Models\User`: ユーザー情報を管理するモデル。
* `App\Filament\Resources\UserResource`: ユーザーを管理するリソース。
* `App\Models\Role`: ロールを管理するモデル。
* `App\Models\Organization`:  組織を管理するモデルです。
