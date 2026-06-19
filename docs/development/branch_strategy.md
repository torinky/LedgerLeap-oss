# Git ブランチ戦略と開発フロー

**対象**: LedgerLeap プロジェクトに参加するすべての開発者。

このドキュメントを読めば、今日から LedgerLeap の開発に参加できます。

---

## 戦略の概要

LedgerLeap は **シンプルな GitHub Flow + develop** を採用しています。本格的な Git Flow（`release/*`, `hotfix/*`）は個人/小規模開発ではオーバーヘッドが大きいため、必要最小限のブランチ構成で運用します。

```
main          ← 常にデプロイ可能な安定版。OSS 公開用。
develop       ← 統合ブランチ。すべての作業はここに集約。
feature/#xxx  ← 機能開発ブランチ（作業後は即削除）
fix/#xxx      ← バグ修正ブランチ（作業後は即削除）
chore/xxx     ← CI/依存/ドキュメントなどの雑務（作業後は即削除）
```

リリースはタグ (`vX.Y.Z`) で管理し、リリース用ブランチは作成しません。

---

## クイックスタート（初めて開発する方へ）

```bash
# 1. リポジトリをクローン
git clone https://github.com/torinky/LedgerLeap.git
cd LedgerLeap

# 2. 環境構築（Docker + Sail）
./bin/setup.sh

# 3. 最新の develop を取得
git checkout develop
git pull origin develop

# 4. 作業ブランチを作成
git checkout -b feature/#999-my-first-feature

# 5. 開発 → コミット → プッシュ
# （詳細は下記「日々の開発フロー」参照）

# 6. develop にマージしたらブランチを削除
git branch -d feature/#999-my-first-feature
git push origin --delete feature/#999-my-first-feature
```

---

## ブランチ命名規則

| 種類 | 形式 | 例 | 説明 |
|------|------|-----|------|
| 機能 | `feature/#<issue>-<kebab-desc>` | `feature/#230-search-improvement` | 新機能。Issue 番号必須 |
| 修正 | `fix/#<issue>-<kebab-desc>` | `fix/#208-folder-tree-indent` | バグ修正。Issue 番号必須 |
| 雑務 | `chore/<kebab-desc>` | `chore/update-laravel-11` | CI、依存関係更新、ドキュメント |

**ルール**:
- 常に**小文字 + ケバブケース**（`-` 区切り）
- Issue 番号は `#` 付き（`chore` は例外）
- 説明は 3〜4 単語の簡潔な英語
- ベースブランチは**常に `develop`**（緊急修正のみ `main` から）

**悪い例**: `singleTenant`, `feature/my_feature`, `fix-bug`
**良い例**: `feature/#230-search-improvement`, `fix/#208-folder-tree-indent`

---

## 日々の開発フロー

### 1. 作業開始前：ブランチの棚卸し

```bash
# マージ済みの古いブランチがないか確認
git branch --merged develop | grep -v 'develop\|main'
```

マージ済みブランチがあれば削除してください。放置するとブランチが増殖し、リポジトリが荒れます。

### 2. ブランチ作成

```bash
git checkout develop
git pull origin develop
git checkout -b feature/#230-my-feature
```

### 3. 実装とコミット

LedgerLeap のテストは **Laravel Sail (Docker)** 内で実行します。ホスト側の `php artisan test` は使わないでください。

```bash
# コード整形（コミット前必須）
./vendor/bin/sail pint

# 関連テストを実行
./vendor/bin/sail test

# コミット（Conventional Commits 形式）
git add <files>
git commit -m "feat(scope): 変更内容の簡潔な説明"
```

