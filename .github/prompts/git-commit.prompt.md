---
description: Git commit workflow for LedgerLeap. Handles Sail environment silent-failure and Japanese character encoding. Run this before every commit.
---

# git-commit

## ⚠️ Sail Environment: Always Use `bash -c`

After `./vendor/bin/sail ...` runs, plain `cd && git` commands produce **empty output silently**.

**Always wrap every git operation:**
```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git status"
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git add -A && git status --short"
```

## ⚠️ Commit Message Encoding

| Method | Failure mode |
|---|---|
| `printf 'msg\n...'` | Newlines collapsed |
| heredoc `<< 'EOF'` | Same collapse |
| `bash -c "... 'Japanese body'"` | Single-quote inside `bash -c` → hang |

**Required method: `create_file` → python3 script → `git commit -F`**

### Step-by-Step

1. **Pre-commit checks:**
```bash
./vendor/bin/sail pint
./vendor/bin/sail test
```

2. **Stage files:**
```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git add -A && git status --short"
```

3. **Create `/tmp/msg_input.txt`** using the `create_file` tool with this format:
```
fix(scope): subject line here

body line 1
body line 2

Refs #N
```

4. **Generate commit message:**
```bash
python3 .github/skills/git-commit/scripts/make_commit_msg.py --file /tmp/msg_input.txt
```

5. **Commit:**
```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git commit -F /tmp/commit_msg.txt"
```

6. **Verify:**
```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git log --oneline origin/main..HEAD"
```

## Conventional Commits Types

| Type | When |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `test` | Test only |
| `refactor` | No behavior change |
| `chore` | Build, config, deps |

See `.github/skills/git-commit/references/sail-environment.md` for diagnosis details.

