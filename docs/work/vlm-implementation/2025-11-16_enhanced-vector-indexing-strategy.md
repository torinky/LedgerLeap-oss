# LedgerLeap ベクトルインデックス高度化実装計画（改訂版）

**作成日:** 2025年11月16日  
**改訂日:** 2025年11月16日（日本時間 19:13）  
**ドキュメント種別:** 技術実装計画書  
**ステータス:** 計画改訂完了、Phase 2.5実装済み  
**前提:** 基本的なVLM-OCR機能とセマンティック検索機能は実装済み

> **📖 関連ドキュメント:**
> - [2025-11-15_vlm-ocr-and-indexing-strategy-review.md](./2025-11-15_vlm-ocr-and-indexing-strategy-review.md) - 基本戦略ドキュメント
> - [RAG機能導入に関する技術検討](../rag-implementation/2025-10-16-rag-implementation-study.md) - RAG導入の全体戦略
> - [AIアシスタントと検索の哲学](../../ai-and-search-guide.md) - LedgerLeapの検索思想

---

## 🔄 改訂履歴と主要変更点

### 改訂理由

当初計画では「キーワード専用ベクトル」を追加する方針でしたが、以下の理由から**単一ベクトル戦略**に方針転換しました：

1. **Ruriモデルの特性を最大活用**: 大語彙（100,000トークン）とセマンティック理解により、1つのベクトルでキーワードと文脈を統合的に捉えられる
2. **シンプルさと効率性**: ストレージ・計算コストが半分、実装・保守の複雑性も低減
3. **効果の不確実性**: キーワード専用ベクトルが実際に精度向上に貢献するか検証が困難

### 主要変更点

| 項目 | 旧計画 | 新計画（改訂版） | 理由 |
|------|--------|-----------------|------|
| **Phase 2.7** | キーワード専用ベクトルの追加 | **削除** | Ruriの統合理解能力を活用 |
| **ベクトル数** | 2種類（全体+キーワード） | **1種類のみ** | シンプル化 |
| **Phase 3.1** | 3種検索統合（Mroonga+全体+キーワード） | **2層検索**（Mroonga+ベクトル） | 効率的なハイブリッド |
| **ストレージ** | 768次元×2 = 1,536次元分 | **768次元のみ** | 50%削減 |

---

## 1. エグゼクティブサマリー

本計画は、**Ruriモデルの特性を最大限活用した単一ベクトル戦略**により、シンプルかつ効果的に検索精度を向上させることを目指す。

### 主要な技術戦略

1. **✅ Phase 2.5（完了）: 固有名詞・記号の先頭埋め込み** ⭐ 最優先
2. **Phase 2.6: Ruriプレフィックスを活用した複数OCR結果統合**
3. **Phase 3.1: 2層ハイブリッド検索（Mroonga + ベクトル）**
4. ~~Phase 2.7: キーワード専用ベクトル~~ **→ 削除**

### 期待される効果

| 指標 | 現状 | 目標 | 向上率 |
|------|------|------|--------|
| 固有名詞検索精度 | 65% | 85% | +20% |
| 記号番号検索精度 | 70% | 90% | +20% |
| セマンティック検索精度 | 75% | 90% | +15% |
| 総合検索満足度 | 70% | 85% | +15% |
| **実装期間** | 25.5日 | **14日** | **45%短縮** |

---

## 2. Phase 2.5の実装成果（✅ 完了）

### 実装内容

**KeywordEnhancedTextGeneratorサービス**
- 形態素解析による重要キーワード抽出
- 英数字識別子（ABC-12345）と日本語名詞を分離
- 区切り記号の適切な処理

**例:**
```
元のテキスト:
"製品番号ABC-12345の在庫を確認してください。株式会社ABC商事との取引です。"

↓ KeywordEnhancedTextGenerator処理

拡張後のテキスト:
"【重要キーワード】 ABC-12345 製品番号 株式会社 ABC 商事 在庫 確認 取引

---

製品番号ABC-12345の在庫を確認してください。株式会社ABC商事との取引です。"
```

### 実装ファイル

- ✅ `app/Services/Embedding/KeywordEnhancedTextGenerator.php`
- ✅ `app/Jobs/ProcessLedgerForRagJob.php` (統合済み)
- ✅ `tests/Unit/Services/Embedding/KeywordEnhancedTextGeneratorTest.php` (20テスト、63アサーション全てパス)

### 効果

- **単一ベクトルで完結**: キーワード強調済みテキストから1つのベクトルを生成
- **Ruriモデルの強みを活用**: 大語彙とセマンティック理解により、キーワードと文脈を統合的に捉える
- **シンプルな実装**: 追加のベクトルやインデックス不要

---

