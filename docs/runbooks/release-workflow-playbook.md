# Release Workflow Playbook

**対象:** LedgerLeap の CalVer リリース手順。全 AI エージェント共通ルール。

関連ドキュメント:
- `.github/skills/release-workflow/SKILL.md` — リリース判断・フォーマット
- `docs/work/2026-05-30_versioning-strategy.md` — バージョン番号戦略の全体設計
- `docs/work/2026-05-23_oss-publication-plan.md` — OSS 公開移行計画
- `docs/runbooks/git-branch-workflow.md` — ブランチ戦略・リリース連携

---

## 1. バージョン形式

**CalVer `YYYY.MINOR.PATCH`**（年.MINOR.パッチ）

```
v2026.1.0-alpha.1    Alpha プレリリース
v2026.1.0-beta.1     Beta プレリリース
v2026.1.0-rc.1       RC
v2026.1.0            正式版
v2026.2.0            機能追加
v2026.1.1            バグ修正
v2027.1.0            年跨ぎ（MINOR リセット）
```

### OSS タグ同期判断

| 段階 | `push origin` (private) | `push staging` (OSS) | 備考 |
|------|------------------------|---------------------|------|
| Alpha | ✅ | ✅ | OSS repo が private の間は全段階同期 |
| Beta | ✅ | ✅ | |
| RC | ✅ | ✅ | |
| Stable | ✅ | ✅ | |
| MINOR bump | ✅ | ✅ | |
| PATCH bump | ✅ | ✅ | |
| Year transition | ✅ | ✅ | |

---

## 2. Alpha リリース手順

```bash
# 1. 事前確認
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git status"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git log --oneline -10"

# 2. CHANGELOG 確認（[Unreleased] に変更が蓄積されているか）
# → Alpha では [Unreleased] のまま確定不要

# 3. SECURITY.md 更新
# Supported Versions に v2026.1.0-alpha.N を追加

# 4. タグ作成（annotated tag）
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.0-alpha.1 -m 'v2026.1.0-alpha.1: Initial alpha pre-release'"

# 5. プッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin develop"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.0-alpha.1"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.1.0-alpha.1"

# 6. private リポジトリの Release 確認
gh release view v2026.1.0-alpha.1 -R torinky/LedgerLeap
# → isPrerelease: true を確認

# 7. OSS リポジトリの Release 確認
gh release view v2026.1.0-alpha.1 -R torinky/LedgerLeap-oss
```

---

## 3. Beta リリース手順

```bash
# 1. Alpha で収集した不具合をすべて修正済みであることを確認

# 2. 全機能の動作確認
# → DB マイグレーション（新規 + アップグレード）の正常動作も確認

# 3. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.0-beta.1 -m 'v2026.1.0-beta.1: Beta pre-release'"

# 4. プッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin develop"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.0-beta.1"

# 5. 公開リポジトリにもタグをプッシュ（Beta から OSS 公開対象）
# → 公開リポジトリの visibility を public に切り替えた後に実行
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.1.0-beta.1"

# 6. 公開 Issue 受付開始
```

---

## 4. RC リリース手順

```bash
# 1. Beta で報告された全バグを修正

# 2. 回帰テスト
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && ./vendor/bin/sail test"

# 3. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.0-rc.1 -m 'v2026.1.0-rc.1: Release Candidate'"

# 4. private リポジトリにプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin develop"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.0-rc.1"

# 5. OSS リポジトリにタグ同期
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.1.0-rc.1"

# 6. 両リポジトリの Release を確認
gh release view v2026.1.0-rc.1 -R torinky/LedgerLeap
gh release view v2026.1.0-rc.1 -R torinky/LedgerLeap-oss
```

---

## 5. Stable リリース手順（正式版）

```bash
# 1. CHANGELOG 確定
# [Unreleased] → [2026.1.0] - YYYY-MM-DD に書き換え

# 2. SECURITY.md 更新
# Supported Versions を Stable としてマーク

# 3. コミット
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git add CHANGELOG.md SECURITY.md && \
  git commit -m 'chore(release): v2026.1.0 stable release'"

# 4. develop → main にマージ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git checkout main"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git merge develop"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin main"

# 5. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.0 -m 'v2026.1.0: Stable release'"

# 6. タグプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.0"

# 7. 公開リポジトリ同期
# main への push で sync-to-public.yml が自動実行
# 公開リポジトリにタグをプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.1.0"

# 8. 確認
gh release view v2026.1.0 -R torinky/LedgerLeap
# → isPrerelease: false を確認

# 9. develop に戻る
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git checkout develop"

# 10. アナウンス
```

---

## 6. 機能追加リリース手順（MINOR バンプ）

```bash
# v2026.2.0, v2026.3.0, ...
# 1. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.2.0 -m 'v2026.2.0: New feature release'"

# 2. private リポジトリにプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.2.0"

# 3. OSS リポジトリにタグ同期（初回 Stable 以降は必須）
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.2.0"
```

---

## 7. バグ修正リリース手順（PATCH バンプ）

