# 日本語検索API利用ガイド

**最終更新:** 2025年10月5日

---

## 📋 概要

LedgerLeap検索APIは、日本語およびマルチバイト文字の検索をフルサポートしています。このガイドでは、日本語キーワードを使用した検索の最適な方法を説明します。

---

## 🎯 推奨方法: POSTメソッドを使用

日本語キーワードを含む検索には、**POSTメソッド**の使用を強く推奨します。POSTメソッドではリクエストボディにJSONを送信するため、URLエンコードの問題を回避できます。

### エンドポイント

```
POST /api/v1/search
```

### リクエスト例

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Host: demo-tenant.localhost" \
  -d '{
    "q": "株式会社",
    "tags": "重要,新規",
    "limit": 10,
    "offset": 0
  }'
```

### Python例

```python
import requests

response = requests.post(
    "http://localhost/api/v1/search",
    headers={
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "Host": "demo-tenant.localhost"
    },
    json={
        "q": "株式会社",
        "tags": "重要,新規",
        "limit": 10
    }
)

result = response.json()
print(f"見つかった件数: {result['meta']['total']}")
```

### JavaScript例

```javascript
const response = await fetch('http://localhost/api/v1/search', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Host': 'demo-tenant.localhost'
  },
  body: JSON.stringify({
    q: '株式会社',
    tags: '重要,新規',
    limit: 10
  })
});

const result = await response.json();
console.log(`見つかった件数: ${result.meta.total}`);
```

---

## 🔧 代替方法: GETメソッドを使用（URLエンコード必須）

GETメソッドを使用する場合は、日本語キーワードを**必ずURLエンコード**してください。

### エンドポイント

```
GET /api/v1/search
```

### 正しい例（URLエンコード済み）

```bash
# curlの--data-urlencode オプションを使用（推奨）
curl -G --data-urlencode "q=株式会社" \
  --data-urlencode "tags=重要,新規" \
  --data-urlencode "limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search"

# または、手動でURLエンコード
curl "http://localhost/api/v1/search?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Host: demo-tenant.localhost"
```

### ❌ 誤った例（動作しません）

```bash
# URLエンコードせずに日本語を送信
curl "http://localhost/api/v1/search?q=株式会社&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
# → HTTP 52エラー: Empty reply from server
```

---

## 📚 検索パラメータ

| パラメータ | 型 | 説明 | 日本語対応 |
|----------|---|------|-----------|
| `q` | string | 全文検索キーワード | ✅ Yes |
| `tags` | string | タグ名（カンマ区切り、AND条件） | ✅ Yes |
| `exclude_q` | string | 除外キーワード | ✅ Yes |
| `exclude_tags` | string | 除外タグ（カンマ区切り） | ✅ Yes |
| `folder_id` | integer | フォルダID（再帰検索） | - |
| `ledger_define_id` | integer | 台帳定義ID | - |
| `creator_id` | integer | 作成者ユーザーID | - |
| `created_from` | string | 作成日開始（YYYY-MM-DD） | - |
| `created_to` | string | 作成日終了（YYYY-MM-DD） | - |
| `mode` | string | `search` または `count` | - |
| `limit` | integer | 取得件数（最大100） | - |
| `offset` | integer | スキップ件数（ページネーション） | - |

---

## 🔍 検索例

### 1. シンプルな全文検索

```bash
# POSTメソッド（推奨）
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"q": "営業日報", "limit": 5}'
```

**レスポンス:**
```json
{
  "data": [
    {
      "id": 58,
      "define": {
        "id": 52,
        "name": "[DEMO] 営業日報",
        "description": null
      },
      "content": {
        "日付": "2025-10-01",
        "顧客名": "株式会社A商事",
        ...
      },
      ...
    }
  ],
  "meta": {
    "total": 7,
    "limit": 5,
    "offset": 0
  }
}
```

### 2. タグでフィルタリング

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "q": "株式会社",
    "tags": "重要,新規",
    "limit": 10
  }'
```

### 3. 除外検索

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "q": "株式会社",
    "exclude_tags": "見送り",
    "limit": 10
  }'
```

### 4. 日付範囲検索

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "q": "システム",
    "created_from": "2025-10-01",
    "created_to": "2025-10-05",
    "limit": 10
  }'
```

### 5. 件数のみ取得

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "q": "株式会社",
    "mode": "count"
  }'
```

**レスポンス:**
```json
{
  "meta": {
    "total": 7
  }
}
```

---

## 🤖 MCPツールでの使用

LLMがMCPツール `search_ledgers` を使用する場合、以下の点に注意してください：

### MCPツールの説明（LLMが自動的に理解）

```markdown
Search for ledgers based on various criteria. 

**Important for Japanese/Multi-byte Keywords:**
- The 'q', 'tags', 'exclude_q', and 'exclude_tags' parameters support Japanese and other multi-byte characters
- When using these parameters, ensure they are properly passed as-is (the MCP protocol handles encoding automatically)
- Examples of valid Japanese keywords: "株式会社", "営業日報", "重要案件"
```

### LLMからの使用例

LLMは以下のように自然に日本語キーワードを使用できます：

```python
# MCP経由（自動処理）
result = mcp_client.call_tool(
    "search_ledgers",
    {
        "q": "株式会社A商事",
        "tags": "重要",
        "limit": 5
    }
)
```

MCPプロトコルは内部でHTTP POSTメソッドを使用するため、日本語の取り扱いは自動的に適切に処理されます。

---

## ⚠️ トラブルシューティング

### 問題: HTTP 52エラー（Empty reply from server）

**原因:** GETメソッドで日本語キーワードをURLエンコードせずに送信しています。

**解決策:**
1. **POSTメソッドに切り替える**（推奨）
2. または、curlの`--data-urlencode`オプションを使用
3. または、手動でURLエンコードを行う

### 問題: 検索結果が0件

**原因1:** Mroonga全文検索インデックスが更新されていない可能性があります。

**解決策:**
```bash
# データ作成後、わずかな待機時間を設ける
sleep 1
```

**原因2:** キーワードがデータに含まれていない。

**解決策:** 実際のデータ内容を確認してください。

### 問題: 文字化け

**原因:** Content-Typeヘッダーが正しく設定されていません。

**解決策:** POSTメソッドの場合、必ず`Content-Type: application/json`を指定してください。

---

## 📊 パフォーマンス

日本語全文検索のパフォーマンス（実測値）:

| 処理 | 所要時間 |
|------|---------|
| フォルダ権限チェック | 0.5-27ms |
| 全文検索実行 | 1-4ms |
| 件数カウント | 4-114ms |
| データ取得 | 7-9ms |
| リレーション読み込み | 18-20ms |
| **合計** | **60-160ms** |

全文検索を含む複雑なクエリでも、約150ms程度で完了します。

---

## 🔐 認証

すべての検索APIリクエストには、Sanctumトークンによる認証が必要です。

```bash
Authorization: Bearer YOUR_TOKEN
```

トークンは、ユーザー管理画面またはCLIで発行できます：

```bash
./vendor/bin/sail artisan tinker --execute="
\$user = App\Models\User::where('email', 'your-email@example.com')->first();
\$token = \$user->createToken('api-token');
echo 'Token: ' . \$token->plainTextToken . PHP_EOL;
"
```

---

## 📖 関連ドキュメント

- [MCP Architecture and Flow](../development/MCP_Architecture_and_Flow.md)
- [MCP Search Debug Report](../work/MCP_SEARCH_DEBUG_REPORT_2025-10-05.md)
- [Testing Best Practices](../development/Testing-Best-Practices.md)

---

**最終更新:** 2025年10月5日  
**バージョン:** 1.0