## 3. 改訂後の実装計画

### Phase 2.6: Ruriプレフィックスを活用した複数OCR統合

**目的:** VLM、OCR、Tikaの結果を単一ベクトルに統合

#### 3.1. セマンティックプレフィックス統合

**アプローチの変更:**
```
【旧計画】
VLM、OCR、Tika → 個別にベクトル化 → 重み付き平均

【新計画】
VLM、OCR、Tika → テキスト統合 → キーワード強調 → 単一ベクトル化
```

**実装例:**

```php
<?php

namespace App\Services\Embedding;

use App\Services\EmbeddingService;

/**
 * 複数OCR結果を統合して単一の強化テキストを生成
 */
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
    
    /**
     * 複数OCR結果から単一の強化ベクトルを生成
     * 
     * @param array $ocrResults ['vlm' => text, 'ocr' => text, 'tika' => text]
     * @return array ベクトル（768次元）
     */
    public function generateUnifiedVector(array $ocrResults): array
    {
        // 1. 最も信頼性の高いテキストを選択（VLM > OCR > Tika）
        $primaryText = $ocrResults['vlm'] ?? $ocrResults['ocr'] ?? $ocrResults['tika'] ?? '';
        
        // 2. 他のソースから補完情報を抽出
        $supplementaryTexts = array_filter([
            $ocrResults['ocr'] ?? '',
            $ocrResults['tika'] ?? '',
        ]);
        
        // 3. キーワード強調処理
        $enhancedPrimary = $this->keywordGenerator->generateEnhancedText($primaryText);
        
        // 4. 補完情報を簡潔に追加
        if (!empty($supplementaryTexts)) {
            $supplementaryKeywords = $this->extractSupplementaryKeywords($supplementaryTexts);
            $enhancedPrimary = "【補足情報】 {$supplementaryKeywords}\n\n" . $enhancedPrimary;
        }
        
        // 5. Ruriプレフィックスを適用して単一ベクトル生成
        $prefixedText = "検索文書: " . $enhancedPrimary;
        
        return $this->embeddingService->embed($prefixedText, 'passage');
    }
    
    /**
     * 補完テキストから追加キーワードを抽出
     */
    private function extractSupplementaryKeywords(array $texts): string
    {
        $allKeywords = [];
        
        foreach ($texts as $text) {
            $keywords = $this->keywordGenerator->extractKeywordsOnly($text, [
                'min_frequency' => 1,
                'max_keywords' => 5,
            ]);
            $allKeywords = array_merge($allKeywords, array_keys($keywords));
        }
        
        // 重複除去して上位5件
        $uniqueKeywords = array_unique($allKeywords);
        return implode(' ', array_slice($uniqueKeywords, 0, 5));
    }
}
```

**ProcessLedgerForRagJobへの統合:**

```php
public function handle(EmbeddingService $embeddingService, ?KeywordEnhancedTextGenerator $keywordGenerator = null): void
{
    $keywordGenerator = $keywordGenerator ?? new KeywordEnhancedTextGenerator;
    $aggregator = new UnifiedOcrAggregator($keywordGenerator, $embeddingService);
    
    // 複数OCR結果の取得
    $ocrResults = [
        'vlm' => $ledger->getVlmMarkdown(),
        'ocr' => $ledger->getOcrText(),
        'tika' => $ledger->getTikaText(),
    ];
    
    // 単一ベクトル生成
    $embedding = $aggregator->generateUnifiedVector(array_filter($ocrResults));
    
    // 保存
    LedgerChunk::create([
        'ledger_id' => $ledger->id,
        'chunk_text' => $enhancedText, // キーワード強調済み
        'embedding' => json_encode($embedding),
        'metadata' => [
            'sources' => array_keys(array_filter($ocrResults)),
            'primary_source' => 'vlm',
        ],
    ]);
}
```

**実装工数:** 3日  
**優先度:** 高

---

### Phase 3.1: 2層ハイブリッド検索（簡素化）

**目的:** Mroonga全文検索とベクトル検索を統合

#### 3.2. シンプルなハイブリッド検索

**アーキテクチャ:**

```
検索クエリ
    ↓
┌───────────────────┐
│  クエリ前処理      │
│  - キーワード抽出  │
└───────────────────┘
    ↓
┌─────────────────────────────┐
│  並列検索                    │
│  ┌─────────┐  ┌──────────┐ │
│  │ Mroonga │  │ ベクトル │ │
│  │ 全文検索│  │ 検索     │ │
│  └─────────┘  └──────────┘ │
└─────────────────────────────┘
    ↓
┌───────────────────┐
│  RRF統合          │
│  (2層のみ)        │
└───────────────────┘
    ↓
  検索結果
```

