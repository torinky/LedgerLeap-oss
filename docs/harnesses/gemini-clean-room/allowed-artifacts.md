# Allowed artifacts for Gemini clean-room

clean-room harness に **持ち込んでよいものだけ** を列挙します。

| artifact | 許可 | 理由 | 注意点 |
|---|---|---|---|
| `base/workspace/.gemini/settings.json` に複製した **sanitized settings** | ✅ | project-level settings は必要だが、dev repo 依存を除去する必要がある | `includeDirectories`, dev repo absolute path, 開発用 MCP alias は持ち込まない |
| `base/gemini-home/` に作る **専用 `GEMINI_CLI_HOME`** | ✅ | user-level state 分離が必須 | 開発用 `~/.gemini` をコピーしない |
| 評価専用に curated した `GEMINI.md` | ✅ | clean-room で明示的に評価したい場合のみ | dev repo の `.gemini/GEMINI.md` を再利用しない |
| 評価対象として明示的に staged した generated skill pack | ✅ | `#105` placement / delivery 論点の比較対象に必要 | source-of-truth ではなく generated artifact と明示する |
| `evidence/` 配下の比較メモ・スクリーンショット・観測結果 | ✅ | contaminated / clean-room 差分の記録 | runtime discovery 対象の下層へ置かない |
| neutral parent 配下の独立 `.git` boundary | ✅ | parent 探索境界を制御しやすい | `.git` の中身自体は評価対象にしない |
| platform note に従って設定した `GEMINI_CLI_HOME` 環境変数 | ✅ | user-level settings / sessions / skills / trust 分離に必要 | shell profile 永続化は必須ではない |

## 許可するが、記録が必要なもの

次は持ち込み可能ですが、`evidence-template.md` に記録してください。

- 評価用 `settings.json` の `mcpServers` 設定
- staged skill pack を置いた具体的な相対パス
- `workspace/.gemini/GEMINI.md` を置いたかどうか
- `/memory show` の loaded context 概要
- `/skills list` の discovered skills 概要

## 原則

1. **持ち込んだ理由を説明できるものだけ置く**
2. **generated artifact は SoT と混同しない**
3. **比較対象に必要なものだけを最小で staged する**

