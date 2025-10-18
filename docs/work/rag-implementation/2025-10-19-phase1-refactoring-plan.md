# RAG導入 Phase1 計画見直し - RagSearchServiceリファクタリング

**作成日:** 2025年10月19日  
**ステータス:** 計画策定  
**作成者:** Gemini CLI

> **📖 関連ドキュメント:**
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 当初の全体計画

---

## 1. 計画見直しの背景と目的

### 1.1. 背景

当初の計画「[2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md)」に基づき実装された `app/Services/RagSearchService.php` をレビューした結果、アーキテクチャが計画と大きく異なっていることが判明しました。

- **計画:** Mroongaのベクトル検索インデックスを活用し、データベースエンジン内で高速な類似度検索を実行する。
- **現状:** データベースから**すべてのチャンク**を取得し、PHPアプリケーション側で1件ずつ類似度を計算（総当たり方式）。

この実装は、チャンク数が少ないうちは動作しますが、データ量の増加に対してスケーラビリティが全くなく、実用段階で深刻な性能問題を引き起こすことが確実です。UI統合（WBS2.3, WBS3）を進める前に、この技術的負債を解消し、堅牢なバックエンド基盤を再構築する必要があります。

### 1.2. 本計画の目的

本計画は、WBS2「API・検索ロジック実装」の作業を一旦中断し、`RagSearchService` を当初の計画通りMroongaのベクトル検索機能を活用した、スケーラブルな実装にリファクタリングすることを目的とします。

これにより、性能要件を満たすバックエンド基盤を確立し、安心してUI・API統合に進める状態を目指します。

## 2. 課題とリファクタリング方針

### 2.1. 課題分析

- **性能問題:** 現状の実装では、チャンク数が10,000件を超えた場合、PHPのメモリ使用量と処理時間が許容範囲を大幅に超えると予測されます。
- **計画との乖離:** WBS1.2「Mroongaベクトル検索の技術検証（Spike）」が不十分なまま、代替実装が進められた可能性があります。
- **手戻りリスク:** このままUI・API統合を進めても、性能問題により再度バックエンドの改修が必要となり、大きな手戻りが発生します。

### 2.2. リファクタリング方針

1.  **Mroongaベクトル検索の活用:** Mroongaが提供する `mroonga_command(\'select\', ...)` と `vector_search` 関数を利用して、データベース側で類似度上位のチャンクを高速に絞り込む方式に変更します。
2.  **PHPの役割の最適化:** PHP側は、Mroongaから受け取ったチャンクIDとスコアを元に、台帳情報の集約とページネーション処理に専念させます。
3.  **メソッドシグネチャの統一:** 当初計画の `search`, `searchForApi` といったメソッド名に合わせ、責務を明確化します。

## 3. 新規実装タスク一覧 (WBS)

当初計画のWBS2、WBS3、WBS4、WBS5は一旦**保留**とし、以下のタスクを優先して実施します。

| ID | タスク | 担当 | 見積工数 | 備考 |
| :--- | :--- | :--- | :--- | :--- |
| **1.6** | **Mroongaベクトル検索の再検証** | Backend | 1.0日 | |
| 1.6.1 | `mroonga_command` を使ったベクトル検索クエリの構文と動作を再検証 | Backend | 0.5日 | `vector_search` 関数のパラメータ、スコアの正規化方法を確認 |
| 1.6.2 | PHPから安全に `mroonga_command` を実行する方法を確立 | Backend | 0.5日 | `DB::statement` や `DB::select` での実装方法を確定 |
| **1.7** | **`RagSearchService` のリファクタリング** | **Backend** | **1.5日** | |
| 1.7.1 | `search` メソッドをMroongaベクトル検索を利用する形に全面改修 | Backend | 1.0日 | PHPでの総当たり計算ロジックを撤廃 |
| 1.7.2 | `searchForApi` メソッドを実装 | Backend | 0.5日 | `search` メソッドをラップし、API向けのデータ形式に整形 |
| **1.8** | **リファクタリング後のテストと検証** | **Backend** | **1.5日** | |
| 1.8.1 | `RagSearchService` の単体テストをリファクタリング | Backend | 0.5日 | Mroongaを利用した新ロジックに合わせテストを修正。既存テストとの整合性を保つ。 |
| 1.8.2 | 性能測定テストを作成・実施 | Backend | 0.5日 | 1万件以上のチャンクデータでの検索速度が1秒以内であることを確認 |
| 1.8.3 | 統合テストの完走確認 | Backend | 0.5日 | 関連するテストスイートを実行し、全てのテストがパスすることを確認する。 |
| | **合計** | | **4.0日** | |

