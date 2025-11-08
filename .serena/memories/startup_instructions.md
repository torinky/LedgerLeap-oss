# LedgerLeap 初回起動時の指示

## プロジェクトの基本理解

### プロジェクト概要
LedgerLeapは、Laravel 12 + PHP 8.4で構築された**Webベース台帳管理システム**です。
組織内の情報管理と共有を効率化し、全文検索（添付ファイル含む）、柔軟な権限管理、ワークフロー機能を提供します。

### 現在の開発状況
- **現在ブランチ**: `feature/rag-phase1-planning`
- **開発フェーズ**: LLM統合機能（RAG Phase 1）の計画・実装中
- **使用技術**: Laravel Sail (Docker) を使った開発環境

## 環境セットアップの確認

### Docker環境の状態確認
まず、開発環境が起動しているか確認してください：

```bash
# Docker環境の状態確認
./vendor/bin/sail ps

# もし起動していない場合
./vendor/bin/sail up -d
```

### 初回セットアップ（未実施の場合）
環境がセットアップされていない場合は、以下を実行してください：

```bash
# 開発環境の初回セットアップ
./bin/setup.sh        # または ./dev.sh
```

このスクリプトは以下を自動で実行します：
- Dockerコンテナのビルド
- Composer依存関係インストール
- NPM依存関係インストール
- データベースマイグレーション
- アーキテクチャ自動検出（ARM64/AMD64）

## プロジェクト構造の理解

### 主要ディレクトリ
```
/app/
├── Models/           # Eloquentモデル
├── Services/         # ビジネスロジック
├── Livewire/        # インタラクティブUI
├── Filament/        # 管理画面
├── Mcp/             # LLM統合API
└── Http/Controllers/# コントローラー

/docs/               # ドキュメント
├── README.md        # プロジェクト概要（必読）
├── development/     # 開発ガイドライン
└── work/            # 計画・作業記録

/resources/views/    # Bladeテンプレート
/tests/              # テストコード
```

## 重要な制約事項

### 技術的制約
1. **Mroonga全文検索**:
   - 複合インデックスは使用不可
   - 単一インデックスをOR結合で利用

2. **テスト**:
   - 全文検索機能は`DatabaseMigrations`トレイト必須
   - `RefreshDatabase`は使用不可

3. **Livewire**:
   - パブリックプロパティはシンプルな連想配列のみ
   - 状態は単一配列に集約（Single Source of Truth）

## 開発ワークフローの確認

### 基本的な開発サイクル
```bash
# 1. 開発環境起動
./vendor/bin/sail up -d

# 2. アセットのウォッチ（別ターミナルで）
./vendor/bin/sail npm run dev

# 3. コード編集
# ...

# 4. テスト実行
./vendor/bin/sail test

# 5. コード整形（コミット前必須）
./vendor/bin/sail pint

# 6. コミット
git add .
git commit -m "feat(scope): 説明"
```

## アクセスURL

開発環境が起動したら、以下のURLにアクセスできます：

- **アプリケーション**: http://localhost
- **管理画面**: http://localhost/admin
- **Mailpit（開発用メール）**: http://localhost:8025

## 最初に確認すべきドキュメント

### 必読ドキュメント
1. **プロジェクト概要**: `/docs/README.md`
2. **コーディング規約**: `/docs/development/coding_standards.md`
3. **環境構築詳細**: `/docs/development/environment-setup.md`
4. **ブランチ戦略**: `/docs/development/branch_strategy.md`

### 現在の開発関連
- **LLM統合ロードマップ**: `/docs/work/llm-integration/2025-09-23_LLM_Integration_Roadmap.md`
- **MCP実装計画**: `/docs/work/2025-09-29_Comprehensive_MCP_Implementation_Plan.md`

## トラブルシューティング

### Docker環境の問題
```bash
# コンテナを完全に削除して再構築
./vendor/bin/sail down --volumes
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

### キャッシュクリア
```bash
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan view:clear
```

### データベースのリセット
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

## 次のステップ

### 初めて開発する場合
1. ✅ 環境セットアップ完了を確認
2. ✅ `/docs/README.md` を読む
3. ✅ コーディング規約を理解する
4. ✅ 既存のテストを実行して動作確認
5. ✅ 簡単なタスクから始める

### よくある最初のタスク
- 新しいテストケースの追加
- 既存機能のバグ修正
- ドキュメントの改善
- コードスタイルの修正

## 開発時の重要な心得

### コミット前の必須チェック
- [ ] `./vendor/bin/sail pint` 実行（コード整形）
- [ ] `./vendor/bin/sail test` 通過確認
- [ ] 関連ドキュメント更新
- [ ] セキュリティ影響確認

### コーディング原則
- **DRY**: 同じコードの繰り返しを避ける
- **KISS**: シンプルで理解しやすい実装
- **YAGNI**: 現時点で必要ない機能は実装しない

## サポートとヘルプ

### ドキュメント参照
- プロジェクト全般: `/docs/README.md`
- 開発ガイドライン: `/docs/development/`
- API仕様: `/docs/api/`
- 機能仕様: `/docs/function/`

### コマンド一覧
プロジェクトで使用できるコマンドは `suggested_commands.md` メモリに保存されています。

## まとめ

LedgerLeapは、以下の特徴を持つプロジェクトです：

✅ **Laravel 12 + PHP 8.4** の最新スタック
✅ **Docker (Laravel Sail)** による一貫した開発環境
✅ **Livewire + Alpine.js** によるインタラクティブUI
✅ **Mroonga** による高速な日本語全文検索
✅ **テスト駆動開発** を重視
✅ **コード品質** を Laravel Pint で維持

まずは開発環境を起動し、既存のテストを実行して動作を確認してください。
その後、小さなタスクから始めて、徐々にプロジェクトに慣れていきましょう。

**Good luck with your development!** 🚀
