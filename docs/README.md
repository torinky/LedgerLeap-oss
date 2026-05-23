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
* **URL**:
    - アプリ: http://localhost
    - メール: http://localhost:8025
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

* **マルチテナント対応**: 複数の組織やプロジェクト（テナント）が、単一のシステム上でデータを論理的に分離して運用可能。
* **柔軟な台帳定義**: 用途に合わせて自由に項目（テキスト、数値、自動採番、添付ファイル、Markdown対応テキストエリア等）を設定できる台帳を作成可能。
* **台帳レコード複製機能** (✅ 2025年12月実装完了): 
    * 既存の台帳レコードを元に、新規レコードを効率的に作成できます。
    * 日報や週報など、定期的に類似した内容を記録する場合に特に有用です。
    * 日付フィールドや添付ファイルは自動的に除外され、固定的な項目（担当者名、プロジェクト名等）のみが複製されます。
    * 詳細は [台帳レコード複製機能の設計](/docs/work/core-features/2025-12-11_ledger_duplicate_feature_design.md) を参照。
* **関連案件タブ** (✅ 2026年3月実装完了 — Issue [#54](https://github.com/torinky/LedgerLeap/issues/54) / [#76](https://github.com/torinky/LedgerLeap/issues/76)):
    * 台帳レコード詳細画面に「関連案件」タブを追加。**識別番号検索**と**意味検索（RAGベクトル検索）**の2軸で関連レコードを横断探索できます。
    * **識別番号検索（パターンA）**: 自レコードの `auto_number` 型カラムの値で全台帳を横断検索。監査根拠として利用可能な厳密な紐付け。
    * **識別番号検索（パターンB）**: テキスト系カラムに記載された識別番号も検索対象に追加。`AutoNumberPatternService` が `AutoLinkService` と共通のパターン生成ロジックを使用。
    * **意味検索**: RAGベクトル検索によるコサイン類似度ベースの類似レコード探索。スコア降順表示。
    * 識別理由をアイコン＋ツールチップ（🔖 識別番号 / スコアバッジ 意味検索）で表示。フィルタートグルで識別番号・意味検索を個別に ON/OFF 可能。
    * 表示は台帳リスト画面と同一コンポーネント（`x-ledger.table-row`）を再利用。`#[Lazy]` で遅延ロード。
    * 詳細は [関連案件タブ機能](features/related-ledgers.md) を参照。

## 4. 開発環境コマンド

開発環境（Laravel Sail）での基本操作コマンドです。詳細は `/docs/development/environment-setup.md` を参照。

> [!IMPORTANT]
> LedgerLeap のローカル開発・テストは **Laravel Sail (Docker)** 前提です。
> とくにテストは host の `php artisan test` / `./vendor/bin/pest` では実行せず、必ず `./vendor/bin/sail ...` か Docker-based PhpStorm interpreter を使用してください。

```bash
# 起動・停止
./vendor/bin/sail up -d
./vendor/bin/sail stop

# テスト実行
./vendor/bin/sail test
./vendor/bin/sail artisan test

# コード整形（コミット前必須）
./vendor/bin/sail pint

# Artisanコマンド
./vendor/bin/sail artisan [command]
```

* **階層型フォルダ管理**: 直感的なフォルダ構造で情報を整理。
* **強力な全文検索とVLM/OCR統合**: 
    * **MySQL/Mroonga**により、台帳データ・**添付ファイル**を高速に日本語全文検索。類義語検索にも対応。
    * **VLM統合** (✅ Phase 1-5実装完了、2025年12月-2026年1月): **PaddleOCR-VL 0.9B**による高精度なビジュアル言語モデル。Markdown生成、構造化データ抽出、手書き文字認識に対応。
    * **OCR処理**: **OcrMyPDF**を利用し、画像ファイルやスキャンされたPDFからテキストを抽出。
    * **Apache Tika**: Office文書（Word、Excel、PowerPoint等）からのテキスト・メタデータ抽出。
    * **3エンジン統合**: VLM、OCR、Tikaを並列処理し、最適なテキストソースを自動選択。OCR失敗時の自動フォールバックにより、検索精度を大幅に向上。
    * **FileInspectorドロワー** (✅ Phase 4実装完了): 添付ファイルの詳細情報、処理状態、抽出テキスト（VLM/OCR/Tika）、履歴を統合表示するインタラクティブなUI。
* **非同期処理による堅牢なファイル解析**: 
    * ファイルアップロード後のテキスト抽出やOCR処理は、**Redis**と**キューワーカー**を利用した非同期ジョブとして実行されます。これにより、ユーザーは重い処理の完了を待つことなく、スムーズに操作を継続できます。
* **高度な権限管理と追跡**: 
    * **テナント横断の権限設定**: システム共通の役割（ロール）に対し、テナントごと・フォルダごとに柔軟なアクセス権限（閲覧、書き込み、点検、承認、管理）を割り当て可能。
    * **アクセス権限の可視化**: リソース（フォルダ、台帳）ごとに、誰が・どの組織が・どのロールでアクセス可能かを、継承関係も含めて詳細に表示。組織名やロール名でのフィルタリングも可能です。
    * **高度な活動履歴**: 「いつ、誰が、何をしたか」を正確に記録。フォルダを指定すれば配下の全リソースの活動をまとめて追跡できるなど、監査や状況把握のニーズに応えるインテリジェントな表示範囲と、操作者や期間での強力なフィルタリング機能を備えます。
* **インテリジェントなワークフロー機能**: 
    * 台帳データの登録・更新に対して、多段階の承認プロセス（例: 点検→承認）を設定可能。
    * **実績ベースの担当者推薦**: 過去の担当実績や権限に基づき、次の点検者・承認者の候補をインテリジェントに推薦。
    * **複数人承認**: 複数の役割（ロール）による承認を必須とする、より厳格な承認フローを構築可能。
    * 担当者のタスク状況に応じた、個別通知と集約通知に対応。
* **自動リンク機能**: 台帳の本文や台帳定義の説明文に含まれる特定の文字列（例: `SPEC-001`）を、仕様書や外部システムのチケットなど、関連情報へ自動的にハイパーリンクを生成します。内部リンク・外部リンクに加え、テナント横断での柔軟なリンク設定が可能です。
* **リアルタイム通知**: データ更新時に、設定に応じて関係者にリアルタイムで通知。ワークフロー関連の通知も実装。
* **ユーザー中心のインターフェース**:
    * **マイポータル**: ログインユーザーが次に何をすべきか把握しやすいダッシュボード機能。
    * **カラムのグループ化と表示レベル制御**: 多数の項目を持つ台帳でも、項目をグループ化して折りたたんだり、「概要」「詳細」のように表示粒度を切り替えたりすることで、高い視認性と操作性を実現。
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
    * マルチテナント: `stancl/tenancy` (^3.7)
    * 権限管理: `spatie/laravel-permission` (^6.9)
    * アクティビティログ: `spatie/laravel-activitylog` (^4.9)
    * フォルダ階層管理: `kalnoy/nestedset` (^7.0)
    * **ファイル処理統合 (Phase 1-5実装完了)**:
        * **VLM (Visual Language Model)**: **PaddleOCR-VL 0.9B** - 高精度OCR、Markdown生成、構造化データ抽出
        * **OCR**: **OcrMyPDF** - 画像・PDFのOCR処理とPDF最適化
        * **テキスト抽出**: **Apache Tika** (`vaites/php-apache-tika` ^1.3) - Office文書、PDF等の汎用テキスト抽出
    * 日本語形態素解析 (検索用): `logue/igo-php` (^0.2.1)
    * Excel/CSV処理: `maatwebsite/excel` (^3.1)
    * API認証: `laravel/sanctum` (^4)
* **開発環境**: Laravel Sail (Docker)

---

## システムアーキテクチャ

### マルチテナント・アーキテクチャ

LedgerLeapは、複数のテナント（企業、部署、プロジェクトなど）が単一のアプリケーションインスタンスを共有しながら、それぞれのデータを論理的に分離して管理できるマルチテナント・アーキテクチャを採用しています。

#### 採用方式: シングルDB・`tenant_id`方式

本プロジェクトでは、`stancl/tenancy`ライブラリが公式にサポートする**「シングルDB・`tenant_id`方式」**をアーキテクチャの基盤としています。これは、安定性と将来の拡張性を両立するための戦略的な決定です。

*   **論理的なデータ分離:**
    *   単一のデータベース内に、全テナントのデータを格納します。
    *   テナント固有のデータを持つテーブル（例: `ledgers`, `folders`）には`tenant_id`カラムを追加し、どのデータがどのテナントに属するかを識別します。
    *   `stancl/tenancy`が提供する`BelongsToTenant`トレイトを対応するEloquentモデルに適用することで、クエリ実行時に`WHERE tenant_id = ?`句が自動的に付与され、アプリケーションレベルでデータが厳密に分離されます。
*   **中央データとテナントデータの共存:**
    *   `users`, `roles`, `permissions`のように、全テナントで共通して利用されるデータは、`tenant_id`を持たない「中央テーブル」として管理されます。
    *   これにより、ユーザーは一度のログインで、自身がアクセス権を持つ複数のテナントの情報をシームレスに横断できます。

#### 設計決定の経緯と将来の拡張性

当初は、テナントごとに物理的なデータベースを分離するマルチDB方式や、テーブルプレフィックス方式も検討されました。しかし、これらのアプローチは、開発初期段階における実装の複雑性、特に中央データとテナントデータを跨るリレーションの扱いに大きな課題を抱えていました。

過去の失敗分析とライブラリの仕様調査を経て、まずは`stancl/tenancy`が公式にサポートし、多くの実績を持つ「シングルDB・`tenant_id`方式」で堅牢な論理分離基盤を構築することが、最もリスクが低く、かつ合理的であると結論付けました。

このアーキテクチャは、将来的に特定のテナントのデータ量が増大し、パフォーマンスやコンプライアンス上の要件から物理的な分離が必要になった場合でも、`stancl/tenancy`の柔軟な機能を活用して、**対象のテナントのみを専用データベースに移行する「ハイブリッド構成」へ段階的に移行することが可能**です。

その他のアーキテクチャ情報については、以下のドキュメントを参照してください。

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
*   [テストのベストプラクティス](/docs/development/testing/README.md)（旧: [Testing-Best-Practices.md](/docs/development/Testing-Best-Practices.md)）
    * テスト実行は **Laravel Sail / Docker-based PhpStorm interpreter 必須**
*   [ユーティリティコマンド](/docs/development/utility-commands.md)
*   [デモ環境構築ガイド](/docs/development/demo-environment-setup.md) - サンプルデータ付きデモ環境の構築
*   [MCP アーキテクチャと動作フロー](/docs/development/MCP_Architecture_and_Flow.md) - LLM統合のための技術詳解
*   [MCP プロンプトガイドライン](/docs/development/MCP_Prompt_Guidelines.md) - LLM対話の最適化ガイド

---

## 開発環境構築 (Laravel Sail)

LedgerLeap の開発環境は **Laravel Sail (Docker)** を使用して簡単に構築できます。
セットアッププロセス全体は、単一のスクリプトで自動化されています。

### クイックスタート

基本的な開発環境のセットアップ方法については、以下を参照してください:
- [ユーティリティコマンド一覧](/docs/development/utility-commands.md)
- [環境構築の詳細記録](/docs/development/environment-setup.md)

### デモ環境のセットアップ

LLMとの対話テストや機能デモのための環境構築については、以下の詳細ガイドを参照してください:
- **[デモ環境構築ガイド](/docs/development/demo-environment-setup.md)** - サンプルデータ付きデモ環境の構築手順

### 必須要件

-   [Docker Desktop](https://www.docker.com/products/docker-desktop/) がインストールされ、実行中であること。

### インストール手順

1.  リポジトリをクローンします。
    ```bash
    git clone [リポジトリURL] ledgerleap
    cd ledgerleap
    ```

2.  セットアップスクリプトを実行します。
    
    **開発環境:**
    ```bash
    ./bin/setup.sh        # または ./dev.sh
    ```
    
    **本番環境:**
    ```bash
    ./bin/setup.sh -p     # または ./prod.sh
    ```
    
    **GPU環境:**
    ```bash
    # .env で PADDLEOCR_DEVICE=gpu に設定してから
    ./bin/setup.sh
    ```
    
    これにより、Dockerコンテナのビルド、全ての依存関係（Composer & NPM）のインストール、必要なデータベースマイグレーションの実行が行われます。

3.  スクリプトの完了後、アプリケーションが利用可能になります。
    -   **アプリケーションURL:** [http://localhost](http://localhost)
    -   **Mailpit (開発用メールサーバー):** [http://localhost:8025](http://localhost:8025)

### 環境構成の詳細

`setup.sh`は以下の機能を持ちます:

- **アーキテクチャ自動検出**: ARM64またはAMD64を自動判定し、適切なDocker Composeファイルを使用
- **GPU自動判定**: `.env`の`PADDLEOCR_DEVICE`設定に基づき、GPU用設定を自動適用
- **環境別設定**: `-p`オプションで本番環境用の設定を適用

詳細は[環境構築の実装記録](/docs/development/environment-setup.md)を参照してください。

### その他の Sail コマンド例

*   コンテナ停止: `./vendor/bin/sail stop`
*   Artisan コマンド実行: `./vendor/bin/sail artisan [コマンド]`
*   Tinker 実行: `./vendor/bin/sail tinker`
*   テスト実行: `./vendor/bin/sail test` または `./vendor/bin/sail pest`

---

## 制約事項

* **データベース**: 現状、MySQL/MariaDB + **Mroonga** が必須です。Mroonga なしでの動作は保証されません。
* **言語**: UI は主に日本語です。多言語対応は今後の課題です。
* **ブラウザ**: モダンブラウザの最新版を推奨します。

## 用語

* **テナント**: システムを利用する組織やプロジェクトの単位。各テナントのデータは論理的に分離される。
* **台帳**: 情報を管理するためのデータ構造。
* **フォルダ**: 台帳を整理するための入れ物。
* **組織**: ユーザーが所属する団体。例：営業部
* **プロジェクト**: 特定の目的のために作られた横断的な仮想組織。例：新製品開発プロジェクト
* **Mroonga**: 高速な全文検索機能を提供するデータベースエンジン。日本語にも対応。
* **Apache Tika**: 多様なファイル形式（Office文書、PDF等）からテキストやメタデータを抽出するためのツールキット。
* **VLM (Visual Language Model)**: 画像やPDFからテキストを抽出する高精度なAIモデル。LedgerLeapでは**PaddleOCR-VL 0.9B**を採用し、Markdown生成、構造化データ抽出、手書き文字認識が可能。Phase 1-5で統合完了（2025年12月-2026年1月）。
* **OCR (Optical Character Recognition)**: 光学文字認識。画像やスキャンされたPDFからテキストを抽出する技術。LedgerLeapでは**OcrMyPDF**を使用。
* **FileInspector**: 添付ファイルの詳細情報を表示するドロワーUI。VLM/OCR/Tikaの処理状態、抽出テキスト、履歴、権限を統合表示（Phase 4実装完了）。共有用のQR導線はドロワー内にも用意され、表示中は先に閉じてから共有ダイアログを開く。

## 主な機能（詳細リンク）

* **[マイポータル](/docs/function/MyPortal.md)**: ユーザー個別のダッシュボード機能。
* **[台帳管理](/docs/function/Ledger.md)**: 台帳データの登録、編集、削除が可能。
* **[添付ファイル機能](/docs/function/Attachment.md)**: VLM/OCR/Tika統合による高精度テキスト抽出、FileInspectorドロワーによる統合UI（Phase 1-5実装完了）。
* **[スコアリングシステム](/docs/features/scoring-system.md)**: ハイブリッド型情報価値評価により、重要な台帳を自動的に優先表示。

## 運用ガイド

* **[FileInspectorパフォーマンス測定](/docs/operations/fileinspector-performance-monitoring.md)**: 添付ファイルインスペクターのパフォーマンス測定機能の設定と使用方法。
* **[台帳レコード性能監視](/docs/operations/ledger-records-performance-monitoring.md)**: 台帳一覧の常時モニタ指標、回帰検知の閾値、warning ログの確認方法。
* **[データベースパフォーマンス監視](/docs/operations/database-performance-monitoring.md)**: データベースのパフォーマンス監視と最適化。
* **[モデル切り替えガイド](/docs/operations/model-switching-guide.md)**: VLMモデルの切り替え手順。
* **[アクセスとアクティビティ](/docs/function/AccessAndActivity.md)**: 詳細な権限表示と活動履歴の追跡機能。
* **[通知](/docs/function/Notification.md)**: 台帳データやフォルダ更新時にユーザーへ通知。権限や設定で制御。
* **[全文検索](/docs/function/Search.md)**: フォルダ階層以下やタグを対象に全文検索。**添付ファイル検索**も可能。
* **[類義語を使った処理](/docs/services/SynonymService.md)**: 類義語検索に対応。
* **[ワークフロー機能](/docs/function/WorkFlow.md)**: 台帳レコードに対する承認フロー機能。
* **[自動リンク機能](/docs/function/AutoLink.md)**: 特定の文字列を関連情報へ自動的にリンクする機能。
* **[テストコード](/docs/function/Test.md)**: テストコードの実装状況。

### 今後実装予定の機能

* **ワークフロー機能**:
    * 台帳データの登録や変更に際して、承認フローを設ける機能を検討中です。
* **外部連携**:
    * 他のシステムとの連携機能を検討しています。
* **テストコードの拡充**:
    * より多くの機能をテストできるように拡充します。
* **LLM統合の完全化**:
    * [MCP包括的実装計画](./work/llm-integration/2025-09-29_Comprehensive_MCP_Implementation_Plan.md) に基づく、AI統合業務管理プラットフォームへの発展。

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
* `/docs`: ドキュメント（このファイル含む） **← ここに注目**
    * **公式ドキュメント（実装済み機能の仕様）:**
        * `/api/`: REST API仕様
        * `/architecture/`: システムアーキテクチャ
        * `/database/`: データベース設計
        * `/development/`: 開発ガイドライン・技術仕様
        * `/features/`: 機能仕様
        * `/function/`: 機能詳細
        * `/models/`: モデル仕様
        * `/operations/`: 運用ガイド
        * `/services/`: サービス仕様
    * **作業ファイル（計画・設計・作業ログ）:**
        * `/work/`: 実装計画、設計書、作業記録
            * `/llm-integration/`: LLM連携機能の計画・実装記録
                * **主要文書:** [LLM連携 README](./work/llm-integration/README.md), [クライアント接続モデル再計画](./work/llm-integration/2026-03-09_Client_Skill_Bootstrap_Strategy.md)
                * **現行方針:** MCP / API first、client-facing / developer-facing 分離、on-prem / local model 前提
                * **実装計画との対応:** 作業ファイルの実装結果は公式ドキュメント（特に `/development/MCP_Architecture_and_Flow.md` と `/api/README.md`）に反映されます
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

### 📖 ドキュメント管理方針

LedgerLeapのドキュメントは、以下の2種類に分類されます：

1. **公式ドキュメント** (`/docs/` 直下の各ディレクトリ)
   - 実装済み機能の技術仕様・運用ガイド
   - システムの現在の状態を正確に反映
   - 開発者・運用者が参照する正式な仕様書
   - 例: [MCP アーキテクチャと動作フロー](./development/MCP_Architecture_and_Flow.md)

2. **作業ファイル** (`/docs/work/`)
   - 開発計画、設計書、実装記録
   - 意思決定プロセスや検討過程の記録
   - 実装完了後は公式ドキュメントに内容が反映される
   - 例: [クライアント接続モデル再計画](./work/llm-integration/2026-03-09_Client_Skill_Bootstrap_Strategy.md)

**相互リンク:** 各ドキュメントには冒頭に関連ドキュメントへのリンクが記載されており、計画（作業ファイル）と実装結果（公式ドキュメント）の対応関係を追跡できます。

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
*   [AutoLink](/docs/models/AutoLink.md)
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

*   [AutoLinkService](/docs/services/AutoLinkService.md)
*   [ColumnHtmlService](/docs/services/Ledger/ColumnHtmlService.md) - カラム値のHTML表示サービス（Phase 3リファクタリング完了）
*   [LedgerService](/docs/services/LedgerService.md)
*   [NotificationService](/docs/services/NotificationService.md)
*   [SynonymService](/docs/services/SynonymService.md)
*   [UserService](/docs/services/UserService.md)
*   [WorkflowService](/docs/services/WorkflowService.md)
*   [ScoringServices](/docs/development/scoring-system.md) - スコアリングシステムの開発者ガイド
*   （整備中）
