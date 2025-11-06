# VLM/RAG統合 Phase3 - WBS1.0 既存チャンキング処理改修計画 (改訂版)

**ドキュメントID:** 2025-11-05_phase3-wbs1-refactoring-plan.md
**担当者:** (担当者名)
**作成日:** 2025年11月5日
**最終更新日:** 2025年11月6日（実装後修正）

**修正履歴:**
- 2025年11月6日: 実装完了後、`content_attached`の正しい構造（2階層）を反映
  - セクション3.1のコード例を実装結果に基づいて修正
  - `AsColumnArrayJson`キャストの特性を明記
  - 配列インデックス保持のためのカラムID初期化処理を追加
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [VLM/RAG統合 - Phase3 RAG統合実装 WBS (改訂版)](./2025-11-05_phase3-wbs.md)
- [構造化チャンキングによるベクトル化精度向上](./2025-10-21-phase1-wbs1-4-1-chunking-improvement-plan.md)

---

## 1. 目的

改訂版WBSのタスクID 1.0「既存チャンキング処理の改修」を達成するため、`app/Jobs/ProcessLedgerForRagJob.php` の詳細な改修計画を定義する。
この改修の目的は、既存の構造化Markdown生成ロジックに、VLM（Vision Language Model）の処理結果を優先的に組み込み、検索精度をさらに向上させることである。

---

## 2. 改修方針 (改訂)

`ProcessLedgerForRagJob` の責務を再設計し、VLMによる高品質なテキストをRAGパイプラインだけでなく、既存の全文検索（Mroonga）にも活用できるようにする。

1.  **データ準備フェーズの導入:**
    `handle` メソッドの冒頭で、VLMの解析結果 (`vlm_markdown`) と既存のTika/OCR抽出テキスト (`content_attached`) を比較・評価する「データ準備フェーズ」を新たに設ける。

2.  **`content_attached` の動的更新:**
    -   `AttachedFile` に `vlm_markdown` が存在し、かつその情報量が既存のTika/OCRテキストよりも多い（文字列長が長い）と判断された場合、`Ledger` モデルの `content_attached` カラムの該当ファイル部分を `vlm_markdown` の内容で**永続的に上書き**する。
    -   この更新により、VLMによる高精度なテキストが、後続のRAGチャンキング処理だけでなく、Mroongaを利用した通常のキーワード検索の対象にもなり、システム全体の検索品質が向上する。

3.  **チャンキング処理の責務分離:**
    -   データ準備フェーズの導入に伴い、`buildMarkdownFromLedger` メソッドは、VLMとTika/OCRのどちらのテキストソースを利用するかの判断ロジックを持つ必要がなくなる。
    -   `buildMarkdownFromLedger` は、**準備・更新済みの `Ledger` モデル**（特に `content_attached`）を信頼できる唯一の情報源 (Single Source of Truth) として、構造化Markdownを生成することに専念する。

---

## 3. 実装ステップ (改訂)

### 3.1. ステップ1: `handle` メソッドでのデータ準備ロジック実装 (WBS 1.1)

**対象ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

1.  **新プライベートメソッドの作成:**
    `private function updateContentAttachedWithVlmResult(): void` を作成する。このメソッドは、`handle` メソッドの冒頭（既存チャンクの削除前）で呼び出す。

2.  **`updateContentAttachedWithVlmResult` の実装:**
    -   `$this->ledger->load('attachedFiles')` で関連モデルをEager Loadする。
    -   `$this->ledger->attachedFiles` をループ処理する。
    -   **ループ内の処理:**
        -   各ファイル (`$file`) の `vlm_markdown` が空でないことを確認する。
        -   `$this->ledger->content_attached` から、`$file->hashedbasename` をキーとして既存のTika/OCRテキスト (`$existingText`) を取得する。
        -   `vlm_markdown` の文字列長が `$existingText` の文字列長より長いか比較する。
        -   **条件を満たす場合 (VLMが優位):**
            -   `$this->ledger->content_attached[$file->hashedbasename]['meta']['content']` を `vlm_markdown` の内容で更新する。
            -   更新があったことを示すフラグを立てる。
            -   ログに `content_attached` を更新した旨を記録する（ファイルID、VLMテキスト長、旧テキスト長など）。
    -   **ループ後の処理:**
        -   更新フラグが立っている場合のみ、`$this->ledger->save()` を実行し、データベースの変更を永続化する。

