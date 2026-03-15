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

> [!NOTE]
> `#109` の実装後、clean-room の `settings.json` は
> **`httpUrl` + `Authorization: Bearer ...`** を使う remote MCP を正本にします。
> local command MCP は比較用 fallback に留め、通常の clean-room 評価では使いません。

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

前提として、LedgerLeap 側の web app が起動しており、指定する host から
`/mcp/ledgerleap` に到達できる必要があります。

- `__LEDGERLEAP_MCP_HTTP_URL__` → `http://ledgerleap-eval.localhost/mcp/ledgerleap`
- `__LEDGERLEAP_MCP_BEARER_TOKEN__` → `mcp:*` ability を持つ Sanctum token

`httpUrl` の host は、LedgerLeap 側で tenant を解決できる値にしてください。
`localhost` をそのまま使うのではなく、tenant domain として登録済みの
`*.localhost` などを使う方が安全です。

token は既存の evaluation user 用 token でも構いませんが、少なくとも
`mcp:*` ability が必要です。demo user を使う場合は、次のコマンドで token を生成できます。

```zsh
cd "/Users/kazutaka/PhpstormProjects/LedgerLeap"
./vendor/bin/sail artisan demo:generate-mcp-token
```

置換後のイメージは次のとおりです。

```jsonc
{
  "mcpServers": {
    "ledgerleap-api": {
      "httpUrl": "http://ledgerleap-eval.localhost/mcp/ledgerleap",
      "headers": {
        "Authorization": "Bearer <replace-with-mcp-token>"
      }
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
- `httpUrl` が tenant domain を解決できる host を使っている
- bearer token が `mcp:*` ability を持つ
- 初回起動後に `/memory show` と `/skills list` を記録する


