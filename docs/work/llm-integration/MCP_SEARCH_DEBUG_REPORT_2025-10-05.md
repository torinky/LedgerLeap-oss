# MCP検索API調査レポート

**調査日:** 2025年10月5日  
**担当:** GitHub Copilot CLI  
**ステータス:** 原因特定完了

---

## 🎯 調査目的

MCP API経由での検索において、キーワードなしの検索は成功するが、キーワードありの検索がタイムアウトする問題の原因を特定する。

---

## 🔍 調査結果サマリー

### 結論

**問題の原因:** curlコマンドで日本語（マルチバイト文字）をURLエンコードせずに直接URLに含めた場合、Webサーバー（NginxまたはPHP-FPM）がリクエストを正しく処理できず、空のレスポンス（HTTP 52エラー）を返していた。

**解決方法:** URLパラメータの日本語文字列を適切にURLエンコードすることで、正常に動作する。

### 検証結果

| テストケース | 結果 | レスポンス時間 |
|------------|------|--------------|
| キーワードなし (`?limit=2`) | ✅ 成功 | ~60-70ms |
| 英語キーワード (`?q=test`) | ✅ 成功 | ~156ms |
| 日本語直接 (`?q=株式会社`) | ❌ 失敗 | タイムアウト (HTTP 52) |
| 日本語URLエンコード (`?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE`) | ✅ 成功 | ~153ms |

---

## 📊 詳細調査ログ

### ログ実装

以下のファイルに詳細なデバッグログを追加:

1. **SearchController.php**
   - リクエストURL、ヘッダー、検証済みパラメータ
   - サービス実行時間、例外キャッチ
   - レスポンス構築時間

2. **LedgerService.php (searchLedgersForApi)**
   - ユーザーID、入力パラメータ
   - 読み取り可能フォルダID取得時間
   - QueryBuilder構築時間
   - count()クエリ実行時間と結果
   - メインクエリ実行時間と結果件数
   - Eager Loading時間
   - メタデータ構築時間

3. **Ledger.php (scopeSearch)**
   - 入力キーワード
   - 抽出されたキーワード配列
   - MATCH AGAINST用検索文字列
   - スコープ適用完了確認

### 正常動作時のログ（URLエンコード済み日本語）

```
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] === SearchController::search called ===  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Request URL: http://demo-tenant.localhost/api/v1/search?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE&limit=2  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Validated params: {"q":"株式会社","limit":"2"}  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] === Start searchLedgersForApi ===  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] User ID: 3  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Readable folder IDs: [18]  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Time to get folder IDs: 0.53ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Transformed query params: {"filter":{"q":"株式会社"}}  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Building QueryBuilder...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Applying full-text search filter with keyword: 株式会社  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] scopeSearch called with freeWord: 株式会社  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] scopeSearch: extracted keywords: ["株式会社"]  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] scopeSearch: searchString for MATCH AGAINST: +株式会社  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] scopeSearch: applying MATCH AGAINST on content and content_attached  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] scopeSearch: completed successfully  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Search scope applied in: 3.86ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] QueryBuilder built in: 7.1ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Executing count query...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Count query result: 7 (took 113.97ms)  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Applying pagination: limit=2, offset=0  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Executing main query...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Main query result: 2 items (took 8.37ms)  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Loading relationships...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Relationships loaded in: 18.93ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Building metadata...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Metadata built in: 0.89ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] === End searchLedgersForApi (Success) ===  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Total service time: 153.72ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Building resource collection...  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] Resource collection built in: 0.68ms  
[2025-10-05 03:13:24] local.INFO: [MCP Search Debug] === SearchController::search completed ===
```

### パフォーマンス分析

| 処理 | 所要時間 | 備考 |
|------|---------|------|
| フォルダID取得 | 0.53-27ms | キャッシュの有無で変動 |
| QueryBuilder構築 | 4-7ms | フィルタ適用含む |
| 全文検索スコープ適用 | 1-4ms | 高速 |
| countクエリ | 4-114ms | 全文検索時に若干遅い |
| メインクエリ | 7-9ms | 高速 |
| Eager Loading | 18-20ms | リレーション読み込み |
| メタデータ構築 | 0.9-1ms | 高速 |
| **合計** | **60-160ms** | 全文検索時で最大160ms程度 |

