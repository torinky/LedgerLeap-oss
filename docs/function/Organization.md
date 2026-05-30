# 組織管理機能

## 概要

組織管理機能は、LedgerLeap
において、ユーザーが所属する組織を管理するための機能です。この機能により、組織の作成、編集、削除など、組織に関する一連の操作を管理することができます。組織はツリー構造（親子関係）を取ることができ、親組織のロールを子組織も引き継ぎます。個人（ユーザー）は複数の組織に所属することができます。ロールは個人、組織に与えることができますが、基本的な運用は組織のロールのみでされることを想定しています。

## 機能詳細

### 組織の追加

* `App\Models\Organization` に新しいレコードを作成することで、組織を追加できます。
* `App\Filament\Resources\OrganizationResource` で組織を登録します。
* 組織には、`name`(組織名)、`description`(説明)を登録します。
* 組織には親組織を紐づけることができます。

### 組織の編集

* `App\Models\Organization` の既存レコードを更新することで、組織情報を編集できます。
* `App\Filament\Resources\OrganizationResource` で組織情報を編集します。
* 組織の`name`(組織名)、`description`(説明)、`親組織`などを編集します。
* 組織に所属するユーザーを編集します。

### 組織の削除

* `App\Models\Organization` の既存レコードを削除することで、組織を削除できます。
* `App\Filament\Resources\OrganizationResource` で組織を削除します。
* 削除した組織には、ユーザーは所属することができなくなります。
* 削除した組織の子組織も削除されます。

### 組織とユーザー

* ユーザーは複数の組織に所属することができます。
* `App\Models\User` に組織を設定します。
* `App\Filament\Resources\UserResource`で、ユーザーに組織を紐づけます。

### 組織のロール継承

* 組織には、親組織を設定できます。
* 親組織のロールを、子組織は継承します。
* 親組織に設定されたロールは、子組織でも有効になります。

### 組織とロール

* 組織にロールを付与することができます。
* `App\Models\Role`を`App\Models\Organization`に紐づけます。
* `App\Filament\Resources\OrganizationResource`で設定します。
* 基本的な運用は、組織のロールのみで行うことを想定しています。

### ユーザーとロール

* ユーザーは、組織とは別に、ロールを付与することができます。
* `App\Models\Role`を`App\Models\User`に紐づけます。
* `App\Filament\Resources\UserResource`で、設定します。

## 関連ファイル

* `App\Models\Organization`: 組織情報を管理するモデル。
* `App\Filament\Resources\OrganizationResource`: 組織を管理するリソース。
* `App\Models\User`:  ユーザーを管理するモデル。
* `App\Filament\Resources\UserResource`:  ユーザーを管理するリソースです。
* `App\Models\Role`:  ロールを管理するモデル。
