# 外部サービス依存テストの分離

**最終更新:** 2026-02-28
**背景:** Issue #74 の CI 修正作業（2026-02-28）で確立した設計方針

---

## なぜこのドキュメントが必要か

LedgerLeap では `Ledger::factory()->create()` を呼ぶだけで、内部的に以下の連鎖が起きる：

```
Ledger::factory()->create()
  → LedgerObserver::created()
    → ProcessLedgerForRagJob::dispatch()
      → QUEUE_CONNECTION=sync の場合: ジョブが同期実行
        → EmbeddingService::embed()
          → http://embedding:8000 への接続試行
            → CI 環境にコンテナなし → 60秒タイムアウト ❌
```

**テストの目的が「台帳の作成」や「UI の表示」であれば、Embedding の実行はそのテストの責務外**。
`Queue::fake()` で切り離すことで、CI を安定させながらロジックのテストに集中できる。

---

## 基底クラス TestCase の設計

`tests/TestCase.php` に `$fakeQueue = true` がデフォルトで設定されており、
**全テストで自動的に `Queue::fake()` が呼ばれる**。

```php
// tests/TestCase.php
protected bool $fakeQueue = true;

protected function setUp(): void
{
    parent::setUp();

    if ($this->fakeQueue) {
        Queue::fake();  // 全テストで自動的に Embedding コンテナ接続を防ぐ
    }
    // ...
}
```

### オプトアウト: `$fakeQueue = false`

Queue そのものや dispatch の発火を検証するテストは、明示的に無効化する：

```php
class LedgerObserverTest extends TestCase
{
    protected bool $fakeQueue = false;  // dispatch の発火を検証するため無効化

    public function it_dispatches_job_on_ledger_creation(): void
    {
        Queue::fake();  // テストメソッド内で個別に fake する

        $ledger = Ledger::factory()->create();

        Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
            return $job->ledgerId === $ledger->id;
        });
    }
}
```

---

## キュー関連機能のテスト担保マップ

「Queue::fake() で Embedding を省略した」ように見えるが、以下の4層でそれぞれ担保されている。

### 層1: dispatch の発火タイミング（Observer の責務）

**担保テスト:** `tests/Feature/Observers/LedgerObserverTest.php`（`$fakeQueue = false`）

| テストメソッド | 検証内容 |
|---|---|
| `it_dispatches_job_on_ledger_creation` | 台帳作成時に `ProcessLedgerForRagJob` が dispatch されること |
| `it_dispatches_job_on_content_update` | `content` 更新時に dispatch されること |
| `it_dispatches_job_on_content_attached_update` | `content_attached` 更新時に dispatch されること |
| `it_does_not_dispatch_job_on_unrelated_field_update` | `status` 等の無関係なフィールド更新では dispatch されないこと |
| `it_deletes_chunks_on_ledger_deletion` | 台帳削除時に `ledger_chunks` が削除されること |

### 層2: RAG ジョブ本体の処理ロジック（ジョブの責務）

**担保テスト:** `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`（`$fakeQueue = false`）

`new ProcessLedgerForRagJob($id)->handle(...)` で直接呼び出してロジックをテスト：

| テストメソッド | 検証内容 |
|---|---|
| `it_processes_ledger_body_only` | 本文のみのチャンク生成・Embedding 呼び出し |
| `it_processes_attached_file_only` | 添付ファイルのみのチャンク生成 |
| `it_performs_granular_updates` | 差分更新（変更チャンクのみ再 Embedding） |
| `it_uses_previewable_text_if_vlm_markdown_is_empty` | VLM マークダウンが空のときのフォールバック |

### 層3: 添付ファイル処理のジョブ連鎖（Bus の責務）

**担保テスト:** `ProcessAttachedFileTest`・`VectorizeAttachedFileTest`・`FinalizeAttachedFileProcessingTest`
（`$fakeQueue = false`、`Bus::fake()` を使用）

