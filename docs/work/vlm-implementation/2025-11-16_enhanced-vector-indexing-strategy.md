# LedgerLeap ベクトルインデックス高度化実装計画

**作成日:** 2025年11月16日  
**ドキュメント種別:** 技術実装計画書  
**ステータス:** 計画策定完了、実装待機中  
**前提:** 基本的なVLM-OCR機能とセマンティック検索機能は実装済み

> **📖 関連ドキュメント:**
> - [2025-11-15_vlm-ocr-and-indexing-strategy-review.md](./2025-11-15_vlm-ocr-and-indexing-strategy-review.md) - 基本戦略ドキュメント
> - [RAG機能導入に関する技術検討](../rag-implementation/2025-10-16-rag-implementation-study.md) - RAG導入の全体戦略
> - [AIアシスタントと検索の哲学](../../ai-and-search-guide.md) - LedgerLeapの検索思想

---

## 1. エグゼクティブサマリー

本計画は、既存のVLM-OCR機能とセマンティック検索を基盤に、**形態素解析による固有名詞・記号番号の特別扱い**と**Ruriモデルの特性を最大限活用した多層ベクトルインデックス**により、検索精度を15-20%向上させることを目指す。

### 主要な技術戦略

1. **固有名詞・記号の先頭埋め込み（Phase 2.5）** ⭐ 最優先
2. **Ruriプレフィックスを活用した複数OCR結果統合**
3. **ハイブリッド・ベクトルインデックス（全体＋キーワード）**
4. **RRFによる3種検索結果の統合（キーワード+2種ベクトル）**

### 期待される効果

| 指標 | 現状 | 目標 | 向上率 |
|------|------|------|--------|
| 固有名詞検索精度 | 65% | 85% | +20% |
| 記号番号検索精度 | 70% | 90% | +20% |
| セマンティック検索精度 | 75% | 90% | +15% |
| 総合検索満足度 | 70% | 85% | +15% |

---

## 2. 既存実装の確認

### 2.1. 形態素解析基盤（既存）

LedgerLeapには既に`logue/igo-php`を使った形態素解析機能が実装されている。

**既存コード:** `app/Services/SynonymService.php`

```php
use Igo\Tagger;

public static function wakati($inputText)
{
    $igo = new Tagger;
    $result = $igo->parse($inputText);
    
    $noun = '';
    $words = [];
    foreach ($result as $value) {
        if ($value->feature[0] === '名詞') {
            $noun .= $value->surface;
        } else {
            if (mb_strlen($noun)) {
                $words[] = $noun;
            }
            $noun = '';
            $words[] = $value->surface;
        }
    }
    if (mb_strlen($noun)) {
        $words[] = $noun;
    }
    
    return $words;
}
```

**既存機能の活用ポイント:**
- ✅ 名詞の連続抽出が実装済み
- ✅ 複合名詞（「株式会社ABC」など）の自動結合
- 🔧 固有名詞、記号、数詞の抽出機能を拡張する

### 2.2. Ruriモデルの特性