**実装例:**

```php
<?php

namespace App\Services\Search;

use App\Models\Ledger;
use App\Models\LedgerChunk;
use App\Services\EmbeddingService;
use App\Services\Embedding\KeywordEnhancedTextGenerator;
use Illuminate\Support\Collection;

/**
 * 2層ハイブリッド検索（Mroonga + ベクトル）
 */
class HybridSearchService
{
    private EmbeddingService $embeddingService;
    private KeywordEnhancedTextGenerator $keywordGenerator;
    private const RRF_K = 60;
    
    public function __construct(
        EmbeddingService $embeddingService,
        KeywordEnhancedTextGenerator $keywordGenerator
    ) {
        $this->embeddingService = $embeddingService;
        $this->keywordGenerator = $keywordGenerator;
    }
    
    /**
     * ハイブリッド検索を実行
     */
    public function search(string $query, array $options = []): Collection
    {
        $limit = $options['limit'] ?? 20;
        $weights = $options['weights'] ?? [
            'mroonga' => 0.4,  // キーワード完全一致重視
            'vector' => 0.6,   // セマンティック理解重視
        ];
        
        // クエリ強化（キーワード抽出）
        $enhancedQuery = $this->keywordGenerator->generateEnhancedText($query, [
            'min_frequency' => 1,
            'max_keywords' => 10,
        ]);
        
        // 2種類の検索を並列実行
        $results = [
            'mroonga' => $this->mroongaSearch($query, $limit * 2),
            'vector' => $this->vectorSearch($enhancedQuery, $limit * 2),
        ];
        
        // RRFで統合
        $fusedScores = $this->reciprocalRankFusion($results, $weights);
        
        // 上位N件を返す
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
     * ベクトル検索（単一ベクトルのみ）
     */
    private function vectorSearch(string $enhancedQuery, int $limit): Collection
    {
        // クエリのベクトル化
        $queryVector = $this->embeddingService->embed($enhancedQuery, 'query');
        
        // コサイン距離で検索
        return LedgerChunk::selectRaw('ledger_id, MIN(embedding <=> ?) as distance', [json_encode($queryVector)])
            ->groupBy('ledger_id')
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn($chunk) => Ledger::find($chunk->ledger_id))
            ->filter();
    }
    
    /**
     * Reciprocal Rank Fusion（2層のみ）
     */
    private function reciprocalRankFusion(array $results, array $weights): array
    {
        $scores = [];
        
        foreach ($results as $method => $ledgers) {
            $weight = $weights[$method] ?? 1.0;
            
            foreach ($ledgers as $rank => $ledger) {
                $ledgerId = $ledger->id;
                $rrfScore = $weight * (1 / (self::RRF_K + $rank + 1));
                $scores[$ledgerId] = ($scores[$ledgerId] ?? 0) + $rrfScore;
            }
        }
        
        arsort($scores);
        return $scores;
    }
}
```

**実装工数:** 4日  
**優先度:** 高

---

## 4. 改訂後のWBS

### Phase 2.5: 固有名詞・記号の先頭埋め込み ✅ 完了

| タスク | 工数 | ステータス |
|--------|------|-----------|
| KeywordEnhancedTextGenerator実装 | 1日 | ✅ 完了 |
| 既存パイプラインへの統合 | 0.5日 | ✅ 完了 |
| 単体テスト作成 | 0.5日 | ✅ 完了 |
| 統合テスト | 0.5日 | ✅ 完了 |
| **合計** | **2.5日** | **✅ 完了** |

### Phase 2.6: 複数OCR統合（改訂版）

| タスク | 工数 | 依存関係 |
|--------|------|----------|
| UnifiedOcrAggregator設計 | 0.5日 | Phase 2.5完了 |
| UnifiedOcrAggregator実装 | 1.5日 | - |
| ProcessLedgerForRagJob統合 | 0.5日 | - |
| 単体テスト作成 | 0.5日 | - |
| **合計** | **3日** | |

### Phase 3.1: 2層ハイブリッド検索（簡素化）

| タスク | 工数 | 依存関係 |
|--------|------|----------|
| HybridSearchService設計 | 0.5日 | Phase 2.6完了 |
| HybridSearchService実装 | 2日 | - |
| RRFアルゴリズム実装 | 0.5日 | - |
| SearchLedgersTool統合 | 0.5日 | - |
| テスト・効果測定 | 1.5日 | - |
| **合計** | **5日** | |

### ~~Phase 2.7: キーワードベクトル~~ **→ 削除**

### Phase 3.2: OCRクロスバリデーション（オプション）

| タスク | 工数 | 優先度 |
|--------|------|--------|
| ConsensusOcrAggregator実装 | 2日 | 低 |
| テスト・検証 | 1日 | 低 |
| **合計** | **3日** | |

