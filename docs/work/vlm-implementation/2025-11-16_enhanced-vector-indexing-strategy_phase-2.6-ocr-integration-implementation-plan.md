# Phase 2.6: 複数OCR結果の統合戦略 - 実装計画

**作成日:** 2025年11月16日  
**ドキュメント種別:** Phase 2.6 実装計画書  
**ステータス:** 実装準備完了  

---

## 1. 設計方針

### 1.1. ファイルタイプ別の最適化戦略

**重要な認識:**
- **オフィスファイル**: Tikaが最高品質 → OCR/VLM不要
- **画像/スキャン**: VLMが最高品質 → 段階的向上

✅ **ファイルタイプ別の処理:**

| ファイルタイプ | Tika | OCR | VLM | 最適品質 |
|--------------|------|-----|-----|---------|
| Word/Excel/PPT | ⭐⭐⭐⭐⭐ | 不要 | 不要 | **FINALIZED_BY_TIKA** |
| PDF (text) | ⭐⭐⭐⭐⭐ | 不要 | 不要 | **FINALIZED_BY_TIKA** |
| PDF (scan) | ⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | **FINALIZED_BY_VLM** |
| 画像 (JPG/PNG) | ⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | **FINALIZED_BY_VLM** |
| 混在PDF | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | **FINALIZED_BY_VLM** |

✅ **優先順位ロジックの改善:**
```
オフィスファイル: 
  Tika完了 → FINALIZED_BY_TIKA (完了、OCR/VLMスキップ)

画像/スキャンファイル:
  Tika完了 → FINALIZED_BY_TIKA (即座に検索可能)
    ↓ OCR完了
  FINALIZED_BY_OCR (精度向上)
    ↓ VLM完了
  FINALIZED_BY_VLM (最高品質)
```

---

## 2. 新規ステータスの追加

### 2.1. AttachedFileStatusへの追加

```php
<?php

namespace App\Enums;

enum AttachedFileStatus: string
{
    // ... 既存のステータス ...
    
    // Phase 2.6: ソース別ファイナライズステータス
    case FINALIZED_BY_TIKA = 'finalized_by_tika';
    case FINALIZED_BY_OCR = 'finalized_by_ocr';
    case FINALIZED_BY_VLM = 'finalized_by_vlm';
    
    public function icon(): string
    {
        return match ($this) {
            // ... 既存のマッチング ...
            self::FINALIZED_BY_TIKA => 'fa-solid fa-circle-check',
            self::FINALIZED_BY_OCR => 'fa-solid fa-circle-check',
            self::FINALIZED_BY_VLM => 'fa-solid fa-circle-check',
        };
    }
    
    public function colorClass(): string
    {
        return match ($this) {
            // ... 既存のマッチング ...
            self::FINALIZED_BY_TIKA => 'text-success',
            self::FINALIZED_BY_OCR => 'text-success',
            self::FINALIZED_BY_VLM => 'text-success',
        };
    }
    
    public function tooltip(): string
    {
        return match ($this) {
            // ... 既存のマッチング ...
            self::FINALIZED_BY_TIKA => __('ledger.uploadedFile.status.finalized_by_tika'),
            self::FINALIZED_BY_OCR => __('ledger.uploadedFile.status.finalized_by_ocr'),
            self::FINALIZED_BY_VLM => __('ledger.uploadedFile.status.finalized_by_vlm'),
        };
    }
    
    /**
     * ファイナライズ済みか判定
     */
    public function isFinalized(): bool
    {
        return in_array($this, [
            self::FINALIZED_BY_TIKA,
            self::FINALIZED_BY_OCR,
            self::FINALIZED_BY_VLM,
            self::FINALIZED, // 既存の互換性
        ]);
    }
    
    /**
     * より良いソースで上書き可能か判定（ファイルタイプ考慮）
     * 
     * @param string $newSource 新しいソース ('tika', 'ocr', 'vlm')
     * @param AttachedFile $file ファイルオブジェクト
     */
    public function canUpgradeWith(string $newSource, AttachedFile $file): bool
    {
        // オフィスファイルの場合、Tikaが最高品質
        if ($this->isOfficeFile($file->mime)) {
            // FINALIZED_BY_TIKAの場合、上書き不要
            return false;
        }
        
        // 画像/スキャンファイルの場合、通常の優先順位
        $currentPriority = match ($this) {
            self::FINALIZED_BY_TIKA => 1,
            self::FINALIZED_BY_OCR => 2,
            self::FINALIZED_BY_VLM => 3,
            default => 0,
        };
        
        $newPriority = match ($newSource) {
            'tika' => 1,
            'ocr' => 2,
            'vlm' => 3,
            default => 0,
        };
        
        return $newPriority > $currentPriority;
    }
    
    /**
     * オフィスファイルか判定
     */
    private function isOfficeFile(string $mime): bool
    {
        $officeMimes = [
            'application/pdf',                      // PDF
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // Excel
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint
            'application/msword',                   // Word (旧)
            'application/vnd.ms-excel',             // Excel (旧)
            'application/vnd.ms-powerpoint',        // PowerPoint (旧)
            'text/plain',                           // テキスト
            'text/csv',                             // CSV
        ];
        
        return in_array($mime, $officeMimes);
    }
}
```

