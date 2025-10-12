# 環境構築スクリプト検討と実装記録

**日付:** 2025年8月24日

## 1. 背景と目的

本プロジェクトの開発環境セットアップは、これまで手動でのDB権限付与など、再現性に課題がありました。特に、`sail`ユーザーへの`CREATE DATABASE`権限の付与は、新規環境構築時に毎回手動で行う必要があり、開発者のオンボーディングコストとなっていました。

本作業の目的は、この手動プロセスを自動化し、**「誰が実行しても」「いつでも同じ開発環境が」「コマンド一つで構築できる」**ように、セットアップ手順の再現性を確立することです。

## 2. 検討プロセスと課題解決

### 2.1. DB権限の自動化

MySQLコンテナの初期化メカニズムを活用し、`sail`ユーザーへの権限付与を自動化しました。

-   **初期アイデア:** `GRANT ALL PRIVILEGES ON *.* TO 'sail'@'%' WITH GRANT OPTION;` というSQL文を、MySQLコンテナ起動時に自動実行させる。
-   **メカニズム:** Dockerの公式MySQLイメージが提供する`/docker-entrypoint-initdb.d`ディレクトリにSQLスクリプトを配置すると、コンテナ初回起動時に自動実行される機能を利用。
-   **実装:**
    1.  `docker/mysql/init`ディレクトリを新規作成。
    2.  `docker/mysql/init/01-init-grants.sql`ファイルを作成し、上記の`GRANT`文を記述。
    3.  `docker-compose.yml`の`mysql`サービス定義に、`./docker/mysql/init:/docker-entrypoint-initdb.d`のボリュームマウントを追加。

### 2.2. `DockerfileQueue`のビルドエラーと解決

セットアップスクリプト実行時に、`queue`サービスのDockerイメージビルドが失敗する問題が発生しました。

-   **問題:** `docker/app/DockerfileQueue`の`FROM`句が`sail-8.4/app`を参照していたため、ビルドが失敗。
-   **原因:** `sail-8.4/app`は`laravel`サービスがビルドするローカルイメージであり、`queue`サービスがこれをベースイメージとして参照しようとすると、ビルド順序やイメージ解決の仕組みによりエラーが発生していました。
-   **解決策:** `vendor/laravel/sail/runtimes/8.4/Dockerfile`の内容を確認し、`laravel`サービスが`ubuntu:24.04`をベースにしていることを特定。`docker/app/DockerfileQueue`の`FROM`句を`FROM ubuntu:24.04`に変更することで、ビルドエラーを解消しました。

### 2.3. `DockerfileQueue`における`sail`ユーザーの作成問題と解決

`DockerfileQueue`の修正後も、`queue`サービスのビルド中に`usermod: user 'sail' does not exist`というエラーが発生しました。

-   **問題:** `DockerfileQueue`内で`sail`ユーザーを`docker`グループに追加しようとした際に、`sail`ユーザーが存在しないというエラー。
-   **原因:** `DockerfileQueue`が`ubuntu:24.04`をベースにしているため、デフォルトでは`sail`ユーザーが存在しませんでした。`sail`ユーザーは、`laravel`サービスのベースイメージ（`vendor/laravel/sail/runtimes/8.4/Dockerfile`）内で作成されています。
-   **解決策:** `DockerfileQueue`内に、`sail`ユーザーを作成するコマンド（`groupadd --force -g $WWWGROUP sail`と`useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail`）を、`usermod`コマンドの前に追記しました。これにより、`queue`サービスイメージのビルド時に`sail`ユーザーが正しく作成されるようになりました。

## 3. セットアップスクリプトの実装

上記の問題解決を経て、プロジェクトの初期セットアップを完全に自動化するシェルスクリプト`bin/setup.sh`を作成しました。

-   **目的:** 新しい開発者がプロジェクトをクローンした後、このスクリプトを一度実行するだけで、開発を開始できる状態にする。
-   **内容:**
    1.  `.env.example`から`.env`ファイルをコピー（存在しない場合）。
    2.  `sail build --no-cache`でDockerイメージをビルドし、`sail up -d`でコンテナを起動。
    3.  `sail composer install`でComposer依存関係をインストール。
    4.  `sail artisan key:generate`でアプリケーションキーを生成。
    5.  データベースが利用可能になるまで待機。
    6.  `sail artisan migrate`で中央DBのマイグレーションを実行。
    7.  `sail npm install`でNPM依存関係をインストール。
    8.  `sail npm run dev`でフロントエンドアセットをビルド。
-   **実行権限:** `chmod +x bin/setup.sh`で実行権限を付与。

## 4. ドキュメントの更新

新しいセットアップ手順を反映するため、以下のドキュメントを更新しました。

-   **`README.md` (ルート):** 一般ユーザー向けのため、元の概要に戻しました。
-   **`docs/README.md`:** 開発者向けドキュメントとして、新しい`./bin/setup.sh`スクリプトを利用する簡素化されたセットアップ手順を記載しました。

## 5. 検証

作成したセットアップスクリプトの再現性を検証するため、以下の手順でクリーンな環境からのセットアップを試みました。

-   **手順:**
    1.  `./vendor/bin/sail down --volumes --rmi all`で、既存のDockerコンテナ、ボリューム、イメージを全て削除し、完全にクリーンな状態にする。
    2.  `./bin/setup.sh`スクリプトを実行する。
-   **現在の状況:**
    *   `./bin/setup.sh`スクリプトは、Dockerイメージのビルドまでは成功するものの、その後の`composer install`のステップで停止してしまいます。この問題は現在調査中です。

## 6. 今後の課題

-   `bin/setup.sh`スクリプトが`composer install`ステップで停止する問題を特定し、解決する。
-   スクリプトが最後まで正常に動作し、アプリケーションが完全にセットアップされることを確認する。