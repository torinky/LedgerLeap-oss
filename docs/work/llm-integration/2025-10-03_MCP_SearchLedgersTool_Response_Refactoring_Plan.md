# MCP `search_ledgers_tool` 応答仕様変更計画（改訂版）

**日付:** 2025年10月3日（2025年10月4日改訂）  
**ステータス:** 承認済み - 柔軟な対応モードの実装

## 1. 背景と目的

`search_ledgers_tool` は、キーワードや条件に基づいて台帳を検索するコア機能を提供する。本改訂版では、実際のLLM利用シナリオに基づき、以下の3点を実現する。

### 1.1 課題認識

**当初の課題:**
- LLMに有用な「表示用整形フィールド」の不足
- 多言語対応の不備（ハードコード日本語）
- 台帳のカスタムフィールド（content）が活用されていない

**新たな課題:**
- 検索結果が多数（50-100件）の場合のトークン消費
- 詳細確認が必要な場合の追加リクエスト問題
- 一覧表示と詳細表示の使い分けニーズ

### 1.2 設計方針

1. **柔軟な情報量制御**: パラメータで返却する情報量を制御可能に
2. **LLM最適化**: キーは英語固定、値は翻訳済みで提供
3. **段階的情報取得**: サマリー → 詳細 の段階的ワークフローをサポート
4. **将来の拡張性**: フィールドテストを経て最適化できる設計

### 1.3 ワークフローステータスの扱い

**重要な設計決定:**

システム内部では `WorkflowStatus` Enumで管理されており、以下の値を持つ:
- `none` - ワークフローなし
- `draft` - 作成中/編集中
- `pending_inspection` - 点検待ち
- `pending_approval` - 承認待ち
- `approved` - 承認済み

**MCPレスポンスでの返却方法:**

1. **機械処理用 (`status` フィールド)**: 
   - Enum値をそのまま返す（小文字スネークケース）
   - 例: `"status": "pending_approval"`
   - フィルタリングやソートなどの機械処理に使用

2. **表示用 (`__display_fields__.workflow_status`)**: 
   - 翻訳キー `ledger.workflow.status.{value}` で翻訳された文字列
   - 例: `"workflow_status": "承認待ち"`
   - LLMの読み上げやユーザー表示に使用
   - `WorkflowStatus::label()` メソッドで取得

**実装例:**
```php
// 生の値（機械処理用）
$ledger->status = 'pending_approval';  // Enum値

// 翻訳済み値（表示用）
$ledger->status->label() // "承認待ち" を返す
// または
trans('ledger.workflow.status.pending_approval') // "承認待ち"
```

**レスポンス例:**
```json
{
  "id": 101,
  "status": "pending_approval",  // 機械処理用
  "__display_fields__": {
    "workflow_status": "承認待ち"  // 表示用
  }
}
```

**関連ドキュメント:**
-   [MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)
-   [包括的MCP実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md)

## 2. レスポンス仕様（改訂版）

### 2.1 パラメータ設計

```php
// 検索パラメータ
{
  "q": "日報",                    // 検索キーワード
  "folder_id": 10,                // フォルダID
  "format": "summary",            // レスポンス形式
  "include_content": true,        // カスタムフィールドを含めるか
  "limit": 20                     // 取得件数
}
```

#### パラメータ詳細

| パラメータ | 型 | デフォルト | 説明 |
|-----------|---|-----------|------|
| `format` | enum | `summary` | `raw`, `summary`, `detailed` のいずれか |
| `include_content` | boolean | `true` | `summary`/`detailed`時にカスタムフィールドを含めるか |
| `content_preview_length` | int | `200` | `include_content=false`時のプレビュー文字数 |

### 2.2 レスポンス形式

#### モード1: `format=raw` （機械処理向け）

**用途:** 最小限のデータのみ。カウント取得や軽量処理向け。

```json
{
  "ledgers": [
    {
      "id": 101,
      "ledger_define_id": 1,
      "creator_id": 5,
      "status": "pending_approval",
      "created_at": "2025-10-03T10:00:00Z",
      "updated_at": "2025-10-03T14:30:00Z"
    }
  ],
  "total": 1,
  "meta": {
    "ledger_defines": { "1": { "name": "営業日報", "folder_id": 10 } },
    "folders": { "10": { "name": "営業部", "path": "/営業部" } },
    "users": { "5": { "name": "佐藤" } }
  }
}
```

**特徴:**
- 最小限のフィールド（ID、ステータス、日時のみ）
- **ステータス**: 小文字のスネークケース（`pending_approval`）- Enum値をそのまま返す
- `__display_fields__`なし
- `__summary__`なし
- メタ情報は正規化された形式

