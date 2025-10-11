# Seeder統合と使い方ガイド

**作成日:** 2025-10-11  
**目的:** DemoMinimalSeederとDemoPhase1ExtensionSeederの統合と使い方の明確化

---

## 📊 Seeder構成

### 現在の構成

```
database/seeders/
├── DatabaseSeeder.php           # メインSeeder（環境変数で動作切替）
├── DemoCompleteSeeder.php       # 統合Seeder（NEW!）
├── DemoMinimalSeeder.php        # 基盤データ（Step 1）
├── DemoPhase1ExtensionSeeder.php # 拡張データ（Step 2）
└── (その他既存Seeder...)
```

### Seederの関係

```
DemoCompleteSeeder
    ├── 呼び出し → DemoMinimalSeeder (基盤データ)
    │   ├── テナント作成・初期化
    │   ├── ユーザー2名（demo, admin）
    │   ├── ロール2個
    │   ├── フォルダ3個（ルート、デモ用フォルダ、日報）
    │   ├── 台帳定義1種（営業日報）
    │   ├── タグ3個
    │   └── 台帳7件
    │
    └── 呼び出し → DemoPhase1ExtensionSeeder (拡張データ)
        ├── 組織3個（本社、営業部、技術部）
        ├── ロール5個追加（一般ユーザー営業/技術、点検者、承認者、監査）
        ├── ユーザー10名追加
        ├── フォルダ7個追加（階層構造完成）
        ├── 権限設定（部門別アクセス制御）
        ├── 台帳定義3種追加（経費申請、設備点検表、週報）
        ├── タグ25個追加
        └── 台帳データ追加（ワークフロー状態付き）
```

---

## 🚀 使い方

### 方法1: 統合Seederを直接実行（推奨）

**1回のコマンドで全てのデモデータを作成:**

```bash
# デモ用の完全なデータセットを作成
./vendor/bin/sail artisan db:seed --class=DemoCompleteSeeder
```

**利点:**
- ✅ 1回のコマンドで完了
- ✅ 依存関係を気にする必要なし
- ✅ 実行順序が保証される
- ✅ 統一された進捗表示

**出力例:**
```
🚀 Starting Demo Complete Seeder (Phase 1 Full)...

📦 Phase 1: Creating base data (minimal)...
🚀 Starting Demo Minimal Seeder...
   ✓ Tenant created: demo-tenant
   ✓ Users created: 2
   ✓ Folders created: 3
   ✓ Ledger Define created: 1
   ✓ Ledgers created: 7

📦 Phase 2: Creating extended data...
🚀 Starting Demo Phase 1 Extension Seeder...
   ✓ Organizations created: 3
   ✓ Roles created: 5
   ✓ Users created: 10
   ✓ Folders created: 7
   ✓ Ledger Defines created: 3
   ✓ Tags created: 25
   ✓ Ledgers created: 20+

✅ Demo Complete Seeder finished successfully!

🔑 Login Credentials:
   Demo User:    demo@example.com  / demo1234
   Admin User:   admin@example.com / demo1234
   営業太郎:     sales1@example.com / demo1234
   ...
```

---

### 方法2: DatabaseSeederから自動実行（環境変数制御）

**デモモードの設定:**

```bash
# .env または .env.local に追加
SEEDER_MODE=demo
```

**実行:**

```bash
# DatabaseSeederが自動的にDemoCompleteSeederを呼び出す
./vendor/bin/sail artisan db:seed
```

**通常モードに戻す:**

```bash
# .env から SEEDER_MODE を削除または
SEEDER_MODE=standard
```

---

### 方法3: 個別Seeder実行（デバッグ・開発用）

**基盤データのみ作成（最小構成）:**

```bash
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder
```

**拡張データのみ追加（DemoMinimalSeeder実行済みが前提）:**

```bash
./vendor/bin/sail artisan db:seed --class=DemoPhase1ExtensionSeeder
```

**注意:** 拡張Seederは基盤Seederに依存するため、順序が重要です。

---

## 📋 各Seederの詳細

### DemoMinimalSeeder

**目的:** LLMとの対話デモができる最小限のデータセット

**作成データ:**
- テナント: 1個（demo-tenant）
- ユーザー: 2名（demo@example.com, admin@example.com）
- ロール: 2個（デモユーザー、管理者）
- フォルダ: 3個（ルート、デモ用フォルダ、日報）
- 台帳定義: 1種（営業日報、8カラム）
- タグ: 3個（プロジェクト横断用）
- 台帳: 7件（長文日本語コンテンツ）

**実行時間:** 約10秒

**用途:**
- 最小限のデモ環境
- SearchLedgersToolの基本動作確認
- LLM対話テストの最小セット

---

### DemoPhase1ExtensionSeeder

**目的:** マスタープラン Phase 1完全達成用の拡張データセット

**前提条件:** DemoMinimalSeederが実行済みであること

