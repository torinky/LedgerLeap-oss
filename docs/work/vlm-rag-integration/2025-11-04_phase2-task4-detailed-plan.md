# VLM/RAG統合 Phase2 テスト実装計画書

**ドキュメントバージョン:** 1.0
**作成日:** 2025年11月4日
**作成者:** Gemini

---

## 1. 概要

### 1.1. 目的
本ドキュメントは、WBS「VLM/RAG統合 - Phase2」のタスクID 4.0「テスト実装」に関する詳細な実装計画を定義するものです。

この計画は、以下の3つのテストスイートの作成を対象とします。
- **タスク4.1:** `VlmClientService` ユニットテスト
- **タスク4.2:** `ProcessVlmExtraction` ジョブテスト
- **タスク4.3:** VLM統合テスト (`ProcessAttachedFile` ジョブの連携テスト)

### 1.2. 関連ドキュメント
- [VLM/RAG統合 - Phase2 VLM処理実装 WBS](./2025-11-03_phase2-wbs.md)
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [`ProcessAttachedFile` ジョブ改修 詳細計画書](./2025-11-04_phase2-task3.1-detailed-plan.md)

---

## 2. 予備調査と懸念事項への対策

計画策定に先立ち、以下の懸念事項について調査を実施し、その結果を本計画に反映しました。

### 2.1. テストごとの設定値の管理
- **懸念:** VLMの有効/無効を切り替える `config('vlm.enabled')` のような設定値が、テスト間で意図せず影響し合うリスク。
- **調査結果:** Laravel + Pest環境では、`Illuminate\Support\Facades\Config::set()` を使用することで、テストケースごとに設定値を安全に上書きできます。この変更はテストケースの実行後に自動的にリセットされるため、他のテストに影響を与えません。
- **対策:** VLMの有効/無効をテストするケースでは、テストメソッドの冒頭で `Config::set('vlm.enabled', true)` または `Config::set('vlm.enabled', false)` を呼び出し、そのテスト内での挙動を限定的に検証します。

### 2.2. DBトランザクションを含むジョブのテスト
- **懸念:** `ProcessAttachedFile` のように内部で `DB::transaction` を使用するジョブのテストが複雑になる可能性。
- **調査結果:** `RefreshDatabase` トレイトを使用していれば、テスト全体がDBトランザクションでラップされ、テスト終了時にロールバックされます。ジョブの `handle` メソッドをテスト内で直接呼び出しても、その中のDB操作はすべてこのトランザクション内で実行されるため、安全にテストできます。
- **対策:** すべてのフィーチャーテストで `RefreshDatabase` トレイトを使用します。ジョブの `handle()` を直接呼び出し、メソッド実行後のデータベースの状態を `assertDatabaseHas` などで検証するアプローチを採ります。

### 2.3. 物理ファイルを扱う処理のテスト
- **懸念:** `VlmClientService` は `file_get_contents` で物理ファイルパスを読み込むため、`Storage::fake()` が使えない。
- **調査結果:** ユーザーからの情報提供により、`tests/fixtures/files/` にサンプルファイル（`invoice_simple.pdf`, `receipt_01.jpg` 等）が利用可能であることが判明しました。
- **対策:** `VlmClientService` のテストでは、`beforeEach` (または `setUp`) フックで `Storage::fake('public')` を使用しつつ、`Illuminate\Http\Testing\File::createWithContent` を使ってメモリ上にファイルを作成し、`Http::fake()` でそのファイルをアップロードするシミュレーションを行います。`ProcessVlmExtraction` と `VlmIntegrationTest` のフィーチャーテストでは、`Storage::disk('public')->put()` を使用してフィクスチャファイルをテスト用ストレージに実際に配置し、その物理パス `Storage::disk('public')->path()` を取得してテストを行います。

---

## 3. テスト実装計画

### 3.1. タスク4.1: `VlmClientService` ユニットテスト
- **ファイル:** `tests/Unit/Services/VlmClientServiceTest.php`
- **方針:** `Http::fake()` を全面的に活用し、外部APIへの依存を排除します。

