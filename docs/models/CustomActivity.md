# CustomActivity モデル

## クラス概要

* **クラス名**: `App\Models\CustomActivity`
* **役割**: `spatie/laravel-activitylog` パッケージの `Activity` モデルを拡張し、アクティビティログが作成された際に特定の処理を行うためのモデルです。

## ユースケース

* モデルの変更履歴を記録する。
* 変更に応じた通知処理を行う。

## 主な機能

* `created` イベントリスナーで `NotificationService::processActivityLog()` を呼び出す。
* `SpatieActivity`を継承して、SpatieActivityの処理を流用する。

## 関連するクラス

* `Spatie\Activitylog\Models\Activity`
* `App\Services\NotificationService`

## 処理の流れ

1. モデル（例: `Ledger`, `LedgerDefine`, `Folder` など）が更新される。
2. `spatie/laravel-activitylog` パッケージにより、アクティビティログが記録される。
3. `CustomActivity` モデルの `created` イベントリスナーが実行される。
4. `NotificationService::processActivityLog()` が呼び出される。
    * 通知対象のユーザーを特定し、通知を送信します。
