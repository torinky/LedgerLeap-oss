# VLM結果の保存戦略変更提案：attached_files活用案

**提案日:** 2025年10月25日  
**提案者:** ユーザー  
**評価者:** GitHub Copilot CLI (Serena)  
**評価:** ✅ **技術的に妥当・推奨**

---

## 📋 提案概要

`content_attached` JSONではなく、**`attached_files`テーブルに専用カラムを追加**してVLM結果（Markdown）を保存し、**`ledger_chunks`テーブル作成時にこれを活用**する設計変更。

---

## 🎯 メリット

### 1. **データ正規化とスキーマの明確化**
- ❌ **現行案の問題**: `content_attached` JSONの肥大化
- ✅ **改善案**: `attached_files`テーブルで専用カラム管理

### 2. **既存RAG基盤との親和性**
- ✅ 既に実装済みの`ledger_chunks`テーブルとの統合が容易
- ✅ VLM結果をチャンキング処理に直接利用可能

### 3. **パフォーマンス向上**
- ✅ Mroonga全文検索（`content_attached`）への影響なし
- ✅ VLM結果の取得がINDEX検索で高速化

### 4. **拡張性**
- ✅ 複数VLMモデルの結果を並存可能（カラム追加で対応）
- ✅ ベクトル検索への拡張が容易

---

## 🗂️ スキーマ設計

### attached_files テーブルへの追加カラム

```php
// database/migrations/2025_10_25_add_vlm_columns_to_attached_files.php

public function up(): void
{
    Schema::table('attached_files', function (Blueprint $table) {
        // VLM処理結果（Markdown形式）
        $table->longText('vlm_markdown')->nullable()->after('original_mime_type');
        
        // VLM処理結果（構造化JSON）
        $table->json('vlm_structured_data')->nullable()->after('vlm_markdown');
        
        // VLM処理メタデータ
        $table->string('vlm_model', 100)->nullable()->after('vlm_structured_data');
        $table->decimal('vlm_confidence', 4, 3)->nullable()->after('vlm_model');
        $table->unsignedInteger('vlm_processing_time_ms')->nullable()->after('vlm_confidence');
        $table->timestamp('vlm_processed_at')->nullable()->after('vlm_processing_time_ms');
        
        // インデックス
        $table->index('vlm_model');
        $table->index('vlm_processed_at');
    });
    
    // オプション: vlm_markdownへの全文検索インデックス（フェーズ3以降）
    // DB::statement('ALTER TABLE attached_files ADD FULLTEXT INDEX ft_vlm_markdown (vlm_markdown)');
}
```

### データ構造例

```php
// AttachedFile モデルのデータ例
[
    'id' => 123,
    'filename' => 'invoice.pdf',
    'hashedbasename' => 'abc123.pdf',
    'mime' => 'application/pdf',
    'status' => 'completed',
    
    // ★ 新規: VLM結果
    'vlm_markdown' => "# 請求書\n\n**請求番号:** INV-2025-001\n**発行日:** 2025-10-23\n**請求先:** 株式会社A商事 御中\n\n## 請求明細\n\n| 品名 | 数量 | 単価 | 金額 |\n|------|------|------|------|\n| 製品A | 10 | ¥1,500 | ¥15,000 |",
    
    'vlm_structured_data' => [
        'entities' => [
            ['type' => 'invoice_number', 'value' => 'INV-2025-001', 'confidence' => 0.98],
            ['type' => 'date', 'value' => '2025-10-23', 'confidence' => 0.96],
            ['type' => 'amount', 'value' => 30000, 'confidence' => 0.94],
        ],
        'tables' => [
            // テーブル構造データ
        ]
    ],
    
    'vlm_model' => 'PaddleOCR-VL-0.9B',
    'vlm_confidence' => 0.95,
    'vlm_processing_time_ms' => 12300,
    'vlm_processed_at' => '2025-10-25 12:34:56',
]
```

---

## 🔄 処理フロー統合

### ProcessVlmExtraction ジョブの更新

