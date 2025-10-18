# LedgerLeapへのRAG機能導入に関する技術検討

**作成日:** 2025年10月16日  
**更新日:** 2025年10月16日  
**ドキュメント種別:** 作業ファイル（技術検討・計画段階）  
**ステータス:** 計画中（現状実装との整合性確認済み）

> **📖 関連ドキュメント:**
> - [MCP アーキテクチャと動作フロー](../development/MCP_Architecture_and_Flow.md) - 既存のLLM統合基盤
> - [LLM連携機能 開発ロードマップ](./llm-integration/2025-09-23_LLM_Integration_Roadmap.md) - 全体戦略
> - [API仕様概要](../api/README.md) - 現行API仕様

---

## 1. はじめに

### 1.1. 本ドキュメントの目的

本ドキュメントは、LedgerLeapにRAG（Retrieval-Augmented Generation）機能を導入するにあたり、考えられる技術的選択肢を比較検討し、最適なアプローチを判断するための技術資料である。各アプローチのアーキテクチャ、実装上の考慮点、メリット・デメリットを具体的に示し、意思決定を支援することを目的とする。

**2025年10月16日更新:** 既存のMCP統合機能との関係性、現状実装との整合性、技術的詳細を大幅に拡充した。

### 1.2. 既存システムとの関係

#### 1.2.1. 現状のMCP統合機能（2025年10月時点）

LedgerLeapは既に以下のLLM統合基盤を実装済みである:

```
ユーザー → LLM (Claude/Gemini) → MCP Server → Laravel API
                                      ↓
                              SearchLedgersTool
                              CreateLedgerTool
                              GetLedgerDefinesTool
                                      ↓
                              LedgerService::searchLedgersForApi()
                                      ↓
                              Mroonga全文検索 (キーワードベース)
```

**現状の検索能力:**
- キーワード完全一致による全文検索
- 高度なフィルタリング（タグ、フォルダ、作成者、日付範囲）
- スコアリングシステムとの統合（composite_score, activity_score）
- 権限ベースのフィルタリング（ユーザーが閲覧可能な台帳のみ返却）

**現状の限界:**
- **意味的検索の欠如:** 「トラブル対応」と「クレーム処理」は意味的に近いが、キーワード一致しないと検索されない
- **長文書の扱い:** 台帳全体を返すため、LLMのコンテキストウィンドウを圧迫
- **添付ファイル活用の不完全性:** OCR抽出済みの`content_attached`を検索できるが、関連部分のみの抽出は不可

#### 1.2.2. RAGが解決する課題

| 課題 | 現状 | RAG導入後 |
|------|------|-----------|
| 意味的検索 | 不可 | セマンティック検索で類似概念も発見 |
| 長文書処理 | 台帳全体を返却 | 関連チャンクのみ抽出→効率化 |
| 添付ファイル活用 | 全文検索のみ | PDF内の特定セクションを直接参照可能 |
| 検索精度 | キーワード依存 | コンテキスト理解に基づく高精度検索 |

### 1.3. RAGを実現するための共通コンポーネント

どの実装アプローチを選択するにせよ、RAG機能の実現には以下のコンポーネントが共通して必要となる。

#### 1.3.1. コアコンポーネント

- **データソース:** 
  - `ledgers.content` (JSON形式の台帳本体)
  - `ledgers.content_attached` (Apache Tika/OCRにより抽出された添付ファイルテキスト)
  - 既存のMroongaインデックスは継続利用可能

- **チャンキング (Chunking):** 
  - 長文テキストを意味のある塊（チャンク）に分割する処理
  - 推奨チャンクサイズ: 500-1000トークン（オーバーラップ100-200トークン）
  - LangChain PHPの`RecursiveCharacterTextSplitter`が利用可能

