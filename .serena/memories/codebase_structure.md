# LedgerLeap コードベース構造

## ディレクトリ概要

### `/app/` - アプリケーションコア
```
/app/
├── Console/          # Artisanコマンド
├── Database/         # カスタムデータベースクラス
├── Enums/            # Enum定義（ステータス、権限タイプなど）
├── Exceptions/       # カスタム例外
├── Exports/          # Excelエクスポート機能
├── Facades/          # Facadeパターン実装
├── Filament/         # 管理画面（Filament）
├── Helpers/          # ヘルパー関数
├── Http/             # HTTPレイヤー
│   ├── Controllers/  # コントローラー
│   ├── Middleware/   # ミドルウェア
│   └── Requests/     # FormRequest（バリデーション）
├── Imports/          # Excelインポート機能
├── Jobs/             # 非同期ジョブ（キュー処理）
├── Livewire/         # Livewireコンポーネント
├── Mail/             # メール送信クラス
├── Mcp/              # LLM統合API（MCP Server）
├── Models/           # Eloquentモデル
├── Modules/          # モジュール（機能単位）
├── Notifications/    # 通知クラス
├── Observers/        # Eloquentオブザーバー
├── Policies/         # 認可ポリシー
├── Providers/        # サービスプロバイダー
├── QueryFilters/     # クエリフィルター
├── Repositories/     # リポジトリパターン
├── Rules/            # カスタムバリデーションルール
├── Services/         # ビジネスロジック（サービス層）
├── Traits/           # 再利用可能なトレイト
└── View/             # ビューコンポーザー
```

### `/config/` - 設定ファイル
- Laravelの標準設定 + カスタム設定
- アプリケーション固有設定は `config/ledgerleap.php` など

### `/database/` - データベース関連
```
/database/
├── factories/        # モデルファクトリー（テストデータ生成）
├── migrations/       # マイグレーションファイル
└── seeders/          # シーダー（初期データ投入）
```

### `/docs/` - ドキュメント
```
/docs/
├── api/              # REST API仕様
├── architecture/     # システムアーキテクチャ
├── database/         # データベース設計
├── development/      # 開発ガイドライン・技術仕様
├── features/         # 機能仕様
├── function/         # 機能詳細
├── models/           # モデル仕様
├── operations/       # 運用ガイド
├── services/         # サービス仕様
└── work/             # 作業ファイル（計画・設計・作業ログ）
    └── llm-integration/  # LLM連携機能の計画・実装記録
```

**ドキュメント管理方針**:
1. **公式ドキュメント**: `/docs/`直下 - 実装済み機能の技術仕様・運用ガイド
2. **作業ファイル**: `/docs/work/` - 開発計画、設計書、実装記録

### `/resources/` - フロントエンドリソース
```
/resources/
├── css/              # CSSソース
├── js/               # JavaScriptソース
└── views/            # Bladeテンプレート
    ├── components/   # Bladeコンポーネント
    ├── layouts/      # レイアウトファイル
    ├── livewire/     # Livewire用ビュー
    └── profile/      # プロファイル関連ビュー
```

### `/routes/` - ルーティング定義
- `web.php`: Webルート
- `api.php`: APIルート
- `console.php`: Artisanコマンド
- `channels.php`: ブロードキャストチャンネル

### `/storage/` - 生成ファイル
- `app/`: アプリケーション生成ファイル（アップロードファイルなど）
- `framework/`: フレームワークキャッシュ
- `logs/`: ログファイル

### `/tests/` - テストコード
- `Feature/`: フィーチャーテスト（機能テスト）
- `Unit/`: ユニットテスト（単体テスト）

### `/vendor/` - Composer依存パッケージ
- Composerで管理される外部ライブラリ

### `/node_modules/` - NPM依存パッケージ
- NPMで管理されるJavaScript/CSSライブラリ

## 主要コンポーネント

### データモデル（核心テーブル）
- **ledgers**: 台帳データ本体（JSON形式content）
- **ledger_defines**: 台帳テンプレート（JSON形式column_define）
- **folders**: 階層フォルダ（権限制御基盤）
- **users**: ユーザー情報
- **organizations**: 組織情報（マルチテナント対応）
- **role_folder_permissions**: 詳細権限管理

### サービス層（ビジネスロジック）
主要サービスクラス:
- `LedgerService`: 台帳管理
- `AutoLinkService`: 自動リンク機能
- `NotificationService`: 通知機能
- `WorkflowService`: ワークフロー管理
- `SynonymService`: 類義語検索
- `UserService`: ユーザー管理
- スコアリングサービス群（情報価値評価）

### UIフレームワーク
- **Filament**: 管理画面（`/app/Filament/`）
- **Livewire + MaryUI**: 一般ユーザー向けUI（`/app/Livewire/`）

### LLM統合（MCP Server）
- **ディレクトリ**: `/app/Mcp/`
- **用途**: AI統合業務管理プラットフォームへの発展
- **認証**: Laravel Sanctum（APIトークン）

## エントリーポイント

### Web
- `/public/index.php`: Webアプリケーションエントリーポイント

### CLI
- `/artisan`: Artisanコマンドラインツール

### テスト
- `./vendor/bin/sail test`: テスト実行
- `./vendor/bin/sail pest`: Pestフレームワークでテスト実行

## 設定ファイル
- `composer.json`: PHP依存関係
- `package.json`: Node.js依存関係
- `docker-compose.yml`: Laravel Sail設定
- `tailwind.config.js`: Tailwind CSS設定
- `vite.config.js`: Viteビルド設定
- `.env`: 環境変数（機密情報含む、バージョン管理外）
- `.env.example`: 環境変数テンプレート

## アーキテクチャフロー
```
Webサーバー (Nginx) 
    ↓
Laravel (PHP-FPM)
    ↓
┌───────────────────────────┐
│ HTTP Layer                │
│ ├── Controllers           │
│ └── Middleware            │
└───────────────────────────┘
    ↓
┌───────────────────────────┐
│ Application Layer         │
│ ├── Services (ビジネス)   │
│ ├── Policies (認可)       │
│ └── Jobs (非同期)         │
└───────────────────────────┘
    ↓
┌───────────────────────────┐
│ Data Layer                │
│ ├── Models (Eloquent)     │
│ └── Repositories          │
└───────────────────────────┘
    ↓
MySQL/Mroonga (全文検索)
Redis (キャッシュ/キュー)
    ↓
キューワーカー
    ↓
Apache Tika / OCR / VLM
```

## 特記事項

### Mroonga全文検索の制約
- 複合インデックスは使用不可
- 単一インデックスをOR結合で利用

### Livewireの制約
- パブリックプロパティはシンプルな連想配列のみ
- 状態は単一配列に集約（Single Source of Truth）

### テストの制約
- 全文検索機能テストは`DatabaseMigrations`トレイト必須
- `RefreshDatabase`は使用不可
