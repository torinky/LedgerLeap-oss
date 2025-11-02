# VLM/RAG統合 Phase1 - WBS1.0 計画レビューと詳細設計

**ドキュメントID:** 2025-11-03_phase1-wbs1-review-design.md
**担当者:** (担当者名)
**作成日:** 2025年11月3日
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase1 基盤整備 WBS](./2025-11-03_phase1-wbs.md)

---

## 1. 計画内容の再レビュー（WBS 1.1）

`VLM/RAG統合実装計画書`の内容を、現在のシステム構成と照らし合わせ、実装上のリスクや考慮事項を再検証した。

### 1.1. レビュー結果サマリー

| 確認項目 | 評価 | 結論・要点 |
| :--- | :--- | :--- |
| **全体アーキテクチャ** | ✅ **OK** | 既存のジョブキュー（Redis）、ストレージ（S3互換）、DB（MySQL）との連携は、計画書通りの非同期処理で問題なく実現可能。 |
| **処理フロー** | ✅ **OK** | Tika → VLM → RAG のジョブチェーンは合理的。各ジョブの遅延実行（`delay`）とリトライ設定（`tries`, `backoff`）により、堅牢性は担保されている。 |
| **データスキーマ** | ✅ **OK** | `attached_files`へのカラム追加は、既存レコードへの影響が少ない。`vlm_markdown` (`LONGTEXT`) のディスク容量増加は許容範囲内。インデックスも適切。 |
| **影響範囲** | ✅ **OK** | VLM処理は既存のTika/OCR処理フローから分岐するため、VLM機能が無効、または失敗した場合でも、従来の全文検索機能は維持される（後方互換性あり）。 |
| **実現可能性** | ✅ **OK** | VLMコンテナ（Python/FastAPI）とのHTTP通信は、Laravelの`Http`ファサードで容易に実装可能。タイムアウト設定も妥当。 |

### 1.2. 総合評価

**結論:** 計画書の内容は技術的に実現可能であり、大きなリスクは認められない。Phase1の基盤整備に着手することに問題はない。

---

## 2. 実装に向けた詳細設計（WBS 1.2）

レビュー結果に基づき、Phase1で実装する各コンポーネントの要点を以下に定義する。

### 2.1. データベース (WBS 2.0)

- **マイグレーションファイル名:** `YYYY_MM_DD_HHMMSS_add_vlm_columns_to_attached_files_table.php`
- **対象テーブル:** `attached_files`
- **追加カラム:**
  - `vlm_markdown` (LONGTEXT, NULLABLE): RAGの入力ソースとなるVLM抽出結果。
  - `vlm_structured_data` (JSON, NULLABLE): エンティティ、テーブル等の構造化データ。
  - `vlm_model` (VARCHAR(100), NULLABLE): 使用したVLMモデル名。
  - `vlm_confidence` (DECIMAL(4,3), NULLABLE): 処理の信頼度スコア。
  - `vlm_processing_time_ms` (INT UNSIGNED, NULLABLE): 処理時間。
  - `vlm_processed_at` (TIMESTAMP, NULLABLE): 処理完了日時。
- **追加インデックス:**
  - `idx_vlm_model` (`vlm_model`)
  - `idx_vlm_processed_at` (`vlm_processed_at`)
  - `idx_status_vlm_processed` (`status`, `vlm_processed_at`)

### 2.2. モデル・Enum (WBS 3.0)

- **Enum:** `app/Enums/AttachedFileStatus.php`
  - **追加ケース:**
    - `case VLM_PROCESSING = 'vlm_processing';`
    - `case VLM_FAILED = 'vlm_failed';`
- **Model:** `app/Models/AttachedFile.php`
  - **`$fillable` への追加:** 上記 `追加カラム` の全項目。
  - **アクセサ/ヘルパーメソッド:**
    - `hasVlmResult(): bool`: `vlm_markdown` が存在し、ステータスが `COMPLETED` であるかを確認。
    - `isVlmProcessing(): bool`: ステータスが `VLM_PROCESSING` であるかを確認。
    - `isVlmFailed(): bool`: ステータスが `VLM_FAILED` であるかを確認。

### 2.3. 設定ファイル (WBS 4.0)

- **新規作成:** `config/vlm.php`
  - **主要キー:** `enabled`, `url`, `max_file_size`, `default_model`, `timeout`
- **拡張:** `config/rag.php`
  - **追加キー:** `auto_update_chunks`, `prefer_vlm_markdown`
- **更新:** `.env.example`
  - **追加変数:** `VLM_ENABLED`, `VLM_URL`, `VLM_MAX_FILE_SIZE`, `RAG_AUTO_UPDATE_CHUNKS` 等。

---

## 3. 結論

本ドキュメントで定義した要点に基づき、WBS 2.0以降の実装作業を進める。各タスクの担当者は、この設計を実装の指針とすること。
