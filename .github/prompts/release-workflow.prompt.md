---
description: LedgerLeap release workflow — create CalVer tags, update CHANGELOG, publish GitHub Releases on both private and OSS repositories. Run `/release-workflow` before every release.
---

# release-workflow

## Repository Model

```
private repo (torinky/LedgerLeap, origin)  ← タグ作成・CHANGELOG 更新はここ
       │
       │ git push staging <tag>
       ▼
OSS repo (torinky/LedgerLeap-oss, staging)  ← 公開 Release が必要な場合のみタグ同期
```

常に private リポジトリでタグを作成し、必要に応じて OSS に同期する。Alpha は private のみ。

詳細: `.github/skills/release-workflow/SKILL.md`

## Version Format (CalVer)

```
vYYYY.MINOR.PATCH[-alpha.N|-beta.N|-rc.N]
```

## Release Flow

### 1. Determine version

```
変更内容 → version bump:
  年跨ぎ初回 → YYYY 更新, MINOR=1
  破壊的変更 → MINOR++
  新機能     → MINOR++
  バグ修正   → PATCH++
  セキュリティ → PATCH++
  ドキュメントのみ → スキップ
```

### 2. Determine if OSS tag sync is needed

OSS リポジトリが **private** の間は全段階で OSS にタグ同期する。（public 化後も継続）

| Stage | OSS tag sync? |
|-------|---------------|
| Alpha | ✅（private staging、招待開発者向け） |
| Beta  | ✅ |
| RC    | ✅ |
| Stable | ✅ |

### 3. Update CHANGELOG

`CHANGELOG.md` の `[Unreleased]` セクションを確認し、リリース時に確定する。

### 4. Create and push tag (always on private repo)

```bash
git tag -a vYYYY.MINOR.PATCH -m "vYYYY.MINOR.PATCH: summary"
git push origin vYYYY.MINOR.PATCH     # private repo → Release 生成
git push staging vYYYY.MINOR.PATCH    # OSS repo → Release 生成（必要な場合）
```

### 5. Verify

```bash
# private repo
gh release view vYYYY.MINOR.PATCH -R torinky/LedgerLeap
# OSS repo（タグ同期時）
gh release view vYYYY.MINOR.PATCH -R torinky/LedgerLeap-oss
```

## Pre-release Checklist

| Stage | `SECURITY.md` | `CHANGELOG.md` | `push origin` | `push staging` |
|-------|---------------|----------------|---------------|----------------|
| Alpha | エントリ追加 | `[Unreleased]` に追記 | ✅ | ✅ |
| Beta | エントリ更新 | 同上 | ✅ | ✅ |
| RC | エントリ更新 | 同上 | ✅ | ✅ |
| Stable | Supported にマーク | セクション確定 | ✅ | ✅ |

See `docs/runbooks/release-workflow-playbook.md` for step-by-step procedures.