## 4. 詳細設計（案）

### 4.1. `RagSearchService` の改修案

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RagSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private WritableFolderRepository $writableFolderRepository
    ) {}

    /**
     * セマンティック検索を実行（Livewire用）
     */
    public function search(
        string $query,
        User $user,
        array $ledgerDefineIds,
        array $filter = [],
        int $perPage = 100
    ): LengthAwarePaginator
    {
        // 1. クエリをベクトル化
        $queryVector = $this->embeddingService->embed($query);
        $queryVectorJson = json_encode($queryVector);

        // 2. ユーザーが読み取り可能なフォルダIDを取得
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
        if (empty($readableFolderIds)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        // 3. Mroongaのvector_searchを使ってチャンクを検索
        $mroongaCommand = $this->buildMroongaCommand(
            $queryVectorJson,
            $readableFolderIds,
            $ledgerDefineIds
        );
        
        try {
            $rawChunks = DB::select($mroongaCommand);
        } catch (\Exception $e) {
            Log::channel(\'rag\')->error(\'Mroonga vector search failed\', [
                \'error\' => $e->getMessage(),
                \'command\' => $mroongaCommand
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }

        // 4. スコア集計と台帳IDの抽出
        $aggregatedScores = $this->aggregateChunkScores(collect($rawChunks));
        
        // 5. 台帳モデルを取得し、ページネーション
        $ledgerIds = $aggregatedScores->pluck(\'ledger_id\');
        $ledgers = Ledger::whereIn(\'id\', $ledgerIds)
            ->with([\'define\', \'creator\', \'modifier\'])
            ->get()
            ->keyBy(\'id\');

        // Mroongaの結果順に並べ替え
        $sortedLedgers = $ledgerIds->map(fn($id) => $ledgers->get($id))->filter();

        // ページネーション処理
        $currentPage = Paginator::resolveCurrentPage();
        $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $perPage, $perPage)->all();

        return new LengthAwarePaginator(
            $currentPageItems,
            $sortedLedgers->count(),
            $perPage,
            $currentPage
        );
    }

    /**
     * Mroonga検索コマンドを構築する
     */
    private function buildMroongaCommand(
        string $queryVectorJson,
        array $readableFolderIds,
        array $ledgerDefineIds
    ): string
    {
        $folderIdsStr = implode(\'\', \', $readableFolderIds);
        $defineIdsStr = implode(\'\', \', $ledgerDefineIds);

        // mroonga_commandの組み立て
        // \'scorer\' でスコア計算方法を指定
        // \'filter\' で権限と台帳定義を絞り込み
        $command = sprintf(
            "select ledger_chunks --command_version 2 --output_columns 'ledger_id, _score' --limit -1 " . 
            "--drilldown ledger_id --drilldown_output_columns '_key, _nsubrecs' " . 
            "--drilldown_sortby '-_score' " . 
            "--query 'embedding:@{\"query_vector\":%s, \"scorer\":\"VectorCosineSimilarityScorer\", \"parameters\":{\"vector_column\":\"embedding\"}, \"algorithm\":\"brute_force\"}' " . 
            "--filter 'folder_id IN (%s) && ledger_define_id IN (%s)'",
            $queryVectorJson,
            $folderIdsStr,
            $defineIdsStr
        );

        return "SELECT mroonga_command('$command')";
    }
    
    /**
     * チャンクスコアを台帳単位で集計
     */
    private function aggregateChunkScores(Collection $chunks): Collection
    {
        // Mroongaのdrilldown結果は既に集計されているため、
        // ここでは主にデータ形式の変換を行う
        // Mroongaの結果形式に合わせて要調整
        return $chunks;
    }

    /**
     * セマンティック検索を実行（MCP API用）
     */
    public function searchForApi(User $user, array $params): array
    {
        // searchメソッドを呼び出し、API用のレスポンス形式に整形する
        // (実装はリファクタリング後に行う)
        return [];
    }
}
```

## 5. 次のステップ

1.  **本リファクタリング計画の合意:**
    本ドキュメントの内容について合意を得てください。

2.  **タスクの実行:**
    合意後、上記のWBS 1.6〜1.8のタスクに着手します。

3.  **当初計画への復帰:**
    リファクタリングとテストが完了し、`RagSearchService` の性能が担保された後、改めて当初計画のWBS2.3（Livewire統合）以降のタスクを再開します。

---
**このドキュメントは、より堅牢でスケーラブルなRAG機能を実装するための計画見直し案です。**
---
