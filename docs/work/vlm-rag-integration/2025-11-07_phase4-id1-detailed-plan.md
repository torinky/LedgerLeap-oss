# VLM/RAG統合 Phase4 - ID1: Embedding生成処理の統合 詳細計画

**プロジェクト:** VLM/RAG統合 - Phase4: Embedding生成とUI実装
**タスクID:** 1.0
**タスク名:** Embedding生成処理の統合

**目的:** Phase3で実装されるチャンク更新処理と、既存のEmbedding生成処理を確実に連携させる。具体的には、VLM処理完了後に `UpdateLedgerChunks` ジョブがトリガーされ、その後続として `ProcessLedgerForRagJob` が実行され、最終的に `ledger_chunks` テーブルの `embedding` カラムがベクトルデータで更新されるまでの一連のフローを完成させる。

**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase4 Embedding生成とUI実装 WBS](./2025-11-07_phase4-wbs.md)

---

## 1. 関連コンポーネントの役割分担（現状反映版）

調査の結果、Phase3で計画された `UpdateLedgerChunks` ジョブは作成されておらず、代わりに `rag:chunk-existing-ledgers` コマンドが `ProcessLedgerForRagJob` を直接呼び出す形で手動更新機能が実装されていることが判明した。これを踏まえ、コンポーネントの役割を以下のように再定義する。

- **`ProcessVlmExtraction` (Phase2成果物):**
    - 添付ファイルのVLM解析を実行し、`attached_files.vlm_markdown` を保存する。
    - **[修正対象]** 処理成功後、後続の `ProcessLedgerForRagJob` を直接ディスパッチする責務を持つ。（現状は存在しない `UpdateLedgerChunks` を呼び出しており、不整合が生じている）

- **`ProcessLedgerForRagJob` (既存コンポーネント):**
    - `Ledger` モデルを引数に取る。
    - 以下の処理をトランザクショナルに実行する、RAGパイプラインの中核ジョブ。
        1. 既存チャンクの削除
        2. VLM結果を考慮したMarkdownの構築 (`buildMarkdownFromLedger`)
        3. Markdownのチャンク化 (`chunkText`)
        4. チャンクのEmbedding生成とDB保存 (`EmbeddingService->embed()`)

- **`RagChunkExistingLedgersCommand` (Phase3代替実装):**
    - 手動で `ProcessLedgerForRagJob` をディスパッチするためのArtisanコマンド。


## 2. 実装ステップ（現状反映版）

### ステップ1: 自動更新フローの不整合修正 (WBS 1.1)

- **ファイル:** `app/Jobs/Ledger/ProcessVlmExtraction.php`
- **目的:** VLM処理成功後に、後続のチャンク作成・Embedding生成ジョブが正しくトリガーされるように、ジョブの連携を修正する。
- **要点:**
    1. `handle` メソッド内の処理成功ブロックにある、存在しない `\App\Jobs\Rag\UpdateLedgerChunks::dispatch(...)` の呼び出しを削除する。
    2. 代わりに `\App\Jobs\ProcessLedgerForRagJob::dispatch($this->attachedFile->ledger)` を呼び出すように修正する。
    3. このディスパッチが `config('rag.auto_update_chunks', true)` の設定値に依存する点は維持する。

### ステップ2: 統合テストの実装 (WBS 2.1)

- **ファイル:** `tests/Feature/Rag/VlmRagIntegrationTest.php` (新規作成)
- **目的:** VLM処理からEmbedding生成までの一連の自動更新フロー全体が、意図通りに動作することを検証する。
- **要点:**
    1. **テスト準備:**
        - `RefreshDatabase` トレイトと `Bus::fake()` を使用する。
        - テスト用の `Ledger` と `AttachedFile` を作成する。
    2. **テストシナリオ:**
        - `ProcessVlmExtraction` ジョブをディスパッチし、実行する。（VLMコンテナへのAPI呼び出しはモックする）
        - `Bus::assertDispatched(ProcessLedgerForRagJob::class)` を使用して、後続ジョブが正しくディスパッチされたことを確認する。
    3. **結果検証:**
        - `ProcessLedgerForRagJob` を実際に実行させた後、`assertDatabaseHas('ledger_chunks', ...)` を使用して、チャンクが作成され、かつ `embedding` カラムが `null` でないことを検証する。


## 3. 懸念事項（詳細調査後）

予備調査の結果、当初の懸念事項について以下の通り明確化する。

### 3.1. ジョブの密結合と将来的な拡張性

