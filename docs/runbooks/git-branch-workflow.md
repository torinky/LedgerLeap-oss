# Git Branch Workflow

**対象:** LedgerLeap のブランチ戦略とライフサイクル管理。全 AI エージェント (Copilot, Gemini, Antigravity, opencode) 共通ルール。

関連ルール:
- `.github/copilot-instructions.md` — 全エージェント共通の不変条件
- `.github/skills/git-commit/SKILL.md` — コミット後ブランチ削除ルール
- `.github/skills/github-issue-workflow/SKILL.md` — ブランチ命名規則

---

## 1. ブランチ構成

```
main          ← 常にデプロイ可能な安定版（OSS 公開用 torinky/LedgerLeap-oss）
develop       ← 統合ブランチ。すべての feature/fix はここにマージ
```

## 2. ブランチ命名規則

| 種類 | 形式 | 例 |
|------|------|-----|
| 機能 | `feature/#<issue>-<kebab-desc>` | `feature/#230-search-improvement` |
| 修正 | `fix/#<issue>-<kebab-desc>` | `fix/#208-folder-tree-indent` |
| 雑務 | `chore/<kebab-desc>` | `chore/update-laravel-11` |

- 常に小文字 + kebab-case
- Issue 番号は `#` 付きで必須（`chore` は例外）
- 説明は 3-4 単語の簡潔な英語

## 3. ブランチ作成

```bash
# develop が最新であることを確認
git checkout develop
git pull origin develop

# ブランチ作成
git checkout -b feature/#230-search-improvement
```

- **必ず `develop` から作成する**（hotfix は `main` から）
- 作業開始前に Issue 番号が確定していること

## 4. 開発フロー

```
Issue 起票 → ブランチ作成 → 実装 → commit → PR 作成 → CI 通過 → merge → ブランチ削除
```

### Commit

Conventional Commits 形式でコミット。詳細は `.github/skills/git-commit/SKILL.md` 参照。

```
feat(scope): subject
fix(scope): subject
chore: subject
```

### PR 作成

```bash
gh pr create --repo torinky/LedgerLeap --base develop --head feature/#230-search-improvement \
  --title "feat: search improvement" --body "Closes #230"
```

### Merge

- PR は **squash merge** または **rebase merge** で `develop` にマージ
- CI がすべて通っていることを確認してからマージ

## 5. ブランチ削除（最重要ルール）

マージ後、**即座に** ブランチを削除する。マージ済みブランチを保持しない。

```bash
# ローカルブランチ削除
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git branch -d feature/#230-search-improvement"

# リモートブランチ削除
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin --delete feature/#230-search-improvement"

# リモート追跡参照の整理
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git remote prune origin"
```

マージ済みブランチは死んだブランチ。Git 履歴がすべてを保存しているため「念のため残す」は不要。

## 6. リリース

```
develop → main にマージ → バージョンタグ → OSS mirror に自動同期
```

```bash
git checkout main
git merge develop
git tag v1.2.3
git push origin main --tags
# main への push で sync-to-public.yml が自動実行され OSS mirror に同期
```

## 7. ブランチ整理（定期メンテナンス）

```bash
# マージ済みローカルブランチの確認
git branch --merged develop | grep -v 'develop\|main'

# マージ済みブランチの一括削除
git branch --merged develop | grep -v '^\*\|main\|develop' | xargs git branch -d

# リモートブランチの整理
git remote prune origin

# 古いリモートブランチの確認と削除
git ls-remote --heads origin | grep -E '(feature/|fix/|issue/)' # 確認
git push origin --delete <branch>  # 削除
```

## 8. 禁止事項

- ❌ マージ後のブランチを残す
- ❌ プレフィックスなしのブランチ名（`singleTenant` など）
- ❌ 同一機能の複数試行ブランチを放置（`xxx-attempt`, `xxx-other-line` など）
- ❌ `main` に直接 push
- ❌ リリース用ブランチの作成（タグで管理する）
