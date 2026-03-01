# Conventional Commits — LedgerLeap Reference

## Full Type List

| type | when to use |
|---|---|
| `feat` | new feature |
| `fix` | bug fix |
| `docs` | documentation only |
| `style` | formatting, no logic change |
| `refactor` | restructure without feature/bug change |
| `perf` | performance improvement |
| `test` | add or update tests |
| `build` | build system / dependency changes |
| `ci` | CI/CD configuration |
| `chore` | misc changes not fitting above |
| `revert` | revert a previous commit |

## Subject Rules

- ≤50 characters
- Japanese OK: noun phrase or `〜する` / `〜した`
- No period at end

## Body / Footer

- Body: explain *why* and *what*, not *how*
- Footer: `Closes #N`, `Breaking change: ...`

## Examples

```
feat(auth): JWTによるユーザー認証APIを実装

メールアドレスとパスワードで新規登録できるAPIを追加。
登録成功時にユーザートークンを返却する。

Closes #42
```

```
ci(phpunit): wnjpn.db の分割zip結合ステップを全ジョブに追加

CI で SynonymService が SQLite 接続エラーになる問題を修正。
database/wordnet_data/ 配下の分割zipを結合・解凍する。

Closes #74
```

## Why Not `git commit -m`?

Shell expands `$var`, `` `cmd` ``, `(subshell)`, `!history` inside `-m "..."`.
Japanese multi-byte characters depend on terminal locale — unreliable in non-UTF-8 environments.

## Known Failure Cases (do not repeat)

### printf → newlines collapsed (Sprint 8/9 violation)

```bash
# ❌ This was used in Sprint 8/9 — body becomes one line in git log
printf 'fix(test): subject\n\nBody line 1.\nBody line 2.\n' > /tmp/commit_msg.txt
git commit -F /tmp/commit_msg.txt
# Result in git log: "fix(test): subject ## Body line 1. Body line 2."
```

### heredoc → same collapse when piped

```bash
# ❌ Also produces collapsed single-line body
cat > /tmp/commit_msg.txt << 'EOF'
fix(test): subject

Body line.
EOF
```

### Correct method

```python
# ✅ create_file tool writes /tmp/mk_commit_msg.py, then:
msg = "fix(test): subject\n\nBody line 1.\nBody line 2.\n\nCloses #74\n"
open('/tmp/commit_msg.txt', 'w', encoding='utf-8').write(msg)
```

## .gitignore Conflict Note

If `.gitignore` has `!/database/wordnet_data/wnjpn.db` (force-include) AND
`/database/wordnet_data/wnjpn.db` (exclude), the force-include wins.
Fix: remove the `!` line, add explicit exclude at end of file.

