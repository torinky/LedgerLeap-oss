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

# 6. 確認
gh release view v2026.1.0-alpha.1 -R torinky/LedgerLeap
# → isPrerelease: true を確認
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

# 4. プッシュ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin develop"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.0-rc.1"
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
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.2.0 -m 'v2026.2.0: New feature release'"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.2.0"
```

---

## 7. バグ修正リリース手順（PATCH バンプ）

```bash
# v2026.1.1, v2026.1.2, ...
# hotfix は main からブランチを切り、修正後 main にマージ → タグ
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2026.1.1 -m 'v2026.1.1: Bug fix release'"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2026.1.1"
```

---

## 8. 年跨ぎリリース手順

```bash
# 新年最初のリリース: YYYY を当年に、MINOR を 1 にリセット
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  git tag -a v2027.1.0 -m 'v2027.1.0: First release of 2027'"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin v2027.1.0"
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

- コミット同期: `main` への push で `sync-to-public.yml` が自動実行
- タグ同期: 自動化されない。必要に応じて手動で `git push staging <tag>` を実行
- Alpha リリース: private リポジトリのみ
- Beta/RC: 公開リポジトリの public 化後にタグを同期
- Stable: 両リポジトリで Release を作成
