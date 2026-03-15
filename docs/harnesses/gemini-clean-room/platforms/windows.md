# Windows placement notes

## 推奨配置先

user profile (`C:\Users\<name>\...`) の外に置きます。

候補:
- `C:\ledgerleap-gemini-eval\issue-106\`
- `D:\ledgerleap-gemini-eval\issue-106\`

評価専用ドライブやルート直下の neutral parent を使い、親ディレクトリに別 repo や `.env` を同居させないことを推奨します。

## 推奨構成

```text
C:\ledgerleap-gemini-eval\issue-106\
  .git\
  workspace\
  gemini-home\
  evidence\
```

> [!NOTE]
> 現時点の `settings.json` は **`command` ベースの local MCP** を使います。
> `httpUrl: http://localhost/...` で remote-like evaluation を行う案は、
> `routes/ai.php` の web transport と MCP 認証モデルの整理が必要なため、
> follow-up Issue [`#109`](https://github.com/torinky/LedgerLeap/issues/109) で扱います。

## 参考コマンド例

以下は `base/` を `C:\ledgerleap-gemini-eval\issue-106\` 配下へコピーして準備する例です。

```powershell
New-Item -ItemType Directory -Force "C:\ledgerleap-gemini-eval\issue-106" | Out-Null
Copy-Item -Recurse "C:\path\to\LedgerLeap\docs\harnesses\gemini-clean-room\base\*" "C:\ledgerleap-gemini-eval\issue-106\"
Copy-Item "C:\ledgerleap-gemini-eval\issue-106\workspace\.gemini\settings.clean-room.template.jsonc" "C:\ledgerleap-gemini-eval\issue-106\workspace\.gemini\settings.json"
$env:GEMINI_CLI_HOME = "C:\ledgerleap-gemini-eval\issue-106\gemini-home"
Set-Location "C:\ledgerleap-gemini-eval\issue-106\workspace"
```

必要なら親探索の境界を切るために独立 `.git` を作ります。

```powershell
Set-Location "C:\ledgerleap-gemini-eval\issue-106"
git init
```

## 例: 環境変数

```powershell
$env:GEMINI_CLI_HOME = "C:\ledgerleap-gemini-eval\issue-106\gemini-home"
Set-Location "C:\ledgerleap-gemini-eval\issue-106\workspace"
```

## `settings.json` placeholder の埋め方例

`workspace/.gemini/settings.json` では、たとえば次のように置き換えます。

- `__LEDGERLEAP_MCP_COMMAND__` → `cmd`
- `__LEDGERLEAP_MCP_ARGS__` → `[
  "/c",
  "cd /d C:\\absolute\\path\\to\\LedgerLeap && .\\vendor\\bin\\sail artisan mcp:start ledgerleap:mcp"
]`
- `__LEDGERLEAP_MCP_CWD__` → `C:\absolute\path\to\LedgerLeap`

PowerShell で直接起動したい場合は、project ごとの quoting を壊さない形に調整してください。

置換後のイメージは次のとおりです。

```jsonc
{
  "mcpServers": {
    "ledgerleap-api": {
      "command": "cmd",
      "args": [
        "/c",
        "cd /d C:\\absolute\\path\\to\\LedgerLeap && .\\vendor\\bin\\sail artisan mcp:start ledgerleap:mcp"
      ],
      "cwd": "C:\\absolute\\path\\to\\LedgerLeap"
    }
  }
}
```

準備後は `workspace\` で Gemini CLI を起動し、少なくとも次を確認します。

```text
/memory show
/skills list
```

## 実施前チェック

- `workspace\` の親が `%USERPROFILE%` 配下ではない
- `GEMINI_CLI_HOME` が既存 user home と別
- `workspace\.gemini\settings.json` に dev 用 absolute path や余分な includeDirectories がない
- 初回起動後に `/memory show` と `/skills list` を記録する


