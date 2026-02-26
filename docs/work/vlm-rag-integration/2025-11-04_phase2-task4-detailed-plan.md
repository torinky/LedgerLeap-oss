# VLM/RAG統合 Phase2 テスト実装計画書

**ドキュメントバージョン:** 2.0
**作成日:** 2025年11月4日
**最終更新:** 2025年11月4日（実装見直し反映）
**作成者:** Gemini

---

## 1. 概要

### 1.1. 目的
本ドキュメントは、WBS「VLM/RAG統合 - Phase2」のタスクID 4.0「テスト実装」に関する詳細な実装計画を定義するものです。

この計画は、以下の3つのテストスイートの作成を対象とします。
- **タスク4.1:** `VlmClientService` ユニットテスト
- **タスク4.2:** `ProcessVlmExtraction` ジョブテスト
- **タスク4.3:** VLM統合テスト (`ProcessAttachedFile` ジョブの連携テスト)

### 1.2. 改訂履歴
- **v2.0 (2025-11-04):** 初期実装で発見された問題に基づき、テスト戦略を見直し
  - `Log::spy()`の問題への対処
  - `waitUntilReady()`のモック化戦略
  - Feature Test未実装の指摘

### 1.3. 関連ドキュメント
- [VLM/RAG統合 - Phase2 VLM処理実装 WBS](./2025-11-03_phase2-wbs.md)
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [`ProcessAttachedFile` ジョブ改修 詳細計画書](./2025-11-04_phase2-task3.1-detailed-plan.md)

---

## 2. 実装で発見された問題と対策

初期実装を試みた結果、以下の技術的課題が明らかになり、テスト戦略を見直しました。

### 2.1. `Log::spy()` によるチャンネルモックの失敗
- **問題:** `Log::spy()`は`Log::channel()`メソッドチェーンを正しくモックできず、`VlmClientService`内の`Log::channel($this->logChannel)->warning()`呼び出しで`null`エラーが発生
- **影響:** `extract()`メソッド内で呼ばれる`waitUntilReady()`が必ずクラッシュし、テストが実行不可
- **対策:** ログ出力の検証を省略し、機能的な動作検証に集中。ログは実際の統合テストや手動検証で確認

### 2.2. `waitUntilReady()` の複雑性とテストタイムアウト
- **問題:** `waitUntilReady()`はループで`healthCheck()`を呼び、最大タイムアウト秒数（300秒）待機する設計のため、テストが長時間化
- **影響:** 各テストケースが数十秒〜数分かかり、CI/CD環境での実用性が低下
- **対策:** ユニットテストでは`partialMock()`を使用し、`waitUntilReady()`メソッドをスタブ化して即座にリターン。実際の待機処理は統合テストで検証

### 2.3. テストごとの設定値の管理
- **調査結果:** `Config::set()`はテストケースごとに自動リセットされるため、テスト間の干渉なし
- **対策:** `Config::set('vlm.enabled', true/false)`で各テストケース冒頭に設定

### 2.4. DBトランザクションを含むジョブのテスト
- **調査結果:** `RefreshDatabaseWithTenant`トレイト使用により、テスト全体がトランザクション内で実行され、自動ロールバック
- **対策:** ジョブの`handle()`メソッドを直接呼び出し、`assertDatabaseHas`でDB状態を検証

### 2.5. 物理ファイルを扱う処理のテスト
- **調査結果:** `Storage::fake('public')`でダミーファイルを作成し、`AttachedFile`モデルの`getPhysicalPath()`が返すパスを使用可能
- **対策:** `Storage::disk('public')->put()`でテスト用ファイルを配置し、物理パスベースでテスト実行

---

## 3. テスト実装計画（改訂版）

### 3.1. タスク4.1: `VlmClientService` ユニットテスト
- **ファイル:** `tests/Unit/Services/VlmClientServiceTest.php`
- **方針:** `Http::fake()`でVLM APIをモック化し、`partialMock()`で`waitUntilReady()`をスタブ化

**主要な変更点:**
- `Log::spy()`を削除し、ログ検証を省略
- `partialMock()`で`waitUntilReady()`メソッドをスタブ化し、即座にリターンさせることでタイムアウト待機を回避
- `Storage::fake('public')`でテスト用ファイルを作成し、`AttachedFile`ファクトリと連携

**テストケース:**
1. **正常系:** VLM APIが成功レスポンスを返す場合の`extract()`動作を検証
   - `Http::assertSent`でリクエスト送信確認
   - レスポンスデータ（markdown, structured_data, model等）の構造検証
2. **異常系（VLMエラー）:** 500エラー時に`RuntimeException`がスローされることを確認
3. **異常系（接続タイムアウト）:** `ConnectionException`が正しく伝播することを確認
4. **ヘルスチェック:** `healthy`, `unhealthy`, `unreachable`の各ステータスを検証