- **エンベディング (Embedding):** 
  - チャンクをベクトル化（意味を数値配列に変換）する処理
  - **モデル選定の前提条件:** LedgerLeapの運用要件として「外部サービスを利用しないクローズドな環境」「GPUを搭載しない汎用サーバー」が必須となる。このため、エンベディングモデルは自前でホストし、CPUで現実的な時間内に処理を完了できる必要がある。
  - **モデル候補の再調査 (2025/10/18):** 上記の制約に基づき、最新のオープンソースモデルを再調査。性能、モデルサイズ、ライセンス、多言語対応能力を比較検討した結果、以下の2つを最終候補とする。

| 項目 | 【案A】 BAAI/bge-m3 (性能重視) | 【案B】 intfloat/multilingual-e5-base (速度重視) |
| :--- | :--- | :--- |
| **性能 (MTEB)** | **`e5-large` を上回る** | `e5-large` よりは低いが高性能 |
| **コンテキスト長** | **8192 トークン** | 512 トークン |
| **検索方式** | **ハイブリッド対応** (密+疎) | 密検索のみ |
| **モデルサイズ** | 約2.2 GB | 約1.1 GB |
| **次元数** | 1024 | 768 |
| **推奨度** | **◎ (第一候補)** | ○ (代替案) |

  - **結論:** PoCでは、まず第一候補である `BAAI/bge-m3` の性能を実測する。CPUでの処理速度が許容できない場合の代替案として `intfloat/multilingual-e5-base` を用意する。

- **ベクトルストア (Vector Store):** 
  - ベクトルとメタデータを格納・検索するデータベース
  - アプローチ1ではMySQLで実現、アプローチ2では専用DBを導入

- **リトリーバル (Retrieval):** 
  - ユーザークエリに基づき、ベクトルストアから関連チャンクを検索する処理
  - 権限フィルタリングとの統合が必須

- **生成 (Generation):** 
  - 検索したチャンクをコンテキストとしてLLMが最終的な回答を生成する処理
  - 既存のMCPフローに統合可能

#### 1.3.2. LedgerLeap固有の要件

**権限管理との統合 [CRITICAL]:**
```php
// 既存実装: LedgerService::searchLedgersForApi() (L49-L55)
$readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);

$query->whereHas('define.folder', function ($q) use ($readableFolderIds) {
    $q->whereIn('id', $readableFolderIds);
});
```
→ RAG検索でも同様の権限チェックが必須。チャンクレベルでの権限制御を実装する必要がある。

**既存OCR/Tikaパイプラインの活用:**
```
ファイルアップロード → ProcessOcrJob → Apache Tika抽出
                                    ↓
                              ocrmypdf (日本語OCR)
                                    ↓
                          content_attached に保存
```
→ この既存パイプラインに、エンベディング処理を追加する形で統合可能

## 2. 検討アプローチ

本検討では、LedgerLeapの既存技術スタックとの親和性、性能、拡張性の観点から、以下の3つのアプローチを評価する。

| アプローチ | 特徴 | 現状システムとの整合性 |
|-----------|------|---------------------|
| **アプローチ1** | MySQL/Mroonga活用 | 既存インフラを最大限活用 |
| **アプローチ2** | 専用ベクトルDB導入 | 新規ミドルウェア追加 |
| **アプローチ3** | ハイブリッド検索 | 既存全文検索とRAGの融合 |

---

## 3. 各アプローチの詳細検討

### 3.1. アプローチ1: MySQL/Mroonga 活用案（親和性重視型）

#### 3.1.1. アーキテクチャ概要

既存のMySQL/Mroonga環境をベクトルストアとして利用する。台帳の更新をトリガーに非同期ジョブでチャンキングとエンベディングを行い、Mroongaのベクトル検索機能で検索する。

**既存システムとの統合点:**
- Observerパターン: `UserObserver`, `LedgerDiffObserver`等で既に採用済み
- 非同期ジョブ: `ProcessOcrJob`と同様のRedisキュー処理
- Mroongaインデックス: `ledgers`テーブルで既に実装済み（L36-L38参照）