```php
<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Services\VlmClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVlmExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected AttachedFile $attachedFile;
    protected string $vlmModel;

    public function __construct(AttachedFile $attachedFile, string $vlmModel = 'PaddleOCR-VL')
    {
        $this->attachedFile = $attachedFile;
        $this->vlmModel = $vlmModel;
        $this->onQueue('vlm-processing');
    }

    public function handle(VlmClientService $vlmClient): void
    {
        tenancy()->initialize($this->attachedFile->tenant_id);
        
        Log::info("[VLM] Starting extraction", [
            'file_id' => $this->attachedFile->id,
            'model' => $this->vlmModel
        ]);

        $this->attachedFile->update(['status' => AttachedFileStatus::VLM_PROCESSING]);

        try {
            $startTime = microtime(true);
            
            // VLM APIコール
            $vlmOutput = $vlmClient->extract(
                $this->attachedFile->getPhysicalPath(),
                $this->vlmModel,
                timeout: 300
            );
            
            $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // ★ attached_filesテーブルに直接保存
            $this->attachedFile->update([
                'vlm_markdown' => $vlmOutput['markdown'] ?? null,
                'vlm_structured_data' => [
                    'entities' => $vlmOutput['entities'] ?? [],
                    'tables' => $vlmOutput['tables'] ?? [],
                ],
                'vlm_model' => $this->vlmModel,
                'vlm_confidence' => $vlmOutput['confidence'] ?? null,
                'vlm_processing_time_ms' => $processingTimeMs,
                'vlm_processed_at' => now(),
                'status' => AttachedFileStatus::COMPLETED,
            ]);

            Log::info("[VLM] Extraction successful", [
                'file_id' => $this->attachedFile->id,
                'processing_time_ms' => $processingTimeMs,
                'confidence' => $vlmOutput['confidence'] ?? null,
            ]);
            
            // ★ ledger_chunks の更新をトリガー（オプション）
            if (config('rag.auto_update_chunks', true)) {
                \App\Jobs\Rag\UpdateLedgerChunks::dispatch($this->attachedFile->ledger);
            }

        } catch (\Exception $e) {
            Log::error("[VLM] Extraction failed", [
                'file_id' => $this->attachedFile->id,
                'error' => $e->getMessage()
            ]);

            $this->attachedFile->update(['status' => AttachedFileStatus::VLM_FAILED]);
        }
    }
}
```

### ledger_chunks作成時のVLM結果活用

```php
<?php

namespace App\Services\Rag;

use App\Models\Ledger;
use App\Models\LedgerChunk;
use Illuminate\Support\Facades\DB;

class ChunkingService
{
    /**
     * 台帳からチャンクを生成（VLM結果を活用）
     */
    public function createChunksFromLedger(Ledger $ledger): void
    {
        DB::transaction(function () use ($ledger) {
            // 既存チャンクを削除
            LedgerChunk::where('ledger_id', $ledger->id)->delete();
            
            $chunkIndex = 0;
            $allTexts = [];
            
            // 1. 台帳本体のテキスト
            $allTexts[] = $this->extractTextFromContent($ledger->content);
            
            // 2. ★ 添付ファイルのVLM結果（Markdown）を優先使用
            foreach ($ledger->attachedFiles as $file) {
                if (!empty($file->vlm_markdown)) {
                    // VLM Markdownを使用（構造化された高品質データ）
                    $allTexts[] = "## 添付ファイル: {$file->original_filename}\n\n{$file->vlm_markdown}";
                    
                    Log::info("[Chunking] Using VLM markdown", [
                        'ledger_id' => $ledger->id,
                        'file_id' => $file->id,
                        'model' => $file->vlm_model,
                        'confidence' => $file->vlm_confidence,
                    ]);
                } else {
                    // VLM結果がない場合はTika/OCR結果にフォールバック
                    $tikaText = $this->extractTikaTextFromFile($file, $ledger);
                    if ($tikaText) {
                        $allTexts[] = "## 添付ファイル: {$file->original_filename}\n\n{$tikaText}";
                    }
                }
            }
            
            // 3. テキストをチャンク分割
            $chunks = $this->splitIntoChunks(implode("\n\n---\n\n", $allTexts));
            
            // 4. チャンクをDBに保存
            foreach ($chunks as $chunkText) {
                LedgerChunk::create([
                    'ledger_id' => $ledger->id,
                    'ledger_define_id' => $ledger->ledger_define_id,
                    'folder_id' => $ledger->define->folder_id,
                    'chunk_index' => $chunkIndex++,
                    'chunk_text' => $chunkText,
                    // embedding は別途 GenerateEmbedding ジョブで生成
                ]);
            }
            
            Log::info("[Chunking] Chunks created", [
                'ledger_id' => $ledger->id,
                'chunk_count' => $chunkIndex,
                'vlm_used_count' => $ledger->attachedFiles->filter(fn($f) => !empty($f->vlm_markdown))->count(),
            ]);
        });
    }
    
    /**
     * Tika/OCRテキストを取得（フォールバック用）
     */
    protected function extractTikaTextFromFile($file, $ledger): ?string
    {
        $contentAttached = $ledger->content_attached ?? [];
        $columnId = $file->column_id;
        $hashedBasename = $file->hashedbasename;
        
        return $contentAttached[$columnId][$hashedBasename]['meta']['content'] ?? null;
    }
    
    /**
     * テキストをチャンクに分割
     */
    protected function splitIntoChunks(string $text, int $maxTokens = 500): array
    {
        // チャンキングロジック（既存実装を活用）
        // ...
    }
}
```

