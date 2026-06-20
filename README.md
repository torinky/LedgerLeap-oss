<p align="center">
  <img src="public/images/icon.svg" alt="LedgerLeap icon" width="112">
</p>

<h1 align="center">LedgerLeap</h1>

<p align="center">
  <strong>検索性・統制・現場運用のしやすさを両立する、業務台帳 / ドキュメント管理システム</strong>
</p>

<p align="center">
  必要なときに探せる ・ 権限に応じて安全に共有できる ・ 変更理由と承認履歴を残せる
</p>

<p align="center">
  <a href="README.md"><strong>日本語</strong></a> |
  <a href="README.en.md">English</a> |
  <a href="https://torinky.github.io/LedgerLeap-oss/"><strong>🌐 Website </strong></a> |
  <a href="#こんな悩みに向いています">課題</a> |
  <a href="#ペルソナ別にわかる-ledgerleap-の良さ">ペルソナ</a> |
  <a href="#画面イメージ">画面</a> |
  <a href="#quick-start">Quick Start</a>
</p>

<p align="center">
  <a href="https://github.com/sponsors/torinky"><img src="https://img.shields.io/badge/sponsor-30363D?style=for-the-badge&logo=GitHub-Sponsors&logoColor=#EA4AAA" alt="GitHub Sponsors"></a>
</p>

LedgerLeap は、組織内に散在する台帳、業務記録、申請、添付ファイルを一元管理し、**必要なときに探せる・権限に応じて安全に共有できる・証跡を残せる** Web ベースの台帳 / ドキュメント管理システムです。

紙、共有フォルダ、Excel、メールに分散した情報を、**検索性・統制・現場運用のしやすさ**を両立しながらまとめたい日本の業務現場を想定しています。

<p align="center"><em>マイポータルの実画面です。ライトモード / ダークモードの両方を確認できます。</em></p>

| Light mode | Dark mode |
| --- | --- |
| <img src="docs/assets/readme/my-portal-light.png" alt="LedgerLeap My Portal screenshot in light mode" width="100%"> | <img src="docs/assets/readme/my-portal.png" alt="LedgerLeap My Portal screenshot in dark mode" width="100%"> |

## こんな悩みに向いています

- 必要な記録や資料が、担当者しか見つけられない
- Excel、PDF、Word、スキャン文書が別々に保管され、横断検索できない
- 部門や案件ごとに、見せてよい情報と見せてはいけない情報を分けたい
- 承認や差し戻しの履歴が追えず、監査や引き継ぎで説明しづらい
- 現場には入力しやすさが必要だが、管理側には統制と証跡が必要

## ペルソナ別にわかる LedgerLeap の良さ

| ペルソナ | 良いこと | 支える機能 |
| --- | --- | --- |
| 現場担当 | 必要な記録や添付をすぐ探せる。入力ルールがそろい、探し直しや二重入力が減る。 | 柔軟な台帳定義、全文検索、レコード複製 |
| 現場リーダー / 管理職 | チームの記録状況、承認待ち、差し戻し理由が把握しやすい。判断や引き継ぎが属人化しにくい。 | マイポータル、ワークフロー、通知、関連案件 |
| 情報システム / 管理者 | 組織・フォルダ単位でアクセスを制御しやすく、誰が何をしたかを追跡しやすい。 | マルチテナント、権限管理、アクセス可視化、活動履歴 |
| 監査 / 内部統制 / DX担当 | 変更理由、版差分、承認経路、アクセス記録を説明しやすい。API / MCP 連携の入口も持てる。 | 差分比較、承認フロー、監査ログ、API / MCP |

## LedgerLeap の強み

- **後から探せる**: 台帳データだけでなく、PDF、Office 文書、画像由来のテキストまで検索対象に含められます。
- **運用に合わせやすい**: テキスト、数値、自動採番、添付ファイル、Markdown 対応テキストなどを組み合わせて、部門ごとの台帳を柔軟に定義できます。
- **統制しやすい**: テナント、フォルダ、ロール単位の権限制御と活動履歴により、現場運用と監査対応を両立しやすくします。
- **重い添付でも止まりにくい**: OCR やテキスト抽出は非同期ジョブで処理できるため、利用者の操作を妨げにくい構成です。
- **外部連携しやすい**: REST API と remote MCP の公開契約を整備しており、既存システムや LLM クライアントとの連携導線を持てます。

## 主な機能

- **マルチテナント対応**: 複数の組織やプロジェクトが、単一システム上でデータを論理分離して運用できます。
- **柔軟な台帳定義**: テキスト、数値、自動採番、添付ファイル、Markdown 対応テキストエリアなどを組み合わせた台帳を作成できます。
- **階層型フォルダ管理**: 直感的なフォルダ構造で、部門・案件・用途ごとの整理が可能です。
- **全文検索とファイル解析統合**: MySQL / Mroonga による日本語全文検索に加え、Apache Tika、OcrMyPDF、PaddleOCR-VL 0.9B を組み合わせて添付ファイルの検索性を高めます。
- **FileInspector ドロワー**: 添付ファイルの処理状態、抽出テキスト、履歴をまとめて確認できます。
- **非同期処理**: 重いファイル解析や OCR を Redis とキューワーカーで非同期実行し、画面操作の待ち時間を抑えます。
- **高度な権限管理と可視化**: ロール、組織、フォルダ単位で閲覧・書き込み・点検・承認・管理権限を割り当て、継承関係を含めて可視化できます。
- **活動履歴と監査対応**: 「いつ、誰が、何をしたか」を追跡し、フォルダ配下まで含めた絞り込みや確認が可能です。
- **ワークフロー**: 点検、承認、多段階フロー、複数人承認、担当者推薦、通知に対応します。
- **台帳レコード複製**: 日報や定型申請のような繰り返し入力を、既存レコードをもとに効率化できます。
- **関連案件タブ**: 識別番号検索と意味検索（RAG ベクトル検索）の 2 軸で、関連レコードを横断探索できます。
- **自動リンク機能**: 台帳本文や説明文に含まれる識別子から、関連仕様書や外部チケットへのリンクを自動生成できます。
- **ユーザー中心の UI**: マイポータル、表示レベル切り替え、カラムのグループ化、レスポンシブ対応で、日常利用しやすい操作感を目指しています。