---

#### モード2: `format=summary` + `include_content=false` （一覧表示向け）

**用途:** 多数の検索結果を一覧表示。概要のみ把握したい場合。

```json
{
  "ledgers": [
    {
      "id": 101,
      "ledger_define_id": 1,
      "creator_id": 5,
      "status": "pending_approval",
      "created_at": "2025-10-03T10:00:00Z",
      "updated_at": "2025-10-03T14:30:00Z",
      "__display_fields__": {
        "title": "2025年Q4 営業日報",
        "folder": "/営業部/営業日報",
        "creator": "佐藤",
        "workflow_status": "承認待ち",
        "updated_at": "2025年10月03日 14:30",
        "content_preview": "訪問先: A社 / 商談内容: 新製品XYZの紹介..."
      }
    }
  ],
  "total": 15,
  "meta": { /* 省略 */ },
  "__summary__": "台帳が15件見つかりました。"
}
```

**特徴:**
- 固定フィールド（title, folder, creator, workflow_status, updated_at）
- **ワークフローステータス**: `__display_fields__.workflow_status` に翻訳済み（例: "承認待ち"）
- **生ステータス**: `status` フィールドにEnum値（例: "pending_approval"）- 機械処理用
- カスタムフィールドのプレビュー（200文字）
- キーは英語固定、値は翻訳済み
- トークン消費が中程度

---

#### モード3: `format=summary` + `include_content=true` （詳細表示向け・デフォルト）

**用途:** 詳細確認。LLMが内容を理解して要約・質問応答する場合。

```json
{
  "ledgers": [
    {
      "id": 101,
      "ledger_define_id": 1,
      "creator_id": 5,
      "status": "pending_approval",
      "created_at": "2025-10-03T10:00:00Z",
      "updated_at": "2025-10-03T14:30:00Z",
      "__display_fields__": {
        "title": "2025年Q4 営業日報",
        "folder": "/営業部/営業日報",
        "creator": "佐藤",
        "workflow_status": "承認待ち",
        "updated_at": "2025年10月03日 14:30",
        "content": {
          "日付": "2025-10-03",
          "訪問先": "A社",
          "担当者": "山田部長",
          "商談内容": "新製品XYZの紹介を実施。先方の反応は良好で、特に価格面での競争力が評価された。",
          "次回アクション": "2025-10-10までに正式な見積もりを提出する。",
          "進捗状況": "順調"
        }
      }
    }
  ],
  "total": 1,
  "meta": { /* 省略 */ },
  "__summary__": "台帳が1件見つかりました。"
}
```

**特徴:**
- 全カスタムフィールドを含む
- **ワークフローステータス**: `__display_fields__.workflow_status` に翻訳済み（例: "承認待ち"）
- **生ステータス**: `status` フィールドにEnum値（例: "pending_approval"）- 機械処理用
- LLMが内容を理解・要約可能
- 追加リクエスト不要で詳細確認完結
- トークン消費が多い

---

#### モード4: `format=detailed` （完全情報・デバッグ向け）

**用途:** 開発者のデバッグ。完全な台帳情報が必要な場合。

```json
{
  "ledgers": [
    {
      "id": 101,
      "tenant_id": "tenant_abc",
      "ledger_define_id": 1,
      "folder_id": 10,
      "creator_id": 5,
      "status": "pending_approval",
      "inspector_id": 8,
      "inspector": { "id": 8, "name": "田中" },
      "approver_id": null,
      "content": [
        "2025-10-03",
        "A社",
        "山田部長",
        "新製品XYZの紹介を実施...",
        "2025-10-10までに見積もり提出",
        "順調"
      ],
      "content_attached": "...",
      "tags": ["営業", "新規顧客"],
      "attached_files": [
        {
          "id": 42,
          "name": "見積書案.pdf",
          "size": 524288,
          "mime_type": "application/pdf"
        }
      ],
      "created_at": "2025-10-03T10:00:00Z",
      "updated_at": "2025-10-03T14:30:00Z",
      "__display_fields__": { /* summary形式と同じ */ }
    }
  ],
  "total": 1,
  "meta": { /* 完全なメタ情報 */ },
  "__summary__": "台帳が1件見つかりました。",
  "__debug_info__": {
    "query_time_ms": 45.2,
    "database_queries": 3,
    "cache_hits": 2
  }
}
```

**特徴:**
- 全フィールドを含む完全情報
- デバッグ情報付き
- 開発者向け

