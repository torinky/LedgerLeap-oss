# LedgerLeap ドキュメント

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[//]: # ({{-- バッジを追加する場合: [![Build Status]&#40;...&#41;]&#40;&#41; [![Code Coverage]&#40;...&#41;]&#40;&#41; --}})

## プロジェクト概要

LedgerLeap は、組織内の情報管理と共有を効率化するための**Webベース台帳管理システム**です。散在しがちな業務記録やノウハウ、各種情報を一元的に管理し、
**全文検索 (添付ファイル含む)** や**柔軟な権限管理**
を通じて、必要な情報へのアクセスと適切な情報共有を実現します。このドキュメントは、開発者向けに、システムの設計思想、機能、技術的側面を理解するために必要な情報を提供します。

---

## ターゲットユーザーと利用シナリオ

LedgerLeap は、以下のようなユーザーと状況での利用を想定して設計されています。

* **対象組織**: 主に中小企業や大企業の部門・チーム単位での利用。紙ベースや共有フォルダでの情報管理に限界を感じ、記録・検索・共有・監査を効率化したい組織。
* **想定規模**:
    * ユーザー: 数人～数千人規模。
    * 組織/プロジェクト: 数百件規模。
    * 台帳の種類（定義）: 数千種類作成可能。
    * 台帳レコード総数: 数百万件規模（Mroongaによる高速検索）。
* **想定ユーザー層とITリテラシー**:
    * **実務担当者**: 日々の業務記録を入力・参照するユーザー（例: 製造現場、営業、事務）。ITリテラシーは不問。
    * **管理者**: 部門長、プロジェクトマネージャー、情報システム担当者。メンバー管理、フォルダ構成、権限設定、利用状況確認・監査などを行う。
    * **現場リーダー/作業班長**: メンバーの代理入力、チーム内での情報共有・指示確認など。
* **主な利用シナリオ**:
    * **情報共有とナレッジ蓄積**: 業務手順、申し送り事項、顧客情報などを記録・共有し属人化を防止。全文検索で迅速に情報発見。
    * **複数組織・兼務への対応**: ユーザーは複数の部署やプロジェクトに所属可能。役割に応じた権限でアクセス。
    * **アクセス制御と監査**: フォルダ・台帳ごとにアクセス権限を細かく設定。管理者は変更履歴やアクセスログを確認し、情報管理の監査に利用。
    * **ペーパーレス化と検索性向上**: 紙の記録を電子化し、保管スペース削減と検索性を向上。**Apache Tika** により、Word,
      Excel, PDFなど多様な形式の**添付ファイル内容も全文検索対象**に。

---

## LedgerLeap の特徴と機能

* **柔軟な台帳定義**: 用途に合わせて自由に項目を設定できる台帳を作成可能。
* **階層型フォルダ管理**: 直感的なフォルダ構造で情報を整理。
* **強力な全文検索とOCR**: 
    * **MySQL/Mroonga** により、台帳データ・**添付ファイル**を高速に日本語全文検索。類義語検索にも対応。
    * **OCR機能**: **OcrMyPDF** を利用し、画像ファイルやスキャンされたPDFからテキストを抽出し、全文検索の対象に含めることで、検索能力を大幅に向上させます。
* **非同期処理による堅牢なファイル解析**: 
    * ファイルアップロード後のテキスト抽出やOCR処理は、**Redis**と**キューワーカー**を利用した非同期ジョブとして実行されます。これにより、ユーザーは重い処理の完了を待つことなく、スムーズに操作を継続できます。
* **詳細な権限管理と追跡**: 
    * **アクセス権限の可視化**: リソース（フォルダ、台帳）ごとに、誰が・どの組織が・どのロールでアクセス可能かを、継承関係も含めて詳細に表示。組織名やロール名でのフィルタリングも可能です。
    * **高度な活動履歴**: 「いつ、誰が、何をしたか」を正確に記録。フォルダを指定すれば配下の全リソースの活動をまとめて追跡できるなど、監査や状況把握のニーズに応えるインテリジェントな表示範囲と、操作者や期間での強力なフィルタリング機能を備えます。
* **ワークフロー機能**: 台帳データの登録・更新に対して、多段階の承認プロセス（例: 点検→承認）を設定可能。柔軟な担当者設定と、通知負荷を軽減する集約通知に対応。
* **リアルタイム通知**: データ更新時に、設定に応じて関係者にリアルタイムで通知。ワークフロー関連の通知も実装。
* **ユーザー中心のインターフェース**:
    * **マイポータル**: ログインユーザーが次に何をすべきか把握しやすいダッシュボード機能。
    * **レスポンシブデザイン**: PC、タブレットなど様々なデバイスで快適に利用可能。
    * **UIフレームワーク**: **Filament (管理者向け)** および **MaryUI (DaisyUIベース、一般ユーザー向け)** を採用。

---

## 技術スタック

* **言語**: PHP (^8.4)
* **フレームワーク**: Laravel (^12.0)
* **データベース**: MySQL (^8.0) / MariaDB + **Mroonga** (全文検索エンジン)
* **フロントエンド**:
    * **UI (一般)**: MaryUI (V2.x-dev), DaisyUI (^5.0), Alpine.js (^3.14), Tailwind CSS (^4.0), Vite (^6.2)
    * **UI (管理)**: Filament PHP (^3.2)
    * **Livewire**: (^3.6)
* **主要ライブラリ**:
    * 権限管理: `spatie/laravel-permission` (^6.9)
    * アクティビティログ: `spatie/laravel-activitylog` (^4.9)
    * フォルダ階層管理: `kalnoy/nestedset` (^6.0)
    * ファイル内容抽出: **Apache Tika** (`vaites/php-apache-tika` ^1.3)
    * 日本語形態素解析 (検索用): `logue/igo-php` (^0.2.1)
    * Excel/CSV処理: `maatwebsite/excel` (^3.1)
    * API認証: `laravel/sanctum` (^4)
* **開発環境**: Laravel Sail (Docker)

---

## システムアーキテクチャ

LedgerLeap の全体的なシステム構成については、以下のドキュメントを参照してください。

*   [システムアーキテクチャ概要](/docs/architecture/overview.md)
*   [キューワーカと非同期処理](/docs/architecture/QueueProcessing.md)

---

## データベーススキーマ

主要なデータベーステーブルの構造やリレーションについては、以下のドキュメントを参照してください。

*   [データベーススキーマ概要](/docs/database/schema.md)

---

## API仕様

LedgerLeap APIの利用方法や主要なエンドポイントについては、以下のドキュメントを参照してください。

*   [API仕様概要](/docs/api/README.md)

---

## 開発ガイドライン

開発を進める上でのコーディング規約やGitの運用ルールについては、以下のドキュメントを参照してください。

*   [コーディング規約](/docs/development/coding_standards.md)
*   [Gitブランチ戦略とコミット規約](/docs/development/branch_strategy.md)

---

## 開発環境構築 (Laravel Sail)

LedgerLeap の開発環境は **Laravel Sail (Docker)** を使用して簡単に構築できます。

1. **必須要件**: Docker Desktop がインストールされていること。
2. **リポジトリのクローン**:
   ```bash
   git clone [リポジトリURL] ledgerleap
   cd ledgerleap
   ```
3. **`.env` ファイルの準備**:
   ```bash
   cp .env.example .env
   ```
   `.env` ファイル内のデータベース接続情報 (`DB_HOST`, `DB_PORT` など) や `APP_URL` を、必要に応じて Sail
   の設定に合わせて確認・調整してください (通常はデフォルトで動作します)。
4. **Sail の起動と依存関係インストール**:
   ```bash
   ./vendor/bin/sail up -d # コンテナをバックグラウンドで起動
   ./vendor/bin/sail composer install # PHP依存関係をインストール
   ./vendor/bin/sail npm install # Node.js依存関係をインストール
   ./vendor/bin/sail npm run build # フロントエンドアセットをビルド
   ```
   初回起動時はイメージのビルドに時間がかかります。`docker-compose.yml` には以下のサービスが含まれています:
    * `laravel`: アプリケーション本体 (PHP-FPM)
    * `queue`: Laravel Queue Worker
    * `mysql`: Mroonga 付き MySQL データベース
    * `redis`: キャッシュ、セッション、キュー用
    * `tika`: Apache Tika サーバー (ファイル内容抽出用)
    * `mailpit`: 開発用メールサーバー
    * `meilisearch`: (現状利用されていない可能性あり)
    * `selenium`: (ブラウザテスト用)
5. **アプリケーションキーの生成**:
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```
6. **データベースマイグレーションとシーディング**:
   ```bash
   ./vendor/bin/sail artisan migrate:fresh --seed # DBを初期化し、初期データを投入
   ```
7. **アクセス**: ブラウザで `.env` の `APP_URL` (デフォルト: http://localhost) にアクセスします。

**その他の Sail コマンド例**:

* コンテナ停止: `./vendor/bin/sail stop`
* Artisan コマンド実行: `./vendor/bin/sail artisan [コマンド]`
* Tinker 実行: `./vendor/bin/sail tinker`
* テスト実行: `./vendor/bin/sail test` または `./vendor/bin/sail pest`

---

## 制約事項

* **データベース**: 現状、MySQL/MariaDB + **Mroonga** が必須です。Mroonga なしでの動作は保証されません。
* **言語**: UI は主に日本語です。多言語対応は今後の課題です。
* **ブラウザ**: モダンブラウザの最新版を推奨します。

## 用語

* **台帳**: 情報を管理するためのデータ構造。
* **フォルダ**: 台帳を整理するための入れ物。
* **組織**: ユーザーが所属する団体。例：営業部
* **プロジェクト**: 特定の目的のために作られた横断的な仮想組織。例：新製品開発プロジェクト
* **Mroonga**: 高速な全文検索機能を提供するデータベースエンジンです。Mroongaは日本語にも対応した高速な全文検索エンジンです。
* **Apache Tika**: 多様なファイル形式からテキストやメタデータを抽出するためのツールキット。添付ファイル検索に利用。

## 主な機能（詳細リンク）

* **[マイポータル](/docs/function/MyPortal.md)**: ユーザー個別のダッシュボード機能。
* **[台帳管理](/docs/function/Ledger.md)**: 台帳データの登録、編集、削除が可能。
* **[アクセスとアクティビティ](/docs/function/AccessAndActivity.md)**: 詳細な権限表示と活動履歴の追跡機能。
* **[通知](/docs/function/Notification.md)**: 台帳データやフォルダ更新時にユーザーへ通知。権限や設定で制御。
* **[全文検索](/docs/function/Search.md)**: フォルダ階層以下やタグを対象に全文検索。**添付ファイル検索**も可能。
* **[類義語を使った処理](/docs/function/Synonym.md)**: 類義語検索に対応。
* **[権限管理](/docs/function/Authority.md)**: フォルダへのアクセス権限をロールごとに管理。
* **[ユーザー管理](/docs/function/User.md)**: ユーザーの追加、編集、削除。
* **[組織管理](/docs/function/Organization.md)**: 組織の管理。
* **[ロール管理](/docs/function/Role.md)**: ロールの管理。
* **[ワークフロー機能](/docs/function/Workflow.md)**: 台帳レコードに対する承認フロー機能。
* **[テストコード](/docs/function/Test.md)**: テストコードの実装状況。

### 今後実装予定の機能

* **ワークフロー機能**:
    * 台帳データの登録や変更に際して、承認フローを設ける機能を検討中です。
* **外部連携**:
    * 他のシステムとの連携機能を検討しています。
* **テストコードの拡充**:
    * より多くの機能をテストできるように拡充します。

## ディレクトリ構成

* `/app/`: アプリケーションのソースコード
    * `/Models`: Eloquent モデル
    * `/Services`: サービスクラス
    * `/Enums`: Enum
    * `/Providers`: サービスプロバイダ
    * `/Http/Controllers`: コントローラー
    * `/Livewire`: Livewire コンポーネント
    * `/Filament`: Filament リソース、ページなど
* `/config/`: 設定ファイル
* `/database`: マイグレーション、シーダー、ファクトリ
    * `/migrations`: マイグレーションファイル
    * `/seeders`: シーダーファイル
* `/docs`: ドキュメント（このファイル含む）
* `/lang`: 言語ファイル
* `/public`: 公開ディレクトリ (index.php, アセット)
* `/resources`: ビュー、CSS、JavaScript ソース
    * `/css`: CSS ソース
    * `/js`: JavaScript ソース
    * `/views`: Blade テンプレート
        * `/layouts`: レイアウトファイル
        * `/livewire`: Livewire 用ビュー
        * `/profile`: プロファイル関連ビュー
        * `/components`: Blade コンポーネント
* `/routes`: ルーティング定義
* `/storage`: アプリケーション生成ファイル (ログ、キャッシュ、アップロードファイルなど)
* `/tests`: テストコード (Feature, Unit)
* `/vendor`: Composer 依存パッケージ
* `docker-compose.yml`: Laravel Sail 設定ファイル
* `tailwind.config.js`: Tailwind CSS 設定ファイル
* `vite.config.js`: Vite 設定ファイル
* `composer.json`: PHP 依存関係定義
* `package.json`: Node.js 依存関係定義

## テスト

* PHPUnit および Pest を使用したテストが `/tests` ディレクトリに含まれています。
* テストの実行: `./vendor/bin/sail test` または `./vendor/bin/sail pest`

## 貢献方法

バグ報告や機能提案は GitHub Issues へお願いします。プルリクエストを送る際は、事前に Issue
で議論するか、開発ブランチからトピックブランチを作成してください。コードスタイルは `laravel/pint` に準拠してください (
`./vendor/bin/sail pint` でチェック可能)。

## ライセンス

LedgerLeap は [MITライセンス](https://opensource.org/licenses/MIT) の下で公開されています。

## 今後のドキュメント追加予定

開発が進むにつれ、以下の項目についてドキュメントを追加・更新していく予定です。

*   **テストコードの書き方ガイドライン**: テストコードの作成方針や具体的な記述方法について。
*   **各種ユースケースの詳細な説明**: LedgerLeapの主要な利用シナリオに基づいた、より具体的な操作手順や活用例。
*   **エンドユーザー向け操作マニュアル**: システムを利用するエンドユーザー向けの、機能ごとの操作方法を網羅したマニュアル。
*   **APIエンドポイント詳細**: 各APIエンドポイントの具体的なリクエストパラメータ、レスポンスフィールド、認証要件など。([API仕様概要](/docs/api/README.md)は作成済み)
*   （その他、必要に応じて追加）

## 関連ドキュメント

### models

*   [AttachedFile](/docs/models/AttachedFile.md)
*   [CustomActivity](/docs/models/CustomActivity.md)
*   [Folder](/docs/models/Folder.md)
*   [Ledger](/docs/models/Ledger.md)
*   [LedgerDefine](/docs/models/LedgerDefine.md)
*   [Organization](/docs/models/Organization.md)
*   [Permission](/docs/models/Permission.md)
*   [Role](/docs/models/Role.md)
*   [User](/docs/models/User.md)
*   （整備中）

### services

*   [LedgerService](/docs/services/LedgerService.md)
*   [NotificationService](/docs/services/NotificationService.md)
*   [SynonymService](/docs/services/SynonymService.md)
*   [UserService](/docs/services/UserService.md)
*   [WorkflowService](/docs/services/WorkflowService.md)
*   （整備中）