| テスト | 検証内容 |
|---|---|
| `it_dispatches_generate_thumbnail_job_for_image_file` | 画像ファイル処理時にサムネイルジョブが dispatch されること |
| `it_dispatches_parallel_processing_for_vlm_ocr_target_files` | VLM/OCR 対象ファイルの並列ジョブ dispatch |
| `it_upgrades_from_tika_to_ocr` / `_to_vlm` | Tika → OCR → VLM の段階的アップグレード判定 |
| `command_selects_vlm_over_ocr` 等 10件 | finalize コマンドの選択ロジック網羅 |

### 層4: Embedding の実際の呼び出し（外部コンテナ必須）

**担保テスト:** `RagSearchServiceTest`・`RagPerformanceTest`（`#[Group('external')]`）

これは CI から除外しているが意図的な設計。Embedding コンテナが存在するローカル環境でのみ実行する。

```bash
# ローカルで外部依存テストを実行する場合
./vendor/bin/sail pest --group=external
```

---

## $fakeQueue = false を設定すべきテスト一覧

以下のテストは `Bus::fake()` または dispatch の検証が必要なため、オプトアウトが必要：

| ファイル | オプトアウト理由 |
|---|---|
| `Feature/Observers/LedgerObserverTest.php` | `Queue::assertPushed()` で dispatch を検証 |
| `Feature/Jobs/ProcessLedgerForRagJobTest.php` | ジョブを直接 `handle()` 呼び出し |
| `Unit/Jobs/Embedding/VectorizeAttachedFileTest.php` | `Bus::assertDispatched()` で dispatch を検証 |
| `Feature/Console/FinalizeAttachedFileProcessingTest.php` | `Bus::assertDispatched()` を使用 |
| `Feature/Jobs/ProcessAttachedFileTest.php` | `Bus::fake()` で実ジョブを検証 |
| `Feature/Jobs/ProcessVlmExtractionTest.php` | `Bus::fake()` を使用 |
| `Feature/Rag/VlmRagIntegrationTest.php` | 実コンテナ必須（`#[Group('external')]` も付与） |
| `Feature/Vlm/VlmIntegrationTest.php` | 実コンテナ必須（`#[Group('external')]` も付与） |
| `Feature/RagSearchServiceTest.php` | `EmbeddingService` のモック + 実挙動検証 |
| `Feature/Mcp/SearchLedgersToolSemanticSearchTest.php` | `dispatchSync()` で実ジョブを実行 |
| Livewire 系（ShowTest / ShowAdditionalTest / ModifyColumnTest / LedgerDiffViewerTest / FileInspectorTest） | `Bus::fake()` で OCR/VLM ジョブを検証 |
| `Feature/Ledger/LedgerTimestampSuppressionTest.php` / `OcrAndOptimizeFileJobTest.php` | `Bus::fake()` を使用 |
| `Unit/Jobs/OcrAndOptimizeFileTest.php` / `GenerateThumbnailTest.php` | `Bus::fake()` を使用 |
| `Unit/Models/AttachedFileTest.php` | `Bus::fake()` を使用 |

---

## `Bus::fake()` と `Queue::fake()` の関係

`Bus::fake()` と `Queue::fake()` は内部的に別のものであり、後から呼んだ方が有効になる。
`Bus::fake()` を使うテストでは `Queue::fake()` が上書きされてしまうため `$fakeQueue = false` が必要。

```php
class MyJobTest extends TestCase
{
    protected bool $fakeQueue = false;  // Queue::fake() を無効化

    public function setUp(): void
    {
        parent::setUp();
        Bus::fake();  // Bus::fake() を使用
    }
}
```

---

## 新しいテストを書くときのチェックリスト

- [ ] `Ledger::factory()->create()` を呼ぶ場合、`$fakeQueue = true`（デフォルト）のままか確認
- [ ] `AttachedFile::factory()->create()` も同様（Ledger 生成を伴う場合がある）
- [ ] `Bus::fake()` を使う場合は `$fakeQueue = false` を追加したか
- [ ] `Queue::assertPushed()` / `Bus::assertDispatched()` で dispatch を検証する場合は `$fakeQueue = false` を追加したか
- [ ] 実コンテナ（VLM/LDAP/OCR）への接続が必須なら `#[Group('external')]` を付与したか
- [ ] 「省略した外部処理」が別テストで担保されているか確認したか

詳細な判断フローは、この文書と
[`README.md`](./README.md)
の入口を参照。