### 2.2. 言語ファイルへの追加

```php
// lang/ja/ledger.php

'uploadedFile' => [
    'status' => [
        // ... 既存の翻訳 ...
        'finalized_by_tika' => 'テキスト抽出完了（基本）',
        'finalized_by_ocr' => 'テキスト抽出完了（OCR）',
        'finalized_by_vlm' => 'テキスト抽出完了（高精度）',
    ],
],
```

---

## 3. 実装設計

### 3.1. VectorizeAttachedFile（イベント駆動）

```php
<?php

namespace App\Jobs\Embedding;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Services\Embedding\KeywordEnhancedTextGenerator;
use App\Services\Embedding\UnifiedOcrAggregator;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * ファイルのOCR結果をベクトル化
 * 
 * 各OCR処理完了時に即座に呼ばれる
 */
class VectorizeAttachedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function __construct(
        public int $attachedFileId,
        public string $source  // 'tika', 'ocr', 'vlm'
    ) {}
    
    public function handle(
        EmbeddingService $embeddingService,
        KeywordEnhancedTextGenerator $keywordGenerator
    ): void {
        $file = AttachedFile::find($this->attachedFileId);
        
        if (!$file) {
            return;
        }
        
        $logChannel = config('rag.log_channel', 'stack');
        
        // 既にファイナライズ済みで、より良いソースでない場合はスキップ
        if ($file->status->isFinalized() && !$file->status->canUpgradeWith($this->source)) {
            Log::channel($logChannel)->info('[Vectorization] Skip, already better quality', [
                'file_id' => $file->id,
                'current' => $file->status->value,
                'new_source' => $this->source,
            ]);
            return;
        }
        
        // ベクトル化実行
        $this->vectorize($file, $embeddingService, $keywordGenerator);
    }
    
    /**
     * ベクトル化を実行
     */
    private function vectorize(
        AttachedFile $file,
        EmbeddingService $embeddingService,
        KeywordEnhancedTextGenerator $keywordGenerator
    ): void {
        $logChannel = config('rag.log_channel', 'stack');
        
        try {
            $aggregator = new UnifiedOcrAggregator($keywordGenerator, $embeddingService);
            $result = $aggregator->generateVector($file);
            
            // ProcessLedgerForRagJobをトリガー
            \App\Jobs\ProcessLedgerForRagJob::dispatch($file->ledger_id);
            
            // ステータスをソース別に更新
            $newStatus = match ($this->source) {
                'tika' => AttachedFileStatus::FINALIZED_BY_TIKA,
                'ocr' => AttachedFileStatus::FINALIZED_BY_OCR,
                'vlm' => AttachedFileStatus::FINALIZED_BY_VLM,
                default => AttachedFileStatus::FINALIZED,
            };
            
            $file->update([
                'status' => $newStatus,
                'processing_finalized_at' => now(),
                'finalized_source' => $this->source,
            ]);
            
            Log::channel($logChannel)->info('[Vectorization] Completed', [
                'file_id' => $file->id,
                'source' => $this->source,
                'status' => $newStatus->value,
            ]);
            
        } catch (\Exception $e) {
            Log::channel($logChannel)->error('[Vectorization] Failed', [
                'file_id' => $file->id,
                'source' => $this->source,
                'error' => $e->getMessage(),
            ]);
            
            $file->update(['status' => AttachedFileStatus::PROCESSING_FAILED]);
            
            throw $e;
        }
    }
}
```

