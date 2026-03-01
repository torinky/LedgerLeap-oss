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

**Preferred: direct `git commit -m` inside `bash -c` (no script needed)**

**Long body alternative (script):**

```bash
bash -c "cd /path && python3 .github/skills/git-commit/scripts/make_commit_msg.py \
  --type feat --scope foo --subject 'subject' \
  --body 'line1\nline2' --footer 'Refs #N' && \
  git commit -F /tmp/commit_msg.txt"
```

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

## Full Workflow (Sail-safe)

```bash
bash -c "cd /path && git add <files> && git status --short"
bash -c "cd /path && git commit -m 'type(scope): subject

body

Refs #N'"
bash -c "cd /path && git log --oneline origin/main..HEAD"
bash -c "cd /path && git push origin <branch>"
```