```
┌───────────┐      ┌─────────────────┐      ┌─────────────────┐
│ Ledger    │      │ Laravel         │      │ MySQL / Mroonga │
│ (Create/  │─────>│ LedgerObserver  │─────>│ (Redis Queue)   │
│ Update)   │      │ [NEW]           │      │                 │
└───────────┘      └─────────────────┘      └─────────────────┘
                                                     │
                                                     ▼
┌───────────────────────┐      ┌─────────────────────────┐
│ ledger_chunks Table   │<─────│ ProcessLedgerForRagJob  │
│ + Mroonga Vector Index│      │ [NEW]                   │
│ [NEW]                 │      │ - Chunking              │
└───────────────────────┘      │ - Embedding (Python/API)│
                               │ - Permission metadata   │
                               └─────────────────────────┘
```

**既存コンポーネントの再利用:**
```php
// 既存: app/Observers/LedgerDiffObserver.php
class LedgerDiffObserver {
    public function created(LedgerDiff $ledgerDiff) {
        // 既存のObserverパターン
    }
}

// 新規: app/Observers/LedgerObserver.php
class LedgerObserver {
    public function created(Ledger $ledger) {
        ProcessLedgerForRagJob::dispatch($ledger);
    }
    
    public function updated(Ledger $ledger) {
        // content または content_attached が変更された場合のみ
        if ($ledger->wasChanged(['content', 'content_attached'])) {
            ProcessLedgerForRagJob::dispatch($ledger);
        }
    }
}
```

#### 3.1.2. データベース設計

`ledger_chunks`テーブルを新設し、Mroongaのベクトルインデックスを設定する。

**既存のledgersテーブルとの関係:**
```sql
-- 既存: database/migrations/2022_05_08_100514_create_ledgers_table.php
-- ENGINE = Mroonga
-- FULLTEXT INDEX on content, content_attached
-- ※複合インデックスは機能しない（L38のインデックスは実質使用不可）

-- 新規: ledger_chunksテーブル
CREATE TABLE ledger_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ledger_id BIGINT UNSIGNED NOT NULL,
    
    -- チャンクメタデータ
    chunk_index INT NOT NULL,           -- チャンク順序（0から開始）
    chunk_text TEXT NOT NULL,           -- 元テキスト（検索結果表示用）
    chunk_source ENUM('content', 'content_attached') NOT NULL, -- 由来
    
    -- ベクトルデータ（bge-m3: 1024次元, e5-base: 768次元）
    embedding VARBINARY(4096) NOT NULL, -- 1024次元 * 4bytes = 4096 bytes
    
    -- 権限管理用の非正規化カラム [CRITICAL]
    folder_id BIGINT UNSIGNED NOT NULL, -- 権限チェック高速化
    ledger_define_id BIGINT UNSIGNED NOT NULL,
    creator_id INT UNSIGNED NOT NULL,
    
    -- メタデータ
    token_count INT UNSIGNED,           -- チャンクのトークン数
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- インデックス設計
    INDEX idx_ledger_id (ledger_id),
    INDEX idx_folder_id (folder_id),    -- 権限フィルタリング用
    INDEX idx_source (chunk_source),
    
    -- Mroongaベクトルインデックス
    FULLTEXT INDEX idx_vector (embedding) COMMENT 'flags "VECTOR"',
    
    -- 複合インデックス（権限チェック最適化）
    INDEX idx_folder_ledger (folder_id, ledger_id)
    
) ENGINE=Mroonga COLLATE utf8mb4_unicode_ci;
```

**設計の根拠:**

1. **権限管理カラムの非正規化:**
   ```php
   // 非正規化なし（N+1問題）
   $chunks->each(fn($c) => 
       $user->canReadFolder($c->ledger->define->folder) // 各チャンクでJOIN発生
   );
   
   // 非正規化あり（高速）
   $chunks = Chunk::whereIn('folder_id', $readableFolderIds)->get();
   ```

