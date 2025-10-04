# Task 5.2: content_attached データ構造確認手順

## 目的

`ledgers.content_attached` に抽出テキストが含まれているかを確認し、Option 3-A の実装可否を判断する。

## 実行方法

### Option 1: 直接実行（推奨）

```bash
cd /Users/kazutaka/PhpstormProjects/LedgerLeap
./vendor/bin/sail artisan tinker < scripts/check_content_attached_structure.php
```

### Option 2: Tinker内で実行

```bash
./vendor/bin/sail tinker
```

Tinkerプロンプトで:
```php
include 'scripts/check_content_attached_structure.php';
```

## 確認項目

スクリプトは以下を自動的に確認します:

1. **添付ファイルを持つ台帳の検索**
   - 最大3件の台帳を取得
   - content_attachedが空でないものを検索

2. **content_attachedの構造分析**
   - データ型（object or array）
   - 各カラムIDの添付ファイル一覧
   - ファイルメタデータの全キー
   - テキスト関連キーの検出（extracted_text, text, content等）

3. **AttachedFileテーブルの確認**
   - contain_content = true のレコード存在確認
   - メタデータの確認

## 期待される出力

### ケース1: テキストキーが存在する場合

```
=== Checking content_attached structure ===

Searching for ledgers with attachments...
✅ Found 2 ledger(s) with attachments

--- Ledger #1 (ID: 123) ---
Title: テスト台帳
Created: 2025-10-04 10:30:00

Type of content_attached: object
Converting object to array...

Column ID: 1
Number of files: 1
First file hash: abc123hash
Available keys: name, path, size, mime, extracted_text

  [name]: (string, 12 chars) 見積書.pdf
  [path]: (string, 45 chars) attachments/123/abc123hash.pdf
  [size]: (524288)
  [mime]: (string, 15 chars) application/pdf
  [extracted_text]: (string, 2543 chars) これは見積書の内容です...

✅ Found text-related keys: extracted_text
   extracted_text length: 2543 chars
   Preview: これは見積書の内容です。株式会社ABC 御中...

------------------------------------------------------------

=== Checking attached_files table ===

✅ Found AttachedFile with contain_content = true
   ID: 456
   Filename: 見積書.pdf
   Status: EXTRACTED_AND_SAVED
   ...

=== Analysis Complete ===
```

→ **判断: Option 3-A を実装** （2-3.5時間）

### ケース2: テキストキーが存在しない場合

```
=== Checking content_attached structure ===

...

Available keys: name, path, size, mime

  [name]: (string, 12 chars) 見積書.pdf
  [path]: (string, 45 chars) attachments/123/abc123hash.pdf
  [size]: (524288)
  [mime]: (string, 15 chars) application/pdf

❌ No text-related keys found in: name, path, size, mime

------------------------------------------------------------
```

→ **判断: Task 5.2 を見送り** → Phase 3（統計機能）へ

### ケース3: 添付ファイルを持つ台帳が見つからない場合

```
=== Checking content_attached structure ===

Searching for ledgers with attachments...
❌ No ledgers with attachments found.
   Please create a test ledger with file attachments first.
```

→ **対応: テスト用台帳を作成してから再実行**

## 判断フロー

```
実行結果
  ├─ テキストキー(extracted_text等)あり
  │   └─→ Option 3-A を実装
  │       - SearchLedgersTool に extracted_text_preview 追加
  │       - 実装時間: 2-3.5時間
  │
  ├─ テキストキーなし
  │   └─→ Task 5.2 を見送り
  │       - 現状の機能で十分と判断
  │       - Phase 3（統計機能）へ移行
  │
  └─ 添付ファイルなし
      └─→ テスト台帳作成 → 再実行
```

## 次のステップ

### テキストキーがある場合

1. ✅ Option 3-A の実装開始
2. ✅ SearchLedgersTool の formatSummaryResponse() を更新
3. ✅ getExtractedTextPreview() メソッドを追加
4. ✅ テストケース追加（1-2件）
5. ✅ ドキュメント更新

### テキストキーがない場合

1. ✅ Task 5.2 を正式に見送り
2. ✅ 分析ドキュメントを更新（結論追記）
3. ✅ Phase 3（統計機能）の準備開始
4. ✅ 包括的実装計画の更新

## トラブルシューティング

### エラー: "Class 'App\Models\Ledger' not found"

Tinkerのコンテキスト問題。以下を試す:

```php
use App\Models\Ledger;
use App\Models\AttachedFile;
```

### エラー: "syntax error, unexpected token"

PHPバージョンの問題。PHP 8.0以降が必要。

```bash
./vendor/bin/sail php --version
```

### 添付ファイルを持つ台帳が見つからない

Web UIで添付ファイル付きの台帳を作成するか、ファクトリで作成:

```php
$ledger = Ledger::factory()
    ->has(AttachedFile::factory()->count(1))
    ->create([
        'content_attached' => [
            1 => [
                'testhash' => [
                    'name' => 'test.pdf',
                    'path' => 'test/path.pdf',
                    'size' => 1024,
                    'mime' => 'application/pdf',
                ]
            ]
        ]
    ]);
```

---

**作成日:** 2025年10月4日  
**関連:** `docs/work/2025-10-04_MCP_Task5.2_AttachedFile_Analysis.md`
