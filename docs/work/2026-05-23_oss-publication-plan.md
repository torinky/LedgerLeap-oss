# LedgerLeap OSS 公開移行計画

**作成日:** 2026-05-23  
**最終更新:** 2026-05-23  
**ステータス:** 計画策定中（改訂 v3）

---

## 1. 基本方針

| 項目 | 方針 |
|------|------|
| **開発の主軸** | 現行プライベートリポジトリで継続。開発フローは変えない |
| **公開リポジトリへの同期** | GitHub Actions による CI ミラー（cherry-pick 方式）。過去履歴は公開しない |
| **公開ドキュメント** | 既存 `docs/` からのコピーではなく、**利用者・コントリビュータ向けに新規作成** |
| **AI 資産** | プライベート姉妹リポジトリ `LedgerLeap-ai-assets` で別管理 |
| **公開化のタイミング** | 全体計画が完了するまで公開用リポジトリは private で保持し、最終段階で public に切り替える |
| **安全の担保** | CI フィルタがプライベートファイルを自動除外するため、人手による判断は不要 |

### 非目標

- AI 動作資産の即時公開
- 既存 `docs/work/`（内部作業記録）の公開
- 本番運用環境の公開
- `git filter-repo` による過去履歴の書き換え（コスト・リスクが高い）

---

## 2. リポジトリ構成（移行後）

```
ledgerleap (private)           ← 開発の主軸。履歴・AI資産・作業記録を含む
      │
      │  GitHub Actions: sync-to-public.yml
      │  （push to main のたびに自動起動）
      ▼
ledgerleap (public-target, private) ← 公開窓口の土台。計画完了後に public 化
      │
      └── コントリビュータの PR / Issue はここへ
            └── マージ後、プライベート側へ手動 cherry-pick

ledgerleap-ai-assets (private)  ← AI 動作資産のみを管理する姉妹リポジトリ
```

---

## 3. ドキュメント戦略（重要）

### 3.1 既存 docs/ の位置づけ

現行の `docs/` は**内部開発者向け実装記録**として書かれており、公開向けには適していない。

| ディレクトリ | 内容の性質 | 公開版での扱い |
|-------------|----------|--------------|
| `docs/work/` | スプリント計画・調査ログ・紆余曲折の記録 | **公開しない**（プライベートリポジトリに残す） |
| `docs/development/` | 実装経緯・技術選定記録（例: `environment-setup.md` は設計経緯の記録） | **公開しない**（参考にして新規作成） |
| `docs/runbooks/` | AI 資産や内部手順の操作ガイド | AI 専用は `ai-assets` へ、汎用のみ参考にして新規作成 |
| `docs/harnesses/` | AI 評価ハーネス | `ai-assets` へ移動 |

### 3.2 公開ドキュメントは新規作成する

既存 `docs/` を参照資料として活用しながら、公開リポジトリ用のドキュメントを**外部向けに書き直す**。

**対象読者の分類:**

| 読者 | 目的 | 主なドキュメント |
|------|------|----------------|
| **利用者**（自組織でホスティング） | セットアップして使いたい | Getting Started、設定リファレンス、機能ガイド |
| **コントリビュータ** | バグ修正・機能追加したい | CONTRIBUTING、アーキテクチャ概要、テスト手順 |
| **API/MCP クライアント開発者** | 外部連携したい | API リファレンス、MCP 仕様 |

### 3.3 公開ドキュメントの構成（新規作成対象）