## 対象組織と利用シナリオ

- **対象組織**: 紙、共有フォルダ、メール、Excel ベースの管理に限界を感じ、記録・検索・共有・監査を改善したい部門や組織
- **想定ユーザー層**:
  - 実務担当者
  - 現場リーダー / 作業班長
  - 部門長 / 承認者
  - 情報システム担当
  - 監査 / 内部統制 / DX 推進担当
- **想定規模**:
  - ユーザー: 数人〜数千人
  - 組織 / プロジェクト: 数百件規模
  - 台帳定義: 数千種類
  - 台帳レコード: 数百万件規模
- **主な利用シナリオ**:
  - 業務手順、申し送り、顧客情報、申請記録の蓄積と検索
  - 複数部署 / 複数案件をまたぐ情報共有
  - アクセス権限と活動履歴を前提にした監査対応
  - 紙や添付中心の運用から、検索しやすい電子管理への移行

## 画面イメージ

以下は、現在のデモデータで動作している**実際の台帳一覧画面**です。検索、フォルダナビゲーション、表示レベル切り替え、一覧確認を 1 画面で行えます。こちらも **ライトモード / ダークモード** の両方を掲載しています。

| Light mode | Dark mode |
| --- | --- |
| <img src="docs/assets/readme/ledger-list-light.png" alt="LedgerLeap ledger list screenshot in light mode" width="100%"> | <img src="docs/assets/readme/ledger-list.png" alt="LedgerLeap ledger list screenshot in dark mode" width="100%"> |

## ドキュメント案内

| 目的 | ドキュメント                                                                         |
| --- |--------------------------------------------------------------------------------|
| プロジェクト紹介ページ (GitHub Pages) | [LedgerLeap AI-Native Platform](https://torinky.github.io/LedgerLeap-oss/)     |
| 開発者向けドキュメントの入口 | [docs/documentation.md](docs/documentation.md)                                            |
| REST API / remote MCP の公開契約 | [docs/api/README.md](docs/api/README.md)                                       |
| 開発環境のセットアップ詳細 | [docs/development/environment-setup.md](docs/development/environment-setup.md) |
| 画面利用イメージの入口 | [My Portal とナビゲーション](docs/getting-started/portal-and-navigation.md)            |
| 検索機能の公開契約 | [Search API](docs/api/search-api.md)                                           |

> AI / automation client 向けの公開導線は **`docs/api/` 配下の API / MCP 公開契約** に集約しています。

## Quick Start

### 開発環境のセットアップ

詳細は以下を参照してください。

- [Developer Documentation](docs/documentation.md)
- [Environment Setup Details (`docs/development/environment-setup.md`)](docs/development/environment-setup.md)

**クイックセットアップ:**

```bash
# Clone the repository
git clone [repository-url] ledgerleap
cd ledgerleap

# Setup (automatically detects your environment)
./bin/setup.sh        # Development environment
./bin/setup.sh -p     # Production environment
```

セットアップスクリプトは次を実行します。

- Docker コンテナのビルド
- Composer / NPM 依存関係のインストール
- データベースマイグレーション
- アーキテクチャ（ARM64 / AMD64）の自動判定
- `.env` 設定に応じた GPU 関連設定の適用

### Composer を手動インストールする場合

Laravel Sail の起動前に Composer 依存関係を手動で入れたい場合は、以下の方法を利用できます。

Reference: https://readouble.com/laravel/9.x/ja/sail.html#installing-composer-dependencies-for-existing-projects

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v $(pwd):/var/www/html \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-reqs
```

## 技術概要

- **言語 / フレームワーク**: PHP 8.4, Laravel 13
- **データベース**: MySQL / MariaDB, Mroonga
- **フロントエンド**: Livewire 4, Alpine.js, Tailwind CSS 4, DaisyUI 5, Mary UI, Filament
- **主要機能基盤**:
  - マルチテナント: `stancl/tenancy`
  - 権限管理: `spatie/laravel-permission`
  - 活動履歴: `spatie/laravel-activitylog`
  - 添付ファイル解析: Apache Tika, OcrMyPDF, PaddleOCR-VL 0.9B
  - API 認証: Laravel Sanctum
- **開発環境**: Laravel Sail (Docker)

システム全体像や API / MCP の技術詳細は、ドキュメントの [docs/documentation.md](docs/documentation.md) と [docs/api/README.md](docs/api/README.md) を参照してください。

## License

LedgerLeap is open-sourced software licensed under the [MIT license](LICENSE).

## Credit

- Japanese Wordnet (v1.1) © 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi
  https://bond-lab.github.io/wnja/index.en.html
- 日本語ワードネット（1.1 版）© 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi
  https://bond-lab.github.io/wnja/index.ja.html