コミットメッセージの詳細は [コミットメッセージ規約](#コミットメッセージ規約) を参照。

### 4. プッシュとマージ

```bash
git push origin feature/#230-my-feature
```

GitHub 上で Pull Request を作成し、CI が通ったら `develop` にマージします。マージ方法は **squash merge** または **rebase merge** を推奨。

### 5. ブランチ削除（最重要）

**マージ後は必ずブランチを削除してください。** これは絶対ルールです。

```bash
# ローカル
git branch -d feature/#230-my-feature

# リモート
git push origin --delete feature/#230-my-feature

# リモート追跡参照の掃除
git remote prune origin
```

マージ済みブランチは死んだブランチです。Git 履歴がすべてを保存しているため、「念のため残す」は不要です。

---

## OSS 公開リポジトリとの連携

LedgerLeap には 2 つのリポジトリがあります：

| リポジトリ | 用途 | URL |
|-----------|------|-----|
| `torinky/LedgerLeap` | **プライベート**（開発用） | github.com/torinky/LedgerLeap |
| `torinky/LedgerLeap-oss` | **公開**（OSS ミラー） | github.com/torinky/LedgerLeap-oss |

`main` ブランチにプッシュされると、GitHub Actions が自動的に公開リポジトリに同期します。プライベートな AI アセット（`.github/copilot-instructions.md`, `AGENTS.md` など）は同期対象外です（`sync-excludes.txt` で管理）。

**注意**: 公開リポジトリの操作は明示的に指示された場合のみ行ってください。通常の開発は常にプライベートリポジトリで行います。

---

## 環境別の注意点

### Sail 環境での Git 操作

Laravel Sail のコマンド実行後は、通常の `cd && git` が空出力になる場合があります（Issue #54）。
常に `bash -c` でラップしてください：

```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git status"
```

### 日本語を含むコミットメッセージ

日本語のコミットメッセージは、専用スクリプトを使ってエンコードします：

```bash
python3 .github/skills/git-commit/scripts/make_commit_msg.py --file /tmp/msg_input.txt
bash -c "cd /path && git commit -F /tmp/commit_msg.txt"
```

詳細は `.github/skills/git-commit/SKILL.md` を参照。

---

## コミットメッセージ規約

Conventional Commits 形式を採用します。コミットメッセージは原則**日本語**で記述します。

### 形式

```
<type>(<scope>): <subject>

<body>

<footer>
```

| 部分 | 必須 | 説明 |
|------|------|------|
| `<type>` | 必須 | `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert` |
| `(<scope>)` | 任意 | 変更範囲（例: `auth`, `ledger-api`, `user-profile`） |
| `<subject>` | 必須 | 50 文字以内。体言止めまたは「〜する」「〜した」 |
| `<body>` | 任意 | 変更理由・詳細・背景 |
| `<footer>` | 任意 | `Closes #N`, `BREAKING CHANGE:` |

### 例

```
feat(auth): ユーザー登録機能のAPIエンドポイント実装

メールアドレスとパスワードで新規登録できるAPIを追加。
登録成功時にユーザートークンを返却。

Closes #42
```

```
fix(ledger): 台帳一覧表示時の日付フォーマット誤りを修正

特定条件下で日付が正しくフォーマットされていなかった問題を修正。
表示ライブラリのタイムゾーン設定を見直し。
```

```
chore: Laravel 11.x → 12.x アップグレード
```

---

## 関連ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| [Git Branch Workflow（詳細手順）](/docs/runbooks/git-branch-workflow.md) | コマンドを含む完全な操作手順と禁止事項 |
| [コーディング規約](/docs/development/coding_standards.md) | PHP/Livewire/Blade の命名規則とスタイル |
| [環境構築](environment-setup.md) | Docker/Sail による開発環境のセットアップ |
| [テストのベストプラクティス](/docs/development/testing/README.md) | テストの書き方と実行方法 |
| [GitHub Issue Body Sync Playbook](/docs/runbooks/github-issue-body-sync-playbook.md) | Issue 本文の同期手順 |

---

## AI エージェントとの協調

LedgerLeap の開発では、GitHub Copilot / Gemini CLI / Antigravity CLI / opencode などの AI エージェントがブランチ戦略を理解し、以下のタイミングで自律的にブランチ管理を支援します：

- **セッション開始時**: マージ済みブランチの検出と削除提案
- **コミット時 (`/git-commit`)**: 作業前のブランチ棚卸し
- **マージ時**: ブランチ削除のリマインド（`post-merge` Git Hook）
- **Issue クローズ時**: 関連ブランチの残存チェックと自動削除

エージェント向けの内部ルールは `.github/copilot-instructions.md` と `.github/skills/` で一元管理され、symlink 経由で全エージェントに共有されています。この仕組みについて詳しくは [AI Asset Maintenance Playbook](/docs/runbooks/ai-asset-maintenance-playbook.md) を参照してください。