```
docs/  （公開リポジトリ内）
  README.md                  ← ドキュメントハブ（新規）
  getting-started/
    installation.md          ← Sail によるセットアップ手順（新規）
    demo-setup.md            ← デモデータ投入とログイン手順（新規）
    configuration.md         ← 主要な .env 設定項目（新規）
  features/
    overview.md              ← 機能一覧と概要（新規 or 既存から抜粋）
    ledger-management.md     ← 台帳管理の使い方（新規）
    search.md                ← 検索機能の使い方（新規）
    workflow.md              ← ワークフロー機能（新規）
    permissions.md           ← 権限管理（新規）
  architecture/
    overview.md              ← システム構成図・技術スタック（新規）
    multi-tenancy.md         ← マルチテナント設計（既存から抜粋して改訂）
    data-model.md            ← コアデータモデル（新規）
  contributing/
    development-setup.md     ← 開発環境の作り方（新規）
    coding-standards.md      ← コーディング規約（既存から抜粋して改訂）
    testing.md               ← テスト方針と実行方法（新規）
    branch-strategy.md       ← ブランチ・PR 規約（新規）
  api/
    README.md                ← API 概要（既存から改訂）
    mcp.md                   ← MCP 仕様概要（新規）
```

> **作成方針**: 新規作成が基本。既存ドキュメントは「参照資料・種本」として使い、外部読者向けに書き直す。実装経緯や紆余曲折の記述は不要。

---

## 4. 履歴紐付けの方式（CI ミラー）

### 4.1 ブートストラップ（一回限り）

このブートストラップは公開用リポジトリの土台を作る工程だが、visibility の切り替えは全体計画完了後に行う。

```bash
# 1. プライベートリポジトリで orphan ブランチ作成（過去履歴なし）
git checkout --orphan public-bootstrap

# 2. 公開対象ファイルのみステージ（§5 のリスト参照）
git rm -rf .
# 公開対象ファイルを git add ...

# 3. 初期コミット（プライベートの現在 SHA を記録）
PRIVATE_SHA=$(git rev-parse main)
git commit -m "chore: initial public release

Squashed from private repository.
[private-origin: ${PRIVATE_SHA}]"

# 4. private staging リポジトリへ push
git remote add staging https://github.com/torinky/LedgerLeap-oss.git
git push staging public-bootstrap:main
```

### 4.2 继続同期 CI ワークフロー（`sync-to-public.yml`）

push to main のたびに自動起動するワークフロー。詳細実装は `docs/work/2026-05-23_sync-ci-implementation.md` に記載予定。

**概念フロー:**

```
push to main
     │
     ▼
最後に同期した private SHA を公開リポジトリの最新コミットから取得
     │
     ├── プライベート専用パスのみ変更のコミット → skip
     │
     └── 公開パスを含むコミット
             │
             ▼
         cherry-pick → [private-ref: <SHA>] を付記 → push
```

**紐付けの確認方法:**

```bash
# 公開リポジトリで、あるプライベート SHA に対応するコミットを探す
git log --grep="private-ref: abc1234f" --oneline

# 最後に同期したプライベート SHA を確認する（公開リポジトリ側）
git log -1 --format=%B | grep "private-ref:"
```

### 4.3 除外パターン（`.github/sync-excludes.txt` で一元管理）

```
.github/copilot-instructions.md
.github/instructions/
.github/skills/
.github/prompts/
.github/agents/
.github/workflows/opencode.yml
.github/workflows/sync-to-public.yml
.github/sync-excludes.txt
AGENTS.md
opencode.json
resources/ai/
docs/work/
docs/runbooks/ai-asset-maintenance-playbook.md
docs/runbooks/skill-maintenance-playbook.md
docs/runbooks/browser-har-analysis-playbook.md
docs/runbooks/local-llm-mcp-setup.md
docs/harnesses/
docker-compose.prod.yml
prod.sh
.env.development
.env.production
.env.testing
```

> 除外パターンを変更する際は **このドキュメントの §5 と `.github/sync-excludes.txt` を同時に更新する**。

### 4.4 コントリビュータ PR の逆同期

```
1. 公開リポジトリで PR をレビュー・マージ
2. プライベートリポジトリに手動で cherry-pick またはパッチ適用
3. プライベート main から次回 CI で公開リポジトリへ再同期
```

逆同期の自動化は将来対応とし、当面は手動運用とする。

---

## 5. 公開リポジトリに含めるもの・含めないもの

### 5.1 含めるもの（コード・設定）

