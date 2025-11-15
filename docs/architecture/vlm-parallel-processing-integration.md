# VLM/OCR並列処理統合アーキテクチャ

**作成日:** 2025年11月8日  
**ステータス:** 🔄 **Phase5 設計完了・実装準備中**  
**ドキュメント種別:** 公式アーキテクチャ文書  

**関連ドキュメント:**
- [並列処理提案書](../work/vlm-rag-integration/2025-11-08_parallel-processing-proposal.md)
- [Phase4実装完了レポート](../work/vlm-rag-integration/2025-11-08_phase4-id3-implementation-report.md)
- [VLM-OCR技術調査](./vlm-ocr-technology-selection.md)

---

## 📋 エグゼクティブサマリー

本文書は、VLM（Visual Language Model）とOCRの**並列処理統合アーキテクチャ**を定義します。

### 🎯 設計方針

**✅ 並列処理**: VLMとOCRを同時実行し、処理時間を最大50%削減  
**✅ スケジュール最終化**: 1分ごとの定期処理でインデックス空白期間を最小化  
**✅ 結果優先順位**: VLM > OCR > Tika の順で最高品質データを自動選択  
**✅ 冗長性**: 片方が失敗しても他方で補完可能

### 🔄 アーキテクチャ概要

```
ファイルアップロード
  ↓
Tika処理（基本メタデータ・テキスト抽出）
  ↓
並列ディスパッチ（画像/PDF対象）
  ├─ VLM処理（構造化・Markdown生成）
  └─ OCR処理（テキストレイヤー追加）
  
★スケジューラー（1分ごと）
  ↓
FinalizeProcessing コマンド
  - VLM/OCR完了ファイルを検索
  - 最適な結果を選択
  - content_attached更新
  - インデックス構築
```

---

## 1. 処理フロー詳細設計

### 1.0. ファイルタイプ別処理フロー一覧

**重要:** Phase5並列処理アーキテクチャでは、ファイルタイプによって処理フローが異なります。

