# Search API

## 概要

`/api/v1/search` は、LedgerLeap の台帳を REST API から検索するためのエンドポイントです。  
キーワード検索、タグ指定、作成者指定、日付範囲、件数のみ取得を 1 つの検索面で扱えます。

このページは **HTTP の Search API** を対象にしています。  
MCP の検索ツールは別契約なので、同じ検索ロジックを利用していてもレスポンス形式と利用手順は分けて扱ってください。

## 契約面

| 項目 | 内容 |
|---|---|
| 認証 | Sanctum Bearer token |
| エンドポイント | `GET /api/v1/search`, `POST /api/v1/search` |
| 主な用途 | 台帳の全文検索、条件検索、件数確認 |
| 正本 | [OpenAPI Specification (JSON)](openapi.json) |
| 日本語検索 | `POST /api/v1/search` を推奨 |

### メソッドの使い分け

- **GET**: 単純な検索やブラウザ・既存ツールからの呼び出し向け
- **POST**: 日本語やマルチバイト文字を含む検索、JSON ボディで条件を渡したい場合

## パラメータ

| パラメータ | 型 | 説明 |
|---|---|---|
| `q` | string | 全文検索キーワード |
| `tags` | string | カンマ区切りのタグ名 |
| `folder_id` | integer | 指定フォルダ配下を再帰検索 |
| `ledger_define_id` | integer | 台帳定義 ID で絞り込み |
| `creator_id` | integer | 作成者ユーザー ID で絞り込み |
| `exclude_q` | string | 除外キーワード |
| `exclude_tags` | string | 除外タグ |
| `created_from` | date | 作成日の開始日 |
| `created_to` | date | 作成日の終了日 |
| `mode` | string | `search` または `count` |
| `limit` | integer | 取得件数 |
| `offset` | integer | 取得開始位置 |

### 補足

- `mode=count` を指定すると、件数確認だけを軽く行えます
- `GET` で日本語を使う場合は URL エンコードが必要です
- `POST` では JSON ボディでそのまま日本語を送れます

## レスポンス

### 通常検索

通常の検索では `data` と `meta` を返します。

```json
{
  "data": [
    {
      "id": 58,
      "define": {
        "id": 52,
        "name": "[DEMO] 営業日報"
      }
    }
  ],
  "meta": {
    "total": 7,
    "limit": 10,
    "offset": 0
  }
}
```

### 件数のみ取得

`mode=count` のときは `meta.total` のみを返します。

```json
{
  "meta": {
    "total": 7
  }
}
```

## 制約

- 検索結果は **認証済みユーザーが閲覧可能なフォルダ** に自動で絞り込まれます
- 権限のないフォルダを指定しても、REST Search API は 403 ではなく **0 件** を返すことがあります
- このページは REST API の説明です。MCP の `SearchLedgersTool` は別のレスポンス契約を持ちます
- デバッグログの有無は公開契約ではありません

## 例

### POST で日本語検索する

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

### GET で件数だけ確認する

```bash
curl -G http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --data-urlencode "q=営業日報" \
  --data-urlencode "mode=count"
```

## 失敗時の扱い

| ステータス | 代表例 |
|---|---|
| `401` | Bearer token が無い、または無効 |
| `422` | パラメータ形式が不正 |
| `200` + 0 件 | 検索条件に一致しない、または閲覧権限の範囲外 |

## 関連ソース

- `routes/api.php`
- `app/Http/Controllers/Api/V1/SearchController.php`
- `app/Http/Requests/Api/V1/SearchRequest.php`
- `tests/Feature/Api/SearchApiTest.php`
- `tests/Feature/Search/SearchControllerAdditionalTest.php`
- [日本語検索API利用ガイド](JAPANESE_SEARCH_GUIDE.md)
- [MCP アーキテクチャと動作フロー](../development/MCP_Architecture_and_Flow.md)