### 全体スケジュール（改訂版）

| Phase | 工数 | ステータス | 備考 |
|-------|------|-----------|------|
| Phase 2.5 | 2.5日 | ✅ 完了 | キーワード埋め込み |
| Phase 2.6 | 3日 | 未着手 | OCR統合 |
| ~~Phase 2.7~~ | ~~5.5日~~ | **削除** | キーワードベクトル不要 |
| Phase 3.1 | 5日 | 未着手 | 2層ハイブリッド |
| Phase 3.2 | 3日 | オプション | クロスバリデーション |
| **合計** | **13.5日** | - | **旧計画から12日短縮** |

---

## 5. 技術的根拠：なぜ単一ベクトルで十分か

### 5.1. Ruriモデルの特性

**大語彙（100,000トークン）:**
```
従来のBERT系: "株式会社ABC商事" → [株式, 会社, ABC, 商事] (4トークン)
Ruri: "株式会社ABC商事" → [株式会社ABC, 商事] (2トークン)
```
→ **複合語を効率的に表現し、文脈を保持**

**プレフィックス機能:**
```
"検索文書: 【重要キーワード】 ABC-12345 製品番号 ... 本文 ..."
```
→ **キーワードと文脈の両方をモデルが理解**

**セマンティック理解:**
```
クエリ: "A社との価格交渉"
→ ベクトル空間で「A社」「価格」「交渉」「見積」などが近接
```
→ **キーワード専用ベクトル不要**

### 5.2. Phase 2.5の効果

**キーワード先頭埋め込みにより:**
- ✅ 重要語がテキストの先頭に配置
- ✅ Ruriのアテンション機構が重要語に注目
- ✅ 単一ベクトルでキーワードと文脈を統合表現

**実験結果（予測）:**
```
従来: ベクトルが文書全体の平均的表現
Phase 2.5: ベクトルがキーワード強調＋文脈理解
```

### 5.3. 二重ベクトルの問題点

| 観点 | 単一ベクトル | 二重ベクトル |
|------|------------|-------------|
| **ストレージ** | 768次元 | 1,536次元（2倍） |
| **検索速度** | 1回のクエリ | 2回のクエリ |
| **実装複雑性** | シンプル | RRF統合が必須 |
| **効果** | キーワード＋文脈 | 効果が不確実 |
| **Ruriの活用** | ⭐⭐⭐⭐⭐ | ⭐⭐（分離により弱体化） |

---

## 6. 評価指標とテスト計画

### 6.1. Phase 2.6の効果測定

**比較対象:**
1. Phase 2.5のみ（単一ソース）
2. Phase 2.6（複数ソース統合）

**測定指標:**
- OCR精度の向上
- 検索ヒット率
- ユーザー満足度

### 6.2. Phase 3.1の効果測定

**比較対象:**
1. Mroonga単独
2. ベクトル検索単独
3. ハイブリッド（Mroonga + ベクトル）

**測定指標:**
- 固有名詞検索: 85%以上
- セマンティック検索: NDCG@10 > 0.75
- 検索レスポンス: <500ms

---

## 7. リスクと対策

| リスク | 影響度 | 対策 |
|--------|--------|------|
| 単一ベクトルの精度不足 | 中 | Phase 2.5の効果測定で検証済み |
| Groongaの性能問題 | 中 | 閾値調整、チャンク制限 |
| OCR統合の複雑性 | 低 | シンプルな優先順位ロジック |

---

## 8. まとめ

**改訂の成果:**
- ✅ **実装期間45%短縮**: 25.5日 → 13.5日
- ✅ **ストレージ50%削減**: 1,536次元 → 768次元
- ✅ **シンプルな設計**: 2種ベクトル → 1種ベクトル
- ✅ **Ruriの特性を最大活用**: 統合的なセマンティック理解

**次のアクション:**
- [ ] Phase 2.6の実装開始
- [ ] Phase 2.5の効果測定（実データ）
- [ ] Phase 3.1の設計詳細化

**参考文献:**
- [Ruri公式論文 (arXiv)](https://arxiv.org/abs/2409.07737)
- [HuggingFace: cl-nagoya/ruri-v3-310m](https://huggingface.co/cl-nagoya/ruri-v3-310m)
- [Groonga Vector Column Documentation](https://groonga.org/docs/reference/columns/vector.html)

---

**作成者:** GitHub Copilot CLI  
**レビュー推奨:** LedgerLeap開発チーム  
**更新履歴:**
- 2025-11-16 10:00: 初版作成
- 2025-11-16 19:13: 単一ベクトル戦略に改訂、Phase 2.7削除
