# LedgerLeap システムアーキテクチャ概要

## 概要説明
LedgerLeapは、複数のコンポーネントが連携して動作するWebアプリケーションです。本ドキュメントでは、これらのコンポーネント構成と、それらがどのように連携して機能を提供するかを概説します。

## 主要コンポーネント図 (Mermaid.js)

```mermaid
graph TD
    subgraph "ユーザー環境"
        User[/"ユーザー (ブラウザ)"/]
    end

    subgraph "LedgerLeap システム (Dockerコンテナ群)"
        subgraph "Webフロント"
            WebServer["Webサーバー (Nginx)"]
        end

        subgraph "アプリケーション層"
            AppServer["アプリケーションサーバー (PHP-FPM, Laravel)"]
            QueueWorker["キューワーカー"]
        end

        subgraph "データストア・サービス"
            DBServer["データベースサーバー (MySQL/MariaDB + Mroonga)"]
            CacheQueueServer["キャッシュ/キューサーバー (Redis)"]
            TikaServer["ファイル内容抽出サーバー (Apache Tika)"]
        end

        subgraph "開発用サービス"
            MailServer["メールサーバー (Mailpit)"]
        end
    end

    User -- HTTPリクエスト --> WebServer
    WebServer -- FastCGI --> AppServer
    AppServer -- DBクエリ/保存 --> DBServer
    AppServer -- キャッシュ/セッション/ジョブ投入 --> CacheQueueServer
    AppServer -- ファイル内容抽出リクエスト --> TikaServer
    AppServer -- メール送信ジョブ --> CacheQueueServer
    QueueWorker -- ジョブ取得 --> CacheQueueServer
    QueueWorker -- メール送信 --> MailServer
    QueueWorker -- DB更新など --> DBServer
    TikaServer -- 抽出テキスト --> AppServer

    style User fill:#f9f,stroke:#333,stroke-width:2px
    style WebServer fill:#ccf,stroke:#333,stroke-width:2px
    style AppServer fill:#ccf,stroke:#333,stroke-width:2px
    style DBServer fill:#cfc,stroke:#333,stroke-width:2px
    style CacheQueueServer fill:#cfc,stroke:#333,stroke-width:2px
    style TikaServer fill:#cfc,stroke:#333,stroke-width:2px
    style MailServer fill:#fcf,stroke:#333,stroke-width:2px
    style QueueWorker fill:#ccf,stroke:#333,stroke-width:2px
```

## 各コンポーネントの説明

*   **ユーザー (ブラウザ)**: システムの利用者。Webブラウザを通じてシステムにアクセスする。
*   **Webサーバー**: HTTPリクエストを受け付け、静的コンテンツの配信やPHP-FPMへのリクエスト転送を行う。 (例: Nginx)
*   **アプリケーションサーバー (Laravel)**: ビジネスロジック、ルーティング、コントローラー、モデル、ビューなどの処理を担当するコアコンポーネント。PHP-FPM上で動作。
*   **データベースサーバー (MySQL/Mroonga)**: 台帳データ、ユーザー情報、設定など永続的なデータを格納。Mroongaによる全文検索機能を提供する。
*   **キャッシュ/キューサーバー (Redis)**: セッション情報、キャッシュデータ、非同期ジョブのキューイングに使用されるインメモリデータストア。
*   **ファイル内容抽出サーバー (Apache Tika Server)**: アップロードされたファイル (Word, Excel, PDF等) の内容を抽出し、全文検索の対象とするために使用。LaravelアプリケーションからAPI経由で利用される。
*   **メールサーバー**: システムからの通知メール（ワークフロー関連、アラートなど）を送信する。開発環境ではMailpitが使用される。
*   **キューワーカー**: Redisに積まれた非同期ジョブ（メール送信、重い処理など）をバックグラウンドで実行するプロセス。
