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
> Antigravity/Gemini CLI の `mcp_config.json` / `settings.json` は stdio サーバーのみをサポートしています。
> そのため、remote MCP (HTTP/SSE) を直接 `httpUrl` で指定することはできません。
> 代わりに **`mcp-remote`** パッケージをプロキシとして使用します。
> これには Node.js (npx) のインストールが必要です。

## 必須ソフトウェア (Windows)

`mcp-remote` を動かすために Node.js が必要です。

1.  **Node.js のインストール**
    *   [nodejs.org](https://nodejs.org/) から LTS 版をダウンロードしてインストールする
    *   または `winget` コマンドを使用: `winget install OpenJS.NodeJS.LTS`
2.  **npx の確認**
    *   PowerShell で `npx --version` を実行してバージョンが表示されることを確認してください。

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

前提として、LedgerLeap 側の web app が起動しており、指定する URL から
MCP endpoint に到達できる必要があります。

- `__LEDGERLEAP_MCP_HTTP_URL__` → `http://localhost/tenant-a/mcp/ledgerleap`
- `__LEDGERLEAP_MCP_BEARER_TOKEN__` → `mcp:*` ability を持つ Sanctum token

推奨は path-based tenant URL です。

- 推奨: `http://localhost/{tenant}/mcp/ledgerleap`
- 互換: `http://tenant-name.localhost/mcp/ledgerleap`

path-based でも subdomain-based でも、token 利用者にその tenant へのアクセス権が必要です。

token は既存の evaluation user 用 token でも構いませんが、少なくとも
`mcp:*` ability が必要です。demo user を使う場合は、次のコマンドで token を生成できます。

```powershell
Set-Location "C:\path\to\LedgerLeap"
.\vendor\bin\sail artisan demo:generate-mcp-token
```

任意ユーザーへ発行する場合:

```powershell
Set-Location "C:\path\to\LedgerLeap"
.\vendor\bin\sail artisan tinker
```

```php
$user = App\Models\User::where('email', 'operator@example.com')->firstOrFail();
$token = $user->createToken('mcp-client', ['mcp:*']);
$token->plainTextToken;
```

置換後のイメージは次のとおりです。

```jsonc
{
  "mcpServers": {
    "ledgerleap-api": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://localhost/tenant-a/mcp/ledgerleap",
        "--header",
        "Authorization:${AUTH_HEADER}"
      ],
      "env": {
        "AUTH_HEADER": "Bearer <replace-with-mcp-token>"
      }
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
- `httpUrl` が対象 tenant を正しく表現している（推奨: `/{tenant}/mcp/ledgerleap`）
- bearer token が `mcp:*` ability を持つ
- 初回起動後に `/memory show` と `/skills list` を記録する


