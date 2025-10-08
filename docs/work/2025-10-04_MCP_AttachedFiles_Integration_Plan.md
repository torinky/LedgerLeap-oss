# MCP 添付ファイル活用計画

**日付:** 2025年10月4日  
**ステータス:** <span style="color:orange;">Phase 1 実装中</span>

---
## 更新履歴
- **2025年10月8日:** 現状調査を実施。`SearchLedgersTool` に、添付ファイル情報をレスポンスに含める基本機能が実装済みであることをソースコードから確認。ただし、パラメータによる表示制御やフィルタリング機能は未実装。Phase 2, 3は未着手。ステータスを「Phase 1 実装中」に更新。
---

## 1. 背景と課題

### 1.1 現状の理解

**`content_attached` の構造:**
```php
// ledgers.content_attached (JSON配列)
[
  0 => [],  // カラムID 0 の添付ファイル情報
  1 => [    // カラムID 1 の添付ファイル情報
    "abc123hash" => [
      "name" => "見積書.pdf",
      "path" => "attachments/123/abc123hash.pdf",
      "size" => 524288,
      "mime" => "application/pdf"
    ],
    "def456hash" => [
      "name" => "契約書.pdf",
      "path" => "attachments/123/def456hash.pdf"
    ]
  ]
]
```

**`attached_files` テーブルとの関係:**
- `AttachedFile` モデル: 個別ファイルのメタデータと処理状態を管理
- `contain_content`: 抽出されたテキスト内容（Apache Tika / OCR処理結果）
- `status`: 処理状態（PENDING, EXTRACTING, EXTRACTED_AND_SAVED等）
- **Mroonga全文検索**: `ledgers.content_attached` にテキスト内容が保存され、検索対象となる

### 1.2 課題

**ユースケースドキュメントの問題点:**
1. ❌ 添付ファイル検索シナリオが欠落
2. ❌ ファイル内容の確認方法が不明確
3. ❌ ファイルメタデータの活用が未定義
4. ❌ OCR/Tika抽出テキストの確認方法が未提供

**SearchLedgersTool の問題点:**
1. ❌ `content_attached` 情報がレスポンスに含まれない
2. ❌ 添付ファイルの存在が不明
3. ❌ ファイル名・サイズ・MIMEタイプが取得できない
4. ❌ 全文検索でマッチした場合、どのファイルがマッチしたか不明

---

## 2. 添付ファイル活用ユースケース

### 2.1 実務担当者（Operational Staff）

#### ユースケース1: 「請求書を検索して内容を確認」

**シナリオ:**
```
User: 「A社からの請求書を探して」
LLM: search_ledgers(q='A社 請求書', format='summary', include_content=true)
     → 3件見つかる

LLM: 「A社からの請求書が3件見つかりました:
      1. 2025年9月分請求書 (PDF, 250KB)
         - 金額: ¥150,000
         - 内訳: システム利用料...
      2. 2025年8月分請求書 (PDF, 180KB)
      ...」

User: 「9月分の請求書の詳細を見せて」
LLM: 「9月分請求書の内容:
      - 請求先: 株式会社LedgerLeap
      - 金額: ¥150,000 (税込)
      - 内訳:
        * システム利用料: ¥100,000
        * オプション機能: ¥50,000
      - 支払期限: 2025年10月末」
```

**必要な機能:**
- ✅ 添付ファイル名・サイズ・MIMEタイプの表示
- ✅ PDFから抽出されたテキストの取得
- ✅ カスタムフィールドと添付ファイルの統合表示

---

#### ユースケース2: 「契約書の変更確認（添付ファイル版）」

**シナリオ:**
```
User: 「契約書Aの最新版と前の版で、何が変わったか教えて」
LLM: 
  1. search_ledgers(q='契約書A', include_content=true, limit=1)
     → 台帳ID: 123を特定
  
  2. get_workflow_history(ledger_id=123, limit=2)
     → version 2 と version 1 の情報取得
  
  3. 両バージョンの添付ファイル内容を比較

LLM: 「契約書Aの変更点:
      
      【ファイル変更】
      - 旧: 契約書_v1.pdf (512KB)
      - 新: 契約書_v2.pdf (530KB)
      
      【内容変更】
      - 第3条: 契約期間が12月末→3月末に延長
      - 第5条: 違約金条項が追加
      - 署名欄: 法務部確認印が追加」
```

**必要な機能:**
- ✅ バージョン間での添付ファイル比較
- ✅ 抽出テキストの差分表示
- ✅ ファイルメタデータの変更検出

---

### 2.2 管理者（Administrator / Manager）

