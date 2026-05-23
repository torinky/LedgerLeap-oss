# Forbidden artifacts for Gemini clean-room

次は clean-room harness に **持ち込まない** ことを前提にします。

| artifact | 禁止理由 | 典型例 |
|---|---|---|
| 開発 repo の `.gemini/GEMINI.md` | repo 固有の開発コンテキストが強すぎる | `LedgerLeap/.gemini/GEMINI.md` をそのままコピー |
| 開発 repo の `.gemini/skills/` / `.agents/skills/` | workspace skill discovery で即時混入しうる | `LedgerLeap/.gemini/skills/*` の丸ごとコピー |
| 開発 repo の `.gemini/settings.json` | dev repo absolute path / includeDirectories / MCP 設定を含みうる | `cd /Users/.../LedgerLeap && ...` を含む設定 |
| 開発用 `~/.gemini` / `~/.agents` | user-level context / skills / sessions / trust が残る | 既存 home state の再利用 |
| parent directory 上の `GEMINI.md` / `.env` / 別 repo | 上方向探索で混入しうる | `C:\Users\...` 配下や `~/work` 配下の既存 context |
| harness root 配下に同居させた開発 repo コピー | subdirectory discovery の contamination source になる | `workspace/dev-repo-copy/...` |
| `.github` 一式 | 開発者向け SoT であり、初回 user-like 評価を汚す | `.github/instructions`, `.github/skills`, `.github/prompts` |
| 開発用 session / shell history / trustedFolders | 過去の使用履歴・承認状態が混ざる | 既存 `~/.gemini/tmp/*`, `trustedFolders.json` |
| 未 sanitize の `.env` | 認証情報・評価と無関係な env が混ざる | home `.env`, repo parent `.env` |

## 特に注意すること

### 1. 「一部だけだから安全」は成立しない
repo subtree であっても、`.gemini/settings.json` や `.gemini/skills/` を含めば clean-room ではありません。

### 2. neutral parent が必要
copy 先の親が home 配下や別 repo 配下だと、公式 docs 上の parent discovery と衝突します。

### 3. `GEMINI_CLI_HOME` 未分離は no-go
workspace が clean でも、user-level state が残っていれば contaminated run です。

## no-go 判定

次のいずれかを満たしたら **clean-room 実施として扱わない** ことを推奨します。

- `GEMINI_CLI_HOME` を分離していない
- `workspace/.gemini/settings.json` が dev repo absolute path を含む
- `/memory show` に想定外の `GEMINI.md` が含まれる
- `/skills list` に想定外の user skill / workspace skill が含まれる
- harness root の親または子に別 repo context が同居している

