# Connecting Local LLMs to LedgerLeap MCP

**対象:** LM Studio / Ollama を LLM バックエンドとし、OpenCode / Continue.dev / OpenClaw から
LedgerLeap MCP サーバーに接続する運用者向け

> **アーキテクチャ:** LedgerLeap MCP サーバーは **HTTP ベースのリモート MCP** を主契約としています。
> クライアントは `mcp-remote` ブリッジ経由でサーバーに接続します。
> ローカルでの `php artisan mcp:serve` 実行は補助的な手段です（[後述](#appendix-local-command-execution-auxiliary)）。

## 1. 構成概要

```
[LM Studio / Ollama (LLM)]
        ↑↓ API
[OpenCode / Continue.dev / OpenClaw (MCP クライアント)]
        ↑↓ stdio
[mcp-remote ブリッジ (npx)]
        ↑↓ HTTP + Bearer token
[LedgerLeap MCP サーバー (Mcp::web)]
```

MCP ツールの応答は LLM のコンテキストに読み込まれます。ローカル LLM のコンテキスト
ウィンドウは限られているため、大きすぎるツール応答でクラッシュが発生することがあります。

LedgerLeap の Sprint 1-2 (#212, #213) で以下の対策が実装済みです：
- `SearchLedgersTool`: `include_content` / `include_meta` / `include_attachment_payloads` / `include_trace` がデフォルト `false`
- `GetLedgerDefinesTool`: デフォルト 20 件・コンパクトJSON
- 全 MCP ツールに 32KB 安全網（`TruncatableResponse`）

## 2. 共通: アクセストークンの発行

リモート MCP 接続には Sanctum の Personal Access Token が必要です。

1. LedgerLeap にログイン
2. 設定 → API トークン からトークンを発行
3. 必要な ability: `mcp:read`, `mcp:write`（用途に応じて選択）
4. 発行されたトークンを安全に保管

## 3. LM Studio 推奨設定

LM Studio のモデル設定画面で以下を設定してください：

| 設定項目 | 推奨値 | 説明 |
|---|---|---|
| Context Length | 32768 以上（推奨 65536） | ツール結果を含めた会話全体のトークン予算 |
| GPU Layers | 全レイヤーを GPU オフロード | VRAM に余裕があれば最大値。VRAM 不足時は調整 |
| contextOverflowPolicy | `stopAtLimit` | コンテキスト超過時にクラッシュさせず安全停止 |

### LM Studio API の `allowed_tools` 設定

LM Studio の API リクエストに `allowed_tools` を指定することで、
特定のリクエストで呼び出せるツールを制限できます（LLM に渡すスキーマ定義を削減）。
クライアントアプリケーションがこのフィールドに対応している場合に有効です：

```json
{
  "allowed_tools": [
    "SearchLedgersTool",
    "GetLedgerDetailTool",
    "GetLedgerDefinesTool",
    "GetFoldersTool",
    "GetTagsTool"
  ]
}
```

## 4. クラッシュ原因の特定手順

### 4.1 Context Overflow の確認

1. LM Studio の画面下部で context 使用率（%）を確認
2. クラッシュ時に 100% 付近なら context overflow が原因
3. 対処: Context Length を引き上げる（VRAM が許す範囲で）

### 4.2 VRAM 不足の確認

```bash
nvidia-smi
```

VRAM 使用量が GPU 搭載量の 90% を超えている場合は VRAM 不足の可能性が高い。
対処: GPU Layers を下げる、またはモデルの量子化レベルを下げる。

### 4.3 再現確認

`contextLength` を明示的に低い値（例: 4096）に設定して再現確認することで、
context overflow と VRAM 不足を確実に切り分けられます。
低い contextLength で必ずクラッシュ → context overflow が主原因。
低い contextLength でもクラッシュしない → VRAM 不足または別の原因。

## 5. OpenCode での設定例

### 5.1 OpenCode native remote MCP 設定（推奨）

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "ledgerleap": {
      "type": "remote",
      "url": "http://localhost/tenant-a/mcp/ledgerleap",
      "oauth": false,
      "headers": {
        "Authorization": "Bearer {env:LEDGERLEAP_MCP_TOKEN}"
      }
    }
  }
}
```

OpenCode の current docs では remote MCP を直接扱えるため、OpenCode 単体では
`mcp-remote` ブリッジは必須ではありません。LedgerLeap の token は
`LEDGERLEAP_MCP_TOKEN` 環境変数で渡してください。

### 5.2 publication packet harness（#219 / doc packet trial）

Issue #219 の packet trial を OpenCode で行う場合は、
`docs/harnesses/doc-publication-packet/opencode-config.template.jsonc` をコピーして
使うのが最短です。

1. `opencode-config.template.jsonc` をローカル用ファイルへコピー
2. `__LM_STUDIO_MODEL_ID__`, `__TENANT_SLUG__`, `LEDGERLEAP_MCP_TOKEN` を置換
3. リポジトリ直下で次のように起動

```bash
OPENCODE_CONFIG=/absolute/path/to/opencode-config.local.jsonc \
opencode -m ledgerleap-lmstudio/<model-id>
```

この overlay は repo 既定の `opencode.json` を壊さず、packet trial だけ
LM Studio + remote MCP + `plan` default agent へ切り替えます。

### 5.3 システムプロンプト経由のパラメータ指示

MCP サーバーの `instructions` は LLM に自動的に渡されますが、
より明示的な指示が必要な場合はシステムプロンプトに追記してください：

```
LedgerLeap MCP ツールを使用する際のルール:
- SearchLedgersTool では必ず include_content=false, include_meta=false, limit=5 を指定
- 最初に mode=count で件数を確認してから検索
- 詳細な内容は GetLedgerDetailTool で個別に取得
- include_attachment_payloads=true は明示的に要求された場合のみ使用
```

## 6. Continue.dev での設定例

### 6.1 mcp-remote 経由のリモート接続（推奨）

`.continue/mcpServers/ledgerleap.yaml` を作成：

```yaml
name: ledgerleap
command: npx
args:
  - "-y"
  - "mcp-remote"
  - "https://your-server.example.com/mcp/ledgerleap"
  - "--header"
  - "Authorization:Bearer ${LEDGERLEAP_TOKEN}"
