# LedgerLeap ドキュメント

## プロジェクト概要

LedgerLeap は、組織内の情報管理と共有を効率化するための台帳管理システムです。このドキュメントは、開発者向けに、システムを理解するために必要な情報を提供します。

## 運用で想定する事項

* **対象組織**: 主に中小企業やチーム、部門での利用を想定しています。情報共有や記録の管理を効率化したい組織を対象としています。
* **規模**:
    * ユーザー: 数千人規模
    * 組織: 小規模な組織も含めて数百件
    * 台帳の種類: 数千件
    * 台帳の総レコード数: 数百万件
* **ユーザー**:
    * ユーザーは、複数の組織に所属（兼務）することがあります。
    * ユーザーは、特定の目的のために作られた横断的な仮想組織（プロジェクト）に所属することがあります。
* **組織とプロジェクト**:
    * 組織やプロジェクトごとに、個別に管理すべき台帳やフォルダが存在します。
    * プロジェクト、組織は複数あり、閲覧、編集対象も複数存在します。
    * 閲覧、編集対象が、プロジェクトや組織間で部分的に重複することもあります。
* **監査**: 組織の管理責任者は、それぞれの組織活動で情報が混用されていないことを監査する必要があります。

## 制約事項

* **データベース**: 現状では、Mroonga をサポートしています。
    * 今後、他のデータベースに対応することを検討しています。
* **言語**: 現時点では、日本語での利用を想定した開発が進められています。多言語対応は後日実装予定です。
* **ブラウザ**: 基本的に最新のブラウザで動作することを想定しています。
* **環境**: Laravel, PHP, Composerで動作する環境が必要です。

## 用語

* **台帳**: 情報を管理するためのデータ構造。
* **フォルダ**: 台帳を整理するための入れ物。
* **組織**: ユーザーが所属する団体。例：営業部
* **プロジェクト**: 特定の目的のために作られた横断的な仮想組織。例：新製品開発プロジェクト
* **Mroonga**: 高速な全文検索機能を提供するデータベースエンジンです。Mroongaは日本語にも対応した高速な全文検索エンジンです。

## 技術スタック

* **言語**: PHP
* **フレームワーク**: Laravel
* **UI フレームワーク**: Filament
* **アクティビティログ**: spatie/laravel-activitylog
* **日本語解析**: igo-php
* **検索**:
    * コサイン類似度
    * レーベンシュタイン距離
* **ファイル解析**: Apache Tika

## 主な機能

### 現在の機能

* **[台帳管理](/docs/function/Ledger.md)**:
    * 台帳データの登録、編集、削除が可能です。
* **[変更履歴の記録](/docs/function/History.md)**:
    * 台帳データに対する変更履歴を記録します。
* **[通知](/docs/function/Notification.md)**:
    * 台帳データやフォルダーの更新時に、適切なユーザーへ通知を送信します。
    * 通知の配信先や、通知の有無は権限や設定によって決定されます。
* **[全文検索](/docs/function/Search.md)**:
    * 現在位置しているフォルダ階層以下や、タグ付けした台帳の内容を対象とした全文検索が可能です。
    * アップロードされたファイルはApache Tikaを用いて内容とメタデータを抽出しインデックス化することで、ファイルの全文検索も可能です。
* **[類義語を使った処理](/docs/function/Synonym.md)**:
    * 類義語を使った検索が可能です。
* **[権限管理](/docs/function/Authority.md)**:
    * フォルダーへのアクセス権限をロールごとに管理できます。
* **[ユーザー管理](/docs/function/User.md)**:
    * ユーザーの追加、編集、削除が行えます。
* **[組織管理](/docs/function/Organization.md)**:
    * 組織を管理できます。
* **[ロール管理](/docs/function/Role.md)**:
    * ロールを管理できます。
* **[モデルに対する変更管理](/docs/function/Activity.md)**:
    * モデルが変更された際に、記録されます。
* **[テストコード](/docs/function/Test.md)**:
    * テストコードが実装されています。

### 今後実装予定の機能

* **ワークフロー機能**:
    * 台帳データの登録や変更に際して、承認フローを設ける機能を検討中です。
* **アクセス権限の柔軟化**:
    * より細かな権限管理をできるようにする機能追加を検討中です。
* **外部連携**:
    * 他のシステムとの連携機能を検討しています。
* **テストコードの拡充**:
    * より多くの機能をテストできるように拡充します。

## ディレクトリ構成

* `/app/`: アプリケーションのソースコード
    * `/Models`:  Eloquent モデル
    * `/Services`:  サービスクラス
    * `/Enums`: enum
    * `/Providers`: サービスプロバイダ
* `/docs`: ドキュメント
* `/storage`: ログファイルなど
* `/resources`: view
* `/vendor`: composerでインストールされたパッケージ
* `/database`: マイグレーションファイル
* `/public`: 公開するファイル
* `/routes`: ルーティング
* `/tests`: テストコード

## 開発環境構築

1. PHP, Composer, Mroonga をインストールします。
2. `.env` ファイルを適切に設定します。
3. `composer install` コマンドを実行して、依存パッケージをインストールします。
4. `php artisan migrate` コマンドを実行して、データベースを構築します。

## 今後のドキュメント追加予定

開発が進むにつれ、以下の項目についてドキュメントを追加・更新していく予定です。

* 全体アーキテクチャ
* 各モデルの詳細
* テストコードの書き方
* 各種リレーション
* 各種ユースケース
* エンドユーザー向けドキュメント

## 関連ドキュメント

* [CustomActivity](/docs/models/CustomActivity.md)
* [NotificationService](/docs/services/NotificationService.md)
* [SynonymService](/docs/services/SynonymService.md)
* [NotificationType](/docs/models/NotificationType.md)
* [RoleFolderPermission](/docs/models/RoleFolderPermission.md)
* [TikaService](/docs/services/TikaService.md)
* [GenericNotification](/docs/notification/GenericNotification.md)
* [notification_user](/docs/notification/notification_user.md)
* [RoleResource](/docs/ui/RoleResource.md)
* [NotificationSettingsRelationManager](/docs/ui/NotificationSettingsRelationManager.md)
