---
description: LedgerLeap release workflow — create CalVer tags, update CHANGELOG, publish GitHub Releases. Run `/release-workflow` before every release.
---

# release-workflow

## Version Format (CalVer)

```
vYYYY.MINOR.PATCH[-alpha.N|-beta.N|-rc.N]
```

詳細: `.github/skills/release-workflow/SKILL.md`

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

### 2. Update CHANGELOG

`CHANGELOG.md` の `[Unreleased]` セクションを確認し、リリース時に確定する。

### 3. Create annotated tag

```bash
git tag -a vYYYY.MINOR.PATCH -m "vYYYY.MINOR.PATCH: summary"
git push origin vYYYY.MINOR.PATCH
```

### 4. Verify

- GitHub Actions が Release を作成したか確認
- `gh release view vYYYY.MINOR.PATCH -R torinky/LedgerLeap`

## Pre-release Checklist

| Stage | `SECURITY.md` | `CHANGELOG.md` | 公開範囲 |
|-------|---------------|----------------|----------|
| Alpha | エントリ追加 | `[Unreleased]` に追記 | 非公開/クローズド |
| Beta | エントリ更新 | 同上 | OSS private staging |
| RC | エントリ更新 | 同上 | OSS private staging |
| Stable | Supported にマーク | セクション確定 | public リリース |

See `docs/runbooks/release-workflow-playbook.md` for step-by-step procedures.
