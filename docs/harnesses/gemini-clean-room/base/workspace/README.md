# Workspace directory

このディレクトリは、**Gemini CLI を実行する current working directory** を表します。

## 置いてよいもの

- sanitize 済みの `.gemini/settings.json`
- 評価のために明示的に staged した `GEMINI.md`
- 評価対象として明示した generated skill pack
- その run に必要な最小ファイルだけ

## 置いてはいけないもの

- 開発 repo の `.github` 一式
- 開発用 `.gemini/GEMINI.md`
- 開発用 `.gemini/skills/`
- dev repo の subtree copy
- neutral parent を壊す余分な repo / `.env`

## 次の手順

- `.gemini/settings.clean-room.template.jsonc` を `settings.json` へ複製
- `mcp-remote` の第2引数に tenant を解決できる MCP endpoint URL を設定
- `env.AUTH_HEADER` に `Bearer <token>` 形式で bearer token を設定
- platform note に従って `GEMINI_CLI_HOME` を別ディレクトリへ設定