**追加データ:**
- 組織: 3個（本社、営業部、技術部）
- ロール: 5個（営業、技術、点検者、承認者、監査）
- ユーザー: 10名（ペルソナ別）
- フォルダ: 7個（営業部、技術部、全社共通の階層）
- 台帳定義: 3種（経費申請、設備点検表、週報）
- タグ: 25個（カテゴリ別）
- 台帳: 20件+ (ワークフロー状態付き)

**実行時間:** 約20秒

**用途:**
- 全MCPツールのテスト
- 全InputTypeの網羅確認
- ワークフロー機能のテスト
- 権限管理のテスト

---

### DemoCompleteSeeder

**目的:** MinimalとExtensionを統合し、1回で完全なデモ環境を構築

**作成データ:** DemoMinimalSeeder + DemoPhase1ExtensionSeeder の合計

**実行時間:** 約30秒

**用途:**
- プレゼンテーション・デモ準備
- MCPツール統合テスト
- E2Eテスト環境構築
- 新規開発者のオンボーディング

---

## 🔧 開発・テストのベストプラクティス

### デモ環境の初期化

```bash
# 1. データベースをリセット
./vendor/bin/sail artisan migrate:fresh

# 2. デモデータを投入
./vendor/bin/sail artisan db:seed --class=DemoCompleteSeeder

# または環境変数を使用
SEEDER_MODE=demo ./vendor/bin/sail artisan migrate:fresh --seed
```

### テスト環境の使い分け

| 用途 | Seeder | コマンド |
|------|--------|----------|
| 最小限のデモ | DemoMinimalSeeder | `--class=DemoMinimalSeeder` |
| 完全なデモ | DemoCompleteSeeder | `--class=DemoCompleteSeeder` |
| 通常の開発 | DatabaseSeeder | `db:seed` (標準モード) |
| E2Eテスト | DemoCompleteSeeder | CI/CDで自動実行 |

### CI/CDでの使用例

```yaml
# .github/workflows/test.yml
- name: Seed demo data
  run: |
    php artisan migrate:fresh
    php artisan db:seed --class=DemoCompleteSeeder

- name: Run E2E tests
  run: php artisan test --testsuite=E2E
```

---

## ✅ データ検証

### 作成データの確認

```bash
./vendor/bin/sail artisan tinker
```

```php
// 統計情報
echo "Tenants: " . \App\Models\Tenant::count() . PHP_EOL;
echo "Users: " . \App\Models\User::count() . PHP_EOL;
echo "Organizations: " . \App\Models\Organization::count() . PHP_EOL;
echo "Roles: " . \App\Models\Role::count() . PHP_EOL;
echo "Folders: " . \App\Models\Folder::count() . PHP_EOL;
echo "LedgerDefines: " . \App\Models\LedgerDefine::count() . PHP_EOL;
echo "Ledgers: " . \App\Models\Ledger::count() . PHP_EOL;
echo "Tags: " . \App\Models\Tag::count() . PHP_EOL;

// 期待される結果（DemoCompleteSeeder実行後）
// Tenants: 1 (demo-tenant)
// Users: 42+ (既存 + デモ12名)
// Organizations: 173+ (既存 + デモ3個)
// Roles: 16+ (既存 + デモ7個)
// Folders: 17+ (既存 + デモ10個)
// LedgerDefines: 8 (既存5 + デモ4種 - 重複除く)
// Ledgers: 79+ (既存59 + デモ20+)
// Tags: 28+

// InputType網羅確認
$defines = \App\Models\LedgerDefine::where('title', 'like', '[DEMO]%')->get();
foreach ($defines as $define) {
    echo "台帳定義: {$define->title}" . PHP_EOL;
    foreach ($define->column_define as $col) {
        echo "  - {$col->getName()}: {$col->getTypeIdentifier()}" . PHP_EOL;
    }
}
```

---

## 🎯 まとめ

### 推奨される使い方

**日常的なデモ・テスト:**
```bash
./vendor/bin/sail artisan db:seed --class=DemoCompleteSeeder
```

**開発環境の自動セットアップ:**
```bash
# .env
SEEDER_MODE=demo

# セットアップスクリプト
./bin/setup.sh  # 内部でdb:seedが実行される
```

**個別Seederの使用（デバッグ時のみ）:**
```bash
# 最小データのみ必要な場合
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder

# 拡張データを追加（Minimal実行済みが前提）
./vendor/bin/sail artisan db:seed --class=DemoPhase1ExtensionSeeder
```

### メリット

1. **シンプルな実行:** 1コマンドで完全なデモ環境
2. **依存関係の明確化:** Seeder間の順序が保証される
3. **柔軟性:** 用途に応じて最小/完全を選択可能
4. **保守性:** 各Seederは独立して開発・テスト可能
5. **環境変数制御:** DatabaseSeederから自動実行も可能

---

**作成者:** AI Assistant  
**最終更新:** 2025-10-11  
**ステータス:** 完成・運用可能
