# MCP統合テスト環境整備 - 作業ログ

**作業日:** 2025年1月XX日  
**現在のブランチ:** `feature/LLM-integration`  
**作業ステータス:** 統合テスト環境整備中

---

## 🎯 作業目標

LLMとの連携動作を検証するため、および今後のデモ・統合テストに活用する最小限のテストデータ環境を構築する。

### 要件
- MCP API経由で実用的な検索・作成操作が可能
- 日本語項目名・長文コンテンツでLLMとの高度な対話を実現
- 既存のユニットテストと干渉しない
- 権限・ロール・フォルダ構造を最小限ながら網羅

---

## 📊 実施内容

### ✅ Phase 1: デモデータSeeder作成

#### 実装内容
- **Seeder:** `database/seeders/DemoMinimalSeeder.php`
- **テナント:** `demo-tenant` (demo-tenant.localhost)
- **ユーザー:** 2名
  - 田中太郎 (demo@example.com / demo1234) - Demo User ロール
  - 山田花子 (admin@example.com / demo1234) - Super Admin ロール

#### データ構造
```
フォルダ構造:
├── / (ルートフォルダ)
│   └── デモ用フォルダ
│       └── 日報

台帳定義:
- [DEMO] 営業日報
  - 6カラム（日付、顧客名、訪問目的、商談内容、成果・所感、次回アクション）
  - 全項目日本語名

台帳データ: 7件
- 株式会社A商事 × 2件（新規提案、フォローアップ）
- 株式会社Bシステムズ（定期訪問）
- C製造株式会社（価格交渉）
- 株式会社Dコーポレーション（トラブル対応）
- 株式会社E物産（見送り）
- 株式会社Fソリューションズ（初回訪問）

タグ: 16個
- 新規、重要、大型案件、フォローアップ、データ移行
- 既存顧客、定期訪問、要望、価格交渉、契約直前
- トラブル対応、メンテナンス、見送り、再提案予定
- 初回訪問、有望
```

#### 権限設定
- Demo User: 日報フォルダへのWRITE権限
- Super Admin: ルートフォルダへのADMIN権限

#### Seeder実行
```bash
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder

# 出力:
✓ Users created: 田中太郎, 山田花子
✓ Folders created: /, デモ用フォルダ, 日報
✓ Permissions set: WRITE for demo user, ADMIN for admin user
✓ Ledger define created: [DEMO] 営業日報 with 6 columns
✓ Tags created: 16 tags
✓ All 7 demo ledgers created successfully
```

### ✅ Phase 2: API動作確認

#### APIトークン取得
```bash
./vendor/bin/sail artisan tinker --execute="
\$user = App\Models\User::where('email', 'demo@example.com')->first();
\$token = \$user->createToken('mcp-demo-token');
echo 'Token: ' . \$token->plainTextToken . PHP_EOL;
"

# Token: 5|h630i50gUjbjWzTvasiHrWch0MP8ajc3xiTGYM1xd9358d73
```

#### 台帳定義API確認
```bash
curl -H "Authorization: Bearer 5|h630i50gUjbjWzTvasiHrWch0MP8ajc3xiTGYM1xd9358d73" \
  -H "Accept: application/json" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/ledger-defines"

# 結果: ✅ 正常動作
# - [DEMO] 営業日報の定義が取得できた
# - 6カラムすべてが正しく返された
```

#### 検索API確認（権限なしリクエスト）
```bash
curl -H "Authorization: Bearer 5|h630i50gUjbjWzTvasiHrWch0MP8ajc3xiTGYM1xd9358d73" \
  -H "Accept: application/json" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search?limit=5"

# 結果: ✅ 正常動作
# - total: 7件（全台帳）
# - 返却: 5件（リミット適用）
```