2. **chunk_source分離:**
   - `content`由来: 台帳本体の構造化データ
   - `content_attached`由来: OCR抽出テキスト（長文・非構造化）
   - 検索時に由来を明示できると、LLMの回答生成時に信頼性判断可能

3. **token_countの記録:**
   - LLMのコンテキストウィンドウ管理に使用
   - 「上位Nチャンク」ではなく「合計Xトークンまで」という取得が可能

**容量試算 (2025/10/18 更新):**
```
前提:
- 台帳数: 100,000件
- 平均チャンク数/台帳: 3個
- 1次元: float32 = 4 bytes

**【案A】 BAAI/bge-m3 (1024次元) の場合:**
- 1チャンクのエンベディングサイズ: 1024 * 4 = 4,096 bytes ≈ 4KB
- 総ベクトルデータ: 300,000 * 4KB ≈ 1.2GB
- メタデータ等込み合計: **約1.4GB**

**【案B】 intfloat/multilingual-e5-base (768次元) の場合:**
- 1チャンクのエンベディングサイズ: 768 * 4 = 3,072 bytes ≈ 3KB
- 総ベクトルデータ: 300,000 * 3KB ≈ 0.9GB
- メタデータ等込み合計: **約1.1GB**

→ いずれのモデルを採用した場合でも、10万台帳規模で1.1GB〜1.4GB程度。MySQL/Mroongaで十分に管理可能な現実的な範囲内である。
```

#### 3.1.3. 実装詳細

##### 3.1.3.1. データ同期処理（Observer実装）

**既存のObserverパターンを踏襲:**
- `app/Observers/UserObserver.php` - ユーザー操作の監視
- `app/Observers/LedgerDiffObserver.php` - 差分管理
- **新規:** `app/Observers/LedgerObserver.php` - RAGチャンク同期

```php
// 新規ファイル: app/Observers/LedgerObserver.php
class LedgerObserver
{
    public function created(Ledger $ledger): void
    {
        ProcessLedgerForRagJob::dispatch($ledger);
    }

    public function updated(Ledger $ledger): void
    {
        if ($ledger->wasChanged(['content', 'content_attached'])) {
            ProcessLedgerForRagJob::dispatch($ledger, deleteExisting: true);
        }
    }
}
```

##### 3.1.3.2. チャンキングとエンベディング

**ジョブキューの活用:**
- 既存の`ProcessOcrJob`と同様、Redisキューで非同期処理
- タイムアウト設定: 10分（CPUでのエンベディング処理は時間がかかる可能性があるため、長めに設定）