### 3.2. ProcessAttachedFile（Tika処理）

**Tika完了後、即座にベクトル化:**

```php
// app/Jobs/Ledger/ProcessAttachedFile.php

public function handle(): void
{
    // ... 既存のTika処理 ...
    
    // Tika完了
    $this->attachedFile->update([
        'tika_processed_at' => now(),
    ]);
    
    // ★ 即座にベクトル化（検索可能に）
    \App\Jobs\Embedding\VectorizeAttachedFile::dispatch(
        $this->attachedFile->id,
        'tika'
    );
    
    // VLM/OCRをディスパッチ（非同期で品質向上）
    if ($this->shouldProcessVlm()) {
        ProcessVlmExtraction::dispatch($this->attachedFile);
    }
    
    if ($this->shouldProcessOcr()) {
        OcrAndOptimizeFile::dispatch($this->attachedFile);
    }
}
```

### 3.3. OcrAndOptimizeFile（OCR処理）

**OCR完了後、即座に上書き:**

```php
// app/Jobs/Ledger/OcrAndOptimizeFile.php

public function handle(): void
{
    // ... 既存のOCR処理 ...
    
    // OCR完了
    $this->attachedFile->update([
        'ocr_processed_at' => now(),
    ]);
    
    // ★ 即座にベクトル化（Tikaより高品質で上書き）
    \App\Jobs\Embedding\VectorizeAttachedFile::dispatch(
        $this->attachedFile->id,
        'ocr'
    );
}
```

### 3.4. ProcessVlmExtraction（VLM処理）

**VLM完了後、即座に上書き:**

```php
// app/Jobs/Vlm/ProcessVlmExtraction.php

public function handle(): void
{
    // ... 既存のVLM処理 ...
    
    // VLM完了
    $this->attachedFile->update([
        'vlm_processed_at' => now(),
    ]);
    
    // ★ 即座にベクトル化（最高品質で上書き）
    \App\Jobs\Embedding\VectorizeAttachedFile::dispatch(
        $this->attachedFile->id,
        'vlm'
    );
}
```

---

## 4. UnifiedOcrAggregator（変更なし）

```php
<?php

namespace App\Services\Embedding;

use App\Models\AttachedFile;
use App\Services\EmbeddingService;

class UnifiedOcrAggregator
{
    private KeywordEnhancedTextGenerator $keywordGenerator;
    private EmbeddingService $embeddingService;
    
    public function __construct(
        KeywordEnhancedTextGenerator $keywordGenerator,
        EmbeddingService $embeddingService
    ) {
        $this->keywordGenerator = $keywordGenerator;
        $this->embeddingService = $embeddingService;
    }
    
    public function generateVector(AttachedFile $file): array
    {
        $text = $this->selectBestText($file);
        
        if (empty($text)) {
            throw new \RuntimeException('No OCR text available');
        }
        
        $enhancedText = $this->keywordGenerator->generateEnhancedText($text);
        $vector = $this->embeddingService->embed($enhancedText, 'passage');
        
        return [
            'vector' => $vector,
            'enhanced_text' => $enhancedText,
            'source' => $this->getSource($file),
        ];
    }
    
    private function selectBestText(AttachedFile $file): string
    {
        // VLM > OCR > Tika
        if ($file->vlm_processed_at && !empty($file->vlm_markdown)) {
            return $file->vlm_markdown;
        }
        
        $ledger = $file->ledger;
        if (!$ledger) {
            return '';
        }
        
        $contentAttached = $ledger->content_attached ?? [];
        $columnId = $file->column_id;
        
        return $contentAttached[$columnId][$file->hashedbasename]['meta']['content'] ?? '';
    }
    
    private function getSource(AttachedFile $file): string
    {
        if ($file->vlm_processed_at) return 'vlm';
        if ($file->ocr_processed_at) return 'ocr';
        if ($file->tika_processed_at) return 'tika';
        return 'unknown';
    }
}
```

