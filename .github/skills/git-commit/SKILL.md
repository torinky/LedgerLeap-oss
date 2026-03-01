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

**Use `create_file` tool + `python3 script.py` — the only method proven safe:**

```
Step 1 — create_file tool writes /tmp/mk_commit_msg.py:
# -*- coding: utf-8 -*-
msg = "feat(scope): subject line (≤50 chars)\n\nBody detail.\n\nCloses #123\n"
open('/tmp/commit_msg.txt', 'w', encoding='utf-8').write(msg)
print('OK')

Step 2 — execute:
python3 /tmp/mk_commit_msg.py && cat /tmp/commit_msg.txt

Step 3 — commit:
git commit -F /tmp/commit_msg.txt
```

The `create_file` tool writes bytes directly as UTF-8, bypassing all shell encoding issues.

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
# create_file → python3 /tmp/mk_commit_msg.py
git commit -F /tmp/commit_msg.txt
git push origin <branch>
```
