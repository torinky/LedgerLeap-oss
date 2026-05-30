---
name: git-commit
description: Creates git commit messages without character corruption, following Conventional Commits. Use when running git commit with Japanese or special characters in the message.
compatibility: LedgerLeap (macOS/Linux, requires python3)
---

# git-commit

## ⚠️ Sail Environment: Always Use `bash -c` (Issue #54)

After `./vendor/bin/sail ...` runs, plain `cd && git` commands produce **empty
output silently** — `git add`, `git log`, `git commit` all appear to do nothing.

**Fix: wrap every git operation in `bash -c "..."`**

```bash
bash -c "cd /path && git add file1 file2 && git status --short"
bash -c "cd /path && git commit -m 'feat(scope): subject

body

Refs #N'"
bash -c "cd /path && git log --oneline origin/main..HEAD"
```

See [references/sail-environment.md](references/sail-environment.md) for full diagnosis and detection heuristic.

## ⚠️ Commit Message Encoding — No Exceptions

| Method | Failure mode |
|---|---|
| `printf 'msg\n...'` | Newlines collapsed → body becomes 1 line |
| heredoc `<< 'EOF'` | Same collapse; `$`, backtick expansion risk |
| `python3 -c "..."` | Shell-escaping corrupts Japanese / special chars |
| `bash -c "... 'Japanese body'"` | Single-quote inside `bash -c` → `dquote>` hang (Issue #81) |
| `make_commit_msg.py --body 'Japanese'` | Same hang — all quoted args with Japanese affected |

**Preferred: `create_file` で `/tmp/msg_input.txt` に書いて `--file` で渡す**

```bash
# Step 1: create_file tool で /tmp/msg_input.txt を作成（plain text、シェル不要）
# Step 2:
python3 .github/skills/git-commit/scripts/make_commit_msg.py --file /tmp/msg_input.txt
bash -c "cd /path && git commit -F /tmp/commit_msg.txt"
```

`/tmp/msg_input.txt` の書式（Conventional Commits プレーンテキスト）:
```
fix(scope): subject line here

Body paragraph.

Refs #N
```

**ASCII のみで body なし:** `bash -c` の `-m` で直接渡しても OK。

See [references/conventional-commits.md](references/conventional-commits.md) for type list.

## Commit Format

```
<type>(<scope>): <subject>   ← ≤50 chars
<blank line>
<body>                        ← why / what
<blank line>
<footer>                      ← Closes #N / Breaking changes
```

**Common types**: `feat` `fix` `test` `refactor` `docs` `ci` `chore`

## Pre-flight: Branch Health Check

**Before every commit session**, scan for stale merged branches:

```bash
bash -c "cd /path && git branch --merged develop | grep -v 'develop\|main'"
```

If any output appears, **proactively offer to delete them**:
- "マージ済みのブランチが N 本あります。削除しますか？"
- User confirms → run `git branch -d <each>` + `git push origin --delete <each>`
- Also prune remote tracking: `git remote prune origin`

This check MUST run at:
- Start of any coding session
- Before `/git-commit`
- After merge to develop (verify branch was deleted)
- When user says "done" / "完了" / "マージした"

## Full Workflow (Sail-safe, Japanese body)

```bash
# 0. Pre-flight: check for stale merged branches
bash -c "cd /path && git branch --merged develop | grep -v 'develop\|main'"
# 1. Stage
bash -c "cd /path && git add <files> && git status --short"
# 2. Write message with create_file tool → /tmp/msg_input.txt, then:
python3 .github/skills/git-commit/scripts/make_commit_msg.py --file /tmp/msg_input.txt
# 3. Commit
bash -c "cd /path && git commit -F /tmp/commit_msg.txt"
# 4. Verify
bash -c "cd /path && git log --oneline origin/main..HEAD"
# 5. Push
bash -c "cd /path && git push origin <branch>"
```

## After Merge — Delete Branch (MANDATORY)

Once the PR is merged, delete the branch **immediately**:

```bash
# Delete local branch
bash -c "cd /path && git branch -d <branch>"
# Delete remote branch
bash -c "cd /path && git push origin --delete <branch>"
# Prune stale remote tracking refs
bash -c "cd /path && git remote prune origin"
```

**Merged branches are dead.** Never keep them "just in case" — Git history preserves everything. See `docs/runbooks/git-branch-workflow.md` for the full lifecycle.