## 3. 使用シナリオと推奨モード

### シナリオ1: 「日報を検索して一覧表示」

```
User: 「先週の営業日報を見せて」
LLM: search_ledgers(
       q='日報', 
       created_from='2025-09-24',
       format='summary',
       include_content=false,  // 一覧表示なのでプレビューのみ
       limit=50
     )
```

**推奨:** `format=summary` + `include_content=false`
- 50件返っても約10KBで済む
- プレビューで概要は把握できる
- 詳細が必要なら追加リクエスト

---

### シナリオ2: 「特定の日報の詳細を確認」

```
User: 「9/24のA社訪問の日報の詳細を教えて」
LLM: search_ledgers(
       q='A社 9/24',
       format='summary',
       include_content=true,   // 詳細確認なのでフル情報
       limit=1
     )
```

**推奨:** `format=summary` + `include_content=true`
- 1-2件なので全フィールド取得してもOK
- LLMが内容を理解して要約・質問応答
- 追加リクエスト不要

---

### シナリオ3: 「承認待ちタスクの確認と処理」

```
User: 「承認待ちの台帳を確認したい」
LLM: 
  // Step 1: 一覧取得
  search_ledgers(
    status='pending_approval',
    format='summary',
    include_content=false,    // まずは一覧
    limit=20
  )
  → 3件見つかる
  
  // Step 2: ユーザーが詳細確認を要求
  User: 「山田さんの経費申請の内容は？」
  LLM: search_ledgers(
         q='山田 経費申請',
         status='pending_approval',
         format='summary',
         include_content=true,   // 今度は詳細
         limit=1
       )
```

**推奨:** 段階的な情報取得
- 最初は `include_content=false` で一覧
- 必要に応じて `include_content=true` で詳細
- **ステータス**: 小文字スネークケース（`pending_approval`）でフィルタ

---

### シナリオ4: 「デバッグ・開発」

```
Developer: 「台帳ID:123の完全な情報を見せて」
LLM: search_ledgers(
       ledger_id=123,
       format='detailed'       // デバッグモード
     )
```

**推奨:** `format=detailed`
- 完全な情報とデバッグ情報
- 開発者向け

---

## 4. パフォーマンス比較

| モード | 1件あたり | 100件 | トークン (100件) | 用途 |
|--------|----------|-------|-----------------|------|
| `raw` | ~150B | ~15KB | ~300 | カウント・軽量処理 |
| `summary` (preview) | ~400B | ~40KB | ~1,000 | 一覧表示 |
| `summary` (full) | ~2KB | ~200KB | ~10,000 | 詳細確認 (少数) |
| `detailed` | ~5KB | ~500KB | ~25,000 | デバッグ (1件推奨) |

**推奨ガイドライン:**
- **10件以下**: `include_content=true` で一度に取得
- **10-50件**: `include_content=false` → 必要なら個別に詳細
- **50件以上**: `format=raw` で軽量化を検討

## 5. 実装計画

### Step 1: LedgerService の拡張 ✅ (完了)

**対象:** `app/Services/LedgerService.php`

現在の `searchLedgersForApi` は正規化構造 `['ledgers', 'meta', 'total']` を返している。これは維持。

---

### Step 2: SearchLedgersTool の改修

**対象:** `app/Mcp/Tools/SearchLedgersTool.php`

#### 2.1 スキーマ拡張

```php
public function schema(JsonSchema $schema): array
{
    return [
        // 既存パラメータ
        'q' => $schema->string('検索キーワード'),
        'folder_id' => $schema->integer('フォルダID'),
        'ledger_define_id' => $schema->integer('台帳定義ID'),
        // ... 他の既存パラメータ
        
        // 新規パラメータ
        'format' => $schema->string('レスポンス形式')
            ->enum(['raw', 'summary', 'detailed'])
            ->default('summary'),
        
        'include_content' => $schema->boolean('カスタムフィールドを含めるか')
            ->default(true),
        
        'content_preview_length' => $schema->integer('プレビュー文字数')
            ->default(200),
    ];
}
```

#### 2.2 handle メソッドの実装

```php
public function handle(Request $request): Response
{
    $user = $this->authenticateOrError();
    if ($user instanceof Response) return $user;
    
    $params = $request->toArray();
    $format = $params['format'] ?? 'summary';
    $includeContent = $params['include_content'] ?? true;
    $previewLength = $params['content_preview_length'] ?? 200;
    
    // LedgerService で検索実行
    $results = $this->ledgerService->searchLedgersForApi($user, $params);
    
    // format に応じて処理分岐
    return match($format) {
        'raw' => $this->buildRawResponse($results),
        'summary' => $this->buildSummaryResponse($results, $includeContent, $previewLength),
        'detailed' => $this->buildDetailedResponse($results, $includeContent),
    };
}
```