- **現状分析:**
    - `ProcessLedgerForRagJob` の `handle` メソッドは、①Markdown生成、②チャンク化、③Embedding生成、④DB保存、という4つの責務を単一のメソッド内で手続き的に実行しており、密結合な状態にある。
- **根拠:**
    - コード上、`buildMarkdownFromLedger` と `chunkText` でチャンクの元データを準備し、その結果を `EmbeddingService->embed()` に渡し、最終的に `DB::table()->insert()` で保存するまでが一連の流れとして実装されている。
- **影響:**
    - **メリット:** 現在の「台帳ごとにチャンクとEmbeddingをまとめて生成する」という要件に対しては、処理が単一ジョブで完結するためシンプルで効率的である。
    - **デメリット（懸念）:** 将来的に「Embeddingモデルを更新したため、全台帳のEmbeddingだけを再計算したい」といった要件が発生した場合、Markdown生成やチャンク化の処理が無駄に実行されてしまう。このジョブをそのまま流用することができず、改修が必要となる。
- **将来的な改善策:**
    - 責務の分離を考慮し、以下の2つのジョブに分割するリファクタリングが考えられる。
        1. **`CreateOrUpdateChunksJob`:** Markdown生成とチャンク化を行い、`ledger_chunks` テーブルに `embedding` が `NULL` の状態で保存する。
        2. **`GenerateEmbeddingsJob`:** `ledger_chunks` テーブルから `embedding` が `NULL` のレコードを対象に、Embeddingを生成して更新する。

### 3.2. 外部サービス連携によるタイムアウトのリスク

- **現状分析:**
    - Embedding生成は、外部の `embedding` コンテナへのHTTP API呼び出しによって実行されており、これが処理全体のボトルネックとなりうる。
- **根拠:**
    - `EmbeddingService.php` は、`Http::post` を使用して `config('rag.embedding_service.url')` へチャンクテキストの配列を一括で送信している。
    - ジョブ (`ProcessLedgerForRagJob`) のタイムアウトは **300秒** に設定されているが、HTTPリクエスト自体のタイムアウトは `config('rag.embedding_service.timeout', 60)` により **60秒** となっている。
    - `config/rag.php` の設定では、1チャンクあたり最大2000文字、添付ファイル1つあたり最大50000文字まで許容される。多数の添付ファイルを持つ巨大な台帳の場合、生成されるチャンク数が数百に達する可能性があり、それらを一括で処理するEmbeddingコンテナの応答が60秒を超えるリスクがある。
- **影響（懸念）:**
    - 大量のチャンクが生成された場合にHTTPタイムアウト (60秒) が発生し、ジョブが失敗する可能性がある。`ProcessLedgerForRagJob` 全体のタイムアウト(300秒)に達する前に、API呼び出しが失敗するシナリオが想定される。
- **将来的な改善策:**
    - `ProcessLedgerForRagJob` 内で、全チャンクを一度に `EmbeddingService` に渡すのではなく、例えば100チャンクずつのバッチに分割して複数回呼び出すように改修する。
    - `config('rag.embedding_service.timeout')` の値を、実測値に基づいてより現実的な値に調整する。

### 3.3. エラー発生時のトレーサビリティ

- **現状分析:**
    - `ProcessLedgerForRagJob` 内のログは、エラーが「Markdown生成」フェーズか、それ以降の「Chunking/Embedding」フェーズのどちらで発生したかを大まかに区別できる。しかし、後者のフェーズ内での詳細な原因特定が困難な場合がある。
- **根拠:**
    - `handle` メソッド内の2段階の `try-catch` ブロックにより、`Markdown generation failed` と `Chunking process failed for ledger` のログは区別されて出力される。
    - しかし、`Chunking process failed` のログは、DBへの書き込み失敗、`EmbeddingService` でのAPI接続失敗、APIからのエラー応答など、複数の失敗パターンを同じログメッセージで集約してしまっている。
- **影響（懸念）:**
    - 運用時に `Chunking process failed` エラーが発生した場合、ログメッセージだけでは原因の切り分けができず、`EmbeddingService` のコンテナログなど、複数のログを突き合わせて調査する必要があり、問題解決に時間がかかる可能性がある。
- **将来的な改善策:**
    - `ProcessLedgerForRagJob` の `catch` ブロック内で、捕捉した例外オブジェクト (`$e`) の具体的な型 (`Illuminate\Database\QueryException`, `Illuminate\Http\Client\ConnectionException` 等) を判定し、ログメッセージをより具体的にする。
    - 例: `Log::error('Embedding APIへの接続に失敗しました', ...)`、`Log::error('チャンクのDB保存に失敗しました', ...)`
