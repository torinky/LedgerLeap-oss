# Search API

## 目的

`GET /api/v1/search` と `POST /api/v1/search` は、LedgerLeap の台帳を検索するための公開 HTTP REST API です。クライアントはキーワード、タグ、フォルダ、台帳定義、作成者、日付範囲などで台帳を検索できます。

## 対象読者

開発者・API 利用者（クライアント実装者、統合者）

## 概要

- 提供エンドポイント: `GET /api/v1/search`, `POST /api/v1/search`
- 認証: Sanctum Bearer token
- 主な機能: 全文検索、タグ/フォルダ/台帳定義/作成者による絞り込み、件数取得（`mode=count`）
- 日本語・マルチバイト検索は `POST`（JSON ボディ）を推奨

## エンドポイント（契約）

### GET /api/v1/search
クエリ文字列でパラメータを渡します。簡易な呼び出しやブラウザからの確認に適しています。

### POST /api/v1/search
JSON ボディでパラメータを渡します。日本語や長文、複雑な条件を扱うときに推奨します。

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
| `created_from` | date | 作成日の開始日（ISO 8601 形式推奨） |
| `created_to` | date | 作成日の終了日（ISO 8601 形式推奨） |
| `mode` | string | `search` または `count`（`count` は件数のみ取得） |
| `limit` | integer | 取得件数（省略時はデフォルト制限あり） |
| `offset` | integer | 取得開始位置 |

**注意**: GET で日本語を使う場合は URL エンコードが必要です。POST では JSON ボディでそのまま日本語を送信できます。

## レスポンス（例）

通常の検索は `data`（結果配列）と `meta`（ページ情報）を返します。返却項目は将来拡張される可能性がありますが、クライアントは `data` と `meta` を扱う前提で実装してください。

```json
{
  "data": [
    {
      "id": 58,
      "define": { "id": 52, "name": "[DEMO] 営業日報" }
    }
  ],
  "meta": { "total": 7, "limit": 10, "offset": 0 }
}
```

`mode=count` を指定した場合は件数のみを返す軽量レスポンスになります:

```json
{ "meta": { "total": 7 } }
```

## エラーと制約

- `401` — 認証トークンが無い、または無効
- `422` — パラメータ形式が不正
- `200` + 0 件 — 検索条件に一致しない、または閲覧権限の範囲外

制約:
- レスポンスは認証済みユーザーが閲覧可能なフォルダに自動で絞り込まれます。権限のないフォルダを指定した場合、403 ではなく 0 件が返ることがあります。
- 本ページは HTTP REST API の契約です。MCP （SearchLedgersTool など）は別の契約・レスポンス形式を持ちます。MCP に関する利用方法は別ページを参照してください。

## 使用例

### POST（日本語検索）

```bash
curl -X POST http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"q":"株式会社","tags":"重要,新規","limit":10}'
```

### GET（件数確認）

```bash
curl -G http://localhost/api/v1/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --data-urlencode "q=営業日報" \
  --data-urlencode "mode=count"
```

## 実装参照

実装やテストの詳細は内部の companion record に記録しています。主要な参照ポイント:

- routes/api.php (エンドポイント登録)
- SearchController (実際の検索処理と OpenAPI 注釈)
- SearchRequest (リクエストのバリデーション規則)
- SearchApiTest (主要な振る舞いを検証するテスト)

**テスト実行（ローカル）**

Sail を利用する場合の例:

```bash
./vendor/bin/sail test tests/Feature/Api/SearchApiTest.php
```

## 関連ドキュメント

- [日本語検索 API 利用ガイド](JAPANESE_SEARCH_GUIDE.md)
- [MCP アーキテクチャと動作フロー](../development/MCP_Architecture_and_Flow.md)

---

*注: 公開ドキュメントには内部の作業ノートや issue 番号を含めないでください。実装の詳細やトレースはパケットの companion record に記録されています。*
