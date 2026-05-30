---
name: release-workflow
description: LedgerLeap の CalVer リリースフロー。プライベートリポジトリ (torinky/LedgerLeap) と OSS 公開リポジトリ (torinky/LedgerLeap-oss) の両方を考慮したタグ作成、CHANGELOG 更新、GitHub Release、OSS 同期の一連の手順を管理する。Use when creating a release tag, publishing a pre-release (alpha/beta/rc), or managing the release lifecycle.
compatibility: "LedgerLeap (owner: torinky, repo: LedgerLeap, OSS: LedgerLeap-oss, CalVer YYYY.MINOR.PATCH)"
---

# release-workflow

## Dual Repository Model

| リポジトリ | Remote | 用途 | 現在の visibility |
|-----------|--------|------|------------------|
| `torinky/LedgerLeap` | `origin` | 開発主軸。タグ・Release の起点 | private |
| `torinky/LedgerLeap-oss` | `staging` | OSS 公開窓口 | private（計画完了後に public） |

**鉄則: タグ作成・CHANGELOG 更新は常に private リポジトリで行う。**

```
private repo でタグ作成 → git push origin <tag>
  ├─ release.yml が private repo に Release を作成
  └─ 必要に応じて git push staging <tag> → OSS repo にも Release 生成
```

## Version Format

**CalVer `YYYY.MINOR.PATCH`** — 年.MINOR.パッチ

```
v2026.1.0-alpha.1    Alpha（private のみ）
v2026.1.0-beta.1     Beta（OSS にもタグ同期）
v2026.1.0-rc.1       RC（OSS にもタグ同期）
v2026.1.0            Stable（OSS にもタグ同期）
v2026.2.0            機能追加
v2026.1.1            バグ修正
v2027.1.0            年跨ぎ（MINOR リセット）
```

## OSS Tag Sync Decision

| 段階 | private にタグ | OSS にタグ同期 | 理由 |
|------|--------------|-------------|------|
| Alpha | ✅ | ❌ | クローズドテスト。OSS repo はまだ private |
| Beta | ✅ | ✅（公開化後） | 公開テスト。public 化後にタグを push staging |
| RC | ✅ | ✅ | リリース候補。OSS 側でも確認可能に |
| Stable | ✅ | ✅ | 正式版。両リポジトリで Release 生成 |

```bash
# OSS にタグを同期する場合
git push staging v2026.1.0-beta.1
# → OSS リポジトリの release.yml が動作し、torinky/LedgerLeap-oss に Release を作成
```

**OSS リポジトリ側の release.yml はコミット同期で自動反映される**（sync-excludes 対象外のため）。

## Release Workflow (private repo)

```bash
# 1. develop ブランチで作業（Stable 以外）
# 2. CHANGELOG 更新、SECURITY.md 更新
# 3. コミット
git add CHANGELOG.md SECURITY.md
git commit -m "chore(release): prepare v2026.1.0"

# 4. タグ作成（annotated tag 必須）
git tag -a v2026.1.0 -m "v2026.1.0: Stable release"

# 5. プッシュ
git push origin develop          # ブランチ
git push origin v2026.1.0         # タグ → release.yml 起動

# 6. OSS 同期（Stable/Beta/RC の場合）
git push staging v2026.1.0       # OSS リポジトリにもタグ → Release 生成
```

## Stable Release (main マージ)

Stable リリース時のみ `develop → main` にマージ:

```bash
git checkout main && git merge develop && git push origin main
# → main への push で sync-to-public.yml が OSS にコミット同期
# → タグは別途 push staging
```

## GitHub Release 自動化

`release.yml` はタグプッシュを検知し、プッシュ先のリポジトリに Release を作成:
- private repo に push → `torinky/LedgerLeap` に Release
- OSS repo に push → `torinky/LedgerLeap-oss` に Release

## Reference

- 戦略文書: `docs/work/2026-05-30_versioning-strategy.md`
- 手順詳細: `docs/runbooks/release-workflow-playbook.md`
- OSS 公開計画: `docs/work/2026-05-23_oss-publication-plan.md`