| パス | 備考 |
|------|------|
| `app/`, `bootstrap/`, `config/`, `database/` | アプリケーションコア |
| `docker/`, `lang/`, `public/` | Docker, 翻訳, 公開ディレクトリ |
| `resources/`（`resources/ai/` 除く） | フロントエンド資産 |
| `routes/`, `tests/`, `bin/` | ルート・テスト・セットアップスクリプト |
| `.env.example` | 環境変数テンプレート |
| `composer.json`, `package.json` | 依存関係定義 |
| `docker-compose.yml`, `docker-compose.amd64.yml`, `docker-compose.arm64.yml`, `docker-compose.gpu.yml` | Sail 設定 |
| `artisan`, `phpunit.xml`, `phpunit.*.xml`, `infection.json5` | ツール設定 |
| `tailwind.config.js`, `vite.config.js`, `postcss.config.js` | フロントエンド設定 |
| `LICENSE`, `NOTICE.md` | ライセンス |

### 5.2 含めるもの（新規作成ドキュメント・コミュニティファイル）

| パス | ステータス |
|------|-----------|
| `README.md` | OSS 向けに新規作成 |
| `CONTRIBUTING.md` | 新規作成 |
| `CODE_OF_CONDUCT.md` | 新規作成（Contributor Covenant 2.1） |
| `SECURITY.md` | 実態に合わせて改訂 |
| `docs/`（§3.3 の構成） | 全て新規作成 |
| `.github/ISSUE_TEMPLATE/` | 汎用バグ報告・機能要望に改訂 |
| `.github/workflows/`（`opencode.yml`, `sync-to-public.yml` 除く） | CI ワークフロー |
| `.github/actions/laravel-test-setup/` | テストセットアップアクション |

### 5.3 含めないもの（プライベートリポジトリ側に残す）

| パス | 理由 |
|------|------|
| `.github/copilot-instructions.md`, `.github/instructions/`, `.github/skills/`, `.github/prompts/`, `.github/agents/` | AI 資産（`ai-assets` へ） |
| `.github/workflows/opencode.yml`, `sync-to-public.yml` | AI・同期専用 |
| `AGENTS.md`, `opencode.json` | AI 設定 |
| `resources/ai/capabilities/` | クライアント向け Capability YAML |
| `docs/`（現行の全ディレクトリ） | 内部実装記録として残す |
| `docker-compose.prod.yml`, `prod.sh` | 本番設定 |
| `.env.development`, `.env.production`, `.env.testing` | 環境固有値 |

### 5.4 表示・権利表記を config で制御する

公開リポジトリ初期化時に、画面上のブランド表記や権利表記を設定ファイルで調整できるようにする。

| 項目 | 現状 | 公開版での方針 |
|------|------|----------------|
| システムタイトル | `config/app.php` の `APP_NAME` を利用済み | 既存の `APP_NAME` を基点に、ヘッダー・`<title>`・通知メールの表記を整合させる |
| フッターの権利表記 | 現状はレイアウト側で明示的な権利表記の統一が未完了 | `config/app.php` または `config/ledgerleap.php` に寄せ、表示文言・年次・ライセンス導線を一元化する |
| その他の表示文言 | 一部は Blade に直書き、または個別ビュー依存 | 将来増える案内文・補助テキスト・ロゴ代替文を設定駆動に寄せる |

**対象ファイル候補**

- `config/app.php`
- `config/ledgerleap.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/appWithDrawer.blade.php`
- `resources/views/layouts/daisyuiNavigation.blade.php`
- `resources/views/components/application-logo.blade.php`

**補足**