#### Mroonga全文検索動作確認
```bash
./vendor/bin/sail artisan tinker --execute="
\$tenant = App\Models\Tenant::first();
\$tenant->run(function() {
    \$results = App\Models\Ledger::search('株式会社')->get();
    echo 'Found: ' . \$results->count() . ' ledgers' . PHP_EOL;
    foreach (\$results as \$ledger) {
        echo 'ID: ' . \$ledger->id . ' - Customer: ' . (\$ledger->content[1] ?? 'N/A') . PHP_EOL;
    }
});
"

# 結果: ✅ 正常動作
# Found: 7 ledgers
# ID: 58 - Customer: 株式会社A商事
# ID: 59 - Customer: 株式会社A商事
# ID: 60 - Customer: 株式会社Bシステムズ
# ID: 61 - Customer: C製造株式会社
# ID: 62 - Customer: 株式会社Dコーポレーション
# ID: 63 - Customer: 株式会社E物産
# ID: 64 - Customer: 株式会社Fソリューションズ
```

### ✅ Phase 3: 問題調査・原因特定

#### 検索APIキーワード指定時のタイムアウト問題

**現象:**
```bash
curl -H "Authorization: Bearer ..." \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search?q=株式会社"

# 結果: タイムアウト（レスポンスなし）
# curl: (52) Empty reply from server
```

**調査実施:**
1. ✅ SearchController、LedgerService、Ledger::scopeSearchに詳細ログを追加
2. ✅ キーワードなし、英語キーワード、日本語キーワードで比較テスト
3. ✅ URLエンコードされた日本語キーワードで検証

**原因特定:**
- **問題の本質:** curlで日本語をURLエンコードせずに送信した場合、Webサーバー（Nginx/PHP-FPM）がリクエストを拒否
- **Laravel側は正常:** アプリケーション層は日本語を正しく処理できている
- **Mroonga全文検索も正常:** 日本語キーワードで正常に検索できている

**検証結果:**
| テストケース | 結果 | レスポンス時間 |
|------------|------|--------------|
| キーワードなし | ✅ 成功 | ~60-70ms |
| 英語キーワード (`?q=test`) | ✅ 成功 | ~156ms |
| 日本語直接 (`?q=株式会社`) | ❌ 失敗 | HTTP 52エラー |
| 日本語URLエンコード (`?q=%E6%A0%AA...`) | ✅ 成功 | ~153ms |

**解決策:**
```bash
# 正しい方法1: curl --data-urlencode を使用
curl -G --data-urlencode "q=株式会社" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search"

# 正しい方法2: 手動でURLエンコード
curl "http://localhost/api/v1/search?q=%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Host: demo-tenant.localhost"
```

**パフォーマンス分析:**
- フォルダID取得: 0.5-27ms
- QueryBuilder構築: 4-7ms
- 全文検索実行: 1-4ms
- countクエリ: 4-114ms
- メインクエリ: 7-9ms
- Eager Loading: 18-20ms
- 合計: **60-160ms** (全文検索時でも十分高速)

**詳細レポート:** `docs/work/MCP_SEARCH_DEBUG_REPORT_2025-10-05.md`

---

## 📁 関連ファイル

### 作成・修正したファイル
- `database/seeders/DemoMinimalSeeder.php` (新規作成)
- `app/Http/Controllers/Api/V1/SearchController.php` (デバッグログ追加)
- `app/Services/LedgerService.php` (デバッグログ追加)
- `app/Models/Ledger.php` (scopeSearchにデバッグログ追加)
- `docs/work/MCP_SEARCH_DEBUG_REPORT_2025-10-05.md` (調査レポート作成)

### 確認が必要なファイル
- ~~`app/Http/Controllers/Api/V1/SearchController.php`~~ (調査完了)
- ~~`app/Services/LedgerService.php`~~ (調査完了)
- ~~`tests/Feature/Api/SearchApiTest.php`~~ (調査完了)
- ~~`app/Models/Ledger.php` (scopeSearch メソッド)~~ (調査完了)
- `docs/development/MCP_Architecture_and_Flow.md` (URLエンコードの注意事項追加が必要)

---

## 🔧 開発コマンド集