| ファイルタイプ | MIME | Tika | VLM | OCR | ファイル名変更 | 最終source | 備考 |
|--------------|------|------|-----|-----|---------------|-----------|------|
| **画像（JPG/PNG/GIF）** | image/* | 空（メタデータのみ） | ✅ Markdown生成 | ✅ PDF化+テキスト抽出 | ✅ image.jpg→image.pdf | vlm > ocr > tika | VLM優先、OCRはフォールバック |
| **テキスト付きPDF** | application/pdf | ✅ テキスト抽出 | ✅ Markdown生成 | ✅ 最適化のみ（skip-text） | ❌ document.pdf→document.pdf | vlm > tika | OCRは最適化のみ、テキスト抽出なし |
| **画像のみPDF（スキャン）** | application/pdf | 空（メタデータのみ） | ✅ Markdown生成 | ✅ PDF化+テキスト抽出 | ❌ scan.pdf→scan.pdf | vlm > ocr > tika | OCRがテキスト抽出を実行 |
| **Office文書（DOCX/XLSX/PPTX）** | application/vnd.* | ✅ テキスト抽出 | ❌ 非対象 | ❌ 非対象 | ❌ | tika | VLM/OCR非対象、Tikaのみ |
| **テキストファイル（TXT/CSV）** | text/* | ✅ テキスト抽出 | ❌ 非対象 | ❌ 非対象 | ❌ | tika | VLM/OCR非対象、即座に最終化 |
| **バイナリファイル（ZIP/EXE）** | application/zip等 | メタデータのみ | ❌ 非対象 | ❌ 非対象 | ❌ | tika | テキスト抽出なし |

**処理フローの分岐条件:**
```php
// app/Models/AttachedFile.php
public function isVlmOrOcrTarget(): bool
{
    return str_starts_with($this->mime, 'image/') 
        || str_starts_with($this->mime, 'application/pdf');
}
```

**キー命名規則:**
- **元のキー:** `content_attached[$columnId][$hashedbasename]`
  - 例: `content_attached[1]['abc123.jpg']`, `content_attached[2]['def456.pdf']`
- **OCR後のキー（画像のみ）:** `content_attached[$columnId][$hashedbasename_without_ext . '.pdf']`
  - 例: `abc123.jpg` → `content_attached[1]['abc123.pdf']`
  - 注意: PDF→PDFの場合は元のキーが上書きされる

---

## 2. データベース設計

### 2.1. 新規カラム

```sql
ALTER TABLE attached_files
  ADD COLUMN tika_processed_at TIMESTAMP NULL COMMENT 'Tika処理完了日時',
  ADD COLUMN vlm_processed_at TIMESTAMP NULL COMMENT 'VLM処理成功日時',
  ADD COLUMN vlm_failed_at TIMESTAMP NULL COMMENT 'VLM処理失敗日時',
  ADD COLUMN ocr_processed_at TIMESTAMP NULL COMMENT 'OCR処理成功日時',
  ADD COLUMN ocr_failed_at TIMESTAMP NULL COMMENT 'OCR処理失敗日時',
  ADD COLUMN processing_finalized_at TIMESTAMP NULL COMMENT '最終化完了日時',
  
  ADD INDEX idx_finalization (
    tika_processed_at,
    processing_finalized_at,
    vlm_processed_at,
    vlm_failed_at,
    ocr_processed_at,
    ocr_failed_at
  );
```

### 2.2. 処理状態の判定

```php
// AttachedFile モデル
public function getProcessingStatusAttribute(): array
{
    return [
        'tika_completed' => $this->tika_processed_at !== null,
        'vlm_completed' => $this->vlm_processed_at !== null,
        'vlm_failed' => $this->vlm_failed_at !== null,
        'ocr_completed' => $this->ocr_processed_at !== null,
        'ocr_failed' => $this->ocr_failed_at !== null,
        'finalized' => $this->processing_finalized_at !== null,
    ];
}

public function isReadyForFinalization(): bool
{
    // 画像/PDF以外はTika完了のみで最終化可能
    if (!$this->isVlmOrOcrTarget()) {
        return $this->tika_processed_at !== null;
    }
    
    // 両方完了（成功/失敗問わず）
    $vlmDone = $this->vlm_processed_at || $this->vlm_failed_at;
    $ocrDone = $this->ocr_processed_at || $this->ocr_failed_at;
    
    return $this->tika_processed_at && $vlmDone && $ocrDone;
}

public function isVlmOrOcrTarget(): bool
{
    return str_starts_with($this->mime, 'image/') 
        || str_starts_with($this->mime, 'application/pdf');
}
```

---

## 3. キュー設定

### 3.1. キュー構成

```
default (4 workers)
  - ProcessAttachedFile
  - ProcessLedgerForRagJob
  - その他一般ジョブ

vlm (2 workers)
  - ProcessVlmExtraction

ocr (2 workers)
  - OcrAndOptimizeFile
```

### 3.2. Supervisor設定

```ini
[program:ledgerleap-queue-default]
command=/usr/bin/php /var/www/html/artisan queue:work --queue=default --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=4
autostart=true
autorestart=true

[program:ledgerleap-queue-vlm]
command=/usr/bin/php /var/www/html/artisan queue:work --queue=vlm --tries=2 --timeout=300
process_name=%(program_name)s_%(process_num)02d
numprocs=2
autostart=true
autorestart=true

[program:ledgerleap-queue-ocr]
command=/usr/bin/php /var/www/html/artisan queue:work --queue=ocr --tries=2 --timeout=600
process_name=%(program_name)s_%(process_num)02d
numprocs=2
autostart=true
autorestart=true
```

---

## 4. RAG統合

### 4.1. Chunking処理

**タイミング:** FinalizeProcessing完了後、ProcessLedgerForRagJobがディスパッチ

**処理内容:**
1. `attached_files`のVLM結果を取得
2. VLM Markdown優先でChunk生成
3. `ledger_chunks`テーブルに保存

**実装:**
```php
// ChunkingService::createChunksFromLedger()
foreach ($ledger->attachedFiles as $file) {
    // VLM結果優先
    $content = $file->vlm_markdown 
             ?? $ledger->content_attached[$file->column_id][$file->filename]['meta']['content']
             ?? '';
    
    $chunks = $this->splitIntoChunks($content);
    
    foreach ($chunks as $index => $text) {
        LedgerChunk::create([
            'ledger_id' => $ledger->id,
            'attached_file_id' => $file->id,
            'chunk_index' => $index,
            'chunk_text' => $text,
            'source_type' => $file->vlm_markdown ? 'vlm' : 'tika',
        ]);
    }
}
```

### 4.2. Embedding生成

**タイミング:** Chunk作成後、GenerateEmbeddingJobがディスパッチ

**処理内容:**
1. Embeddingコンテナに送信
2. ベクトルを取得
3. `ledger_chunks.embedding`に保存

---

## 5. UI/UX設計

### 5.1. ステータス表示

**処理中の表示例:**
```
📄 file.png
  ├─ Tika処理: ✅ 完了
  ├─ VLM処理: 🔄 処理中
  └─ OCR処理: 🔄 処理中
```

**完了後の表示例:**
```
📄 file.png ✅ 完了
  ├─ 使用結果: VLM (高品質)
  ├─ VLM信頼度: 95.3%
  └─ 最終化: 1分前
```

### 5.2. プレビュー機能

- VLM結果プレビューボタン（Phase4実装済み）
- Markdown表示モーダル
- ダウンロード機能（Markdown/JSON）

---

## 6. 運用・監視

### 6.1. ログ確認

```bash
# 最終化処理のログ
tail -f storage/logs/laravel.log | grep "\[Finalize\]"

# VLM処理のログ
tail -f storage/logs/queue.log | grep "\[VLM\]"

# OCR処理のログ
tail -f storage/logs/queue.log | grep "\[OCR\]"
```

### 6.2. 手動最終化

```bash
# 通常実行
php artisan ledger:finalize-processing

# タイムアウト短縮（300秒）
php artisan ledger:finalize-processing --timeout=300

# 処理件数増加（100件）
php artisan ledger:finalize-processing --limit=100
```

### 6.3. 監視ポイント

- 最終化待ちファイル数
- VLM/OCR処理時間
- 失敗率
- キュー滞留数

---

## 7. メリットとトレードオフ

### 7.1. メリット

✅ **処理時間50%削減**: 並列実行により待ち時間を大幅短縮  
✅ **インデックス空白最大1分**: スケジュール方式で確実に更新  
✅ **結果の冗長性**: VLM/OCR片方失敗しても補完可能  
✅ **最高品質データ**: VLM優先で自動選択  
✅ **タイムアウト処理**: スケジューラーで確実に処理  
✅ **システム安定性**: キュー詰まりに影響されない  

### 7.2. トレードオフ

⚠️ **複雑性増加**: 処理フローが増加（スケジューラーで吸収）  
⚠️ **リソース消費**: 並列実行により同時リソース使用（専用キューで制限）  
⚠️ **デバッグ難易度**: 非同期処理の追跡（詳細ログで対応）  

---

## 8. 移行計画

Phase5として実装します。詳細は別途WBSドキュメントを参照してください。

---

## 9. 重要な実装上の注意事項（Phase5/6で判明）

### 9.1. ファイル名変更とキー管理

**OCR処理によるファイル名変更:**
```php
// OcrAndOptimizeFile.php
$outputFileName = pathinfo($this->attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf';
// abc123.jpg → abc123.pdf
// def456.pdf → def456.pdf (変更なし)
```

**content_attachedのキー管理:**

| 元ファイル | OCR後のファイル名 | content_attachedのキー | 挙動 |
|----------|-----------------|----------------------|------|
| `image.jpg` | `image.pdf` | `content_attached[1]['image.pdf']` | 新しいキー作成、元のキー削除 |
| `document.pdf` | `document.pdf` | `content_attached[2]['document.pdf']` | 元のキー上書き |

**重要:** PDFファイルの場合、OCR処理は最適化のみで新しいテキスト抽出は行われません（`--skip-text`オプション使用）。

### 9.2. 最終化処理の結果選択ロジック

**VLM > OCR > Tikaの優先順位:**

```php
// FinalizeAttachedFileProcessing.php: selectBestContent()
private function selectBestContent(AttachedFile $file): array
{
    // 1. VLM結果（最優先）
    if ($file->vlm_processed_at && !empty($file->vlm_markdown)) {
        return ['source' => 'vlm', 'text' => $file->vlm_markdown];
    }
    
    // 2. OCR結果の厳密確認
    if ($file->ocr_processed_at) {
        $originalExt = pathinfo($file->hashedbasename, PATHINFO_EXTENSION);
        $isImageFile = str_starts_with($file->original_mime_type ?? '', 'image/');
        
        // 画像ファイルの場合のみ .pdf キーをチェック
        if ($isImageFile && $originalExt !== 'pdf') {
            $pdfHashedbasename = pathinfo($file->hashedbasename, PATHINFO_FILENAME) . '.pdf';
            $ocrText = $file->ledger?->content_attached[$file->column_id][$pdfHashedbasename]['meta']['content'] ?? null;
            
            if (!empty($ocrText)) {
                return ['source' => 'ocr', 'text' => $ocrText];
            }
        }
        
        // PDFファイルの場合は元のキーをチェック
        // OCRは最適化のみなので、Tika再処理で更新されたテキストを使用
    }
    
    // 3. Tika結果（フォールバック）
    $tikaText = $this->extractTikaTextFromContentAttached($file);
    return ['source' => 'tika', 'text' => $tikaText ?? ''];
}
```

**ロジックの理由:**
- **画像ファイル:** OCRで`.pdf`に変換されるため、新しいキーを探す
- **PDFファイル:** OCRは最適化のみ（`--skip-text`）で、元のキーが上書きされる。Tikaが再処理したテキストを使用

### 9.3. プレビュー機能の実装（Phase6）

**hasPreviewableText()の実装:**
```php
// AttachedFile.php
public function hasPreviewableText(): bool
{
    if (!$this->processing_finalized_at || !$this->finalized_source) {
        return false;
    }
    
    // VLMの場合
    if ($this->finalized_source === 'vlm') {
        return !empty($this->vlm_markdown);
    }
    
    if (!$this->relationLoaded('ledger') || !$this->ledger) {
        return false;
    }
    
    $contentAttached = $this->ledger->content_attached;
    $columnId = $this->column_id;
    $hashedbasename = $this->hashedbasename;
    
    // OCRの場合: .pdf付きと元の両方のキーをチェック
    if ($this->finalized_source === 'ocr') {
        $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME) . '.pdf';
        
        return isset($contentAttached[$columnId][$pdfHashedbasename]['meta']['content'])
            || isset($contentAttached[$columnId][$hashedbasename]['meta']['content']);
    }
    
    // Tikaの場合: 元のキーのみをチェック
    if ($this->finalized_source === 'tika') {
        return isset($contentAttached[$columnId][$hashedbasename]['meta']['content']);
    }
    
    return false;
}
```

**getOcrTikaFormattedText()の実装:**
```php
// AttachedFile.php
private function getOcrTikaFormattedText(): ?string
{
    if (!$this->relationLoaded('ledger') || !$this->ledger) {
        return null;
    }
    
    $columnId = $this->column_id;
    $hashedbasename = $this->hashedbasename;
    
    // OCRの場合は .pdf キーもチェック
    if ($this->finalized_source === 'ocr') {
        $originalExt = pathinfo($hashedbasename, PATHINFO_EXTENSION);
        if ($originalExt !== 'pdf') {
            $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME) . '.pdf';
            $text = $this->ledger->content_attached[$columnId][$pdfHashedbasename]['meta']['content'] ?? null;
            if ($text) {
                return "```\n{$text}\n```";
            }
        }
    }
    
    // 元のキーをチェック
    $text = $this->ledger->content_attached[$columnId][$hashedbasename]['meta']['content'] ?? null;
    return $text ? "```\n{$text}\n```" : null;
}
```

**重要:** AsColumnArrayJsonキャストの制約により、`data_get()`は使用できません。直接配列アクセス（`$ledger->content_attached[$columnId][$key]`）を使用してください。

### 9.4. ファイルタイプ別の最適な処理戦略

| ファイルタイプ | 推奨処理 | 理由 |
|--------------|---------|------|
| **写真・スキャン画像** | VLM優先 | 構造化データ抽出、高品質Markdown |
| **テキスト付きPDF** | VLM優先、Tikaフォールバック | VLMで構造保持、Tikaでプレーンテキスト |
| **画像のみPDF** | VLM優先、OCRフォールバック | VLMで構造保持、OCRで確実なテキスト抽出 |
| **Office文書** | Tikaのみ | VLM/OCR不要、Tikaが最適 |
| **テキストファイル** | Tikaのみ | 即座に処理完了 |

---

**作成者:** GitHub Copilot CLI  
**最終更新:** 2025年11月15日（Phase6実装反映）  
**バージョン:** 3.1（ファイルタイプ別処理フロー明記版）