### 3.2. ステップ2: `buildMarkdownFromLedger` メソッドの簡素化 (WBS 1.2)

**対象ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

1.  **添付ファイル処理ロジックの簡素化:**
    -   `handle` メソッドで `content_attached` が最適化済みであることを前提とする。
    -   `buildMarkdownFromLedger` 内の添付ファイル処理では、`$this->ledger->content_attached` をループし、`['meta']['content']` の内容を連結する。
    -   各テキストの出典を明確にするため、`AttachedFile` モデルの `vlm_processed_at` タイムスタンプの有無を確認し、「(VLM解析結果)」または「(テキスト抽出結果)」というラベルを動的に付与する。
    -   テキスト長の制限と切り詰め処理は、このメソッド内に集約する。

**実装イメージ (改訂後):**
```php
// in ProcessLedgerForRagJob.php

// ... (use statements) ...
use App\Models\AttachedFile; // 追加

// ...

public function handle(EmbeddingService $embeddingService): void
{
    // ... (ログ設定)

    // ★★★ STEP 1: データ準備フェーズ ★★★
    $this->updateContentAttachedWithVlmResult();

    // 1. Delete existing chunks ...
    // ...
}

/**
 * ★★★ STEP 1で追加 ★★★
 * VLMの結果が優れている場合、content_attachedを更新する
 */
private function updateContentAttachedWithVlmResult(): void
{
    $logChannel = config('rag.log_channel', 'stack');
    $wasUpdated = false;
    $contentAttached = $this->ledger->content_attached ?? [];

    // 関連ファイルをEager Load
    $this->ledger->load('attachedFiles');
    
    // ★★★ 重要: content_attachedは2階層構造 ★★★
    // [column_id][hashedbasename]['meta']['content']
    // AsColumnArrayJsonキャストにより1階層目は強制的にインデックス配列になる
    
    // すべてのカラムIDの位置を初期化（AsColumnArrayJsonの要件）
    $columnDefines = $this->ledger->define->column_define;
    $maxColumnId = $columnDefines->max('id');
    for ($i = 0; $i <= $maxColumnId; $i++) {
        if (!isset($contentAttached[$i])) {
            $contentAttached[$i] = [];
        }
    }

    foreach ($this->ledger->attachedFiles as $file) {
        if (empty($file->vlm_markdown)) {
            continue;
        }

        $vlmText = $file->vlm_markdown;
        $vlmTextLength = mb_strlen($vlmText);
        $columnId = $file->column_id;

        // content_attachedにエントリが存在しない場合も更新対象
        $existingText = $contentAttached[$columnId][$file->hashedbasename]['meta']['content'] ?? '';
        $existingTextLength = mb_strlen($existingText);

        if ($vlmTextLength > $existingTextLength) {
            // VLMのテキストで上書き
            // 配列構造を確保
            if (!isset($contentAttached[$columnId])) {
                $contentAttached[$columnId] = [];
            }
            if (!isset($contentAttached[$columnId][$file->hashedbasename])) {
                $contentAttached[$columnId][$file->hashedbasename] = [];
            }
            if (!isset($contentAttached[$columnId][$file->hashedbasename]['meta'])) {
                $contentAttached[$columnId][$file->hashedbasename]['meta'] = [];
            }
            
            $contentAttached[$columnId][$file->hashedbasename]['meta']['content'] = $vlmText;
            $wasUpdated = true;

            Log::channel($logChannel)->info('[RAG Pre-processing] Updated content_attached with VLM result.', [
                'ledger_id' => $this->ledger->id,
                'file_id' => $file->id,
                'column_id' => $columnId,
                'hashedbasename' => $file->hashedbasename,
                'vlm_text_length' => $vlmTextLength,
                'old_text_length' => $existingTextLength,
            ]);
        }
    }

    if ($wasUpdated) {
        $this->ledger->content_attached = $contentAttached;
        Ledger::withoutEvents(fn () => $this->ledger->save());
        Log::channel($logChannel)->info('[RAG Pre-processing] Saved updated content_attached to database.', [
            'ledger_id' => $this->ledger->id,
        ]);
    }
}


/**
 * ★★★ STEP 2で簡素化 ★★★
 * Build structured Markdown text from ledger and its definition.
 */
private function buildMarkdownFromLedger(Ledger $ledger): string
{
    // ... (台帳本体の項目処理は変更なし) ...

    // 3. Add attached file content
    $attachedTexts = [];
    if (!empty($ledger->content_attached)) {
        // Eager load attachedFiles with ledger relation for original_filename accessor
        $ledger->load('attachedFiles');
        
        // ファイル情報を効率的に引くためにhashedbasenameをキーにした連想配列を作成
        $filesMap = $ledger->attachedFiles->keyBy('hashedbasename');

        // ★★★ 重要: content_attachedは2階層ループが必要 ★★★
        // [column_id][hashedbasename]['meta']['content']
        foreach ($ledger->content_attached as $columnId => $filesInColumn) {
            if (!is_array($filesInColumn)) {
                continue;
            }
            
            foreach ($filesInColumn as $hashedbasename => $contentData) {
                $file = $filesMap->get($hashedbasename);
                $originalFilename = $file ? $file->original_filename : $hashedbasename;
                $text = $contentData['meta']['content'] ?? '';

                if (empty($text)) {
                    continue;
                }

                // VLMで処理されたかどうかに基づいてラベルを決定
                $sourceLabel = ($file && !empty($file->vlm_processed_at)) ? 'VLM解析結果' : 'テキスト抽出結果';

                // 長さ制限ロジック
                $maxAttachedLength = config('rag.chunking.max_attached_text_length', 50000);
                if (mb_strlen($text) > $maxAttachedLength) {
                    $text = mb_substr($text, 0, $maxAttachedLength) . "

[...以降のテキストは省略されました]";
                    Log::channel(config('rag.log_channel', 'stack'))->warning('Attached text truncated for RAG', [
                        'ledger_id' => $ledger->id,
                        'file' => $originalFilename,
                    ]);
                }

                $attachedTexts[] = "### 添付ファイル: {$originalFilename} ({$sourceLabel})

{$text}";
            }
        }
    }

    if (!empty($attachedTexts)) {
        $lines[] = '---';
        $lines[] = '## 添付ファイル内容';
        $lines[] = implode("\n\n---\n", $attachedTexts);
    }

    return implode("\n", $lines);
}
```