---

## 🐛 問題の詳細

### curlコマンドでの日本語直接指定時の挙動

```bash
# 失敗するケース
curl -H "Authorization: Bearer {TOKEN}" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search?q=株式会社"

# curlの出力:
# * Request completely sent off
# * Empty reply from server
# * curl: (52) Empty reply from server
```

**原因:**
- curlが日本語文字列をUTF-8バイト列としてそのままURLに含めて送信
- WebサーバーがURLパラメータの不正な文字列を検出
- リクエストを破棄し、空のレスポンスを返す
- Laravelアプリケーションには到達しない（ログに何も記録されない）

### 正常動作するケース

```bash
# URLエンコードを使用
curl -H "Authorization: Bearer {TOKEN}" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE"

# または curl --data-urlencode を使用
curl -G --data-urlencode "q=株式会社" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search"
```

---

## ✅ 解決策と推奨事項

### 1. クライアント側での対応（推奨）

すべてのMCPクライアントおよびcurlコマンド使用時は、URLパラメータを適切にエンコードする:

**Python (requestsライブラリ):**
```python
import requests

# 自動的にURLエンコードされる
response = requests.get(
    "http://localhost/api/v1/search",
    params={"q": "株式会社", "limit": 10},
    headers={"Authorization": f"Bearer {token}"}
)
```

**JavaScript (fetch API):**
```javascript
const params = new URLSearchParams({
  q: "株式会社",
  limit: 10
});

fetch(`http://localhost/api/v1/search?${params}`, {
  headers: {
    "Authorization": `Bearer ${token}`
  }
});
```

**curl:**
```bash
# --data-urlencode オプションを使用
curl -G --data-urlencode "q=株式会社" \
  -H "Authorization: Bearer {TOKEN}" \
  "http://localhost/api/v1/search"

# または手動でURLエンコード
curl "http://localhost/api/v1/search?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE" \
  -H "Authorization: Bearer {TOKEN}"
```

### 2. サーバー側の対応（不要）

Laravel側は既に正しく日本語を処理できているため、追加の対応は不要。

### 3. ドキュメント更新

MCPアーキテクチャドキュメントおよびAPI使用例に、URLエンコードの必要性を明記する。

---

## 📚 参考情報

### テスト環境

- **Tenant:** demo-tenant (demo-tenant.localhost)
- **User:** demo@example.com (ID: 3)
- **Token:** 6|vWINgkz8s1iYD8e8gEryO8bkfRUlq5Pw4jQfEhg0d828e570
- **Ledger Defines:** [DEMO] 営業日報 (ID: 52)
- **Ledgers:** 7件（株式会社A商事、B、C、D、E、F）

### デモデータ

`database/seeders/DemoMinimalSeeder.php` で作成されたデータを使用。

### ログファイル

- 場所: `storage/logs/laravel-2025-10-05.log`
- ログチャンネル: `daily` (config/logging.php)

---

## 🔄 次のアクション

### 完了した項目

- [x] 問題の原因特定
- [x] URLエンコード方式での動作確認
- [x] 詳細ログの実装
- [x] パフォーマンス計測

### 今後の対応

- [ ] デバッグログの削除または本番環境での無効化
- [ ] MCPドキュメントの更新（URLエンコードの注意事項追加）
- [ ] API使用例の更新（正しいcurlコマンド例）
- [ ] MCPサーバー実装時の注意事項ドキュメント化

---

## 💡 学んだこと

1. **Webサーバーの挙動:** NginxやPHP-FPMは、不正なURL文字列を含むリクエストを早期に拒否し、アプリケーション層に到達させない。

2. **Laravel側の実装は正常:** Laravelアプリケーションは正しくUTF-8を処理でき、Mroonga全文検索も日本語で正常に動作する。

3. **HTTP標準の重要性:** URLパラメータのエンコードはHTTP標準の一部であり、クライアント側で適切に処理する必要がある。

4. **ログの重要性:** 詳細なログを実装することで、問題の切り分けが容易になった。特に処理の各段階での時間計測が有用だった。

5. **パフォーマンス:** 全文検索を含む複雑なクエリでも、約150ms程度で完了しており、実用的な速度が出ている。

---

**作成日:** 2025年10月5日  
**最終更新:** 2025年10月5日