**エンベディングモデルの実行方法:**
クローズド環境・GPUなしの制約のため、モデルを自前でホストしCPUで実行する。実装方法としては、以下の選択肢が考えられる。
- **案A: Python連携:** PHPから `shell_exec` などでPythonスクリプトを呼び出す。最もシンプルだが、エラーハンドリングや環境分離が課題。
- **案B: Pythonマイクロサービス:** エンベディング処理専用の軽量なWebサーバー（Flask/FastAPIなど）を別コンテナで立て、PHPからHTTPでリクエストする。処理が分離され、安定性が高い。
- **案C: PHPライブラリ:** [php-ai/php-ml](https://github.com/php-ai/php-ml) のようなライブラリとONNX形式に変換したモデルを利用する。PHP内で完結するが、モデルの互換性やパフォーマンスは未知数。

**推奨実装フロー:**
1.  **PoC (Phase 1):** まずは **案A（Python連携）** で迅速に技術検証を行う。`sentence-transformers` を利用したシンプルなPythonスクリプトを用意し、そのCPU実行時の性能（速度、メモリ使用量）を実測することが主目的。
2.  **本格導入 (Phase 2):** PoCの結果を踏まえ、安定性とスケーラビリティを考慮して **案B（Pythonマイクロサービス）** への移行を推奨する。Docker Composeにエンベディング用コンテナを追加する。

**性能に関する注記:**
CPUでの実行速度は、モデルサイズ、テキストの長さ、サーバーのスペックに大きく依存する。PoC段階で `bge-m3` と `e5-base` の両モデルについて、代表的なデータでベンチマークを取得し、どちらのモデルが本番環境に適しているかを判断することが不可欠である。

##### 3.1.3.3. 検索処理の統合

**既存のsearchLedgersForApiとの関係:**
```php
// 既存: LedgerService::searchLedgersForApi() - キーワード検索
// 新規: RagSearchService::searchChunks() - セマンティック検索

// MCPツール内で使い分け
if ($params['search_mode'] === 'semantic') {
    return $ragService->searchChunks($user, $params['q']);
} else {
    return $ledgerService->searchLedgersForApi($user, $params);
}
```

```php
// In a service class
$questionVector = $this->embeddingService->embed($question);

$results = DB::table('ledger_chunks')
    ->select('id', 'ledger_id', 'chunk_text')
    ->selectRaw('mroonga_vector_search(embedding, ?) AS score', [$questionVector])
    ->whereRaw('mroonga_vector_search(embedding, ?)', [$questionVector])
    ->orderBy('score', 'desc')
    ->limit(10)
    ->get();

// ここで$resultsのledger_idを元に権限チェックを行う
$accessibleResults = $this->permissionService->filterReadableChunks($results, auth()->user());
```

#### 3.1.4. 評価

##### メリット

**✅ 低コスト・低リスク導入:**
- 新規ミドルウェア不要（既存MySQL/Mroonga環境のみ）
- Docker Composeへの追加サービスなし
- 運用監視対象の増加なし

**✅ 既存システムとの高い親和性:**
```php
// 既存の権限管理ロジックをそのまま流用可能
$readableFolderIds = $this->folderRepository->getReadableFolderIds($user);

// ledger_chunksテーブルにfolder_idを非正規化することで高速化
$chunks = LedgerChunk::whereIn('folder_id', $readableFolderIds)->get();
```

**✅ トランザクション整合性:**
```php
DB::transaction(function () use ($ledger) {
    $ledger->save();
    $ledger->chunks()->delete();
    // 新チャンク作成
    // → 同一トランザクション内で原子性保証
});
```

**✅ 開発効率:**
- Eloquent ORM継続利用可能
- マイグレーション/Seederでテストデータ作成容易
- 既存のテストパターン（DatabaseMigrations）適用可能

##### デメリットと対策

**⚠️ 性能の懸念:**

| 項目 | 懸念 | 実測データ | 対策 |
|------|------|-----------|------|
| ベクトル検索速度 | 専用DBより遅い | 要ベンチマーク | chunk_size最適化、索引チューニング |
| 同時接続 | OLTP/OLAPの競合 | 現行は問題なし | 必要に応じて Read Replica 検討 |
| スケーラビリティ | 100万台帳超で懸念 | 未検証 | Phase2でアプローチ2移行を計画 |

**⚠️ Mroongaベクトル検索の制約:**
- **未検証項目:** 複合インデックスでの動作（FULLTEXTでは不可を確認済み）
- **対策:** PoC段階で以下を検証
  ```sql
  -- テストクエリ
  SELECT * FROM ledger_chunks
  WHERE folder_id IN (1,2,3) -- 権限フィルタ
  ORDER BY mroonga_vector_distance(embedding, ?) ASC;
  ```

**⚠️ 機能の制約:**
- HNSW等の高度なアルゴリズム不可
- メタデータフィルタリングは手動実装
- **判断:** 10万台帳規模では許容範囲。100万台帳超ならアプローチ2へ

### 3.2. アプローチ2: 専用ベクトルDB導入案

#### 3.2.1. アーキテクチャ概要

ベクトルデータの格納・検索に、Qdrant, Weaviate等の専用ベクトルDBを導入する。LedgerLeapとはAPI経由で連携する。

```
+-----------+      +-----------------+      +-----------------+
| Ledger    |      | Laravel         |      | (Job Queue)     |
| (Create/  |----->| Observer        |----->|                 |
| Update)   |      +-----------------+      +-----------------+
+-----------+                                      |
                                                     |
+-----------------------+      +---------------------+
| Vector DB             |<-----| ProcessLedgerForRag |
| (e.g., Qdrant)        |      | Job                 |
+-----------------------+      +---------------------+
```

#### 3.2.2. データベース設計

ベクトルDBには、ベクトルデータと共に豊富なメタデータを格納する。これにより、検索時に効率的なフィルタリングが可能になる。

**格納するペイロードの例 (JSON):**
```json
{
  "points": [
    {
      "id": "...", // UUID
      "vector": [0.1, 0.2, ...],
      "payload": {
        "chunk_text": "...",
        "ledger_id": 123,
        "folder_id": 10,
        "creator_id": 5,
        "accessible_roles": [1, 3, 5] // 権限チェック用のメタデータ
      }
    }
  ]
}
```

#### 3.2.3. 実装詳細

- **データ同期:** ジョブ内で、ベクトルDBのPHPクライアント（例: `qdrant/qdrant-php`）を使い、ベクトルとメタデータを`upsert`する。
- **検索処理:** ユーザーの権限情報（所属ロール、アクセス可能なフォルダIDリストなど）を取得し、ベクトルDBの検索APIに`filter`条件として渡す。

```php
// In a service class
$questionVector = $this->embeddingService->embed($question);
$user = auth()->user();
$accessibleFolderIds = $this->folderRepository->getAccessibleFolderIds($user);

$results = $this->qdrantClient->search('ledger_chunks_collection', [
    'vector' => $questionVector,
    'limit' => 10,
    'filter' => [
        'should' => [
            [
                'key' => 'folder_id',
                'match' => [
                    'any' => $accessibleFolderIds
                ]
            ],
            // 他の権限条件
        ]
    ]
]);
```

#### 3.2.4. 評価

- **メリット:**
    - **高性能・高拡張性:** 大規模データに対して高速な検索レスポンスを維持できる。
    - **高度な機能:** 検索と同時にメタデータでフィルタリングする「pre-filtering」が可能で効率的。
- **デメリット:**
    - **運用コスト増:** 新たなミドルウェアの学習、構築、監視コストが発生する。
    - **データ同期の複雑化:** LedgerLeapのDBとベクトルDBの間で、データ（特に権限情報）の整合性を保つ仕組みが必要。
    - **権限管理の二重化:** LedgerLeap本体の権限ロジックとは別に、ベクトルDBのメタデータにも権限情報を反映させる必要があり、管理が複雑になる。

### 3.3. アプローチ3: ハイブリッド検索案

#### 3.3.1. アーキテクチャ概要

Mroongaの全文検索で候補を絞り込み、その結果に対してのみベクトル検索を実行する。キーワード検索の正確性とセマンティック検索の柔軟性を両立させる。

```
User Query ---> [ 1. Keyword Search (Mroonga) ] ---> Ledger ID List
                  |
                  +--> [ 2. Vector Search (on filtered IDs) ] ---> Final Results
```

#### 3.3.2. 実装詳細

- **検索フロー:**
    1. ユーザーの質問から`llm-query-extractor`のような仕組みでキーワードを抽出し、Mroongaで全文検索を実行。数十〜数百件の`ledger_id`を取得する。
    2. 取得した`ledger_id`をフィルタ条件として、アプローチ1または2の方法でベクトル検索を実行する。
    3. 全文検索のスコアとベクトル検索のスコアを、Reciprocal Rank Fusion (RRF) などのアルゴリズムで統合し、最終的なランキングを決定する。

#### 3.3.3. 評価

- **メリット:**
    - **最高の検索品質:** キーワードに完全一致する結果を取りこぼさず、かつ意味的に関連する結果も発見できる。
    - **性能と精度の両立:** 全検索対象を絞り込むことで、ベクトル検索の負荷を軽減しつつ、ノイズの少ない高品質な結果を得られる。
- **デメリット:**
    - **最高の実装難易度:** 検索ロジックが非常に複雑になる。
    - **チューニングの複雑さ:** スコア統合アルゴリズムの導入や、各検索エンジンの重み付けなど、高度な調整が必要になる。

---

## 4. 総合評価と比較

### 4.1. 定量的比較表

| 評価軸 | アプローチ1 (Mroonga) | アプローチ2 (専用DB) | アプローチ3 (ハイブリッド) |
| :--- | :--- | :--- | :--- |
| **導入コスト** | ◎ 低（0日） | △ 中〜高（3-5日） | ▽ 高（7-10日） |
| **パフォーマンス** | ○ 中（要検証） | ◎ 高 | ◎ 高 |
| **拡張性** | ○ 中（~50万台帳） | ◎ 高（数百万台帳） | ◎ 高 |
| **実装難易度** | ○ 低（既存パターン） | △ 中（新技術導入） | ▽ 高（複雑ロジック） |
| **権限管理の容易さ** | ◎ 高（既存ロジック） | △ 中（同期必要） | △ 中 |
| **検索品質** | ○ 中（セマンティックのみ） | ○ 中〜高 | ◎ 高（キーワード+意味） |
| **運用監視** | ◎ 既存体制で対応可 | △ 新規監視項目追加 | △ 新規監視項目追加 |
| **データ同期** | ◎ 不要 | △ 複雑（権限情報） | △ 複雑 |

### 4.2. 既存システムとの整合性評価

#### アプローチ1（推奨）

**✅ 整合性が高い点:**
- Observerパターン（既に7つのObserverが存在）
- Redis非同期ジョブ（ProcessOcrJobと同パターン）
- Mroongaエンジン（ledgersテーブルで既に採用）
- 権限管理（WritableFolderRepositoryを再利用）
- テストパターン（DatabaseMigrations使用）

**🔧 追加が必要な点:**
- LedgerObserver実装（2時間）
- ProcessLedgerForRagJob実装（4時間）
- EmbeddingService実装（3時間）
- RagSearchService実装（4時間）
- ledger_chunksマイグレーション（1時間）

**合計工数:** 約14時間（2営業日）

#### アプローチ2

**⚠️ 整合性の課題:**
- Docker Composeへの新サービス追加（Qdrant等）
- 権限情報の二重管理（LedgerLeap DB + ベクトルDB）
- データ同期ロジックの複雑化
- 新規監視項目（ベクトルDBの死活監視）

**合計工数:** 約40時間（5営業日）

#### アプローチ3

**⚠️ 整合性の課題:**
- 検索スコア統合ロジック（RRF実装）
- キーワード検索とベクトル検索の使い分けロジック
- スコアリングシステムとの三重統合

**合計工数:** 約60時間（8営業日）

### 4.3. ユースケース別の推奨アプローチ

| ユースケース | 推奨 | 理由 |
|-------------|------|------|
| **PoC・検証段階** | アプローチ1 | 最速で価値検証可能 |
| **10万台帳以下** | アプローチ1 | コストパフォーマンス最高 |
| **50万台帳以上** | アプローチ2 | 性能要件を満たせる |
| **最高の検索品質** | アプローチ3 | キーワードと意味の両方を活用 |
| **オンプレミス必須** | アプローチ1 | 外部API不要で実現可能 |

## 5. 結論と推奨アプローチ

### 5.1. 段階的導入戦略

各アプローチの特性と既存システムとの整合性を考慮し、以下の段階的な導入が最も現実的かつ効果的であると結論付ける。

#### Phase 1: PoC検証（推奨期間: 2週間）

**目標:**
1.  RAGの基本機能（チャンキング・エンベディング・ベクトル検索）の技術的実現可能性を検証する。
2.  **自前ホストのエンベディングモデル（`bge-m3`）が、CPU環境で許容可能な性能（速度・メモリ）で動作するかを実測・評価する。**
3.  セマンティック検索のビジネス価値を検証する。

**採用アプローチ:** アプローチ1「MySQL/Mroonga 活用案」

**実施内容:**
```
Week 1: 基盤実装とモデル性能評価
├─ Day 1: ledger_chunksテーブル設計・マイグレーション
├─ Day 2-3: Observer + Job実装
├─ Day 4: EmbeddingService実装（Python連携）と、bge-m3/e5-baseの性能ベンチマーク測定
└─ Day 5: 性能評価とモデルの最終決定

Week 2: 検証・評価
├─ Day 1-2: RagSearchService実装
├─ Day 3: MCPツール統合
├─ Day 4: 100台帳での検索品質・パフォーマンステスト
└─ Day 5: ユーザビリティ検証・レポート作成
```

**成功基準:**
- [ ] **エンベディング処理時間が、1チャンクあたり平均2秒以内であること。**
- [ ] ベクトル検索レスポンスが < 500ms (10チャンク取得) であること。
- [ ] 既存キーワード検索で発見できない情報を20%以上発見できること。
- [ ] 権限フィルタリングが正しく動作すること。
- [ ] LLMの回答品質が既存検索比で向上すること。

**Go/No-Go判断:**
- ✅ Go → Phase 2へ移行
- ❌ No-Go → 代替モデル（e5-base）での再検証、またはRAG導入を延期

#### Phase 2: 本格導入（推奨期間: 1ヶ月）

**条件:** Phase 1のPoC成功

**実施内容:**
```
Week 1-2: スケーラビリティと安定性向上
├─ **エンベディング実行環境の安定化（必要に応じてPythonマイクロサービス化）**
├─ バッチ処理の最適化（既存台帳の一括チャンク化）
└─ モニタリング・アラート設定

Week 3: 機能拡張
├─ SearchLedgersToolへのRAGモード追加
├─ チャンクソースの明示（content vs content_attached）
└─ ユーザーフィードバック収集機能

Week 4: 運用準備
├─ 運用ドキュメント作成
├─ トラブルシューティング手順整備
└─ 本番環境展開・段階的ロールアウト
```

**KPI:**
- 全台帳のチャンク化完了率 > 95%
- RAG検索の平均レスポンス時間 < 1秒
- ユーザー満足度調査での肯定的評価 > 70%

#### Phase 3: 移行判断（データ量依存）

**アプローチ2への移行判断基準:**
```
以下のいずれかに該当する場合、アプローチ2を検討:
- 台帳数が50万件を超える
- RAG検索のレスポンスが2秒を超える
- 同時接続数の増加でOLTP/OLAP競合が発生
- より高度なベクトル検索機能が必要になる
```

**アプローチ3への発展判断基準:**
```
以下の両方に該当する場合、アプローチ3を検討:
- アプローチ2で十分な性能が出ている
- キーワード検索との併用でさらなる精度向上が見込める
```

### 5.2. 最終推奨

**初期導入としては、アプローチ1が以下の理由から最適解である:**

1. **技術的負債の最小化**
   - 既存システムとの高い親和性
   - 新規ミドルウェア導入なし
   - 既存の開発・運用パターンを踏襲

2. **迅速な価値提供**
   - 2週間でPoC完了
   - 1ヶ月で本番展開可能
   - 早期にビジネス価値を検証

3. **柔軟な発展経路**
   - アプローチ2/3への移行が容易
   - データ構造は共通
   - 段階的な投資判断が可能

4. **既存MCP統合との相乗効果**
   - SearchLedgersToolへの自然な統合
   - 既存の権限管理・スコアリングシステムを活用
   - ユーザー体験の一貫性維持

**次のステップ:**
1. 本ドキュメントのレビュー・承認取得
2. Phase 1 PoC計画の詳細化
3. 開発環境での実装開始
