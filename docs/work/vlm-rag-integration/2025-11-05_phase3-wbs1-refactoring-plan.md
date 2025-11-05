# VLM/RAG統合 Phase3 - WBS1.0 既存チャンキング処理改修計画

**ドキュメントID:** 2025-11-05_phase3-wbs1-refactoring-plan.md
**担当者:** (担当者名)
**作成日:** 2025年11月5日
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [VLM/RAG統合 - Phase3 RAG統合実装 WBS (改訂版)](./2025-11-05_phase3-wbs.md)
- [構造化チャンキングによるベクトル化精度向上](./2025-10-21-phase1-wbs1-4-1-chunking-improvement-plan.md)

---

## 1. 目的

改訂版WBSのタスクID 1.0「既存チャンキング処理の改修」を達成するため、`app/Jobs/ProcessLedgerForRagJob.php` の詳細な改修計画を定義する。
この改修の目的は、既存の構造化Markdown生成ロジックに、VLM（Vision Language Model）の処理結果を優先的に組み込み、検索精度をさらに向上させることである。

---

## 2. 改修方針

`ProcessLedgerForRagJob` 内の `buildMarkdownFromLedger` メソッドを拡張し、添付ファイル部分のテキストソース選択ロジックを変更する。具体的には、`AttachedFile` モデルに `vlm_markdown` が存在する場合はそれを最優先で利用し、存在しない場合にのみ、従来の `content_attached` (Tika/OCR抽出テキスト) を利用するフォールバック機構を実装する。

---

## 3. 実装ステップ

### 3.1. ステップ1: `buildMarkdownFromLedger` メソッドの改修 (WBS 1.1)

**対象ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

1.  **添付ファイル処理ロジックの変更:**
    現在、`content_attached` を直接利用している部分を、`$ledger->attachedFiles` リレーションをループ処理する形に変更する。

2.  **VLM結果の優先利用:**
    ループ内で、各 `$file` (`AttachedFile`モデル) の `vlm_markdown` カラムが空でないかを確認する。
    -   **`vlm_markdown` が存在する場合:**
        -   その内容をMarkdownテキストに追加する。
        -   ログにVLMのMarkdownを使用した旨を記録する（ファイルID、モデル名、信頼度など）。
    -   **`vlm_markdown` が存在しない場合 (フォールバック):**
        -   後述のステップ2で実装する共通メソッドを呼び出し、`content_attached` からTika/OCRテキストを取得してMarkdownテキストに追加する。

**実装イメージ:**
```php
// in ProcessLedgerForRagJob::buildMarkdownFromLedger()

// ... (台帳本体の項目を処理) ...

$attachedTexts = [];
foreach ($this->ledger->attachedFiles as $file) {
    if (!empty($file->vlm_markdown)) {
        // VLM結果を優先
        $attachedTexts[] = "### 添付ファイル: {$file->original_filename} (VLM解析結果)\n\n{$file->vlm_markdown}";
        Log::info('[RAG Chunking] Using VLM markdown.', ['file_id' => $file->id]);
    } else {
        // フォールバック処理
        $tikaText = $this->extractTikaTextFromFile($file);
        if ($tikaText) {
            $attachedTexts[] = "### 添付ファイル: {$file->original_filename} (テキスト抽出結果)\n\n{$tikaText}";
            Log::info('[RAG Chunking] Falling back to Tika/OCR text.', ['file_id' => $file->id]);
        }
    }
}

if (!empty($attachedTexts)) {
    $markdownParts[] = "---";
    $markdownParts[] = "## 添付ファイル内容";
    $markdownParts[] = implode("\n\n---
", $attachedTexts);
}

// ... (Markdownを結合して返す)
```

### 3.2. ステップ2: Tika/OCRテキスト抽出ロジックの共通化 (WBS 1.2)

**対象ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

1.  **新プライベートメソッド作成:**
    `private function extractTikaTextFromFile(AttachedFile $file): ?string` を作成する。

2.  **ロジックの実装:**
    -   `$this->ledger->content_attached` の中から、引数で受け取った `$file` の `hashedbasename` をキーとしてテキストコンテンツを検索する。
    -   テキストが見つかれば、既存の長さ制限ロジック（`config('rag.chunking.max_attached_text_length')`）を適用し、制限を超えた場合は切り詰めて警告ログを出力する。
    -   最終的なテキストを返す。

### 3.3. ステップ3: テストの更新 (WBS 1.3)

**対象ファイル:** `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`

1.  **テストケースの追加:**
    -   **VLMありのテスト:** `AttachedFile` に `vlm_markdown` を設定した台帳を準備し、生成されるMarkdownにVLMの内容が含まれることを検証するテストを追加する。
    -   **VLMなしのテスト:** `vlm_markdown` が `null` の `AttachedFile` を持つ台帳を準備し、`content_attached` の内容がフォールバックとして利用されることを検証するテストを追加する。
    -   **混在テスト:** VLMあり/なしの添付ファイルが混在する台帳で、それぞれが正しく処理されることを検証するテストを追加する。

2.  **ファクトリの活用:**
    `AttachedFileFactory` を利用して、テストデータ（`vlm_markdown` の有無）を簡単に作成できるようにする。

**テストコードのイメージ:**
```php
// tests/Feature/Jobs/ProcessLedgerForRagJobTest.php

#[Test]
public function it_prioritizes_vlm_markdown_when_available()
{
    // 準備: vlm_markdownを持つAttachedFileを作成
    $ledger = Ledger::factory()->create();
    AttachedFile::factory()->for($ledger)->create([
        'vlm_markdown' => '## VLM解析結果\nこれはVLMによるテキストです。',
        'hashedbasename' => 'file1.pdf',
    ]);
    $ledger->content_attached = ['file1.pdf' => ['meta' => ['content' => 'これは古いTikaテキストです。']]];
    $ledger->save();

    // 実行
    $job = new ProcessLedgerForRagJob($ledger);
    $markdown = $this->invokeMethod($job, 'buildMarkdownFromLedger', [$ledger]);

    // 検証
    $this->assertStringContainsString('これはVLMによるテキストです。', $markdown);
    $this->assertStringNotContainsString('これは古いTikaテキストです。', $markdown);
}

#[Test]
public function it_falls_back_to_tika_text_when_vlm_is_unavailable()
{
    // 準備: vlm_markdownがnullのAttachedFileを作成
    // ...

    // 検証
    $this->assertStringNotContainsString('VLM', $markdown);
    $this->assertStringContainsString('これは古いTikaテキストです。', $markdown);
}
```

---

## 4. 結論

本改修計画に基づき `ProcessLedgerForRagJob` をリファクタリングすることで、既存の構造化チャンキング機能の利点を維持しつつ、VLMによる高品質なテキストデータを優先的にRAGパイプラインに組み込むことが可能となる。これにより、添付ファイルを持つ台帳の検索精度が大幅に向上することが期待される。
