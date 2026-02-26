# LedgerLeap プロジェクトエントリーポイント

## Webアプリケーション

### メインエントリーポイント
- **ファイル**: `/public/index.php`
- **用途**: Webブラウザからのアクセス
- **URL**: http://localhost（開発環境）

### アクセス方法
```bash
# 開発環境起動
./vendor/bin/sail up -d

# ブラウザでアクセス
# http://localhost
```

## CLIツール

### Artisanコマンド
- **ファイル**: `/artisan`
- **用途**: コマンドライン操作

```bash
# Artisanコマンド実行の基本形
./vendor/bin/sail artisan [command]

# 例:
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan queue:work
```

### 対話的シェル（Tinker）
```bash
# Tinker起動
./vendor/bin/sail tinker

# 例: ユーザー作成
>>> $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
>>> $user->save();
```

## テスト

### テスト実行
```bash
# PHPUnitで全テスト実行
./vendor/bin/sail test

# Pestで全テスト実行
./vendor/bin/sail pest

# 特定のテストファイル実行
./vendor/bin/sail test tests/Feature/LedgerTest.php

# 特定のテストメソッド実行
./vendor/bin/sail test --filter test_user_can_create_ledger
```

## API

### REST API
- **ベースURL**: http://localhost/api/v1（開発環境）
- **認証**: Laravel Sanctum（Bearer Token）

```bash
# API動作確認例
curl -H "Authorization: Bearer {token}" \
     http://localhost/api/v1/search?q=テスト
```

### 実装済みAPIエンドポイント
- `POST /api/v1/ledgers` - 台帳作成
- `GET /api/v1/search` - 高度検索（RAG対応）
- `GET /api/v1/ledger-defines` - 台帳定義一覧

## キュー処理

### キューワーカー
```bash
# キューワーカー起動
./vendor/bin/sail artisan queue:work

# キューワーカー再起動
./vendor/bin/sail artisan queue:restart

# キューワーカーをリスニングモードで起動
./vendor/bin/sail artisan queue:listen
```

### 非同期処理の用途
- ファイルテキスト抽出（Apache Tika）
- OCR処理（OcrMyPDF）
- VLM処理（PaddleOCR-VL）
- メール送信
- 通知送信

## 管理画面

### Filament管理画面
- **URL**: http://localhost/admin（開発環境）
- **用途**: システム管理者向け管理画面
- **機能**:
  - ユーザー管理
  - 組織管理
  - 権限管理
  - APIトークン管理

## 開発ツール

### Mailpit（開発用メールサーバー）
- **URL**: http://localhost:8025
- **用途**: 開発環境でのメール送信確認

### Laravel Debugbar
```bash
# 開発環境で自動的に有効化
# ブラウザの画面下部にデバッグ情報が表示される
```

## アセットビルド

### Vite開発サーバー
```bash
# ウォッチモード（開発中）
./vendor/bin/sail npm run dev

# 本番ビルド
./vendor/bin/sail npm run build
```

## データベース

### MySQLシェル接続
```bash
# MySQLシェル起動
./vendor/bin/sail mysql

# 例: データベース確認
mysql> SHOW DATABASES;
mysql> USE ledgerleap;
mysql> SHOW TABLES;
```

### マイグレーション
```bash
# マイグレーション実行
./vendor/bin/sail artisan migrate

# ロールバック
./vendor/bin/sail artisan migrate:rollback

# リフレッシュ＆シード
./vendor/bin/sail artisan migrate:fresh --seed
```

## セットアップスクリプト

### 初回セットアップ
```bash
# 開発環境
./bin/setup.sh        # または ./dev.sh

# 本番環境
./bin/setup.sh -p     # または ./prod.sh

# GPU環境（.envでPADDLEOCR_DEVICE=gpu設定後）
./bin/setup.sh
```

**セットアップスクリプトの処理内容**:
1. Dockerコンテナのビルド
2. Composer依存関係インストール
3. NPM依存関係インストール
4. データベースマイグレーション実行
5. アーキテクチャ自動検出（ARM64/AMD64）
6. GPU設定の適用（設定されている場合）

## ログ確認

### アプリケーションログ
```bash
# ログ表示（全サービス）
./vendor/bin/sail logs

# 特定サービスのログ表示
./vendor/bin/sail logs laravel.test

# リアルタイムログ表示
./vendor/bin/sail logs -f
```

### Laravelログファイル
- **場所**: `/storage/logs/laravel.log`
- **確認方法**:
```bash
./vendor/bin/sail shell
tail -f storage/logs/laravel.log
```

## コンテナ管理

### コンテナ操作
```bash
# コンテナ起動
./vendor/bin/sail up -d

# コンテナ停止
./vendor/bin/sail stop

# コンテナ完全停止（削除）
./vendor/bin/sail down

# コンテナ再ビルド
./vendor/bin/sail build --no-cache

# コンテナ一覧表示
./vendor/bin/sail ps

# コンテナ内でシェル実行
./vendor/bin/sail shell
```

## まとめ

### 主要エントリーポイント
1. **Web**: `/public/index.php` → http://localhost
2. **CLI**: `/artisan` → `./vendor/bin/sail artisan`
3. **テスト**: `./vendor/bin/sail test`
4. **API**: http://localhost/api/v1
5. **管理画面**: http://localhost/admin

### よく使うコマンド
```bash
# 開発環境起動
./vendor/bin/sail up -d

# コード整形
./vendor/bin/sail pint

# テスト実行
./vendor/bin/sail test

# Tinker起動
./vendor/bin/sail artisan tinker

# マイグレーション
./vendor/bin/sail artisan migrate
```