- システムタイトル表記は既に `APP_NAME` 参照があるため、ここでは「設定ファイルでの統一」を明示化する。
- フッターの権利表記は、公開時に必要な文言・導線・年次表示を config 化する方針を別 issue で具体化する。
- 公開用 `docs/` の `configuration.md` では、この設定項目を利用者向けに案内する。
- このテーマはサブイシューとして切り出し、[UI ブランディング設定の config 化](https://github.com/torinky/LedgerLeap/issues/222) を起点に実装計画へ落とし込む。

> このテーマは後続のサブ issue で実装し、公開リポジトリの初期構成に組み込む。

---

## 6. AI 資産リポジトリ（`LedgerLeap-ai-assets`）

CI ミラーとは独立した姉妹リポジトリ。将来コントリビュータへ開示する際は、公開リポジトリの `CONTRIBUTING.md` の案内 URL を更新するだけでよい。

**移行方法**: ファイルコピーで十分（履歴は不要）。

```
LedgerLeap-ai-assets/ (private)
  AGENTS.md
  .github/
    copilot-instructions.md
    instructions/
    skills/
    prompts/
    agents/
  opencode.json
  resources/ai/capabilities/
  docs/
    runbooks/    ← AI 専用 runbook のみ
    harnesses/   ← AI 評価ハーネス
    work/
      llm-integration/  ← LLM 設計作業記録
```

**AI 資産内の参照ルール:**

| リンク方向 | ルール |
|-----------|--------|
| AI 資産 → 公開コード | GitHub 絶対 URL (`https://github.com/torinky/LedgerLeap/blob/main/...`) |
| AI 資産 → AI 資産内 | 相対パス可 |
| 公開コード → AI 資産 | `CONTRIBUTING.md` の 1 か所のみ |

**プライベートリポジトリの `AGENTS.md` 移行後:**  
現行の `AGENTS.md` は `LedgerLeap-ai-assets` へ移動。プライベートリポジトリには最小限の案内のみ残す。

---

## 7. 維持方針

### 7.1 日常の開発フロー（移行後）

```
1. プライベートリポジトリで feature ブランチを作成
2. 実装・テスト → main にマージ
3. GitHub Actions (sync-to-public.yml) が自動起動
   └── 公開対象コミットのみが cherry-pick されて公開リポジトリへ push
4. コントリビュータの PR / Issue は公開リポジトリで受け取る
5. PR マージ後、プライベートリポジトリへ手動 cherry-pick
```

### 7.2 公開ドキュメントの維持フロー

```
機能追加・変更
     │
     ├── コード変更 → CI ミラーで公開リポジトリへ自動同期
     │
     └── ドキュメント変更（公開向け）
             │
             ├── 新機能 → docs/features/*.md を新規または更新
             ├── API 変更 → docs/api/*.md を更新
             └── アーキテクチャ変更 → docs/architecture/*.md を更新
```

> 内部実装記録（`docs/work/`）はプライベートリポジトリのみで管理。公開ドキュメントには反映しない。

### 7.3 AI 資産の維持フロー

```
1. 新スキル・プロンプトを LedgerLeap-ai-assets に追加
2. AI 資産が本体コードを参照する場合は絶対 URL を使う
3. 本体コードに大きな変更があった場合は AI 資産も手動で更新
   （自動同期なし）
```

### 7.4 セキュリティ

- CI フィルタが除外パターンを自動適用するため、手動確認コストはほぼゼロ
- コミットメッセージにシークレットを埋め込まないルールを守れば他の対策は不要
- `PUBLIC_REPO_TOKEN` は `contents: write` に加えて、`.github/workflows/*` を更新できる権限も付けて発行する

### 7.5 公開リポジトリのブランチ保護設定

| ルール | 設定値 |
|--------|--------|
| Require PR before merging | ON |
| Allow force pushes | OFF（CI ミラーは force push しない設計） |
| Require status checks | ON（CI テスト必須） |

### 7.6 ローカル確認と GitHub 側確認の分担

| 確認対象 | 主体 | 追跡 issue | 備考 |
|---------|------|-----------|------|
| private staging baseline の対象ファイル選定 | ローカル | #223 | 本体一式を staging に載せる前段 |
| `./bin/setup.sh` の完走確認 | ローカル | #223 | クリーン環境で最後まで通す |
| デモログイン確認 | ローカル | #223 | `superadmin@example.com` / `demo@example.com` を使用 |
| bootstrap commit の push | GitHub | #218 | `public-bootstrap` を `staging:main` へ送る |
| sync workflow の実行条件確認 | GitHub | #218 | `sync-to-public.yml` の反映条件を確認 |
| private main → private staging 同期確認 | GitHub | #218 | force reset dispatch 26348939849 で success。`LedgerLeap-oss` の `pushedAt` は `2026-05-24T01:49:50Z`、`updatedAt` は `2026-05-24T01:49:54Z`、最新 commit は orphan の `a81aea98aa8b28eaf0521711c6ee3bc864c0cf3d` |
| visibility 切り替え | GitHub | #216 | 全体計画完了後に実施 |

> 目安: `#223` はローカル実機での完走確認、`#218` は GitHub 側の反映確認。

### 7.7 GitHub 側で完了させる手順

1. まず、`.github/workflows/*` を公開同期の対象に含めるかを決める。
2. 含める場合は、`PUBLIC_REPO_TOKEN` に `contents: write` と `workflow` 権限の両方を付ける。
3. 含めない場合は、`.github/workflows/` を `.github/sync-excludes.txt` に追加し、除外方針を commit する。
4. `PUBLIC_SYNC_ENABLED=true` を GitHub の repo variable に設定する。
5. `PUBLIC_REPO_TOKEN` を GitHub の Actions secret に設定する。
6. `main` へ対象変更を push するか、`workflow_dispatch` を実行する。履歴ごと整理したいときは `force_history_reset=true` を指定する。実行後は `LedgerLeap-oss` の最新 commit が orphan になっているかも確認する。
7. `Preview public sync scope` で `should_sync=true` と `included_files` を確認する。
8. `Sync snapshot to public repo` が success し、`LedgerLeap-oss` の `pushedAt` / `updatedAt` と file tree が更新されていることを確認する。
9. run URL、public commit SHA、変更内容を issue #218 に evidence として記録する。

> workflow ファイルを含める構成では `workflow` 権限が必須。権限を付けられない場合は workflow ファイルを除外して再実行する。
> 現行の sync は除外リスト外を広く mirror するため、`LedgerLeap-oss` に想定より多く見える内容があれば `.github/sync-excludes.txt` を先に見直す。
> `.ai/`, `.aiassistant/`, `.gemini/`, `.serena/`, `.tmp/` は作業用メタデータなので公開同期から除外済み。
---

## 8. 実行フェーズ計画

| フェーズ | 所要目安 | 主なタスク |
|---------|---------|----------|
| **Phase 0** | 1 日 | `trufflehog` 走査・秘密情報確認・除外パターン確定 |
| **Phase 1** | 1〜2 日 | ブートストラップ（private staging 作成・初回 orphan push・動作確認） |
| **Phase 1-A** | 1〜2 日 | private staging baseline と `setup.sh` 完走確認 |
| **Phase 2** | 2〜3 日 | `sync-to-public.yml` 実装・テスト |
| **Phase 3** | 1 日 | `LedgerLeap-ai-assets` 作成・ファイルコピー・リンク更新 |
| **Phase 4** | 3〜5 日 | **公開ドキュメント新規作成**（Getting Started, アーキテクチャ概要, 機能ガイド） |
| **Phase 5** | 2〜3 日 | OSS コミュニティファイル整備（`CONTRIBUTING.md`, `CODE_OF_CONDUCT.md` 等） |
| **Phase 6** | 随時 | 公開後のドキュメント拡充・コントリビュータからのフィードバック対応 |

> Phase 4 が最もボリュームがある。既存 `docs/` を種本にしながら、「初めて見る人が読む」観点で書き直す。

---

## 9. 完了条件チェックリスト

### Phase 0
- [ ] `trufflehog --git file://.` でシークレット 0 件
- [ ] `.env.development`, `.env.production` が `.gitignore` に含まれている
- [x] `.github/sync-excludes.txt` の除外パターンが確定している
  - Evidence: `.github/sync-excludes.txt` を新規作成し、公開除外対象と秘密情報の扱いを明示済み

### Phase 1
- [x] private staging リポジトリが GitHub に存在する
  - Evidence: `gh repo view torinky/LedgerLeap-oss` で `isPrivate: true` を確認済み
- [x] `[private-origin: <SHA>]` 付きの initial commit が private staging リポジトリに存在する
  - Evidence: `public-bootstrap` の orphan commit `aafcb7bb3d2702e7bc159583f447a0734b6d08b3` を `staging:main` へ push 済み

### Phase 1-A
- [ ] private staging baseline の対象ファイルが確定している
- [ ] private staging baseline commit が push 済み
- [ ] `./bin/setup.sh` が完走している
- [ ] デモアカウントでログインできる

> Phase 1-A は、bootstrap 用 .github 基盤の次に置く本体 baseline の検証スプリント。
> 具体的な実装と実行確認は issue #223 で追跡する。

- [ ] private staging リポジトリでセットアップ手順が動作する（`./bin/setup.sh` が完走する）
- [ ] デモ Seeder 実行後にログイン・基本操作ができる

### Phase 2
- [x] `sync-to-public.yml` がプライベートリポジトリに存在する
  - Evidence: `.github/workflows/sync-to-public.yml` を追加し、private staging のまま同期前提を明示済み
- [ ] `PUBLIC_REPO_TOKEN` Secret が設定されている
- [ ] main への push で CI が起動し公開リポジトリへ同期される
- [ ] 各公開コミットに `[private-ref: <SHA>]` が付加されている
- [ ] プライベート専用ファイルのみのコミットがスキップされる

### Phase 3
- [ ] `LedgerLeap-ai-assets` リポジトリが存在する
- [ ] 現行プライベートリポジトリの AI 資産ファイルが全てコピーされている
- [ ] AI 資産内のリンクが公開リポジトリの絶対 URL に更新されている

### Phase 4
- [ ] `docs/getting-started/installation.md` が存在し、クリーンな環境で手順が完走する
- [ ] `docs/getting-started/demo-setup.md` が存在する
- [ ] `docs/architecture/overview.md` が存在する
- [ ] `docs/contributing/development-setup.md` が存在する

### Phase 5
- [ ] `CONTRIBUTING.md` が存在し AI 資産への案内を含む
- [ ] `CODE_OF_CONDUCT.md` が存在する
- [ ] `SECURITY.md` が実態と一致している
- [ ] `.github/ISSUE_TEMPLATE/` が汎用化されている

---

## 10. 参考：既存ドキュメントの活用マッピング

公開ドキュメント新規作成時に参照する既存資料の対応表。

| 作成する公開ドキュメント | 参照する既存資料（種本） |
|------------------------|----------------------|
| `docs/getting-started/installation.md` | `docs/development/environment-setup.md`（経緯記録だが手順は参考になる）、`docs/development/demo-environment-setup.md` |
| `docs/features/workflow.md` | `docs/work/core-features/workflow/`、`docs/services/WorkflowService.md` |
| `docs/features/search.md` | `docs/work/rag-implementation/`（設計は参考に）、`docs/development/scoring-system.md` |
| `docs/architecture/multi-tenancy.md` | `docs/development/multi-tenancy-guidelines.md` |
| `docs/contributing/testing.md` | `docs/development/Testing-Best-Practices.md`、`docs/testing/` |
| `docs/api/mcp.md` | `docs/development/MCP_Architecture_and_Flow.md` |

---

## 11. 関連ドキュメント

- CI 実装詳細（作成予定）: `docs/work/2026-05-23_sync-ci-implementation.md`
- 表示・権利表記の設定化（作成予定）: `docs/work/issue-drafts/2026-05-23_ui-branding-config_issue-body.md`
- 現行の AI 資産ルーティング: `AGENTS.md`（プライベートリポジトリ）
- 現行の AI 動作ルール: `.github/copilot-instructions.md`（プライベートリポジトリ）