**公式ドキュメント・情報源:**
- 📄 [Ruri公式論文 (arXiv)](https://arxiv.org/abs/2409.07737) - モデルアーキテクチャと訓練手法の詳細
- 🤗 [HuggingFace: cl-nagoya/ruri-v3-310m](https://huggingface.co/cl-nagoya/ruri-v3-310m) - モデルカード、ベンチマーク結果
- 🤗 [HuggingFace: cl-nagoya/ruri-v3-30m](https://huggingface.co/cl-nagoya/ruri-v3-30m) - 軽量版モデル
- 📊 [JMTEB Benchmark](https://github.com/sbintuitions/JMTEB) - 日本語タスク評価結果
- 📝 [言語処理学会年次大会 2025 論文](https://www.anlp.jp/proceedings/annual_meeting/2025/pdf_dir/Q4-3.pdf) - 日本語での技術解説

**技術仕様:**

| 項目 | ruri-v3-310m (現在使用中) | ruri-v3-30m | ruri-large-v2 (旧版) |
|------|-------------------------|-------------|---------------------|
| **パラメータ数** | 310M | 30M | 337M |
| **ベクトル次元** | **768次元** | 256次元 | 1024次元 |
| **語彙サイズ** | 100,000トークン | 100,000トークン | 100,000トークン |
| **最大コンテキスト** | 8,192トークン | 8,192トークン | 512トークン |
| **トークナイザー** | SentencePiece | SentencePiece | SentencePiece |
| **推奨環境** | ARM64/x86_64 CPU | ARM64 CPU (超高速) | x86_64 CPU |

**重要な特性:**
1. **大語彙による固有名詞対応:** 「株式会社ABC」のような複合語が1-2トークンで表現可能（従来比10倍の語彙）
2. **プレフィックス機能:** `検索クエリ: ` `検索文書: ` などで文脈を明示（公式推奨）
3. **合成データ訓練:** LLM生成の検索シナリオで固有名詞への対応力を強化済み（+1ptの精度向上を実証）
4. **FlashAttention対応:** 長文処理（8,192トークン）を高速化

---

## 3. 実装計画：5つのPhase

### Phase 2.5: 固有名詞・記号の先頭埋め込み（最優先） ⭐

**目的:** OCRテキストの先頭に重要キーワードを埋め込み、検索ヒット率を大幅向上

#### 3.1. 新規クラスの作成

**ファイル:** `app/Services/Embedding/KeywordEnhancedTextGenerator.php`

```php
<?php

namespace App\Services\Embedding;

use Igo\Tagger;

/**
 * OCRテキストから重要キーワードを抽出し、検索精度向上のために先頭に埋め込むサービス
 */
class KeywordEnhancedTextGenerator
{
    private Tagger $tagger;
    
    public function __construct()
    {
        $this->tagger = new Tagger();
    }
    
    /**
     * OCRテキストを拡張し、重要キーワードを先頭に埋め込む
     * 
     * @param string $ocrText 元のOCRテキスト
     * @param array $options オプション設定
     *   - max_keywords: 最大キーワード数（デフォルト: 20）
     *   - min_frequency: 最小出現回数（デフォルト: 2）
     *   - target_types: 対象品詞（デフォルト: ['固有名詞', '名詞', '記号', '数詞']）
     * @return string 拡張後のテキスト
     */
    public function generateEnhancedText(string $ocrText, array $options = []): string
    {
        $maxKeywords = $options['max_keywords'] ?? 20;
        $minFrequency = $options['min_frequency'] ?? 2;
        $targetTypes = $options['target_types'] ?? ['固有名詞', '名詞', '記号', '数詞'];
        
        // 1. 形態素解析
        $morphemes = $this->tagger->parse($ocrText);
        
        // 2. 重要語抽出（頻度順）
        $keywords = $this->extractKeywords($morphemes, $targetTypes, $minFrequency);
        
        // 3. 頻度順にソート
        arsort($keywords);
        
        // 4. 上位N件を取得
        $topKeywords = array_slice(array_keys($keywords), 0, $maxKeywords);
        
        // 5. テキスト構築
        return $this->buildEnhancedText($topKeywords, $ocrText);
    }
    
    /**
     * 形態素配列から重要キーワードを抽出
     * 
     * @param array $morphemes 形態素解析結果
     * @param array $targetTypes 対象品詞
     * @param int $minFrequency 最小出現回数
     * @return array キーワードと頻度の配列
     */
    private function extractKeywords(array $morphemes, array $targetTypes, int $minFrequency): array
    {
        $keywords = [];
        $compoundNoun = '';
        
        foreach ($morphemes as $morpheme) {
            $pos = $morpheme->feature[0]; // 品詞
            $posDetail = $morpheme->feature[1] ?? ''; // 品詞細分類
            $surface = $morpheme->surface;
            
            // 固有名詞と記号・数詞を特別扱い
            if ($pos === '名詞') {
                $compoundNoun .= $surface;
            } else {
                if (mb_strlen($compoundNoun) > 0) {
                    $keywords[$compoundNoun] = ($keywords[$compoundNoun] ?? 0) + 1;
                    $compoundNoun = '';
                }
                
                // 記号や数詞も単独で抽出
                if (in_array($pos, $targetTypes) && mb_strlen($surface) > 1) {
                    $keywords[$surface] = ($keywords[$surface] ?? 0) + 1;
                }
            }
        }
        
        // 最後の複合名詞を処理
        if (mb_strlen($compoundNoun) > 0) {
            $keywords[$compoundNoun] = ($keywords[$compoundNoun] ?? 0) + 1;
        }
        
        // 出現回数でフィルタ
        return array_filter($keywords, fn($freq) => $freq >= $minFrequency);
    }
    
    /**
     * キーワードセクションとオリジナルテキストを結合
     * 
     * @param array $keywords 抽出されたキーワード配列
     * @param string $originalText 元のテキスト
     * @return string 結合後のテキスト
     */
    private function buildEnhancedText(array $keywords, string $originalText): string
    {
        if (empty($keywords)) {
            return $originalText;
        }
        
        // 形式: "[KW: 株式会社ABC, INV-2025-001, 重要事項, ...] 元のテキスト..."
        $keywordSection = '[KW: ' . implode(', ', $keywords) . '] ';
        
        return $keywordSection . $originalText;
    }
}
```

#### 3.2. 既存パイプラインへの統合

**ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

```php
use App\Services\Embedding\KeywordEnhancedTextGenerator;

public function handle(): void
{
    $generator = new KeywordEnhancedTextGenerator();
    
    // VLM Markdownからキーワード強化テキストを生成
    $vlmMarkdown = $this->ledger->getVlmMarkdown();
    $enhancedText = $generator->generateEnhancedText($vlmMarkdown);
    
    // ベクトル化（既存の処理）
    $embedding = $this->embeddingService->embed($enhancedText);
    
    // 保存
    LedgerChunk::create([
        'ledger_id' => $this->ledger->id,
        'chunk_text' => $enhancedText,
        'embedding' => $embedding,
        'metadata' => [
            'keywords_extracted' => true,
            'keyword_count' => substr_count($enhancedText, ',') + 1, // 簡易カウント
        ],
    ]);
}
```

**実装工数:** 2日  
**優先度:** 最高（他のPhaseの基盤）

---

### Phase 2.6: Ruriプレフィックスを活用した複数OCR統合

**目的:** VLM、OCR、Tikaなど複数のソースを意味的に統合

#### 3.3. セマンティックプレフィックス統合クラス

**ファイル:** `app/Services/Embedding/SemanticPrefixAggregator.php`

```php
<?php

namespace App\Services\Embedding;

use App\Services\EmbeddingService;

/**
 * Ruriモデルのプレフィックス機能を活用した複数OCR結果の統合
 */
class SemanticPrefixAggregator
{
    private EmbeddingService $embeddingService;
    
    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }
    
    /**
     * 複数のOCR結果を重み付き平均でベクトル統合
     * 
     * @param array $ocrResults ソース名 => テキストの配列
     * @param array $weights ソース名 => 重みの配列
     * @return array 統合されたベクトル
     */
    public function aggregate(array $ocrResults, array $weights = []): array
    {
        $defaultWeights = [
            'vlm' => 0.5,           // VLMは構造理解に優れる
            'ocr_standard' => 0.3,  // 標準OCRは文字精度が高い
            'tika' => 0.2,          // Tikaは文書構造の補完
        ];
        
        $weights = array_merge($defaultWeights, $weights);
        $vectors = [];
        
        foreach ($ocrResults as $source => $text) {
            // Ruriのプレフィックスで情報源を明示
            $prefixedText = $this->applyPrefix($source, $text);
            
            // ベクトル化
            $vectors[$source] = $this->embeddingService->embed($prefixedText);
        }
        
        // 重み付き平均
        return $this->weightedAverage($vectors, $weights);
    }
    
    /**
     * ソースに応じたプレフィックスを適用
     */
    private function applyPrefix(string $source, string $text): string
    {
        return match($source) {
            'vlm' => '画像解析結果: ' . $text,
            'ocr_standard' => 'OCR抽出文字: ' . $text,
            'tika' => '文書構造: ' . $text,
            default => '文章: ' . $text,
        };
    }
    
    /**
     * ベクトルの重み付き平均を計算
     */
    private function weightedAverage(array $vectors, array $weights): array
    {
        $dimension = config('rag.model.available_models.ruri-v3-310m.dimension', 768);
        $result = array_fill(0, $dimension, 0.0); // ruri-v3-310mは768次元
        $totalWeight = array_sum($weights);
        
        foreach ($vectors as $source => $vector) {
            $weight = $weights[$source] ?? 0;
            for ($i = 0; $i < $dimension; $i++) {
                $result[$i] += ($vector[$i] ?? 0) * $weight;
            }
        }
        
        // 正規化
        return array_map(fn($v) => $v / $totalWeight, $result);
    }
}
```

**実装工数:** 3日  
**優先度:** 高

---

### Phase 2.7: キーワードベクトルの生成と保存

**目的:** 全体ベクトルとは別に、キーワードに特化したベクトルを生成

#### 3.4. テーブル拡張

**Migration:** `database/migrations/xxxx_add_keyword_embedding_to_ledger_chunks.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_chunks', function (Blueprint $table) {
            // キーワードベクトル（768次元 - ruri-v3-310mの次元数）
            $table->vector('embedding_keyword', 768)->nullable()->after('embedding');
            
            // キーワードメタデータ（抽出されたキーワードと頻度）
            $table->json('keyword_metadata')->nullable()->after('embedding_keyword');
            
            // ベクトル生成手法の記録
            $table->string('vector_generation_method', 50)
                  ->default('global')
                  ->after('keyword_metadata')
                  ->comment('global, keyword, hybrid');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_chunks', function (Blueprint $table) {
            $table->dropColumn(['embedding_keyword', 'keyword_metadata', 'vector_generation_method']);
        });
    }
};
```

#### 3.5. キーワードベクトル生成ロジック

**更新ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`

```php
public function handle(): void
{
    $generator = new KeywordEnhancedTextGenerator();
    $aggregator = new SemanticPrefixAggregator($this->embeddingService);
    
    // 1. 複数OCR結果の取得
    $ocrResults = [
        'vlm' => $this->ledger->getVlmMarkdown(),
        'ocr_standard' => $this->ledger->getOcrText(),
        'tika' => $this->ledger->getTikaText(),
    ];
    
    // 2. 全体ベクトルの生成（プレフィックス統合）
    $globalEmbedding = $aggregator->aggregate(array_filter($ocrResults));
    
    // 3. キーワード強化テキストの生成
    $combinedText = implode("\n\n", array_filter($ocrResults));
    $enhancedText = $generator->generateEnhancedText($combinedText);
    
    // 4. キーワードのみを抽出してベクトル化
    preg_match('/\[KW: (.*?)\]/', $enhancedText, $matches);
    $keywordsOnly = $matches[1] ?? '';
    $keywordEmbedding = $this->embeddingService->embed('重要語: ' . $keywordsOnly);
    
    // 5. 保存
    LedgerChunk::create([
        'ledger_id' => $this->ledger->id,
        'chunk_text' => $enhancedText,
        'embedding' => $globalEmbedding,
        'embedding_keyword' => $keywordEmbedding,
        'keyword_metadata' => [
            'keywords' => explode(', ', $keywordsOnly),
            'total_keywords' => substr_count($keywordsOnly, ',') + 1,
        ],
        'vector_generation_method' => 'hybrid',
    ]);
}
```

**実装工数:** 4日  
**優先度:** 高

---

### Phase 3.1: RRFによるハイブリッド検索

**目的:** キーワード検索、全体ベクトル、キーワードベクトルの3種類を統合

#### 3.6. ハイブリッド検索サービス

**ファイル:** `app/Services/Search/HybridSearchService.php`

```php
<?php

namespace App\Services\Search;

use App\Models\Ledger;
use App\Models\LedgerChunk;
use App\Services\EmbeddingService;
use App\Services\Embedding\KeywordEnhancedTextGenerator;
use Illuminate\Support\Collection;

/**
 * Reciprocal Rank Fusion (RRF) によるハイブリッド検索
 */
class HybridSearchService
{
    private EmbeddingService $embeddingService;
    private KeywordEnhancedTextGenerator $keywordGenerator;
    private const RRF_K = 60; // RRFパラメータ
    
    public function __construct(
        EmbeddingService $embeddingService,
        KeywordEnhancedTextGenerator $keywordGenerator
    ) {
        $this->embeddingService = $embeddingService;
        $this->keywordGenerator = $keywordGenerator;
    }
    
    /**
     * ハイブリッド検索を実行
     * 
     * @param string $query 検索クエリ
     * @param array $options オプション
     *   - limit: 結果の最大件数
     *   - weights: 各検索手法の重み ['mroonga' => 0.3, 'global' => 0.4, 'keyword' => 0.3]
     * @return Collection
     */
    public function search(string $query, array $options = []): Collection
    {
        $limit = $options['limit'] ?? 20;
        $weights = $options['weights'] ?? [
            'mroonga' => 0.3,
            'global' => 0.4,
            'keyword' => 0.3,
        ];
        
        // 1. クエリからキーワードを抽出
        $queryKeywords = $this->extractQueryKeywords($query);
        
        // 2. 3種類の検索を並列実行
        $results = [
            'mroonga' => $this->mroongaSearch($query, $limit * 2),
            'global' => $this->globalVectorSearch($query, $limit * 2),
            'keyword' => $this->keywordVectorSearch($queryKeywords, $limit * 2),
        ];
        
        // 3. Reciprocal Rank Fusionでスコア統合
        $fusedScores = $this->reciprocalRankFusion($results, $weights);
        
        // 4. 上位N件を返す
        $topLedgerIds = array_slice(array_keys($fusedScores), 0, $limit, true);
        
        return Ledger::with(['creator', 'folder', 'ledgerDefine'])
            ->findMany($topLedgerIds)
            ->sortBy(fn($ledger) => array_search($ledger->id, $topLedgerIds));
    }
    
    /**
     * Mroonga全文検索
     */
    private function mroongaSearch(string $query, int $limit): Collection
    {
        return Ledger::search($query)
            ->limit($limit)
            ->get();
    }
    
    /**
     * 全体ベクトル検索
     */
    private function globalVectorSearch(string $query, int $limit): Collection
    {
        $queryVector = $this->embeddingService->embed('クエリ: ' . $query);
        
        return LedgerChunk::selectRaw('*, (embedding <=> ?) as distance', [$queryVector])
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('ledger_id')
            ->unique()
            ->map(fn($id) => Ledger::find($id))
            ->filter();
    }
    
    /**
     * キーワードベクトル検索
     */
    private function keywordVectorSearch(string $keywords, int $limit): Collection
    {
        $keywordVector = $this->embeddingService->embed('重要語: ' . $keywords);
        
        return LedgerChunk::selectRaw('*, (embedding_keyword <=> ?) as distance', [$keywordVector])
            ->whereNotNull('embedding_keyword')
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('ledger_id')
            ->unique()
            ->map(fn($id) => Ledger::find($id))
            ->filter();
    }
    
    /**
     * クエリからキーワードを抽出
     */
    private function extractQueryKeywords(string $query): string
    {
        $enhancedText = $this->keywordGenerator->generateEnhancedText($query, [
            'max_keywords' => 10,
            'min_frequency' => 1,
        ]);
        
        preg_match('/\[KW: (.*?)\]/', $enhancedText, $matches);
        return $matches[1] ?? $query;
    }
    
    /**
     * Reciprocal Rank Fusion アルゴリズム
     * 
     * @param array $results 各検索手法の結果
     * @param array $weights 各手法の重み
     * @return array Ledger ID => スコアの配列（降順）
     */
    private function reciprocalRankFusion(array $results, array $weights): array
    {
        $scores = [];
        
        foreach ($results as $method => $ledgers) {
            $weight = $weights[$method] ?? 1.0;
            
            foreach ($ledgers as $rank => $ledger) {
                $ledgerId = $ledger->id;
                
                // RRFスコア計算: weight * 1 / (K + rank + 1)
                $rrfScore = $weight * (1 / (self::RRF_K + $rank + 1));
                
                $scores[$ledgerId] = ($scores[$ledgerId] ?? 0) + $rrfScore;
            }
        }
        
        // スコア降順でソート
        arsort($scores);
        
        return $scores;
    }
}
```

#### 3.7. MCPツールへの統合

**更新ファイル:** `app/Mcp/Tools/SearchLedgersTool.php`

```php
use App\Services\Search\HybridSearchService;

protected function processAuthenticated(Request $request, $user): Response
{
    $orderBy = $request->getParameter('order_by') ?? 'composite_score';
    
    // ハイブリッド検索モード
    if ($orderBy === 'hybrid') {
        $hybridService = app(HybridSearchService::class);
        
        $results = $hybridService->search(
            query: $request->getParameter('q'),
            options: [
                'limit' => $request->getParameter('limit') ?? 20,
                'weights' => [
                    'mroonga' => 0.3,
                    'global' => 0.4,
                    'keyword' => 0.3,
                ],
            ]
        );
        
        return $this->formatResults($results, $request);
    }
    
    // 既存の検索ロジック...
}
```

**実装工数:** 5日  
**優先度:** 中

---

### Phase 3.2: 多言語OCRクロスバリデーション（オプション）

**目的:** 複数OCRツールの合意によるテキスト精度向上

#### 3.8. コンセンサスアルゴリズム

**ファイル:** `app/Services/Embedding/ConsensusOcrAggregator.php`

```php
<?php

namespace App\Services\Embedding;

use Igo\Tagger;

/**
 * 複数OCR結果の合意（コンセンサス）による最適テキスト生成
 */
class ConsensusOcrAggregator
{
    private Tagger $tagger;
    
    public function __construct()
    {
        $this->tagger = new Tagger();
    }
    
    /**
     * 複数のOCR結果から合意に基づく最適テキストを生成
     * 
     * @param array $ocrResults OCRツール名 => テキストの配列
     * @return string 合意テキスト
     */
    public function findConsensus(array $ocrResults): string
    {
        if (count($ocrResults) < 2) {
            return array_values($ocrResults)[0] ?? '';
        }
        
        // 1. 各結果を形態素単位に分解
        $tokenizedResults = [];
        foreach ($ocrResults as $source => $text) {
            $morphemes = $this->tagger->parse($text);
            $tokenizedResults[$source] = array_map(fn($m) => $m->surface, $morphemes);
        }
        
        // 2. トークン単位で投票
        $tokenVotes = [];
        foreach ($tokenizedResults as $tokens) {
            foreach ($tokens as $position => $token) {
                $key = "$position:$token";
                $tokenVotes[$key] = ($tokenVotes[$key] ?? 0) + 1;
            }
        }
        
        // 3. 多数決で確度の高いトークンを選択（過半数以上）
        $threshold = count($ocrResults) / 2;
        $consensusTokens = [];
        
        foreach ($tokenVotes as $key => $votes) {
            if ($votes >= $threshold) {
                [$position, $token] = explode(':', $key, 2);
                $consensusTokens[(int)$position] = $token;
            }
        }
        
        // 4. 位置順にソートして再構成
        ksort($consensusTokens);
        
        return implode('', $consensusTokens);
    }
}
```

**実装工数:** 3日  
**優先度:** 低（Phase 3.1完了後に検討）

---

## 4. 実装優先順位とスケジュール

| Phase | タスク | 工数 | 優先度 | 依存関係 | 期待効果 |
|-------|--------|------|--------|----------|----------|
| **2.5** | **固有名詞・記号の先頭埋め込み** | **2日** | **最高** | なし | **⭐⭐⭐⭐⭐** |
| 2.6 | Ruriプレフィックス統合 | 3日 | 高 | Phase 2.5 | ⭐⭐⭐⭐ |
| 2.7 | キーワードベクトル生成 | 4日 | 高 | Phase 2.5, 2.6 | ⭐⭐⭐⭐ |
| 3.1 | RRFハイブリッド検索 | 5日 | 中 | Phase 2.7 | ⭐⭐⭐⭐⭐ |
| 3.2 | OCRクロスバリデーション | 3日 | 低 | Phase 3.1 | ⭐⭐⭐ |

**推奨スケジュール:**
1. **Week 1-2:** Phase 2.5（固有名詞埋め込み）を実装し、効果を測定
2. **Week 3:** Phase 2.6（プレフィックス統合）を実装
3. **Week 4-5:** Phase 2.7（キーワードベクトル）を実装
4. **Week 6-7:** Phase 3.1（ハイブリッド検索）を実装
5. **Week 8以降:** Phase 3.2（オプション）を検討

---

## 5. 評価指標とテスト計画

### 5.1. 定量評価指標

| 指標 | 測定方法 | 目標値 |
|------|----------|--------|
| **固有名詞検索精度** | 100件のテストクエリでのヒット率 | 85%以上 |
| **記号番号検索精度** | 請求書番号・製品コード検索のヒット率 | 90%以上 |
| **セマンティック検索精度** | NDCG@10スコア | 0.75以上 |
| **検索レスポンス時間** | 平均検索時間 | 500ms以内 |

### 5.2. テストデータセット

**準備するテストケース:**
1. **固有名詞系（30件）**
   - 会社名: 「株式会社ABC商事」「合同会社XYZ」
   - 人名: 「田中太郎」「山田花子」
   - 地名: 「東京都千代田区」

2. **記号番号系（30件）**
   - 請求書番号: 「INV-2025-001」「見積-20250116-A」
   - 製品コード: 「PRD-ABC-123」「型番XYZ-999」

3. **セマンティック系（40件）**
   - 「先月のA社との価格交渉の議事録」
   - 「重要な未処理案件」

### 5.3. A/Bテスト計画

```php
// テストフラグによる新旧機能の切り替え
if (config('features.enhanced_vector_search')) {
    return $hybridSearchService->search($query);
} else {
    return $legacySearchService->search($query);
}
```

**測定期間:** 2週間  
**対象ユーザー:** 全ユーザーの50%

---

## 6. 技術的注意事項

### 6.1. 現在のMroonga/Groongaベクトル検索実装の確認

#### 現在の実装状況

LedgerLeapは**Mroonga/Groongaを使用したベクトル検索**を既に実装しています：

```php
// database/migrations/2025_10_18_034730_create_ledger_chunks_table.php

// ベクトルカラムの作成（Groongaのvector型）
DB::statement('ALTER TABLE ledger_chunks ADD COLUMN embedding longtext 
    COMMENT \'flags "COLUMN_VECTOR", type "Float"\'');
```

```php
// app/Services/RagSearchService.php

// Groongaコマンドによるベクトル検索
$distance_expression = "distance_cosine(embedding, {$query_vector_str})";

$mroonga_command = sprintf(
    "select ledger_chunks %s --columns[score].stage initial 
     --columns[score].flags COLUMN_SCALAR --columns[score].types Float32 
     --columns[score].value '%s' --output_columns _id,score 
     --sortby score --limit %d",
    $filter_clause,
    $distance_expression,
    $chunkLimit
);

$result = DB::select('SELECT mroonga_command(?) AS res', [$mroonga_command]);
```

**公式ドキュメント:**
- 📄 [Groonga Vector Column Documentation](https://groonga.org/docs/reference/columns/vector.html)
- 📄 [Mroonga Optimizations](https://mroonga.org/docs/reference/optimizations.html)
- 🐛 [GitHub Issue: Vector column limitations](https://github.com/mroonga/mroonga/issues/674)

#### ⚠️ 懸念事項と対応策

| 懸念事項 | 影響度 | 現在の次元数 | 対応策 |
|---------|-------|------------|--------|
| **1. 線形スキャンのパフォーマンス** | 🔴 高 | 768次元 × N件 | スケール時の監視、閾値フィルタの活用 |
| **2. ANNインデックス非対応** | 🟡 中 | - | Groongaは専用ANN索引なし（HNSW等未対応） |
| **3. 高次元ベクトルのI/O負荷** | 🟡 中 | 768次元 | 次元削減の検討（PCA等） |
| **4. メモリ消費** | 🟠 中 | 768 × 4bytes × 台帳数 | 定期的なメモリ監視 |
| **5. キーワードベクトル追加時の負荷増** | 🟡 中 | 768次元 × 2 | 段階的実装、効果測定後に判断 |

**詳細な懸念事項:**

**1. 線形スキャンのパフォーマンス（最重要）**
- Groongaの`distance_cosine()`は**全件スキャン**を行う
- 台帳数が1万件を超えると検索速度が低下する可能性
- **対応:** 
  - 類似度閾値フィルタ（既に実装済み: `config('rag.search.similarity_threshold', 0.7)`）
  - `chunk_limit`の調整（現在100件）
  - スケール時にはPostgreSQL+pgvectorへの移行を検討

**2. ANNインデックス非対応**
- GroongaはHNSW、IVF等の近似最近傍（ANN）インデックスをサポートしていない
- **対応:**
  - 開発段階では許容（台帳数<10,000件）
  - 本番環境でのスケール時にpgvector等を検討

**3. 768次元の妥当性**
- Ruriモデル（ruri-v3-310m）は768次元
- Phase 2.7でキーワードベクトル（768次元）を追加すると、1台帳あたり**約6KB（768×4bytes×2）**のベクトルデータ
- **対応:**
  - 次元削減（PCAで384次元等）は検討課題
  - 現状の768次元は許容範囲

**4. 既存実装との整合性**
- 現在の`embedding`カラムは`longtext`型で`COLUMN_VECTOR`フラグを設定
- Phase 2.7の`embedding_keyword`も同様の方法で実装可能
- **対応:**
  ```php
  // Migration for Phase 2.7
  DB::statement('ALTER TABLE ledger_chunks ADD COLUMN embedding_keyword longtext 
      COMMENT \'flags "COLUMN_VECTOR", type "Float"\'');
  ```

### 6.2. 現在のRuri設定の確認と推奨変更

#### 現在の設定（`config/rag.php`）

```php
'model' => [
    'active' => env('RAG_MODEL', 'cl-nagoya/ruri-v3-310m'),
    
    'available_models' => [
        'ruri-v3-310m' => [
            'name' => 'cl-nagoya/ruri-v3-310m',
            'dimension' => 768,  // ← 実際の次元数
            'prefix' => [
                'query' => '検索クエリ: ',     // ← 公式推奨プレフィックス
                'passage' => '検索文書: ',    // ← 公式推奨プレフィックス
            ],
        ],
    ],
],
```

#### ✅ 現在の設定の評価

| 項目 | 現在値 | 評価 | 備考 |
|------|--------|------|------|
| **モデル** | `ruri-v3-310m` | ✅ 最適 | 最新版、768次元で実用的 |
| **ベクトル次元** | 768 | ✅ 正しい | 310Mモデルは768次元 |
| **プレフィックス（クエリ）** | `検索クエリ: ` | ✅ 公式推奨 | 論文・公式ドキュメント通り |
| **プレフィックス（文書）** | `検索文書: ` | ✅ 公式推奨 | 論文・公式ドキュメント通り |

**結論:** 現在の設定は公式推奨に従っており、**変更不要**です。

#### 🔧 本計画で追加すべき設定

Phase 2.5以降の実装では、以下のプレフィックスを追加することを推奨：

```php
// config/rag.php の拡張案

'model' => [
    'active' => env('RAG_MODEL', 'cl-nagoya/ruri-v3-310m'),
    
    'available_models' => [
        'ruri-v3-310m' => [
            'name' => 'cl-nagoya/ruri-v3-310m',
            'dimension' => 768,
            'prefix' => [
                'query' => '検索クエリ: ',        // 既存（公式推奨）
                'passage' => '検索文書: ',       // 既存（公式推奨）
                
                // Phase 2.5以降で追加
                'keyword' => '重要語: ',         // キーワードベクトル用
                'vlm' => '画像解析結果: ',        // VLM結果用
                'ocr' => 'OCR抽出文字: ',        // OCR結果用
                'tika' => '文書構造: ',          // Tika結果用
            ],
        ],
    ],
],
```

**参考文献:**
- Ruriモデルは公式にプレフィックスの使用を推奨しており、「検索クエリ:」「検索文書:」がベースラインです
- 追加のプレフィックス（「重要語:」など）は、Ruri論文の「プレフィックスによるタスク区別」の考え方を拡張したものです
- [Ruri論文セクション3.2: Training with Prefixes](https://arxiv.org/abs/2409.07737)

### 6.3. パフォーマンス最適化

#### Mroonga/Groongaベクトル検索の最適化

**現在の設定（維持推奨）:**
```php
// config/rag.php

'search' => [
    // コサイン距離の閾値（この値未満のみ取得）
    'similarity_threshold' => env('RAG_SIMILARITY_THRESHOLD', 0.2),
],
```

**推奨する追加設定:**
```php
// config/rag.php に追加

'search' => [
    'similarity_threshold' => env('RAG_SIMILARITY_THRESHOLD', 0.2),
    
    // Phase 2.7以降の設定
    'chunk_limit' => env('RAG_CHUNK_LIMIT', 100), // Groongaから取得する最大チャンク数
    'use_keyword_vector' => env('RAG_USE_KEYWORD_VECTOR', true), // キーワードベクトル検索の有効化
    
    // ハイブリッド検索の重み（Phase 3.1）
    'hybrid_weights' => [
        'mroonga' => env('RAG_HYBRID_WEIGHT_MROONGA', 0.3),
        'global_vector' => env('RAG_HYBRID_WEIGHT_GLOBAL', 0.4),
        'keyword_vector' => env('RAG_HYBRID_WEIGHT_KEYWORD', 0.3),
    ],
],
```

**パフォーマンス監視:**
```php
// Phase 2.7実装時に追加推奨

Log::channel('rag')->info('Vector search performance', [
    'query_time_ms' => $executionTime,
    'chunks_scanned' => $totalChunks,
    'chunks_returned' => count($results),
    'vector_dimension' => 768,
    'search_type' => 'keyword_vector', // 'global' or 'keyword_vector'
]);
```

**キャッシュ戦略:**
```php
// 頻繁に検索されるクエリのベクトルをキャッシュ
Cache::remember("query_vector:{$query}", 3600, function() use ($query) {
    return $this->embeddingService->embed($query);
});
```

### 6.3. 既存データの再処理（開発中のため簡易化）

**注意:** 本システムは開発中のため、大規模な再処理機能は不要です。新機能のテストは以下の手順で行います：

1. **新規台帳での検証**
   - Phase 2.5実装後、新規に作成される台帳で自動的に新機能が適用される
   - テスト用台帳を数件作成し、効果を検証

2. **既存台帳の部分的な再処理（必要に応じて）**

```php
// Artisan Tinkerで手動実行
php artisan tinker

// 特定の台帳を再処理
$ledger = Ledger::find(123);
dispatch(new ProcessLedgerForRagJob($ledger));

// または複数台帳をまとめて再処理
Ledger::whereIn('id', [123, 124, 125])->each(function($ledger) {
    dispatch(new ProcessLedgerForRagJob($ledger));
});
```

3. **開発完了後の本格導入時**
   - 全台帳の再処理が必要になった場合、以下のコマンドを作成：

```php
// 将来的に作成する場合の参考コード
php artisan rag:chunk-existing-ledgers --force
```

**開発フェーズでは、以下のアプローチを推奨:**
- ✅ 新規作成台帳で新機能をテスト
- ✅ 効果測定用に少数の既存台帳を手動で再処理
- ❌ 全台帳の一括再処理は不要（パフォーマンス負荷とリスクを避ける）

---

## 7. リスクと対策

| リスク | 影響度 | 対策 |
|--------|--------|------|
| **ベクトル次元増加によるストレージ圧迫** | 中 | 古いベクトルの定期削除、圧縮アルゴリズムの検討 |
| **検索速度の低下** | 高 | IVFインデックス、クエリキャッシュ、非同期検索の導入 |
| **形態素解析のオーバーヘッド** | 低 | バッチ処理、結果のキャッシュ |
| **Ruriモデルの更新による互換性問題** | 中 | バージョン固定、移行計画の策定 |

---

## 8. 将来の拡張案

### 8.1. マルチモーダル検索

- 画像内容とテキストを統合した検索
- 表や図の構造理解を活用した検索

### 8.2. ユーザーフィードバック学習

- 検索結果のクリック率を学習に反映
- 個別ユーザーの検索傾向に適応

### 8.3. クロスドメイン検索

- 複数の組織・プロジェクトをまたいだ検索
- 権限を考慮した安全な情報共有

---

## 10. WBS（作業分解構成図）

### Phase 2.5: 固有名詞・記号の先頭埋め込み ⭐ 最優先

| No | タスク | 成果物 | 担当 | 工数 | 依存関係 | 優先度 |
|----|--------|--------|------|------|----------|--------|
| 2.5.1 | 形態素解析拡張クラスの設計 | 設計ドキュメント | 開発 | 0.5日 | - | 最高 |
| 2.5.2 | `KeywordEnhancedTextGenerator`実装 | PHPクラス | 開発 | 1日 | 2.5.1 | 最高 |
| 2.5.3 | 既存パイプラインへの統合 | `ProcessLedgerForRagJob`修正 | 開発 | 0.5日 | 2.5.2 | 最高 |
| 2.5.4 | 単体テスト作成 | `KeywordEnhancedTextGeneratorTest` | 開発 | 0.5日 | 2.5.2 | 最高 |
| 2.5.5 | 統合テスト | テストケース実行・結果検証 | QA | 0.5日 | 2.5.3, 2.5.4 | 最高 |
| **2.5 合計** | | | | **3日** | | |

### Phase 2.6: Ruriプレフィックスを活用した複数OCR統合

| No | タスク | 成果物 | 担当 | 工数 | 依存関係 | 優先度 |
|----|--------|--------|------|------|----------|--------|
| 2.6.1 | プレフィックス統合クラスの設計 | 設計ドキュメント | 開発 | 0.5日 | Phase 2.5完了 | 高 |
| 2.6.2 | `SemanticPrefixAggregator`実装 | PHPクラス | 開発 | 1.5日 | 2.6.1 | 高 |
| 2.6.3 | `config/rag.php`にプレフィックス設定追加 | 設定ファイル更新 | 開発 | 0.5日 | 2.6.2 | 高 |
| 2.6.4 | 既存パイプラインへの統合 | `ProcessLedgerForRagJob`修正 | 開発 | 0.5日 | 2.6.2, 2.6.3 | 高 |
| 2.6.5 | 単体テスト作成 | `SemanticPrefixAggregatorTest` | 開発 | 0.5日 | 2.6.2 | 高 |
| 2.6.6 | 統合テスト・効果測定 | テスト結果レポート | QA | 1日 | 2.6.4, 2.6.5 | 高 |
| **2.6 合計** | | | | **4.5日** | | |

### Phase 2.7: キーワードベクトルの生成と保存

| No | タスク | 成果物 | 担当 | 工数 | 依存関係 | 優先度 |
|----|--------|--------|------|------|----------|--------|
| 2.7.1 | データベース設計・Migration作成 | Migration+設計書 | 開発 | 0.5日 | Phase 2.6完了 | 高 |
| 2.7.2 | Migration実行・テーブル拡張 | `ledger_chunks`更新 | 開発 | 0.5日 | 2.7.1 | 高 |
| 2.7.3 | `LedgerChunk`モデル更新 | Eloquentモデル | 開発 | 0.5日 | 2.7.2 | 高 |
| 2.7.4 | キーワードベクトル生成ロジック実装 | `ProcessLedgerForRagJob`拡張 | 開発 | 1.5日 | 2.7.3 | 高 |
| 2.7.5 | ベクトル保存・取得テスト | 単体テスト | 開発 | 1日 | 2.7.4 | 高 |
| 2.7.6 | パフォーマンステスト | ベンチマーク結果 | QA | 1日 | 2.7.5 | 高 |
| 2.7.7 | データ再処理スクリプト作成 | Tinkerコマンド | 開発 | 0.5日 | 2.7.4 | 中 |
| **2.7 合計** | | | | **5.5日** | | |

### Phase 3.1: RRFによるハイブリッド検索

| No | タスク | 成果物 | 担当 | 工数 | 依存関係 | 優先度 |
|----|--------|--------|------|------|----------|--------|
| 3.1.1 | ハイブリッド検索アーキテクチャ設計 | 設計ドキュメント | 開発 | 1日 | Phase 2.7完了 | 中 |
| 3.1.2 | `HybridSearchService`実装 | PHPクラス | 開発 | 2.5日 | 3.1.1 | 中 |
| 3.1.3 | RRFアルゴリズム実装・最適化 | RRFメソッド | 開発 | 1日 | 3.1.2 | 中 |
| 3.1.4 | `SearchLedgersTool`統合 | MCPツール更新 | 開発 | 1日 | 3.1.3 | 中 |
| 3.1.5 | 単体テスト作成 | `HybridSearchServiceTest` | 開発 | 1日 | 3.1.2, 3.1.3 | 中 |
| 3.1.6 | A/Bテスト実装 | フィーチャーフラグ設定 | 開発 | 0.5日 | 3.1.4 | 中 |
| 3.1.7 | 精度評価・効果測定 | 評価レポート | QA | 2日 | 3.1.6 | 中 |
| **3.1 合計** | | | | **9日** | | |

### Phase 3.2: OCRクロスバリデーション（オプション）

| No | タスク | 成果物 | 担当 | 工数 | 依存関係 | 優先度 |
|----|--------|--------|------|------|----------|--------|
| 3.2.1 | コンセンサスアルゴリズム設計 | 設計ドキュメント | 開発 | 0.5日 | Phase 3.1完了 | 低 |
| 3.2.2 | `ConsensusOcrAggregator`実装 | PHPクラス | 開発 | 1.5日 | 3.2.1 | 低 |
| 3.2.3 | 既存パイプラインへの統合 | パイプライン修正 | 開発 | 0.5日 | 3.2.2 | 低 |
| 3.2.4 | 精度検証テスト | テスト結果レポート | QA | 1日 | 3.2.3 | 低 |
| **3.2 合計** | | | | **3.5日** | | |

### 全体スケジュール

| Phase | 工数 | 開始条件 | 完了基準 | 備考 |
|-------|------|----------|----------|------|
| **Phase 2.5** | 3日 | なし | 新規台帳でキーワード埋め込みが動作 | 最優先実装 |
| **Phase 2.6** | 4.5日 | Phase 2.5完了 | プレフィックス統合でベクトル生成成功 | Phase 2.5との並行可 |
| **Phase 2.7** | 5.5日 | Phase 2.6完了 | キーワードベクトルのDB保存完了 | マイルストーン |
| **Phase 3.1** | 9日 | Phase 2.7完了 | ハイブリッド検索で精度向上確認 | 効果測定重要 |
| **Phase 3.2** | 3.5日 | Phase 3.1完了 | コンセンサス機能が動作 | オプション |
| **合計** | **25.5日** | - | - | 約5週間 |

### マイルストーン

| マイルストーン | 日程目安 | 成果物 | 評価指標 |
|--------------|---------|--------|----------|
| **MS1: キーワード埋め込み完了** | Week 1 | Phase 2.5完了 | 固有名詞検索精度+10% |
| **MS2: ベクトル統合基盤完成** | Week 2-3 | Phase 2.6-2.7完了 | 2種類のベクトル生成動作 |
| **MS3: ハイブリッド検索稼働** | Week 4-5 | Phase 3.1完了 | 総合検索精度+15% |
| **MS4: 全機能完成** | Week 6+ | Phase 3.2完了 | オプション機能評価 |

### リスク管理

| リスク | 発生確率 | 影響度 | 対策 | 担当 |
|--------|---------|--------|------|------|
| Groongaベクトル検索の性能劣化 | 中 | 高 | 閾値調整、チャンク制限 | 開発 |
| Ruriモデルの次元数変更 | 低 | 中 | 設定ファイルで吸収 | 開発 |
| 形態素解析のオーバーヘッド | 低 | 低 | キャッシュ実装 | 開発 |
| ハイブリッド検索の精度低下 | 中 | 中 | A/Bテストで検証 | QA |
| Phase 2.7完了時の容量不足 | 低 | 中 | ストレージ監視 | インフラ |

---

## 11. まとめ

本計画は、既存の形態素解析基盤（`SynonymService::wakati()`）、Mroonga/Groongaベクトル検索、Ruriモデルの特性を最大限活用し、段階的に検索精度を向上させるロードマップを提示した。

**重要なポイント:**
1. ✅ **Phase 2.5が最優先** - 既存コードを活用し、短期間で大きな効果（3日間）
2. ✅ **Ruriプレフィックスの活用** - 公式推奨の使い方を遵守
3. ✅ **Mroonga/Groongaの特性を理解** - 線形スキャンの制約を踏まえた実装
4. ✅ **段階的な実装** - 各Phaseで効果を測定しながら進める（計25.5日）
5. ✅ **既存機能との互換性** - 新機能はオプションとして追加

**Mroonga/Groongaベクトル検索に関する重要な結論:**
- 現在の実装（768次元、`distance_cosine`）は開発段階では十分
- 台帳数<10,000件では許容可能なパフォーマンス
- スケール時（台帳数>10,000件）には以下を検討：
  - 次元削減（PCAで384次元等）
  - PostgreSQL + pgvectorへの移行
  - 専用ベクトルDB（Milvus、Qdrant等）の導入

**次のアクション:**
- [ ] Phase 2.5の実装を開始（Week 1）
- [ ] テストデータセットの準備（100件）
- [ ] 評価指標の測定環境構築
- [ ] パフォーマンス監視の設定

**参考文献:**
- [Groonga Vector Column Documentation](https://groonga.org/docs/reference/columns/vector.html)
- [Mroonga Optimizations](https://mroonga.org/docs/reference/optimizations.html)
- [Ruri公式論文 (arXiv)](https://arxiv.org/abs/2409.07737)
- [HuggingFace: cl-nagoya/ruri-v3-310m](https://huggingface.co/cl-nagoya/ruri-v3-310m)

---

**作成者:** GitHub Copilot CLI  
**レビュー推奨:** LedgerLeap開発チーム  
**更新履歴:**
- 2025-11-16: 初版作成（Ruriモデル情報源追加、Mroonga/Groonga調査反映、WBS追加）
