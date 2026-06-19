# 開発環境のセットアップ

## 目的

LedgerLeap の開発環境をローカルマシン上に構築する手順です。Docker と 1 つのセットアップスクリプトで、依存関係のインストールからデータベースマイグレーションまでを自動化します。

## 対象範囲

- Docker / Laravel Sail を使った開発環境の初回セットアップ
- アーキテクチャ別の注意点（Intel/AMD, ARM, Apple Silicon, GPU）
- セットアップ後の基本的な動作確認

### 対象外

- デモ用サンプルデータの投入 → [デモ環境構築ガイド](demo-environment-setup.md)
- 本番環境へのデプロイ → `./bin/setup.sh -p` で対応（本番設定は別途 `.env` で調整）
- CI/CD パイプラインの構築

## 前提条件

| ツール | 最低バージョン | 確認方法 |
|---|---|---|
| Docker | 24.0+ | `docker --version` |
| Docker Compose | v2+ | `docker compose version` |
| Git | 2.40+ | `git --version` |

Docker が未インストールの場合は [Docker Desktop](https://www.docker.com/products/docker-desktop/) をインストールしてください。Linux の場合は `docker` と `docker compose` プラグインを別途セットアップしてください。

## セットアップ手順

```bash
# 1. リポジトリをクローン
git clone https://github.com/torinky/LedgerLeap-oss.git
cd LedgerLeap

# 2. セットアップスクリプトを実行
./bin/setup.sh
```

`./bin/setup.sh` は以下の処理を順に実行します：

1. `.env` ファイルの生成（`.env.example` からコピー、初回のみ）
2. アーキテクチャの自動検出（ARM64 / AMD64）
3. GPU の自動検出（NVIDIA → `PADDLEOCR_DEVICE=gpu` を自動設定）
4. Docker イメージのビルド（`./vendor/bin/sail build`）
5. Docker コンテナの起動
6. Composer 依存パッケージのインストール
7. アプリケーションキーの生成（`artisan key:generate`）
8. データベースマイグレーションの実行（`artisan migrate`）
9. npm 依存パッケージのインストール
10. フロントエンドアセットのビルド（`npm run build`）

セットアップ完了後、`http://localhost` でアプリケーションにアクセスできます。

## 環境別のセットアップ

### 本番環境

```bash
./bin/setup.sh -p
```

### GPU 環境（NVIDIA）

GPU が利用可能な場合、セットアップスクリプトが自動検出して AI 処理（PaddleOCR）の設定を最適化します。手動設定は不要です。

```bash
# GPU 自動検出あり（通常はこれで十分）
./bin/setup.sh
```

### Apple Silicon（M1/M2/M3/M4）

Mac の場合、アーキテクチャが自動検出されます。VLM（Vision Language Model）を使用する場合は、セットアップ完了後に別ターミナルで以下を実行してください：

```bash
./scripts/start-vlm-mlx.sh
```

## 動作確認

セットアップ後、以下のコマンドでアプリケーションの状態を確認できます：

```bash
# コンテナの状態確認
./vendor/bin/sail ps

# データベース接続確認
./vendor/bin/sail artisan db:show

# テストの実行
./vendor/bin/sail test
```

## トラブルシューティング

### セットアップを最初からやり直す

```bash
# コンテナ・ボリューム・イメージをすべて削除
./vendor/bin/sail down --volumes --rmi all

# 再セットアップ
./bin/setup.sh
```

### Docker が起動しない

- macOS / Windows: Docker Desktop が起動していることを確認
- Linux: `sudo systemctl start docker` で Docker サービスを起動

### ポートが競合する

デフォルトではポート 80 を使用します。既存のサービスと競合する場合は `.env` の `APP_PORT` を変更してください：

```bash
APP_PORT=8080
```

変更後、コンテナを再起動します：

```bash
./vendor/bin/sail down && ./vendor/bin/sail up -d
```

### データベース接続エラー

```bash
# マイグレーションを再実行
./vendor/bin/sail artisan migrate:fresh
```

## 制約と注意点

- Windows 環境では WSL2 の使用を推奨します。WSL2 内にリポジトリをクローンし、WSL2 上で Docker を実行してください。
- Mroonga 全文検索エンジンを使用するため、MySQL のストレージエンジンとして Mroonga が有効である必要があります（セットアップスクリプトが自動設定します）。
- `.env.testing` はテスト実行時に必須です。自動生成されないため、`.env.testing.example` から手動でコピーしてください。

## エビデンス

- セットアップスクリプト: [`bin/setup.sh`](../../bin/setup.sh)
- 依存インストールスクリプト: [`bin/install_dependencies_and_migrate.sh`](../../bin/install_dependencies_and_migrate.sh)
- Docker Compose 設定: [`docker-compose.yml`](../../docker-compose.yml)
- 設計経緯: [`docs/work/environment/`](../work/environment/)
