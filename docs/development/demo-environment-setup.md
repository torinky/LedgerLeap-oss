# デモ環境構築ガイド

LedgerLeapのデモ環境を構築し、LLM（Claude、ChatGPTなど）からMCPツール経由でアクセスできるようにする手順を説明します。

## 目次

1. [概要](#概要)
2. [前提条件](#前提条件)
3. [構築手順](#構築手順)
4. [MCP認証設定](#mcp認証設定)
5. [動作確認](#動作確認)
6. [トラブルシューティング](#トラブルシューティング)

---

## 概要

デモ環境は以下の特徴を持ちます:

- **デモユーザー**: `demo@example.com` / `demo1234`
- **管理者ユーザー**: `admin@example.com` / `demo1234`
- **テナント**: `demo-tenant`
- **台帳定義**: 営業日報（8カラム）
- **サンプルデータ**: 7件の営業日報レコード
- **タグ**: プロジェクト横断検索用（3個）
- **MCP認証**: 自動設定済み

### データ構成

```
テナント: demo-tenant
├── デモ用フォルダ/
│   └── 日報/
│       └── [DEMO] 営業日報
│           ├── カラム: 日付、顧客名、訪問目的、商談ステータス、優先度、商談内容、成果・所感、次回アクション
│           ├── タグ: 2025年度営業計画、新製品展開、顧客管理
│           └── レコード: 7件（A商事、Bシステムズ、C製造、Dコーポレーション、E物産、Fソリューションズ）
```

---

## 前提条件

### 必須環境

- **Docker Desktop**: インストール済みで実行中であること
- **Git**: リポジトリのクローン用
- **Bash**: シェルスクリプト実行用

### 推奨環境

- **メモリ**: 8GB以上
- **ディスク空き容量**: 10GB以上

---

## 構築手順

### Step 1: 初期セットアップ

プロジェクトをクローンし、基本的な環境を構築します。

```bash
# 1. リポジトリをクローン
git clone [リポジトリURL] ledgerleap
cd ledgerleap

# 2. セットアップスクリプトを実行（依存関係インストール、マイグレーション）
./bin/setup.sh
```

**処理内容:**
- `.env`ファイルの作成
- Dockerコンテナのビルドと起動
- Composer/NPM依存関係のインストール
- データベースマイグレーション
- フロントエンドアセットのビルド

**所要時間**: 初回は約10-15分

### Step 2: デモデータの投入

デモ環境用のサンプルデータを投入します。

```bash
# デモデータSeederを実行
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder
```

**実行結果の例:**
```
🚀 Starting Demo Minimal Seeder...
🏢 Step 0/7: Creating and initializing tenant...
   ✓ Tenant created or found: demo-tenant
   ✓ Tenant initialized: demo-tenant
📋 Step 1/7: Creating users and roles...
   ✓ Users created: 田中太郎, 山田花子
📁 Step 2/7: Creating folder structure...
   ✓ Folders created: /, デモ用フォルダ, 日報
🔐 Step 3/7: Setting up permissions...
   ✓ Permissions set: WRITE for demo user, ADMIN for admin user
📝 Step 4/7: Creating ledger define...
   ✓ Ledger define created: [DEMO] 営業日報 with 8 columns
🏷️  Step 5/7: Creating tags...
   ✓ Tags attached to ledger define: 2025年度営業計画, 新製品展開, 顧客管理
📊 Step 6/7: Creating demo ledgers...
   ✓ Ledger 1/7 created: 株式会社A商事 (ステータス: 提案中, 優先度: 高)
   ...
✅ Demo data created successfully!

🔑 Login credentials:
   Demo User:  demo@example.com  / demo1234
   Admin User: admin@example.com / demo1234

🏢 Tenant Info:
   Tenant ID: demo-tenant
```

**投入されるデータ:**
- ユーザー: 2名（デモユーザー、管理者）
- フォルダ: 3個（ルート、デモ用フォルダ、日報）
- 台帳定義: 1種（営業日報）
- 台帳レコード: 7件
- タグ: 3個（台帳定義に付与）

### Step 3: データの確認

投入されたデータを確認します。

```bash
# tinkerで確認
./vendor/bin/sail artisan tinker

# ユーザー確認
>>> User::where('email', 'demo@example.com')->first()

# テナント確認
>>> Tenant::find('demo-tenant')

# 台帳定義確認
>>> LedgerDefine::where('title', '[DEMO] 営業日報')->first()

# 台帳レコード数確認
>>> Ledger::count()
=> 7

# タグ確認
>>> Tag::all()->pluck('name')
=> ["2025年度営業計画", "新製品展開", "顧客管理"]

# Ctrl+C で終了
```

---

## MCP認証設定

LLMからMCPツールにアクセスするための認証トークンを設定します。

### トークンの生成

```bash
# デモユーザー用のMCP認証トークンを生成
./vendor/bin/sail artisan demo:generate-mcp-token
```

**出力例:**
```
✅ MCP Token generated successfully!

User: 田中太郎 (demo@example.com)
Token: <generated-token>

Add this to your .env file:
MCP_AUTH_TOKEN=<generated-token>
```

### .envファイルへの設定

DemoMinimalSeederを実行すると、自動的にトークンが生成され`.env`に設定されますが、
必要に応じて手動で確認・更新できます。

```bash
# トークンが設定されていることを確認
grep MCP_AUTH_TOKEN .env

# 必要に応じて手動で設定
# MCP_AUTH_TOKEN="<generated-token>"
```

### 認証エラー時の対処

認証エラーが発生した場合、エラーメッセージに原因と解決方法が表示されます:

#### エラー1: トークン未設定
```
Authentication failed: MCP_AUTH_TOKEN environment variable is not set.
Please set the MCP_AUTH_TOKEN in your .env file with a valid Sanctum token.
```

**解決方法:**
```bash
./vendor/bin/sail artisan demo:generate-mcp-token
# 表示されたトークンを.envに設定
```

#### エラー2: トークン無効
```
Authentication failed: The provided token is invalid or has been revoked.
Please generate a new token using: php artisan demo:generate-mcp-token
```

**解決方法:**
```bash
./vendor/bin/sail artisan demo:generate-mcp-token
# 新しいトークンを.envに設定
```

#### エラー3: 権限不足
```
Authentication failed: The token does not have MCP access permissions.
Please generate a token with mcp:* ability.
```

**解決方法:**
```bash
# demo:generate-mcp-token は自動的に mcp:* 権限を付与します
./vendor/bin/sail artisan demo:generate-mcp-token
```

---

## 動作確認

### Webアプリケーションへのアクセス

ブラウザで以下のURLにアクセスして動作を確認します。

#### アプリケーション
- **URL**: http://localhost
- **ログイン**:
  - デモユーザー: `demo@example.com` / `demo1234`
  - 管理者: `admin@example.com` / `demo1234`

#### Mailpit（開発用メール）
- **URL**: http://localhost:8025
- 送信されたメールを確認できます

### MCPツールのテスト

MCPサーバーを起動してLLMからのアクセスをテストします。

```bash
# MCPサーバーを起動（別ターミナルで）
./vendor/bin/sail artisan mcp:serve
```

#### LLMから試す質問例

1. **基本検索**
   ```
   「株式会社A商事に関する営業記録を見せて」
   → 件1と件2が返ってくる
   ```

2. **状態による検索**
   ```
   「優先度が高い案件を一覧表示して」
   → 優先度:高 の5件が返ってくる
   ```

3. **タグによる横断検索**
   ```
   「2025年度営業計画に関連する台帳を検索して」
   → 営業日報の全7件が返ってくる
   ```

4. **複合条件検索**
   ```
   「提案中または価格交渉中の案件を見せて」
   → 該当する商談ステータスのレコードが返ってくる
   ```

5. **特定企業の情報**
   ```
   「C製造株式会社の商談状況を教えて」
   → C製造の商談情報（価格交渉中）が返ってくる
   ```

### データベースの確認

```bash
# MySQL コンテナに接続
./vendor/bin/sail mysql

# テナントの確認
mysql> SELECT * FROM tenants;

# ユーザーの確認
mysql> SELECT id, name, email FROM users;

# 台帳定義の確認（tenant_id付き）
mysql> SELECT id, title, tenant_id FROM ledgers LIMIT 5;

# Ctrl+D で終了
```

---

## トラブルシューティング

### 問題1: Dockerコンテナが起動しない

**症状:**
```
ERROR: Cannot connect to the Docker daemon
```

**解決方法:**
1. Docker Desktopが起動しているか確認
2. Docker Desktopを再起動
3. 再度 `./bin/setup.sh` を実行

### 問題2: マイグレーションエラー

**症状:**
```
SQLSTATE[42S01]: Base table or view already exists
```

**解決方法:**
```bash
# データベースをリセット
./vendor/bin/sail artisan migrate:fresh --seed

# デモデータを再投入
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder
```

### 問題3: tenant_id が NULL

**症状:**
データが投入されているが、tenant_id が設定されていない

**解決方法:**
```bash
# データベースを完全にリセット
./vendor/bin/sail artisan migrate:fresh

# 標準のシーダーを実行
./vendor/bin/sail artisan db:seed

# デモデータを再投入
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder
```

**確認:**
```bash
./vendor/bin/sail artisan tinker
>>> Folder::first()->tenant_id
=> "demo-tenant"  # NULL でないことを確認
```

### 問題4: MCP認証エラー

**症状:**
```
Authentication failed: The provided token is invalid
```

**解決方法:**
```bash
# 新しいトークンを生成
./vendor/bin/sail artisan demo:generate-mcp-token

# .env に設定されていることを確認
grep MCP_AUTH_TOKEN .env

# アプリケーションをリスタート（設定を再読み込み）
./vendor/bin/sail restart
```

### 問題5: 検索結果が返らない

**症状:**
SearchLedgersToolで検索しても結果が0件

**確認手順:**
```bash
./vendor/bin/sail artisan tinker

# 1. データが存在するか確認
>>> Ledger::count()

# 2. テナントが初期化されているか確認
>>> tenancy()->initialized
=> false  # false の場合は問題

# 3. 手動でテナントを初期化
>>> $tenant = Tenant::find('demo-tenant')
>>> tenancy()->initialize($tenant)
>>> Ledger::count()  # 今度は7が返るはず
```

### 問題6: ポート競合

**症状:**
```
Error: Port 80 is already in use
```

**解決方法:**
```bash
# 既存のポートを使用しているプロセスを確認
lsof -i :80

# 他のアプリケーション（Apache、Nginxなど）を停止
sudo apachectl stop  # macOSの場合

# または、docker-compose.yml でポートを変更
# ports: ["8080:80"] に変更して http://localhost:8080 でアクセス
```

---

## 環境のクリーンアップ

デモ環境を完全に削除する場合:

```bash
# コンテナを停止・削除
./vendor/bin/sail down -v

# データベースボリュームも削除
docker volume prune

# 再構築する場合は最初から
./bin/setup.sh
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder
```

---

## 次のステップ

デモ環境が構築できたら、以下のドキュメントを参照して機能を探索してください:

- [MCP アーキテクチャと動作フロー](MCP_Architecture_and_Flow.md) - LLM統合の技術詳解
- [MCP プロンプトガイドライン](MCP_Prompt_Guidelines.md) - LLM対話の最適化ガイド
- [API仕様概要](../api/README.md) - APIエンドポイントの詳細
- [ユーティリティコマンド](utility-commands.md) - その他の便利なコマンド

---

## 参考情報

### デモデータの詳細

| 項目 | 内容 |
|------|------|
| テナントID | demo-tenant |
| デモユーザー | demo@example.com / demo1234 |
| 管理者ユーザー | admin@example.com / demo1234 |
| 台帳定義 | [DEMO] 営業日報 |
| カラム数 | 8個（日付、顧客名、訪問目的、商談ステータス、優先度、商談内容、成果・所感、次回アクション） |
| タグ | 2025年度営業計画、新製品展開、顧客管理 |
| レコード数 | 7件 |

### 商談ステータスの選択肢

- 初回訪問
- 提案中
- フォローアップ
- 価格交渉中
- 契約直前
- 契約済み
- 見送り
- 再提案予定

### 優先度の選択肢

- 高
- 中
- 低

---

**最終更新**: 2025-10-05  
**作成者**: AI Assistant  
**バージョン**: 1.0
