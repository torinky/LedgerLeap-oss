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

## .gitignore Conflict Note

If `.gitignore` has `!/database/wordnet_data/wnjpn.db` (force-include) AND
`/database/wordnet_data/wnjpn.db` (exclude), the force-include wins.
Fix: remove the `!` line, add explicit exclude at end of file.

