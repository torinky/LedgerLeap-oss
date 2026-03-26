# MCP HTTPアクセスに関する調査とトラブルシューティング

**作成日:** 2026年03月26日  
**ステータス:** 調査結果・検討材料  
**関連Issue:** #109 (Remote MCP), Antigravity設定エラー

## 1. 概要

本ドキュメントでは、MCPクライアントからリモートのHTTPベースMCPサーバーに接続する際の課題、特に「httpUrlプロパティが許可されない」エラーへの対応と、`mcp-remote`ブリッジの活用についてまとめる。

## 2. `httpUrl` プロパティの課題

多くのMCPクライアント実装（初期のGemini CLI、Home Assistant、Qwen等）では、`mcpServers`設定内でリモートエンドポイントを指定するために `httpUrl` や `url` プロパティが有効である。

しかし、**Antigravity**（およびIDEに統合された現行のGemini CLI）の `mcp_config.json` スキーマでは、現在 **stdio形式のトランスポートのみ** が厳格に適用されている可能性が高い。これにより、以下のバリデーションエラーが発生する：
- `Property httpUrl is not allowed`
- `Property url is not allowed`

### 検証済みの修正方法
クライアントがstdioのみをサポートしている環境では、`mcp-remote` などのプロキシ/ブリッジユーティリティを介して接続する。

```json
"ledgerleap-web-api": {
  "command": "npx",
  "args": [
    "-y",
    "mcp-remote",
    "https://your-server.com/mcp/ledgerleap",
    "--header",
    "Authorization:Bearer ${AUTH_TOKEN}"
  ],
  "env": {
    "AUTH_TOKEN": "your-token-here"
  }
}
```

## 3. コミュニティの知見 (GitHub/Web調査)

### トランスポート戦略
`mcp-remote` は、サーバー側の能力に合わせて複数のトランスポート戦略をサポートしている：
- `http-first` (デフォルト): 標準のHTTP POSTを優先的に試行。
- `sse-only`: Server-Sent Events (SSE) を強制。
- `enable-proxy`: 企業内プロキシ等を経由する必要がある場合に指定。

### 設定キーのバリエーション
コミュニティ主導のMCPツールでは、同等の目的で異なるキーが使われているケースがある。今後のスキル開発ではこれらに注意が必要である：
- `httpUrl` (Qwen, Home Assistant)
- `baseUrl` (Promptly)
- `endpoint` (一部のSDK)
- `uri` (ModelContextProtocol公式例)

## 4. セキュリティとヘッダー解析の注意点

### 引数内のスペース
IDEベースのMCP設定（`mcp_config.json`等）において、`args` 内のスペースの扱いがトラブルの原因になりやすい。
- **NG**: `"args": ["mcp-remote", "...", "-H", "Authorization: Bearer ${TOKEN}"]`
- **OK**: `"args": ["mcp-remote", "...", "--header", "Authorization:Bearer ${TOKEN}"]` （シェルパーサーの感度が高い場合、文字列内のコロン直後にスペースを入れない形式が安定する）

### トークン管理
`args` に直接シークレットをハードコードせず、`env` 変数経由で注入することで、リモートサーバーに安全に `Authorization` ヘッダーを渡す方法が推奨される。

## 5. 今後のスキル・検討事項

「リモートMCPオンボーディング」スキルなどの構築に向けた検討材料：
1.  現在のクライアント環境が stdio 限定かどうかを自動判定する。
2.  `mcp-remote` 設定を自動提案または生成する。
3.  `list_tools` チェック等を用いて接続確認を自動化する。