#### ユースケース3: 「特定の契約書がシステム内に存在するか確認」

**シナリオ:**
```
User: 「『業務委託基本契約書』という名前のPDFがシステム内にあるか確認して」
LLM: search_ledgers(
       q='業務委託基本契約書', 
       format='summary',
       include_content=false,  // まずは一覧
       limit=50
     )
     → 15件見つかる

LLM: 「『業務委託基本契約書』を含む台帳が15件見つかりました:
      
      【添付ファイルとして存在】
      1. 契約管理/A社関連 - 業務委託基本契約書.pdf (2025-09-15)
      2. 契約管理/B社関連 - 業務委託基本契約書_改訂版.pdf (2025-08-10)
      ...
      
      【本文に記載のみ】
      10. 会議議事録 - 「業務委託基本契約書について議論」
      ...」
```

**必要な機能:**
- ✅ 添付ファイル名での検索
- ✅ ファイルタイプ（PDF, Word等）でのフィルタ
- ✅ 本文マッチと添付ファイルマッチの区別

---

#### ユースケース4: 「サイズの大きいファイルの一覧」

**シナリオ:**
```
User: 「10MB以上の添付ファイルを持つ台帳をリストアップして」
LLM: search_ledgers(
       format='summary',
       include_content=false,
       file_size_min=10485760  // 10MB in bytes
     )

LLM: 「10MB以上の添付ファイルを持つ台帳が8件あります:
      
      1. プロジェクトX/設計資料 - 設計図面.dwg (25.3MB)
      2. 研修資料/2025年度 - 研修動画.mp4 (18.7MB)
      ...
      
      合計容量: 152.3MB
      ストレージ最適化を検討しますか?」
```

**必要な機能:**
- ✅ ファイルサイズでのフィルタ
- ✅ ファイルタイプごとの集計
- ✅ ストレージ使用量の可視化

---

### 2.3 開発者（Developer）

#### ユースケース5: 「OCR抽出精度の確認」

**シナリオ:**
```
Developer: 「台帳ID:123の添付ファイルから抽出されたテキストを見せて」
LLM: get_attached_files(ledger_id=123, include_extracted_text=true)

LLM: 「台帳ID:123の添付ファイル情報:
      
      1. 請求書.pdf (250KB)
         - 処理状態: EXTRACTED_AND_SAVED
         - 抽出エンジン: Apache Tika
         - 抽出テキスト:
           ```
           株式会社 LedgerLeap
           ご請求書
           
           金額: ¥150,000-
           ...
           ```
      
      2. 領収書.jpg (1.2MB)
         - 処理状態: EXTRACTED_AND_SAVED  
         - 抽出エンジン: Tesseract OCR
         - 抽出テキスト: (画像から抽出)
           ```
           領収書
           金額: 15万円
           ...
           ```」
```

**必要な機能:**
- ✅ 抽出テキストの確認
- ✅ 処理状態の確認
- ✅ 使用された抽出エンジンの情報
- ✅ 処理エラーのデバッグ情報

---

## 3. API設計

### 3.1 SearchLedgersTool の拡張

#### 新規パラメータ

```php
public function schema(JsonSchema $schema): array
{
    return [
        // 既存パラメータ...
        
        // 添付ファイル関連の新規パラメータ
        'include_attachments' => $schema->boolean('添付ファイル情報を含めるか')
            ->default(true),
        
        'attachment_filename' => $schema->string('添付ファイル名での検索'),
        
        'attachment_mime' => $schema->string('MIMEタイプでフィルタ')
            ->enum(['application/pdf', 'image/*', 'application/msword', 'all']),
        
        'attachment_size_min' => $schema->integer('最小ファイルサイズ (bytes)'),
        
        'attachment_size_max' => $schema->integer('最大ファイルサイズ (bytes)'),
    ];
}
```

#### レスポンス拡張

```json
{
  "ledgers": [
    {
      "id": 101,
      "ledger_define_id": 1,
      "__display_fields__": {
        "title": "A社請求書",
        "folder": "/経理/請求書",
        "creator": "佐藤",
        "status": "承認済み",
        "updated_at": "2025年10月01日 14:30",
        "content": {
          "請求日": "2025-09-30",
          "金額": "150000"
        },
        "attachments": [
          {
            "hash": "abc123hash",
            "filename": "請求書202509.pdf",
            "size": 256000,
            "size_formatted": "250 KB",
            "mime": "application/pdf",
            "column_id": 3,
            "column_name": "請求書ファイル",
            "extracted_text_preview": "株式会社 LedgerLeap ご請求書 金額: ¥150,000...",
            "download_url": "/api/attachments/abc123hash/download"
          }
        ],
        "attachments_summary": "2ファイル (合計 1.2MB)"
      }
    }
  ],
  "total": 1,
  "__summary__": "台帳が1件見つかりました。添付ファイル: 2件"
}
```