---

## 5. 状態遷移の例

### シナリオ1: 全て正常完了

```
T=0s:   PENDING_INITIAL_PROCESSING
T=1s:   INITIAL_PROCESSING → Tika処理
T=2s:   Tika完了 → FINALIZED_BY_TIKA ✅ 検索可能
T=3s:   VLM/OCRディスパッチ
T=30s:  OCR完了 → FINALIZED_BY_OCR ✅ 精度向上
T=60s:  VLM完了 → FINALIZED_BY_VLM ✅ 最高品質
```

### シナリオ2: VLM失敗

```
T=0s:   PENDING_INITIAL_PROCESSING
T=1s:   INITIAL_PROCESSING → Tika処理
T=2s:   Tika完了 → FINALIZED_BY_TIKA ✅ 検索可能
T=3s:   VLM/OCRディスパッチ
T=30s:  OCR完了 → FINALIZED_BY_OCR ✅ 精度向上
T=60s:  VLM失敗 → ステータス変更なし（FINALIZED_BY_OCRのまま）
```

### シナリオ3: OCR遅延、VLM先行完了

```
T=0s:   PENDING_INITIAL_PROCESSING
T=1s:   INITIAL_PROCESSING → Tika処理
T=2s:   Tika完了 → FINALIZED_BY_TIKA ✅ 検索可能
T=3s:   VLM/OCRディスパッチ
T=60s:  VLM完了 → FINALIZED_BY_VLM ✅ 最高品質
T=90s:  OCR完了 → canUpgradeWith('ocr')=false → スキップ
```

---

## 6. マイグレーション

**不要** - Enumのみの変更

---

## 7. テスト戦略

### 7.1. ステータス遷移テスト

```php
// tests/Feature/Jobs/Embedding/VectorizeAttachedFileTest.php

#[Test]
public function it_upgrades_from_tika_to_ocr()
{
    $file = AttachedFile::factory()->create([
        'status' => AttachedFileStatus::FINALIZED_BY_TIKA,
        'tika_processed_at' => now(),
    ]);
    
    // OCRで上書き
    VectorizeAttachedFile::dispatch($file->id, 'ocr');
    
    $file->refresh();
    $this->assertEquals(AttachedFileStatus::FINALIZED_BY_OCR, $file->status);
}

#[Test]
public function it_upgrades_from_ocr_to_vlm()
{
    $file = AttachedFile::factory()->create([
        'status' => AttachedFileStatus::FINALIZED_BY_OCR,
        'ocr_processed_at' => now(),
    ]);
    
    // VLMで上書き
    VectorizeAttachedFile::dispatch($file->id, 'vlm');
    
    $file->refresh();
    $this->assertEquals(AttachedFileStatus::FINALIZED_BY_VLM, $file->status);
}

#[Test]
public function it_skips_downgrade()
{
    $file = AttachedFile::factory()->create([
        'status' => AttachedFileStatus::FINALIZED_BY_VLM,
        'vlm_processed_at' => now(),
    ]);
    
    // OCRでは上書きしない
    VectorizeAttachedFile::dispatch($file->id, 'ocr');
    
    $file->refresh();
    $this->assertEquals(AttachedFileStatus::FINALIZED_BY_VLM, $file->status);
}
```

---

## 8. 実装チェックリスト

### Phase 1: ステータス追加（0.5日）
- [ ] `AttachedFileStatus`に3つ追加
- [ ] `isFinalized()`メソッド
- [ ] `canUpgradeWith()`メソッド
- [ ] 言語ファイル更新