#### 2.3 レスポンスビルダーメソッド

```php
private function buildRawResponse(array $results): Response
{
    return Response::json($results);
}

private function buildSummaryResponse(
    array $results, 
    bool $includeContent, 
    int $previewLength
): Response {
    $ledgers = collect($results['ledgers'])->map(function ($ledger) use ($results, $includeContent, $previewLength) {
        $meta = $results['meta'];
        $ledger = (object) $ledger;
        
        $define = $meta['ledger_defines'][$ledger->ledger_define_id] ?? null;
        $folderPath = $this->getFolderPath($define, $meta);
        $statusDisplay = $this->getStatusDisplay($ledger);
        $creatorName = $this->getCreatorName($ledger, $meta);
        
        // __display_fields__ を構築
        $displayFields = [
            'title' => $define['name'] ?? trans('common.unknown'),
            'folder' => $folderPath,
            'creator' => $creatorName,
            'status' => $statusDisplay,
            'updated_at' => Carbon::parse($ledger->updated_at)->isoFormat('YYYY年MM月DD日 HH:mm'),
        ];
        
        // カスタムフィールドの処理
        if ($includeContent && $define) {
            $displayFields['content'] = $this->formatContent($ledger->content, $define);
        } elseif ($define) {
            $displayFields['content_preview'] = $this->createContentPreview(
                $ledger->content, 
                $define, 
                $previewLength
            );
        }
        
        $ledger->__display_fields__ = $displayFields;
        return $ledger;
    });
    
    return Response::json([
        'ledgers' => $ledgers,
        'total' => $results['total'],
        'meta' => $results['meta'],
        '__summary__' => trans_choice('messages.found_ledgers', $results['total'], [
            'count' => $results['total']
        ]),
    ]);
}

private function buildDetailedResponse(array $results, bool $includeContent): Response
{
    // summary と同じだが、追加でデバッグ情報を含める
    $response = $this->buildSummaryResponse($results, $includeContent, 0);
    $data = json_decode($response->content(), true);
    
    $data['__debug_info__'] = [
        'query_time_ms' => microtime(true) - LARAVEL_START,
        'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
    ];
    
    return Response::json($data);
}
```

#### 2.4 ヘルパーメソッド

```php
private function formatContent(array $content, array $define): array
{
    $formatted = [];
    foreach ($define['column_define'] as $column) {
        $value = $content[$column['id']] ?? null;
        $formatted[$column['name']] = $value;
    }
    return $formatted;
}

private function createContentPreview(array $content, array $define, int $length): string
{
    $preview = [];
    $totalLength = 0;
    
    foreach ($define['column_define'] as $column) {
        if ($totalLength >= $length) break;
        
        $value = $content[$column['id']] ?? '';
        if (empty($value)) continue;
        
        $truncated = mb_substr($value, 0, $length - $totalLength);
        $preview[] = $column['name'] . ': ' . $truncated;
        $totalLength += mb_strlen($truncated);
    }
    
    return implode(' / ', $preview);
}

private function getFolderPath(?array $define, array $meta): string
{
    if (!$define || !isset($meta['folders'][$define['folder_id']])) {
        return trans('common.root_folder');
    }
    return $meta['folders'][$define['folder_id']]['path'];
}

private function getStatusDisplay(object $ledger): string
{
    // ステータスはWorkflowStatus Enumで管理
    // Enum値（小文字スネークケース）を翻訳キーに変換
    if ($ledger->status instanceof \App\Enums\WorkflowStatus) {
        return $ledger->status->label(); // Enumのlabel()メソッドを使用
    }
    
    // フォールバック（互換性のため）
    $statusValue = is_object($ledger->status) ? $ledger->status->value : $ledger->status;
    return trans('ledger.workflow.status.' . $statusValue);
}

private function getCreatorName(object $ledger, array $meta): string
{
    return $meta['users'][$ledger->creator_id]['name'] ?? trans('common.unknown');
}
```

**重要な変更点:**
- **Enum値の扱い**: `WorkflowStatus` Enumの `label()` メソッドを活用
- **翻訳キー**: `ledger.workflow.status.{value}` 形式（例: `ledger.workflow.status.pending_approval`）
- **レスポンスフィールド**:
  - `status`: 小文字スネークケース（`pending_approval`）- 機械処理用
  - `__display_fields__.workflow_status`: 翻訳済み文字列（"承認待ち"）- 表示用