---

## 📊 比較表：提案A vs 提案B（改善版）

| 項目 | 提案A: content_attached | 提案B: attached_files（★推奨） |
|------|------------------------|--------------------------------|
| **データ正規化** | ❌ JSON肥大化 | ✅ 正規化された専用カラム |
| **Mroonga全文検索への影響** | ⚠️ JSONサイズ増加で性能劣化リスク | ✅ 影響なし |
| **RAG統合** | △ 取得に複雑なJSON解析が必要 | ✅ シンプルなカラム参照 |
| **複数モデル対応** | △ JSON構造の複雑化 | ✅ カラム追加で容易 |
| **ベクトル検索拡張** | ❌ 別テーブル化が必須 | ✅ そのまま拡張可能 |
| **実装コスト** | 低（既存構造流用） | 中（マイグレーション必要） |
| **保守性** | ❌ JSON構造の管理が煩雑 | ✅ リレーショナルDBの利点 |
| **パフォーマンス** | △ JSON解析オーバーヘッド | ✅ INDEX検索で高速 |

---

## 🚀 推奨実装プラン

### フェーズ1: スキーマ変更とジョブ更新（〜1週間）
- [ ] `attached_files`テーブルへのVLMカラム追加マイグレーション
- [ ] `ProcessVlmExtraction`ジョブの更新
- [ ] `AttachedFile`モデルへのアクセサ追加
- [ ] 既存の`content_attached`参照コードは維持（後方互換性）

### フェーズ2: RAG統合（〜1週間）
- [ ] `ChunkingService`の更新（VLM Markdown優先使用）
- [ ] `UpdateLedgerChunks`ジョブの実装
- [ ] VLM処理完了時の自動チャンク更新

### フェーズ3: UI機能追加（〜2週間）
- [ ] VLM結果ダウンロード機能（`attached_files.vlm_markdown`を使用）
- [ ] プレビュー機能
- [ ] 品質フィードバック収集

---

## 🔧 マイグレーションコード

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attached_files', function (Blueprint $table) {
            // VLM処理結果
            $table->longText('vlm_markdown')->nullable()
                ->comment('VLM抽出Markdown結果（RAG用）')
                ->after('original_mime_type');
            
            $table->json('vlm_structured_data')->nullable()
                ->comment('VLM構造化データ（エンティティ、テーブル等）')
                ->after('vlm_markdown');
            
            // VLMメタデータ
            $table->string('vlm_model', 100)->nullable()
                ->comment('使用VLMモデル名')
                ->after('vlm_structured_data');
            
            $table->decimal('vlm_confidence', 4, 3)->nullable()
                ->comment('VLM処理信頼度（0.000-1.000）')
                ->after('vlm_model');
            
            $table->unsignedInteger('vlm_processing_time_ms')->nullable()
                ->comment('VLM処理時間（ミリ秒）')
                ->after('vlm_confidence');
            
            $table->timestamp('vlm_processed_at')->nullable()
                ->comment('VLM処理完了日時')
                ->after('vlm_processing_time_ms');
            
            // パフォーマンス向上のためのインデックス
            $table->index('vlm_model', 'idx_vlm_model');
            $table->index('vlm_processed_at', 'idx_vlm_processed_at');
            $table->index(['status', 'vlm_processed_at'], 'idx_status_vlm_processed');
        });
    }

    public function down(): void
    {
        Schema::table('attached_files', function (Blueprint $table) {
            $table->dropIndex('idx_vlm_model');
            $table->dropIndex('idx_vlm_processed_at');
            $table->dropIndex('idx_status_vlm_processed');
            
            $table->dropColumn([
                'vlm_markdown',
                'vlm_structured_data',
                'vlm_model',
                'vlm_confidence',
                'vlm_processing_time_ms',
                'vlm_processed_at',
            ]);
        });
    }
};
```

---

## ✅ 結論

**提案B（`attached_files`テーブル活用）を強く推奨します。**

**理由:**
1. ✅ データ正規化とスキーマの明確化
2. ✅ RAG基盤（`ledger_chunks`）との親和性が高い
3. ✅ Mroonga全文検索への影響なし
4. ✅ パフォーマンスと保守性の向上
5. ✅ 将来のベクトル検索拡張が容易

**実装優先度:** **高** - フェーズ1の基盤整備と同時に実施すべき

---

**作成者:** GitHub Copilot CLI (Serena)  
**最終更新:** 2025-10-25