- **テストケース:**
    1.  **`test_extract_successfully_calls_vlm_service`:**
        - `Http::fake` で正常なJSONレスポンスを定義します。
        - `VlmClientService->extract()` を実行し、`Http::assertSent` で期待通りのリクエストが送信されたこと、戻り値が正しいことを検証します。
        - `Log::spy()` で成功ログの出力を確認します。

    2.  **`test_extract_throws_exception_on_vlm_service_error`:**
        - `Http::fake` で500エラーレスポンスを定義します。
        - `extract()` 実行時に `RuntimeException` がスローされることを `expect()->toThrow()` で検証します。
        - `Log::spy()` でエラーログの出力を確認します。

    3.  **`test_extract_throws_exception_on_connection_timeout`:**
        - `Http::fake` で `ConnectionException` がスローされるように設定します。
        - `extract()` 実行時に `ConnectionException` がスローされることを検証します。

    4.  **`test_health_check_handles_various_statuses`:**
        - `healthy`, `unhealthy`, `unreachable` の各シナリオで `Http::fake` を設定し、`healthCheck()` が正しいステータス配列を返すことを検証します。

### 3.2. タスク4.2: `ProcessVlmExtraction` ジョブテスト
- **ファイル:** `tests/Feature/Jobs/ProcessVlmExtractionTest.php`
- **方針:** `VlmClientService` をモックし、ジョブが責務（DB更新、ステータス変更、後続ジョブのディスパッチ）を正しく果たすか検証します。

- **テストケース:**
    1.  **`test_job_updates_database_on_successful_extraction`:**
        - `Tenant` と `AttachedFile` をファクトリで作成します。
        - `VlmClientService` をモックし、`extract` メソッドが成功データを返すように設定します。
        - `Bus::fake()` を使用します。
        - ジョブの `handle()` を実行後、`assertDatabaseHas` で `attached_files` テーブルが `COMPLETED` ステータス等に更新されたことを確認します。
        - `Bus::assertDispatched(UpdateLedgerChunks::class)` を検証します。

    2.  **`test_job_fails_when_vlm_returns_empty_markdown`:**
        - `VlmClientService` のモックが空の `markdown` を返すように設定します。
        - ジョブの `tries` プロパティを1に設定して `handle()` を実行し、`RuntimeException` がスローされることを確認後、`failed()` メソッドを呼び出します。
        - `assertDatabaseHas` で `status` が `VLM_FAILED` に更新されたことを確認します。

    3.  **`test_job_fails_on_extraction_exception`:**
        - `VlmClientService` のモックが `Exception` をスローするように設定します。
        - 上記と同様に、最終的に `status` が `VLM_FAILED` になることを確認します。

### 3.3. タスク4.3: VLM統合テスト
- **ファイル:** `tests/Feature/VlmIntegrationTest.php`
- **方針:** `ProcessAttachedFile` ジョブが、条件に応じて `ProcessVlmExtraction` または既存のジョブを正しくディスパッチするかを検証します。

- **テストケース:**
    1.  **`test_vlm_job_is_dispatched_for_eligible_file_when_vlm_is_enabled`:**
        - `Config::set('vlm.enabled', true)` を設定します。
        - `Bus::fake()` を設定し、対象ファイル（例: `image/png`）を持つ `AttachedFile` を作成します。
        - `ProcessAttachedFile` ジョブを実行し、`Bus::assertDispatched(ProcessVlmExtraction::class)` と `Bus::assertNotDispatched(OcrAndOptimizeFile::class)` を検証します。
        - `status` が `PENDING_VLM` になっていることを確認します。

    2.  **`test_ocr_job_is_dispatched_when_vlm_is_disabled`:**
        - `Config::set('vlm.enabled', false)` を設定します。
        - `ProcessAttachedFile` ジョブを実行し、`Bus::assertDispatched(OcrAndOptimizeFile::class)` と `Bus::assertNotDispatched(ProcessVlmExtraction::class)` を検証します。

    3.  **`test_vlm_job_is_not_dispatched_for_ineligible_file`:**
        - `Config::set('vlm.enabled', true)` を設定します。
        - 対象外ファイル（例: `application/zip`）で `ProcessAttachedFile` を実行し、`Bus::assertNotDispatched(ProcessVlmExtraction::class)` を検証します。

    4.  **`test_vlm_job_is_dispatched_on_tika_failure`:**
        - `Config::set('vlm.enabled', true)` を設定します。
        - `Vaites\ApacheTika\Client` をモックし、`getText` が `Exception` をスローするようにします。
        - `ProcessAttachedFile` を実行し、`Bus::assertDispatched(ProcessVlmExtraction::class)` を検証します。

---

## 4. まとめ

本計画に基づきテストを実装することで、VLM統合機能の品質を多角的に保証します。ユニットテストで個々のコンポーネントの動作を、フィーチャーテストでコンポーネント間の連携と副作用（DB更新、ジョブディスパッチ）をそれぞれ検証し、堅牢なシステム構築を目指します。
