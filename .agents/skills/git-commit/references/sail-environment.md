# Sail Environment: Silent Failure Pattern

## Diagnosis (proven in Issue #54, 2026-03-01)

In the LedgerLeap Sail environment, `run_in_terminal` commands that follow
`./vendor/bin/sail ...` commands **silently produce no output**, even though
the command internally succeeds. Symptoms:

| Command | Expected | Actual in Sail context |
|---|---|---|
| `git add file.php` + `git status --short` | Shows staged files | Empty output |
| `python3 .../make_commit_msg.py ...` | Prints preview | Empty output |
| `git log --oneline origin/main..HEAD` | Shows commits | Empty output |
| `git commit -F /tmp/commit_msg.txt` | Creates commit | "no changes added" |
| `git commit -m "..."` | Creates commit | "no changes added" |

**Root cause**: After `./vendor/bin/sail test ...` runs, the terminal session
inherits a Docker/Sail shell context. Subsequent plain commands (`cd && git`)
execute in a different process environment where STDOUT is not forwarded.

## Solution: `bash -c "..."` Wrapper

Wrapping commands in `bash -c "..."` spawns a fresh macOS shell, bypassing
the inherited Sail context:

```bash
# ✅ Stage + verify in one bash -c call
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git add app/Livewire/Ledger/Foo.php tests/Feature/Livewire/Ledger/FooTest.php && git status --short"

# ✅ Commit with direct -m (most reliable — no temp file needed)
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git commit -m 'feat(scope): subject

Body paragraph here.
Second line.

Refs #54'"

# ✅ Verify commit landed
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git log --oneline origin/main..HEAD"

# ✅ Push
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git push origin feature/issue-54-related-ledgers-tab"
```

## Commit Message Script (when body is very long)

```bash
# Write message file first, then commit — both in same bash -c
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && \
  python3 .github/skills/git-commit/scripts/make_commit_msg.py \
    --type feat --scope related-ledgers \
    --subject 'Sprint N: subject here' \
    --body 'line1\nline2\nline3' \
    --footer 'Refs #54' && \
  git commit -F /tmp/commit_msg.txt"
```

## Detection Heuristic

If any of these symptoms appear → switch to `bash -c`:

1. `git status` after `git add` shows empty or no staged files
2. `python3 script.py` produces no output / no `/tmp/commit_msg.txt`
3. `git log` shows 0 commits but you just committed
4. `git commit -F file` says "no changes added to commit"

## When This Does NOT Occur

- First command in a fresh `run_in_terminal` call (before any `sail` command)
- `bash -c "..."` wrapper — always safe
- `./vendor/bin/sail test ...` itself — unaffected (runs inside container intentionally)

