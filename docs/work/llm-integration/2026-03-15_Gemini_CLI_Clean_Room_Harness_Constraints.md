# Gemini CLI clean-room harness constraints

**作成日:** 2026年03月15日  
**ドキュメント種別:** 作業ファイル（Issue #106: clean-room harness constraints）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#105](https://github.com/torinky/LedgerLeap/issues/105), [#106](https://github.com/torinky/LedgerLeap/issues/106), [#108](https://github.com/torinky/LedgerLeap/issues/108)

## Freshness

- `status`: draft-confirmed
- `last_confirmed_at`: 2026-03-15
- `recheck_after`: 90d
- `recheck_trigger`:
  - Gemini CLI の context / skills / settings / trust 周辺 docs が更新されたとき
  - `#105` の placement / delivery contract を更新するとき
  - `#108` の persona overlay を追加するとき

## 1. 目的

Issue `#106` で検討している **copy-based clean-room harness** が、Gemini CLI 公式 docs の挙動と両立するかを整理する。

この文書では次を固定する。

1. copy だけで足りるのか
2. `GEMINI_CLI_HOME` 分離がどこまで必要か
3. parent / subdirectory discovery が harness 設計に与える制約
4. Mac / Windows で neutral parent をどう考えるか

## 2. 確認した一次情報

確認元: `google-gemini/gemini-cli`

- `docs/cli/gemini-md.md`
- `docs/cli/skills.md`
- `docs/reference/configuration.md`
- `docs/cli/session-management.md`
- `docs/cli/trusted-folders.md`

## 3. 公式 docs から固定できる事実

### 3.1 context (`GEMINI.md`) は階層的に読み込まれる

公式 docs では、少なくとも次が確認できる。

- global context: `~/.gemini/GEMINI.md`
- workspace / parent discovery
- subdirectory discovery
- `/memory show` / `/memory reload` で loaded memory を確認可能

### 3.2 skills は workspace / user / extension tier で自動 discovery される

- workspace: `.gemini/skills/` / `.agents/skills/`
- user: `~/.gemini/skills/` / `~/.agents/skills/`
- precedence: Workspace > User > Extension

### 3.3 settings は user / project / env / CLI args の多層で上書きされる

- user settings: `~/.gemini/settings.json`
- project settings: `.gemini/settings.json`
- project settings は user settings より優先
- `context.includeDirectories`, `mcpServers`, `context.fileName` などで追加混入が起きうる

### 3.4 user-level state は `GEMINI_CLI_HOME` で分離できる

- user config / storage root を切り替えられる
- sessions / shell history / trusted folder state も user-level 側にある

### 3.5 trusted folders は補助だが default off

- untrusted workspace では local settings / `.env` / MCP / memory loading が制限される
- ただし feature 自体は default off

## 4. harness への設計制約

## 4.1 copy だけでは clean-room にならない

workspace copy だけでは、global `GEMINI.md`・user skills・user settings・sessions が残るため不十分である。

**結論:** `GEMINI_CLI_HOME` 分離が必須。

## 4.2 親ディレクトリを neutral にする必要がある

公式 docs では parent 方向の探索があるため、copy 先の親に別 repo / `GEMINI.md` / `.env` があると contamination source になる。

**結論:** home directory 外の neutral parent を推奨し、必要なら独立 `.git` boundary を置く。

## 4.3 子ディレクトリの同居物も contamination source になる

公式 docs では subdirectory discovery がある。

**結論:** harness root の下に dev repo copy や generated assets 一式を同居させない。評価対象として staged した artifact だけを置く。

## 4.4 開発用 `.gemini/settings.json` を持ち込まない

LedgerLeap の現行 `.gemini/settings.json` には dev repo absolute path を含む MCP 起動設定がある。

**結論:** clean-room では sanitized settings template を別途持つ。

## 5. go / no-go 条件

### Go

- copy 対象が curated harness root のみ
- `GEMINI_CLI_HOME` を run 専用に分離
- parent / child contamination を避ける
- settings を sanitize している
- `/memory show` と `/skills list` を evidence として残す

### No-Go

- dev repo subtree をそのままコピー
- 開発用 home state を流用
- `.github` や dev `.gemini/skills/` を同梱
- clean-room root の親または子に別 repo context を同居

## 6. Mac / Windows への落とし込み

### macOS

- 推奨 neutral parent: `/opt/...`, `/private/tmp/...`
- home (`/Users/...`) 配下は避ける
- `GEMINI_CLI_HOME` は評価 root の `gemini-home/` へ向ける

### Windows

- 推奨 neutral parent: `C:\ledgerleap-gemini-eval\...`, `D:\ledgerleap-gemini-eval\...`
- `%USERPROFILE%` 配下は避ける
- `GEMINI_CLI_HOME` は評価 root の `gemini-home\` へ向ける

## 7. この文書から導く成果物

- `docs/harnesses/gemini-clean-room/README.md`
- `docs/harnesses/gemini-clean-room/base/*`
- `docs/harnesses/gemini-clean-room/allowed-artifacts.md`
- `docs/harnesses/gemini-clean-room/forbidden-artifacts.md`
- `docs/harnesses/gemini-clean-room/evidence-template.md`
- `docs/harnesses/gemini-clean-room/platforms/macos.md`
- `docs/harnesses/gemini-clean-room/platforms/windows.md`

## 8. 次に渡すもの

- `#108`: persona overlay (`operator` first steps など)
- `#105`: clean-room 前提での placement / delivery 再評価

