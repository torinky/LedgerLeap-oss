# LedgerLeap 推奨コマンド

## 開発環境の起動・停止
```bash
# 開発環境起動
./vendor/bin/sail up -d

# 開発環境停止
./vendor/bin/sail stop

# 完全停止（コンテナ削除）
./vendor/bin/sail down
```

## 初回セットアップ
```bash
# 開発環境セットアップ
./bin/setup.sh        # または ./dev.sh

# 本番環境セットアップ
./bin/setup.sh -p     # または ./prod.sh

# GPU環境の場合（.envでPADDLEOCR_DEVICE=gpuに設定後）
./bin/setup.sh
```

## テストとコード品質

### テスト実行
```bash
# 全テスト実行（PHPUnit）
./vendor/bin/sail test

# 全テスト実行（Pest）
./vendor/bin/sail pest

# 特定のテストファイル実行
./vendor/bin/sail test tests/Feature/LedgerTest.php

# 特定のテストメソッド実行
./vendor/bin/sail test --filter test_user_can_create_ledger
```

### コード整形（コミット前必須）
```bash
# コード整形実行
./vendor/bin/sail pint

# 整形内容をプレビュー（実際には変更しない）
./vendor/bin/sail pint --test
```

## Artisanコマンド
```bash
# Artisanコマンド実行の基本形
./vendor/bin/sail artisan [command]

# マイグレーション
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:rollback
./vendor/bin/sail artisan migrate:fresh --seed

# キャッシュクリア
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan view:clear

# キュー処理
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan queue:listen
./vendor/bin/sail artisan queue:restart

# Tinker（対話的シェル）
./vendor/bin/sail tinker
```

## アセットビルド
```bash
# 開発モード（ウォッチモード）
./vendor/bin/sail npm run dev

# 本番ビルド
./vendor/bin/sail npm run build
```

## コンテナ管理
```bash
# コンテナ一覧表示
./vendor/bin/sail ps

# ログ表示
./vendor/bin/sail logs

# 特定サービスのログ表示
./vendor/bin/sail logs laravel.test

# コンテナ内でシェル実行
./vendor/bin/sail shell

# MySQLシェル接続
./vendor/bin/sail mysql
```

## その他の便利なコマンド
```bash
# Composerパッケージインストール
./vendor/bin/sail composer install

# Composerパッケージ更新
./vendor/bin/sail composer update

# NPMパッケージインストール
./vendor/bin/sail npm install

# IDE Helperの生成
./vendor/bin/sail artisan ide-helper:generate
```

## アクセスURL
- **アプリケーション**: http://localhost
- **Mailpit（開発用メール）**: http://localhost:8025

## 注意事項
- コミット前に必ず `./vendor/bin/sail pint` を実行してコードを整形してください
- テストが全て通ることを確認してからコミットしてください
- 全文検索機能のテストは`DatabaseMigrations`トレイトを使用し、`RefreshDatabase`は使用しないでください