---

### Step 3: 翻訳キーの確認・追加

**対象:** `lang/ja/ledger.php`, `lang/ja/messages.php`, `lang/ja/common.php`

#### 3.1 必要な翻訳キー

```php
// lang/ja/messages.php
'found_ledgers' => '台帳が:count件見つかりました。',

// lang/ja/common.php
'root_folder' => 'ルート',
'unknown' => '不明',

// lang/ja/ledger.php
'workflow' => [
    'status' => [
        'none' => 'ワークフローなし',
        'draft' => '作成中/編集中',
        'pending_inspection' => '点検待ち',
        'pending_approval' => '承認待ち',
        'approved' => '承認済み',
    ],
],
```

**注意:** 翻訳キーは小文字スネークケース（`pending_approval`）で定義
これらは既に `lang/ja/ledger.php` に存在することを確認済み。

---

### Step 4: テストの改修

**対象:** `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`

#### 4.1 テストケース

```php
#[Test]
public function it_returns_raw_format_correctly()
{
    // format=raw のテスト
    // __display_fields__, __summary__ がないことを確認
}

#[Test]
public function it_returns_summary_format_without_content()
{
    // format=summary, include_content=false のテスト
    // content_preview が含まれることを確認
}

#[Test]
public function it_returns_summary_format_with_content()
{
    // format=summary, include_content=true のテスト（デフォルト）
    // content フィールドが完全に含まれることを確認
}

#[Test]
public function it_returns_detailed_format_with_debug_info()
{
    // format=detailed のテスト
    // __debug_info__ が含まれることを確認
}

#[Test]
public function it_uses_english_keys_in_display_fields()
{
    // __display_fields__ のキーが英語であることを確認
    $this->assertArrayHasKey('title', $displayFields);
    $this->assertArrayHasKey('folder', $displayFields);
    $this->assertArrayHasKey('creator', $displayFields);
}

#[Test]
public function it_uses_translated_values_in_display_fields()
{
    // __display_fields__ の値が翻訳されていることを確認
    // trans() が適切に呼ばれていることをモックで検証
}
```

---

### Step 5: ドキュメント更新

**対象:** 
- `docs/work/2025-09-27_MCP_Prompt_and_Response_Design.md` ✅
- `docs/work/2025-09-29_Comprehensive_MCP_Implementation_Plan.md` ✅
- MCPサーバーのOpenAPI仕様

---

## 6. 実装優先度と工数見積もり

| ステップ | 優先度 | 工数 | 担当 |
|---------|-------|------|------|
| Step 1: LedgerService | - | 完了 | - |
| Step 2: SearchLedgersTool | 🔴 高 | 4時間 | バックエンド |
| Step 3: 翻訳キー確認 | 🟢 低 | 30分 | バックエンド |
| Step 4: テスト改修 | 🟡 中 | 3時間 | QA/バックエンド |
| Step 5: ドキュメント | 🟡 中 | 1時間 | PM/バックエンド |

**合計工数:** 8.5時間（約1-2日）

---

## 7. 今後の最適化計画

### フィールドテスト後の改善点

1. **デフォルト値の調整**
   - 実際の使用パターンに基づき `include_content` のデフォルトを再検討
   - `content_preview_length` の最適値を決定

2. **パフォーマンス最適化**
   - 大量取得時のメモリ使用量監視
   - キャッシュ戦略の導入

3. **追加モードの検討**
   - `format=compact`: 超軽量モード（IDとタイトルのみ）
   - `format=statistics`: 統計情報特化モード

4. **スマートプレビュー**
   - 重要なフィールドを自動判定してプレビュー
   - キーワードマッチ部分を優先的に表示

---

## 8. リスクと対応

| リスク | 影響度 | 対応策 |
|--------|-------|--------|
| 既存のMCPクライアントの互換性 | 中 | デフォルトを `format=summary` + `include_content=true` にして後方互換性維持 |
| トークン消費の増加 | 高 | ドキュメントで推奨モードを明記、監視ダッシュボード追加 |
| パフォーマンス低下 | 中 | N+1問題の確認、レスポンスタイムの監視 |
| テスト複雑化 | 低 | モック化とヘルパーメソッドで対応 |

---

## 9. 成功指標

- [ ] 全テストが通過（カバレッジ95%以上）
- [ ] レスポンスタイムが200ms以下（100件取得時）
- [ ] ドキュメントの完全性（全パラメータ・モード説明済み）
- [ ] 既存機能の後方互換性維持

