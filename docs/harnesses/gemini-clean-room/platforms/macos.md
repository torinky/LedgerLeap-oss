# macOS placement notes

## 推奨配置先

home directory (`/Users/<name>/...`) の外に置きます。

候補:
- `/opt/ledgerleap-gemini-eval/issue-106/`
- `/private/tmp/ledgerleap-gemini-eval/issue-106/`

`/opt/...` が書けない場合は `/private/tmp/...` でもよいですが、親ディレクトリに余計な `GEMINI.md` や `.env` を置かないでください。

## 推奨構成

```text
/opt/ledgerleap-gemini-eval/issue-106/
  .git/
  workspace/
  gemini-home/
  evidence/
```

## 参考コマンド例

以下は `base/` を `/private/tmp` 配下へコピーして準備する例です。

```zsh
mkdir -p "/private/tmp/ledgerleap-gemini-eval/issue-106"
cp -R "/Users/kazutaka/PhpstormProjects/LedgerLeap/docs/harnesses/gemini-clean-room/base/." "/private/tmp/ledgerleap-gemini-eval/issue-106/"
cp "/private/tmp/ledgerleap-gemini-eval/issue-106/workspace/.gemini/settings.clean-room.template.jsonc" "/private/tmp/ledgerleap-gemini-eval/issue-106/workspace/.gemini/settings.json"
export GEMINI_CLI_HOME="/private/tmp/ledgerleap-gemini-eval/issue-106/gemini-home"
cd "/private/tmp/ledgerleap-gemini-eval/issue-106/workspace"
```

必要なら親探索の境界を切るために独立 `.git` を作ります。

```zsh
cd "/private/tmp/ledgerleap-gemini-eval/issue-106"
git init
```

## 例: 環境変数

```zsh
export GEMINI_CLI_HOME="/opt/ledgerleap-gemini-eval/issue-106/gemini-home"
cd "/opt/ledgerleap-gemini-eval/issue-106/workspace"
```

## `settings.json` placeholder の埋め方例

`workspace/.gemini/settings.json` では、たとえば次のように置き換えます。

- `__LEDGERLEAP_MCP_COMMAND__` → `/bin/sh`
- `__LEDGERLEAP_MCP_ARGS__` → `[
  "-c",
  "cd /absolute/path/to/LedgerLeap && ./vendor/bin/sail artisan mcp:start ledgerleap:mcp"
]`
- `__LEDGERLEAP_MCP_CWD__` → `/absolute/path/to/LedgerLeap`

置換後のイメージは次のとおりです。

```jsonc
{
  "mcpServers": {
    "ledgerleap-api": {
      "command": "/bin/sh",
      "args": [
        "-c",
        "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && ./vendor/bin/sail artisan mcp:start ledgerleap:mcp"
      ],
      "cwd": "/Users/kazutaka/PhpstormProjects/LedgerLeap"
    }
  }
}
```

準備後は `workspace/` で Gemini CLI を起動し、少なくとも次を確認します。

```text
/memory show
/skills list
```

## 実施前チェック

- `workspace/` の親が home 配下ではない
- `GEMINI_CLI_HOME` が開発用 `~/.gemini` と別
- `workspace/.gemini/settings.json` に dev 用の不要な MCP / includeDirectories がない
- 初回起動後に `/memory show` と `/skills list` を記録する