### 3.3. ステップ3: テストの更新 (WBS 1.3) (改訂)

**対象ファイル:** `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`

1.  **テストケースの再設計:**
    -   **VLMが `content_attached` を更新するテスト:**
        -   **準備:** `vlm_markdown` が既存の `content_attached` テキストより長い `AttachedFile` を持つ `Ledger` を作成。
        -   **実行:** `ProcessLedgerForRagJob` をディスパッチする。
        -   **検証:**
            1.  `$ledger->refresh()` を実行。
            2.  `$ledger->content_attached` の該当部分が `vlm_markdown` の内容で更新されていることを `assertEquals` で検証。
            3.  `buildMarkdownFromLedger` メソッド（リフレクション経由で呼び出し）が返すMarkdownに、VLMの内容と「(VLM解析結果)」ラベルが含まれることを `assertStringContainsString` で検証。
    -   **VLMが `content_attached` を更新しないテスト (短い場合):**
        -   **準備:** `vlm_markdown` が既存テキストより短い `AttachedFile` を持つ `Ledger` を作成。
        -   **実行:** Jobをディスパッチ。
        -   **検証:** `content_attached` が変更されていないこと、Markdownには既存テキストと「(テキスト抽出結果)」ラベルが含まれることを検証。
    -   **`content_attached` がないファイルにVLMが適用されるテスト:**
        -   **準備:** `content_attached` にエントリがないが `vlm_markdown` を持つ `AttachedFile` を準備。
        -   **実行:** Jobをディスパッチ。
        -   **検証:** `content_attached` に `vlm_markdown` の内容で新しいエントリが追加されていること、Markdownにもその内容が含まれることを検証。

---

## 4. 結論

本改修計画に基づき `ProcessLedgerForRagJob` をリファクタリングすることで、VLMによる高品質なテキストデータをRAGパイプラインと全文検索の両方に活用できるようになる。これにより、添付ファイルを持つ台帳の検索精度が大幅に向上し、システム全体のデータ品質も向上することが期待される。