---

### 3.2 新規MCPツール: GetAttachedFilesTool

**目的:** 特定台帳の添付ファイル詳細情報と抽出テキストの取得

```php
class GetAttachedFilesTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Get detailed information about attached files for a specific ledger.
        Includes extracted text content from PDFs and images (OCR).
        Useful for debugging, content verification, and file analysis.
MARKDOWN;

    public function schema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer('台帳ID')->required(),
            'include_extracted_text' => $schema->boolean('抽出テキストを含めるか')
                ->default(false),
            'column_id' => $schema->integer('特定カラムのファイルのみ取得'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) return $user;

        $ledgerId = $request->input('ledger_id');
        $includeExtractedText = $request->input('include_extracted_text', false);
        $columnId = $request->input('column_id');

        $ledger = Ledger::with(['attachedFiles', 'define'])->findOrFail($ledgerId);
        
        // 権限チェック
        if (!$this->canAccessLedger($user, $ledger)) {
            return Response::error(__('messages.error.no_permission'));
        }

        $attachments = $ledger->attachedFiles;
        
        if ($columnId !== null) {
            $attachments = $attachments->where('column_id', $columnId);
        }

        $result = $attachments->map(function ($file) use ($includeExtractedText, $ledger) {
            $data = [
                'id' => $file->id,
                'filename' => $file->original_filename ?? $file->filename,
                'size' => $file->size,
                'size_formatted' => $this->formatBytes($file->size),
                'mime' => $file->mime,
                'column_id' => $file->column_id,
                'column_name' => $this->getColumnName($ledger, $file->column_id),
                'status' => $file->status->value,
                'status_label' => $file->status->label(),
                'created_at' => $file->created_at->toIso8601String(),
                'download_url' => route('attachments.download', $file->id),
            ];

            if ($includeExtractedText && $file->contain_content) {
                $data['extracted_text'] = $file->contain_content;
                $data['extracted_text_length'] = mb_strlen($file->contain_content);
            } elseif ($file->contain_content) {
                $data['extracted_text_preview'] = mb_substr($file->contain_content, 0, 200);
                $data['has_extracted_text'] = true;
            }

            return $data;
        });

        return Response::json([
            'ledger_id' => $ledgerId,
            'attachments' => $result,
            'total_files' => $result->count(),
            'total_size' => $result->sum('size'),
            'total_size_formatted' => $this->formatBytes($result->sum('size')),
            '__summary__' => trans_choice('messages.found_attachments', $result->count(), [
                'count' => $result->count(),
                'size' => $this->formatBytes($result->sum('size'))
            ]),
        ]);
    }
}
```

---

### 3.3 新規MCPツール: SearchByAttachedFileContentTool

**目的:** 添付ファイル内容に特化した高度検索

```php
class SearchByAttachedFileContentTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Search ledgers specifically by attached file content.
        More focused than general search_ledgers for file-specific queries.
        Returns ledgers with file match details.
MARKDOWN;

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('検索キーワード（ファイル内容）')->required(),
            'file_type' => $schema->string('ファイルタイプ')
                ->enum(['pdf', 'image', 'document', 'all'])
                ->default('all'),
            'folder_id' => $schema->integer('フォルダID'),
            'limit' => $schema->integer('取得件数')->default(20),
        ];
    }

    public function handle(Request $request): Response
    {
        // content_attached のみを対象とした検索
        $query = Ledger::whereRaw(
            'match(`content_attached`) against (? IN BOOLEAN MODE)',
            [$request->input('q')]
        );

        // ファイルタイプでフィルタ
        if ($request->input('file_type') !== 'all') {
            $mimePatterns = $this->getMimePatterns($request->input('file_type'));
            $query->whereHas('attachedFiles', function($q) use ($mimePatterns) {
                $q->whereIn('mime', $mimePatterns);
            });
        }

        $ledgers = $query->with(['attachedFiles', 'define'])->limit($request->input('limit'))->get();

        // マッチしたファイルをハイライト
        $results = $ledgers->map(function ($ledger) use ($request) {
            $matchedFiles = $this->findMatchedFiles($ledger, $request->input('q'));
            
            return [
                'ledger' => $ledger->only(['id', 'ledger_define_id', 'status']),
                'matched_files' => $matchedFiles,
                '__display_fields__' => [
                    'title' => $ledger->define->name,
                    'matched_in' => $matchedFiles->pluck('filename')->implode(', '),
                ],
            ];
        });

        return Response::json([
            'ledgers' => $results,
            'total' => $results->count(),
            '__summary__' => trans_choice('messages.found_in_attachments', $results->count()),
        ]);
    }
}
```