env:
  LEDGERLEAP_TOKEN: your-sanctum-token-here
connectionTimeout: 10000
```

### 6.2 ⚠️ `defaultToolArgs` は非対応

Continue.dev の `.continue/mcpServers/*.yaml` で認められているフィールドは
`name`, `command`, `args`, `env`, `cwd`, `requestOptions`, `connectionTimeout` のみです。
**`defaultToolArgs` は使用しないでください。**（設定しても無視されます）

### 6.3 publication packet harness（#219 / doc packet trial）

Issue #219 の packet trial を Continue.dev で行う場合は、
`docs/harnesses/doc-publication-packet/continue-config.template.yaml` をそのまま
assistant config の雛形として使うのが最短です。

1. `continue-config.template.yaml` をローカル用ファイルへコピー
2. `__LM_STUDIO_MODEL_ID__`, `ABSOLUTE_PROJECT_PATH`, MCP URL, token placeholder を置換
3. `model` には LM Studio の `GET /v1/models` が返す実際の model id を入れる
4. lane 判定は `Plan` mode + `packet-plan`
5. rewrite は `Agent` mode + `packet-rewrite`
6. comment のみは `Agent` mode + `packet-comment-sync`

### 6.4 ✅ 代替: `.continue/rules/` ディレクトリ

プロジェクトルートに `.continue/rules/ledgerleap-local-llm.md` を作成してください：

```markdown
# LedgerLeap MCP ローカルLLM向けルール

MCPツールを呼び出す場合のルール:
- SearchLedgersTool: 必ず include_content=false, include_meta=false, limit=5 を使用
- 詳細は SearchLedgersTool の前に GetLedgerDetailTool で個別に取得
- 最初は mode=count で件数を確認してから検索する
- include_attachment_payloads=true は明示的に要求された場合のみ使用する
- GetLedgerDefinesTool: デフォルト（limit=20, include_options=false）のまま使用
```

## 7. OpenClaw での設定例

### 7.1 mcp-remote 経由のリモート接続（推奨）

```yaml
mcp_servers:
  ledgerleap:
    command: npx
    args:
      - "-y"
      - "mcp-remote"
      - "https://your-server.example.com/mcp/ledgerleap"
      - "--header"
      - "Authorization:Bearer ${LEDGERLEAP_TOKEN}"
    env:
      LEDGERLEAP_TOKEN: "your-sanctum-token-here"
```

### 7.2 システムプロンプト指示

```
LedgerLeap MCP を使用する際:
- SearchLedgersTool では include_content=false, include_meta=false, limit=5 を必ず指定
- 結果件数は mode=count で先に確認
- レコードの詳細は GetLedgerDetailTool で個別に取得
```

## 8. クライアント別トラブルシューティング

### 8.1 ツール呼び出し後に LLM が応答しなくなる

1. LM Studio の context 使用率を確認 → 100% なら context overflow
2. `nvidia-smi` で VRAM 使用量を確認 → 高負荷なら GPU Layers 調整
3. ツール呼び出しのパラメータを見直し:
   - `include_content=false` が指定されているか
   - `include_meta=false` が指定されているか
   - `limit` が 5 以下か

### 8.2 mcp-remote 接続エラー

1. サーバー URL が正しいか確認（末尾の `/mcp/ledgerleap` を含む）
2. Bearer トークンが有効か確認（`curl -H "Authorization: Bearer <token>" <url>` でテスト）
3. トークンの ability に `mcp:*` が含まれているか確認
4. `--header` の書式: コロンの直後にスペースを入れない（`Authorization:Bearer xxx`）

### 8.3 ツール定義が多すぎてコンテキストを消費する

LedgerLeap は 19 ツールを登録しています。LM Studio API の `allowed_tools` で
使用するツールを制限するか、クライアント側で必要なツールのみ有効化してください。

### 8.4 レスポンスに `__truncated__: true` が含まれる

32KB 安全網が作動しています。`__truncated_at__` で削除されたフィールドを確認し、
必要に応じて `GetLedgerDetailTool` で個別レコードの詳細を取得してください。

## 9. 関連ドキュメント

- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Response_Size_Reduction_Plan.md` — 実装計画詳細
- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Plan_Validity_Investigation.md` — 外部仕様調査レポート
- `docs/work/llm-integration/2026-03-15_Issue-109_Remote_MCP_Requirement_and_Transport_Options.md` — リモート MCP 要件
- `docs/work/llm-integration/2026-03-26_MCP_HTTP_Access_Troubleshooting.md` — mcp-remote 接続トラブルシューティング
- `docs/development/MCP_Architecture_and_Flow.md` — アーキテクチャ仕様

---

## Appendix: ローカルコマンド実行（補助的）

LedgerLeap の MCP サーバーはローカルでも `php artisan mcp:serve` で起動できますが、
これは開発・テスト用途の補助的な手段です。

```json
{
  "mcpServers": {
    "ledgerleap": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "env": {
        "MCP_AUTH_TOKEN": "your-token-here"
      }
    }
  }
}
```

ローカル実行では `MCP_AUTH_TOKEN` 環境変数による認証を使用します。
本番運用では上記の `mcp-remote` + HTTP + Bearer token を推奨します。