### Phase 2: VectorizeAttachedFile実装（1日）
- [ ] ジョブクラス作成
- [ ] アップグレード判定ロジック
- [ ] ベクトル化実行
- [ ] 単体テスト

### Phase 3: 既存ジョブ統合（0.5日）
- [ ] ProcessAttachedFileにdispatch追加
- [ ] OcrAndOptimizeFileにdispatch追加
- [ ] ProcessVlmExtractionにdispatch追加

### Phase 4: 統合テスト（0.5日）
- [ ] アップグレードテスト
- [ ] ダウングレード防止テスト
- [ ] 実データでの動作確認

**合計: 2.5日**

---

## 9. まとめ

**設計の特徴:**
✅ **即座に検索可能**: Tika完了で即インデックス化  
✅ **段階的品質向上**: OCR/VLMで自動上書き  
✅ **ダウングレード防止**: 低品質ソースでは上書きしない  
✅ **シンプルな実装**: ステータスのみで状態管理  
✅ **追加DBカラム不要**: Enumのみの変更  

**この設計で実装を開始します。**

---

**作成者:** GitHub Copilot CLI  
**Phase:** 2.6 複数OCR結果の統合戦略  
**レビュー推奨:** LedgerLeap開発チーム

---

## 10. 重要な設計変更：ファイルタイプ別最適化

### 10.1. 課題認識

**問題:**
- オフィスファイル（Word, Excel, PDF with text）にOCR/VLMを実行すると品質が劣化
- Tikaのネイティブテキスト抽出の方が高品質

### 10.2. 解決策

**ProcessAttachedFileの改善:**

```php
private function needsVlmOrOcr(): bool
{
    $mime = $this->attachedFile->mime;
    
    // 画像ファイル → VLM/OCR必要
    if (str_starts_with($mime, 'image/')) {
        return true;
    }
    
    // PDF → 実行（テキストPDFでも画像部分がある可能性）
    if ($mime === 'application/pdf') {
        return true;
    }
    
    // オフィスファイル → VLM/OCR不要
    $officeMimes = [
        'application/vnd.openxmlformats-officedocument',  // Office 2007+
        'application/msword',                              // Word
        'application/vnd.ms-excel',                        // Excel
        'application/vnd.ms-powerpoint',                   // PowerPoint
        'text/plain',
        'text/csv',
    ];
    
    foreach ($officeMimes as $pattern) {
        if (str_contains($mime, $pattern)) {
            return false; // VLM/OCRスキップ
        }
    }
    
    return false; // デフォルトはスキップ
}
```

**canUpgradeWithの改善:**

```php
public function canUpgradeWith(string $newSource, AttachedFile $file): bool
{
    // オフィスファイルはTikaが最高品質
    if ($this->isOfficeFile($file->mime)) {
        if ($this === self::FINALIZED_BY_TIKA) {
            return false; // Tikaで完了、上書き不要
        }
    }
    
    // 画像/スキャンファイルは通常の優先順位
    // Tika(1) < OCR(2) < VLM(3)
    // ...
}
```

### 10.3. 期待される効果

| ファイルタイプ | 変更前 | 変更後 |
|--------------|--------|--------|
| **Word/Excel** | Tika→OCR→VLM（品質劣化） | **Tikaのみ（最高品質）** ✅ |
| **PDF (text)** | Tika→OCR→VLM（品質劣化） | **Tikaのみ（最高品質）** ✅ |
| **画像/スキャン** | Tika→OCR→VLM（段階向上） | **同じ（最適）** ✅ |

### 10.4. 実装チェックリスト追加

- [ ] `needsVlmOrOcr()`メソッド実装
- [ ] `isOfficeFile()`メソッド実装
- [ ] `canUpgradeWith()`にファイルタイプ判定追加
- [ ] オフィスファイルでのスキップテスト
- [ ] 画像ファイルでの段階向上テスト

**実装工数: +0.5日（合計3日）**