```bash
# Seeder実行
./vendor/bin/sail artisan db:seed --class=DemoMinimalSeeder

# トークン取得
./vendor/bin/sail artisan tinker --execute="
\$user = App\Models\User::where('email', 'demo@example.com')->first();
\$token = \$user->createToken('test-token');
echo \$token->plainTextToken . PHP_EOL;
"

# テナント・フォルダ構造確認
./vendor/bin/sail artisan tinker --execute="
\$tenant = App\Models\Tenant::first();
\$tenant->run(function() {
    \$folders = App\Models\Folder::all();
    foreach (\$folders as \$folder) {
        echo 'ID: ' . \$folder->id . ' | Title: ' . \$folder->title . ' | Parent: ' . \$folder->parent_id . PHP_EOL;
    }
});
"

# 台帳データ確認
./vendor/bin/sail artisan tinker --execute="
\$tenant = App\Models\Tenant::first();
\$tenant->run(function() {
    \$ledgers = App\Models\Ledger::where('id', '>=', 58)->get();
    foreach (\$ledgers as \$ledger) {
        echo 'ID: ' . \$ledger->id . ' - ' . (\$ledger->content[1] ?? 'N/A') . PHP_EOL;
    }
});
"

# API確認（台帳定義）
curl -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/ledger-defines" | jq '.'

# API確認（検索・全件）
curl -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json" \
  -H "Host: demo-tenant.localhost" \
  "http://localhost/api/v1/search?limit=5" | jq '.meta.total'

# Featureテスト実行
./vendor/bin/sail pest tests/Feature/Api/SearchApiTest.php --filter=test_admin_can_search_all_ledgers
```

---

## 🐛 既知の問題

### 1. Folder pathが空になる問題

**現象:**
```php
// 期待値: / → /デモ用フォルダ → /デモ用フォルダ/日報
// 実際: すべてのフォルダのpathが空文字列
```

**影響:**
- UI表示で階層構造が正しく表示されない可能性
- 機能的には動作する（parent_idで階層管理されているため）

**対応方針:**
- 低優先度（機能には影響しない）
- Folderモデルのpath自動計算ロジックを確認

### 2. 検索APIキーワード指定時のタイムアウト

**現象:**
キーワードなしの検索は成功するが、日本語キーワードを直接URLに含めるとタイムアウトする

**原因:**
curlコマンドで日本語をURLエンコードせずに送信すると、Webサーバー（Nginx/PHP-FPM）が不正なリクエストとして拒否する。Laravel側は正常に日本語を処理できている。

**解決済み:**
URLエンコードを使用することで正常に動作することを確認。

**対応方針:**
- ✅ 原因特定完了
- [ ] デバッグログを本番環境用に調整（削除または無効化）
- [ ] MCPドキュメントにURLエンコードの注意事項を追加
- [ ] API使用例の更新

---

## 📚 参考ドキュメント

- MCPアーキテクチャ: `docs/development/MCP_Architecture_and_Flow.md`
- 権限管理: `docs/development/Authorization_and_Permission_Management.md`
- Mroonga全文検索: `docs/development/Testing-Best-Practices.md`

---

## ✅ 次回作業チェックリスト

次回作業開始時に以下を実施:

- [x] 検索APIキーワード指定時のタイムアウト問題を調査
  - [x] SearchControllerのコード確認
  - [x] LedgerServiceのコード確認
  - [x] ログ出力追加
  - [x] デバッグ実行
  - [x] 原因特定（URLエンコード問題）
- [ ] デバッグログの整理
  - [ ] 本番環境用にログレベルを調整
  - [ ] 不要なログを削除または条件付きに変更
- [ ] ドキュメント更新
  - [ ] MCPアーキテクチャドキュメントにURLエンコードの注意事項追加
  - [ ] API使用例を更新（正しいcurlコマンド例）
- [ ] MCPサーバー経由での動作確認
  - [ ] MCP_AUTH_TOKEN設定
  - [ ] 実際のMCPクライアントでのテスト

---

**作成日:** 2025年1月XX日  
**最終更新:** 2025年10月5日  
**次回作業:** デバッグログの整理とMCPドキュメント更新