---

## 4. 実装計画

### Phase 1: SearchLedgersTool 拡張（優先度: 高）

**工数:** 6時間

**タスク:**
1. [ ] `include_attachments` パラメータ追加（2時間）
2. [x] `__display_fields__.attachments` レスポンス実装（2時間）
3. [ ] ファイルフィルタパラメータ実装（1時間）
4. [ ] テスト追加（1時間）

**影響範囲:**
- `app/Mcp/Tools/SearchLedgersTool.php`
- `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`
- `lang/ja/messages.php` (翻訳追加)

---

### Phase 2: GetAttachedFilesTool 実装（優先度: 中）

**工数:** 8時間

**タスク:**
1. [ ] ツール本体実装（4時間）
2. [ ] 権限チェック統合（1時間）
3. [ ] レスポンスフォーマッタ実装（2時間）
4. [ ] テスト作成（1時間）

**新規ファイル:**
- `app/Mcp/Tools/GetAttachedFilesTool.php`
- `tests/Unit/Mcp/Tools/GetAttachedFilesToolTest.php`

---

### Phase 3: SearchByAttachedFileContentTool 実装（優先度: 低）

**工数:** 10時間

**タスク:**
1. [ ] content_attached特化検索ロジック（4時間）
2. [ ] マッチファイル検出アルゴリズム（3時間）
3. [ ] MIMEタイプフィルタ実装（2時間）
4. [ ] テスト作成（1時間）

**新規ファイル:**
- `app/Mcp/Tools/SearchByAttachedFileContentTool.php`
- `tests/Unit/Mcp/Tools/SearchByAttachedFileContentToolTest.php`

---

### Phase 4: ドキュメント更新（優先度: 高）

**工数:** 4時間

**対象:**
- `docs/work/2025-09-27_MCP_Prompt_and_Response_Design.md` - ユースケース追加
- `docs/work/2025-10-03_MCP_SearchLedgersTool_Response_Refactoring_Plan.md` - 添付ファイル対応
- OpenAPI仕様更新
- README更新

---

## 5. 技術的考慮事項

### 5.1 パフォーマンス

**課題:**
- 添付ファイル情報取得の N+1 問題
- 大きな抽出テキストのレスポンスサイズ

**対策:**
- Eager Loading: `->with(['attachedFiles'])`
- `include_extracted_text` をデフォルトfalseに
- プレビュー文字数制限（200文字）

### 5.2 セキュリティ

**考慮点:**
- 添付ファイルへのアクセス権限チェック
- ダウンロードURLの有効期限
- 機密情報を含むファイルの扱い

**実装:**
```php
public function canAccessAttachment(User $user, AttachedFile $file): bool
{
    $ledger = $file->ledger;
    return $this->canAccessLedger($user, $ledger);
}
```

### 5.3 全文検索の精度

**Mroongaの制約:**
- `content` と `content_attached` は別々にインデックス
- OR検索で両方をカバー

**検索クエリ例:**
```php
$query->where(function($q) use ($keyword) {
    $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$keyword])
      ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$keyword]);
});
```

---

## 6. 成功指標

### 機能面
- [o] 添付ファイルを含む台帳が検索できる
- [ ] ファイル内容で検索できる
- [ ] 抽出テキストを確認できる
- [ ] ファイルサイズ・タイプでフィルタできる

### パフォーマンス面
- [ ] レスポンスタイム 200ms以下（10件取得時）
- [ ] N+1問題が発生していない
- [ ] トークン消費が許容範囲（プレビューモード）

### ユーザビリティ面
- [ ] 自然な日本語レスポンス
- [ ] ファイル情報が直感的に理解できる
- [ ] エラーメッセージが明確

---

## 7. 次のステップ

1. **レビュー依頼:** 本計画書のレビューと承認
2. **Phase 1 実装開始:** SearchLedgersTool拡張（最優先）
3. **フィールドテスト:** 実際のLLMでの使用感確認
4. **最適化:** フィードバックに基づく改善

---

**関連ドキュメント:**
- [SearchLedgersTool レスポンス仕様変更](./2025-10-03_MCP_SearchLedgersTool_Response_Refactoring_Plan.md)
- [MCPプロンプトと応答内容の設計案](./2025-09-27_MCP_Prompt_and_Response_Design.md)
- [包括的MCP実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md)
