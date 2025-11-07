# VLM/RAG統合 Phase4 - ID1: Embedding生成処理の統合 詳細計画

**プロジェクト:** VLM/RAG統合 - Phase4: Embedding生成とUI実装
**タスクID:** 1.0
**タスク名:** Embedding生成処理の統合

**目的:** Phase3で実装されるチャンク更新処理と、既存のEmbedding生成処理を確実に連携させる。具体的には、VLM処理完了後に `UpdateLedgerChunks` ジョブがトリガーされ、その後続として `ProcessLedgerForRagJob` が実行され、最終的に `ledger_chunks` テーブルの `embedding` カラムがベクトルデータで更新されるまでの一連のフローを完成させる。

**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase4 Embedding生成とUI実装 WBS](./2025-11-07_phase4-wbs.md)

---

## 1. 関連コンポーネントの役割分担

本タスクを計画するにあたり、関連するジョブの役割を以下のように定義する。

- **`ProcessVlmExtraction` (Phase2成果物):**
    - 添付ファイルのVLM解析を実行し、`attached_files.vlm_markdown` を保存する。
    - 処理成功後、`UpdateLedgerChunks` ジョブをディスパッチする責務を持つ。

- **`UpdateLedgerChunks` (Phase3成果物):**
    - `Ledger` モデルを引数に取る。
    - `handle` メソッド内で、後続の `ProcessLedgerForRagJob` をディスパッチする責務を持つ。
    - *備考: 当初計画ではこのジョブがチャンク作成まで担う想定だったが、既存の `ProcessLedgerForRagJob` に責務が集約されているため、本ジョブは後続ジョブへの「トリガー」に特化させる方針とする。*

- **`ProcessLedgerForRagJob` (既存コンポーネント):**
    - `Ledger` モデルを引数に取る。
    - 以下の処理をトランザクショナルに実行する、RAGパイプラインの中核ジョブ。
        1. 既存チャンクの削除
        2. VLM結果を考慮したMarkdownの構築 (`buildMarkdownFromLedger`)
        3. Markdownのチャンク化 (`chunkText`)
        4. チャンクのEmbedding生成とDB保存 (`EmbeddingService->embed()`)

## 2. 実装ステップ

### ステップ1: `UpdateLedgerChunks` ジョブの実装 (WBS 1.1)

- **ファイル:** `app/Jobs/Rag/UpdateLedgerChunks.php` (Phase3で作成)
- **要点:**
    1. `Ledger` モデルをコンストラクタで受け取る。
    2. `handle` メソッドを実装し、その中で `ProcessLedgerForRagJob::dispatch($this->ledger)` を実行する。
    3. キューは `rag-processing` を指定する。
    4. ジョブ失敗時のリトライ処理とログ出力を設定する。

### ステップ2: `ProcessVlmExtraction` からの連携 (Phase3成果物)

- **ファイル:** `app/Jobs/Ledger/ProcessVlmExtraction.php` (Phase2で作成)
- **要点:**
    1. VLM処理が成功し、`attached_files` テーブルへの保存が完了した直後に、`UpdateLedgerChunks::dispatch($this->attachedFile->ledger)` を実行する。
    2. `config('rag.auto_update_chunks', true)` の設定値を参照し、自動更新が有効な場合のみディスパッチする。

### ステップ3: `ProcessLedgerForRagJob` の動作検証 (WBS 1.2)

- **ファイル:** `app/Jobs/ProcessLedgerForRagJob.php`
- **要点:**
    1. **VLM優先ロジックの確認:** `updateContentAttachedWithVlmResult` メソッドが、`attached_files.vlm_markdown` の内容で `content_attached` を上書きすることを確認する。これにより、後続の `buildMarkdownFromLedger` がVLM結果を間接的に利用できる。
    2. **Embedding生成処理の確認:** `handle` メソッドの終盤で `EmbeddingService` が呼び出され、返却されたベクトルデータが `ledger_chunks.embedding` にJSON形式で保存されることをコードレビューで再確認する。
    3. **一括挿入の確認:** チャンクデータが `DB::table('ledger_chunks')->insert($chunkData)` によって一括で挿入されており、パフォーマンスが考慮されていることを確認する。

### ステップ4: 統合テストの実装 (WBS 1.3)

- **ファイル:** `tests/Feature/Rag/RagIntegrationTest.php` (新規作成)
- **要点:**
    1. **テスト準備:**
        - `RefreshDatabase` トレイトを使用する。
        - `Bus::fake()` を使用してジョブチェーンをテストできるようにする。
        - テスト用の `Ledger` と、それに関連する `AttachedFile` (VLM結果あり/なしの両方) をファクトリで作成する。
    2. **テストシナリオ:**
        - `UpdateLedgerChunks` ジョブをディスパッチする。
        - `Bus::assertDispatched(UpdateLedgerChunks::class)` を確認。
        - 実際に `UpdateLedgerChunks` ジョブを実行する。
        - `Bus::assertDispatched(ProcessLedgerForRagJob::class)` を確認。
        - 実際に `ProcessLedgerForRagJob` を実行する。
    3. **結果検証:**
        - `assertDatabaseHas('ledger_chunks', ...)` を使用して、チャンクが作成されたことを確認する。
        - 作成された `ledger_chunks` のレコードを取得し、`embedding` カラムが `null` でないこと、かつ有効なJSON形式であることをアサートする。
        - VLM結果を含むチャンクテキストが正しく生成されていることを部分的に検証する。

## 3. 懸念事項

- **ジョブの密結合:**
    - `ProcessLedgerForRagJob` は、チャンク作成とEmbedding生成という2つの大きな責務を単一のジョブ内で実行しており、密結合な状態です。現状の要件では問題ありませんが、将来的に「Embedding生成だけを再実行したい」といった要件が出た場合、ジョブを分割するリファクタリングが必要になります。

- **タイムアウトのリスク:**
    - 添付ファイル数が非常に多い、または各ファイルの内容が長大な台帳の場合、`ProcessLedgerForRagJob` の実行時間が長くなる可能性があります。特に `buildMarkdownFromLedger` でのテキスト構築と `EmbeddingService->embed()` でのAPI通信がボトルネックになる可能性があります。現状のタイムアウト設定 (`public $timeout = 300;`) で問題ないか、高負荷なデータでのテストが推奨されます。

- **エラー発生時のトレーサビリティ:**
    - `ProcessLedgerForRagJob` 内でエラーが発生した場合、それがチャンク作成段階なのか、Embedding生成段階なのかをログから特定する必要があります。現状のログは主要ステップで出力されていますが、運用開始後は、より詳細なエラーコンテキスト（例: どのチャンクのEmbeddingで失敗したか）を記録する改修が必要になる可能性があります。
