---
name: release-workflow
description: LedgerLeap の CalVer リリースフロー。タグ作成、CHANGELOG 更新、GitHub Release、OSS 同期の一連の手順を管理する。Use when creating a release tag, publishing a pre-release (alpha/beta/rc), or managing the release lifecycle.
compatibility: "LedgerLeap (owner: torinky, repo: LedgerLeap, CalVer YYYY.MINOR.PATCH)"
---

# release-workflow

## Version Format

**CalVer `YYYY.MINOR.PATCH`** — 年.MINOR.パッチ

```
v2026.1.0-alpha.1    Alpha プレリリース
v2026.1.0-beta.1     Beta プレリリース
v2026.1.0-rc.1       RC プレリリース
v2026.1.0            正式版
v2026.2.0            機能追加リリース
v2026.1.1            バグ修正リリース
v2027.1.0            年跨ぎリリース（MINOR リセット）
```

詳細: `docs/work/2026-05-30_versioning-strategy.md`

## Pre-release Stage Decision

| 段階 | タグ例 | 用途 | 対象者 |
|------|--------|------|--------|
| Alpha | `v2026.1.0-alpha.N` | 内部動作確認、DB/API 変更可 | 開発チーム |
| Beta | `v2026.1.0-beta.N` | 公開テスト、Issue 受付開始 | アーリーアダプター |
| RC | `v2026.1.0-rc.N` | 最終確認、リリース判定 | 全ユーザー |
| Stable | `v2026.1.0` | 正式版 | 全ユーザー |

## Release Creation Command

```bash
# 1. CHANGELOG を更新（[Unreleased] → バージョン確定）
# 2. コミット
git add CHANGELOG.md
git commit -m "chore(release): prepare v2026.1.0-alpha.1"

# 3. タグ作成（annotated tag 必須）
git tag -a v2026.1.0-alpha.1 -m "v2026.1.0-alpha.1: リリース説明"

# 4. プッシュ
git push origin develop          # develop ブランチ
git push origin v2026.1.0-alpha.1  # タグ
```

## GitHub Release 自動化

`.github/workflows/release.yml` がタグプッシュを検知し、以下を自動実行:
- プレリリース判定: タグに `alpha`/`beta`/`rc` を含む → `prerelease: true`
- リリースノート: 前回タグからのコミットログを収集

## Stable リリースチェックリスト

1. `CHANGELOG.md` の `[Unreleased]` → `[YYYY.MINOR.PATCH] - YYYY-MM-DD` に確定
2. `SECURITY.md` の Supported Versions を Stable として更新
3. annotated tag を作成しプッシュ
4. Release を確認 → アナウンス

## Year Transition

新年最初のリリース時:
```bash
git tag v2027.1.0  # YYYY を当年に、MINOR を 1 にリセット
```

## OSS 同期

- タグは `sync-to-public.yml` の同期対象外。公開リポジトリに Release が必要な場合は別途タグをプッシュ
- 公開リポジトリ: `torinky/LedgerLeap-oss`（staging remote）
- 詳細: `docs/runbooks/oss-sync-runbook.md`

## Reference

- 戦略文書: `docs/work/2026-05-30_versioning-strategy.md`
- 手順詳細: `docs/runbooks/release-workflow-playbook.md`
- OSS 公開計画: `docs/work/2026-05-23_oss-publication-plan.md`