```bash
# v2026.1.1, v2026.1.2, ...
# hotfix は main からブランチを切り、修正後 main にマージ → タグ
# 1. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.1 -m 'v2026.1.1: Bug fix release'"

# 2. private リポジトリにプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.1"

# 3. OSS リポジトリにタグ同期（初回 Stable 以降は必須）
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2026.1.1"
```

---

## 8. 年跨ぎリリース手順

```bash
# 新年最初のリリース: YYYY を当年に、MINOR を 1 にリセット
# 1. タグ作成
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2027.1.0 -m 'v2027.1.0: First release of 2027'"

# 2. private リポジトリにプッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2027.1.0"

# 3. OSS リポジトリにタグ同期
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push staging v2027.1.0"
```

---

## 9. 自動化（GitHub Actions）

- トリガー: `v[0-9]+.[0-9]+.[0-9]+*` パターンのタグプッシュ
- プレリリース判定: タグに `alpha|beta|rc` を含む → `--prerelease`
- リリースノート: 前回タグからのコミットログを自動収集
- ワークフロー: `.github/workflows/release.yml`

---

## 10. 公開リポジトリとの関係

| リポジトリ | 用途 | Remote |
|-----------|------|--------|
| `torinky/LedgerLeap` | 開発主軸（private） | `origin` |
| `torinky/LedgerLeap-oss` | OSS 公開窓口（private → 計画完了後 public） | `staging` |

### 同期の仕組み

```
private repo (origin)                    OSS repo (staging)
       │                                        │
       │ main push → sync-to-public.yml         │
       │ コミットが cherry-pick される ─────────→ │ main にコミット反映
       │                                        │
       │ .github/workflows/release.yml          │ .github/workflows/release.yml
       │ （sync-excludes 対象外のため           │ （コミット同期で自動反映）
       │   自動で OSS に同期される）             │
       │                                        │
       │ git push origin <tag>                  │ git push staging <tag>
       │ → release.yml 起動                     │ → release.yml 起動
       │ → private repo に Release 作成        │ → OSS repo に Release 作成
```

- **コミット同期**: `main` への push で `sync-to-public.yml` が自動実行。`release.yml` も同期対象（`sync-excludes.txt` の除外対象外）
- **タグ同期**: 自動化されない。各リリース段階に応じて手動で `git push staging <tag>` を実行
- **Alpha 以降**: OSS リポジトリが private の間は全段階でタグ同期（招待開発者向け）。public 化後も継続

---

## 11. CHANGELOG の記述規則

CHANGELOG.md は #232 により、フッターやプロフィールメニューのバージョン文字列クリックで到達する**ランディングページ**として機能する。
各エントリは単なる変更記録ではなく、ペルソナに応じた次のアクションへの導線として記述する。

### 11.1 ペルソナ別の導線

| ペルソナ | コード | 関心 | 推奨リンク先 |
|---------|--------|------|------------|
| 実務担当者 | `operator` | 入力項目・操作手順の変更、新機能の使い方 | ユーザーマニュアル (`docs/user/`) |
| 管理者 | `administrator` | 設定変更、権限、セキュリティ修正、監査ログ | 管理マニュアル (`docs/admin/`)、設定リファレンス |
| 現場リーダー | `field-leader` | ワークフロー変更、承認フロー、ロール権限 | ワークフロー説明 (`docs/user/workflow/`)、ロール権限ドキュメント |
| 開発者 | `developer` | API変更、DBマイグレーション、依存関係、破壊的変更 | Issue/PR (`#NNN`)、マイグレーションガイド、アップグレードノート |

### 11.2 エントリ構造

```markdown
## [2026.2.0] - YYYY-MM-DD

### Added
- **新機能名**: 概要説明。 ([ユーザーマニュアル](docs/user/xxx.md), #NNN)
  - 対象: `operator` `field-leader`

### Changed
- **変更対象**: 変更内容と影響。 ([設定リファレンス](docs/admin/xxx.md))
  - 対象: `administrator`
  - 移行手順: `php artisan ledgerleap:xxx`

### Fixed
- **修正内容**: 現象と修正の概要。 (#NNN)
  - 対象: `operator` `field-leader`

### Security
- **脆弱性対応**: 影響範囲と推奨アクション。 (CVE-XXXX-XXXX, [セキュリティアドバイザリ](SECURITY.md))
  - 対象: `administrator`

### Deprecated
- **非推奨機能**: 代替手段と削除予定時期。
  - 対象: `developer`
```

### 11.3 リンクの優先順位

1. **Issue 番号** (`#NNN`) — 最も簡潔で普遍的な参照。必ず含める
2. **ドキュメントパス** (`docs/user/xxx.md`) — 存在する場合のみ。OSS 公開リポジトリでの到達性を確認
3. **外部 URL** (CVE, アップストリームの変更ログ等) — 該当する場合のみ
4. **コマンド/移行手順** — 破壊的変更や手動操作が必要な場合

### 11.4 禁止事項

- ペルソナを明示せず「改善」「修正」のみで済ませない
- 存在しないドキュメントへのリンクを記載しない
- プライベートリポジトリの絶対パス (`/Users/...`) を記載しない
- `[Unreleased]` への追記を怠り、Stable リリース時にまとめて書かない（PR マージ時点で追記する）
