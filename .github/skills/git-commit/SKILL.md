---
name: git-commit
description: Creates git commit messages without character corruption, following Conventional Commits. Use when running git commit with Japanese or special characters in the message.
compatibility: LedgerLeap (macOS/Linux, requires python3)
---

# git-commit

## ⚠️ ALWAYS Use This Method — No Exceptions

Three approaches **look like they work but silently corrupt newlines or characters**:

| Method | Failure mode |
|---|---|
| `printf 'msg\n...'` | Newlines collapsed → body becomes 1 line in git log |
| heredoc `<< 'EOF'` | Same collapse when piped; `$`, backtick expansion risk |
| `python3 -c "..."` | Shell-escaping required for `"`, `(`, `)`, `$`, backtick |

**Use the bundled script — the only method proven safe:**

```bash
# Structured mode (recommended)
python3 .github/skills/git-commit/scripts/make_commit_msg.py \
  --type fix --scope test \
  --subject "SearchApiTest を DatabaseMigrationsOnce に移行" \
  --body "理由と変更内容。\n複数行は \\n で区切る。" \
  --footer "Closes #74"
git commit -F /tmp/commit_msg.txt

# Raw mode (for complex messages)
python3 .github/skills/git-commit/scripts/make_commit_msg.py \
  --raw "feat(auth): ログイン機能を追加\n\n詳細。\n\nCloses #42"
git commit -F /tmp/commit_msg.txt
```

See [scripts/make_commit_msg.py](scripts/make_commit_msg.py) for full usage (`--help`).

## Commit Format

```
<type>(<scope>): <subject>   ← ≤50 chars
                              ← blank line
<body>                        ← why / what
                              ← blank line
<footer>                      ← Closes #N / Breaking changes
```

**Common types**: `feat` `fix` `test` `refactor` `docs` `ci` `chore`

See [references/conventional-commits.md](references/conventional-commits.md) for full type list and examples.

## Staging Rules

```bash
# Always stage files explicitly — never use git add -A
git add path/to/file1 path/to/file2
git status --short   # verify no unintended files (coverage-*/, wnjpn.db, etc.)
```

## Full Workflow

```bash
git status --short
git add <files>
python3 .github/skills/git-commit/scripts/make_commit_msg.py \
  --type <type> --scope <scope> --subject "<subject>" \
  --body "<body with \\n>" --footer "Closes #N"
git commit -F /tmp/commit_msg.txt
git push origin <branch>
```
