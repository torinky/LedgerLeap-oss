# VLM/RAG統合 Phase2 - WBS2.0 VLM処理ジョブ実装 詳細計画

**ドキュメントID:** 2025-11-03_phase2-wbs2-design.md
**担当者:** (担当者名)
**作成日:** 2025年11月3日
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase2 VLM処理実装 WBS](./2025-11-03_phase2-wbs.md)
- [Phase2 WBS1.0 VLMサービスクラス実装 詳細計画](./2025-11-03_phase2-wbs1-design.md)

---

## 1. 目的

`VlmClientService` を利用して、添付ファイルのVLM処理を非同期で実行する `ProcessVlmExtraction` ジョブを実装するための詳細な計画を定義する。

## 2. 調査概要

`app/Jobs/Ledger/ProcessAttachedFile.php` を調査し、非同期ジョブの標準的な実装パターンを確認した。具体的には、`ShouldQueue`インターフェースの実装、コンストラクタでのモデルインジェクション、`handle`メソッド内でのテナント初期化、ステータス更新、ロギング、エラーハンドリング、後続ジョブのディスパッチ方法などである。本計画はこれらの既存プラクティスを踏襲する。

---

## 3. 実装ステップ

### ステップ1: `ProcessVlmExtraction` ジョブの基本構造を作成

-   **要点:**
    -   `php artisan make:job Ledger/ProcessVlmExtraction` コマンドで `app/Jobs/Ledger/ProcessVlmExtraction.php` を作成する。
    -   `implements ShouldQueue` をクラス定義に追加し、`Queueable`, `Dispatchable` などの標準的なトレイトを使用する。
    -   コンストラクタで `AttachedFile` モデルを受け取り、publicプロパティとして保持する。
    -   `config/vlm.php` の設定値を参照し、`$tries` (リトライ回数), `$backoff` (リトライ間隔), `$timeout` (タイムアウト時間) プロパティを設定する。
    -   コンストラクタで `$this->onQueue('vlm-processing')` を呼び出し、専用キューで実行されるように設定する。

### ステップ2: `handle` メソッドの実装

-   **要点:**
    -   `handle` メソッドの引数で `VlmClientService $vlmClient` をタイプヒントし、サービスをインジェクションする。
    -   メソッドの冒頭で `tenancy()->initialize($this->attachedFile->tenant_id)` を呼び出し、テナントコンテキストを初期化する。
    -   `Log::info` でジョブの開始とファイルIDを記録する。
    -   `try-catch` ブロックでメインの処理を囲み、堅牢なエラーハンドリングを実装する。
    -   **処理開始前:** `AttachedFile` のステータスを `AttachedFileStatus::VLM_PROCESSING` に更新する。
    -   **メイン処理:** `$vlmClient->extract($this->attachedFile)` を呼び出し、VLMコンテナから処理結果を取得する。
    -   **成功時:**
        -   取得した `markdown` や `structured_data` などの結果を `AttachedFile` モデルに `update` する。
        -   `vlm_model`, `vlm_confidence` などのメタデータも併せて更新する。
        -   ステータスを `AttachedFileStatus::COMPLETED` に更新する。
        -   `Log::info` で処理の成功、処理時間、信頼度などを記録する。
        -   `config('rag.auto_update_chunks', true)` が有効な場合、`App\Jobs\Rag\UpdateLedgerChunks::dispatch($this->attachedFile->ledger)` を呼び出し、後続のチャンキングジョブをディスパッチする。
    -   **失敗時 (`catch` ブロック):**
        -   `Log::error` で例外メッセージ、スタックトレース、ファイルIDを記録する。
        -   ジョブが最終試行かどうかを `$this->attempts()` で判定し、最終試行であればステータスを `AttachedFileStatus::VLM_FAILED` に更新する。
        -   例外を再スローし、Laravelのキューシステムにリトライ処理を委ねる。

### ステップ3: `failed` メソッドの実装

-   **要点:**
    -   ジョブがすべてのリトライに失敗した後に呼び出される `public function failed(\Throwable $exception): void` メソッドを実装する。
    -   このメソッド内で、`Log::error` でジョブが恒久的に失敗したことを記録し、`AttachedFile` のステータスを確実に `AttachedFileStatus::VLM_FAILED` に更新する。

---

## 4. 懸念事項

-   **メモリ使用量:** `VlmClientService` が内部で `file_get_contents()` を使用するため、ジョブワーカーのメモリ使用量に注意が必要。`config/vlm.php` の `max_file_size` 設定と、キューワーカーのメモリ上限 (`memory_limit`) の整合性を確認する必要がある。巨大なファイルを処理しようとすると、ジョブがメモリ不足で失敗する可能性がある。
-   **トランザクション:** `ProcessVlmExtraction` は `AttachedFile` モデルの更新が主であり、複数のモデルをまたぐ複雑な更新ではないため、現時点では明示的なDBトランザクションは不要と判断する。ただし、将来的に関連モデルの更新が追加される場合は、トランザクションの導入を検討する必要がある。