### 3.2. タスク4.2: `ProcessVlmExtraction` ジョブテスト
- **ファイル:** `tests/Feature/Jobs/ProcessVlmExtractionTest.php`（新規作成）
- **方針:** `VlmClientService`を完全モック化し、ジョブの責務（DB更新、ステータス変更、後続ジョブディスパッチ）を検証

**主要な変更点:**
- Feature Testとして新規作成（初版では未実装だったファイル）
- `mock(VlmClientService::class)`で完全モック化
- `Bus::fake()`で後続ジョブ（`UpdateLedgerChunks`）のディスパッチを検証
- `RefreshDatabaseWithTenant`で自動ロールバック

**テストケース:**
1. **正常系:** VLM処理成功時のDB更新とジョブディスパッチを検証
   - `vlm_markdown`, `vlm_structured_data`, `vlm_model`カラムの更新確認
   - ステータスが`COMPLETED`に変更されることを確認
   - `UpdateLedgerChunks`ジョブがディスパッチされることを確認
2. **異常系（空Markdown）:** 空文字列が返された場合の`RuntimeException`と`VLM_FAILED`ステータス更新を確認
3. **異常系（VLM例外）:** `VlmClientService`が例外をスローした場合の`failed()`メソッド実行と`VLM_FAILED`ステータス更新を確認

### 3.3. タスク4.3: VLM統合テスト
- **ファイル:** `tests/Feature/Vlm/VlmIntegrationTest.php`（新規作成）
- **方針:** `ProcessAttachedFile`ジョブの条件分岐ロジック（VLM/OCR/完了）を検証

**主要な変更点:**
- Feature Testとして新規作成（初版では未実装だったファイル）
- `Config::set('vlm.enabled', true/false)`でVLM有効/無効を切り替え
- `mock(TikaClient::class)`でApache Tikaの挙動を制御
- `Bus::fake()`で各条件下のジョブディスパッチパターンを検証

**テストケース:**
1. **VLM有効・対象ファイル:** `ProcessVlmExtraction`がディスパッチされ、`OcrAndOptimizeFile`がディスパッチされないことを確認
   - ステータスが`PENDING_VLM`に変更されることを確認
2. **VLM無効:** `OcrAndOptimizeFile`がディスパッチされ、`ProcessVlmExtraction`がディスパッチされないことを確認
3. **VLM有効・非対象ファイル:** ZIPファイル等でどちらのジョブもディスパッチされず、`COMPLETED`ステータスになることを確認
4. **Tika失敗時のフォールバック:** Tika例外発生時に`ProcessVlmExtraction`がフォールバックとしてディスパッチされることを確認

---

## 4. 実装手順

### Phase 1: ユニットテスト修正
1. `VlmClientServiceTest.php`の修正
   - `Log::spy()`削除
   - `partialMock()`で`waitUntilReady()`スタブ化
   - 全テストケースの通過確認

### Phase 2: Feature Test実装
2. `tests/Feature/Jobs/ProcessVlmExtractionTest.php`作成
   - ディレクトリ作成が必要
   - 3つのテストケース実装
   
3. `tests/Feature/Vlm/VlmIntegrationTest.php`作成
   - 既存の`tests/Feature/Vlm/`に配置
   - 4つのテストケース実装

### Phase 3: 全体検証
4. 全VLM関連テストの実行: `./vendor/bin/sail test --filter=Vlm`
5. コードフォーマット確認: `./vendor/bin/sail pint`

---

## 5. 既存テストとの棲み分け

### `tests/Feature/Vlm/MarkerVlmTest.php`等（既存）
- **目的:** 実際のVLMコンテナを使ったE2Eテスト
- **実行条件:** VLMコンテナが起動している環境
- **検証内容:** 実ファイル処理と実VLMレスポンスの検証

### 新規作成するテスト
- **目的:** VLM統合機能の単体・結合テスト
- **実行条件:** VLMコンテナ不要（モック使用）
- **検証内容:** ロジック分岐、エラーハンドリング、DB更新の検証
- **利点:** 高速実行、CI/CD環境で安定動作

---

## 6. まとめ

**改訂版計画の要点:**
- `Log::spy()`問題を回避し、機能検証に集中
- `partialMock()`で`waitUntilReady()`をスタブ化し、テスト実行時間を短縮
- Feature Test（タスク4.2, 4.3）を新規作成し、テストカバレッジを完全化
- モックベースの高速テストと実コンテナベースの統合テストを明確に分離

本計画に基づきテストを実装することで、VLM統合機能の品質を多角的に保証します。ユニットテストで個々のコンポーネントの動作を、フィーチャーテストでコンポーネント間の連携と副作用（DB更新、ジョブディスパッチ）をそれぞれ検証し、堅牢なシステム構築を目指します。
