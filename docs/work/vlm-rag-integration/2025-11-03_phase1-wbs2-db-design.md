# VLM/RAG統合 Phase1 - WBS2.0 データベース整備 詳細設計

**ドキュメントID:** 2025-11-03_phase1-wbs2-db-design.md
**担当者:** (担当者名)
**作成日:** 2025年11月3日
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase1 基盤整備 WBS](./2025-11-03_phase1-wbs.md)
- [VLM/RAG統合 Phase1 - WBS1.0 計画レビューと詳細設計](./2025-11-03_phase1-wbs1-review-design.md)

---

## 1. 概要

本ドキュメントは、WBS 2.0「データベース整備」における`attached_files`テーブルの拡張に関する詳細設計を記述する。特に、VLM（Visual Language Model）コンテナのAPIから取得したデータを、どのカラムにどのように格納するかを明確にする。

## 2. `attached_files` テーブル拡張

`attached_files`テーブルに以下の新規カラムを追加し、VLM処理結果を格納する。

### 2.1. 新規カラム定義

| カラム名 | 型 | NULLABLE | コメント | 投入内容 | APIからの取得元 | 目的 |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `vlm_markdown` | `LONGTEXT` | Yes | VLM抽出Markdown結果（RAG用） | VLMコンテナから抽出されたドキュメントのMarkdown形式テキスト。RAGのチャンク生成の主要入力。 | VLM APIレスポンスの `markdown` フィールド。 | 高品質な構造的情報をRAGに提供し、検索精度と応答品質を向上。 |
| `vlm_structured_data` | `JSON` | Yes | VLM構造化データ（エンティティ、テーブル等） | VLMコンテナから抽出されたエンティティ、テーブルデータ、キーバリューペアなどの構造化情報。 | VLM APIレスポンスの `entities`, `tables` などのフィールドを統合。 | 将来的な機能拡張（エンティティ検索、テーブルデータ利用）のための保存。 |
| `vlm_model` | `VARCHAR(100)` | Yes | 使用VLMモデル名 | VLM処理に使用されたモデルの識別名（例: `PaddleOCR-VL-0.9B`）。 | `ProcessVlmExtraction`ジョブのコンストラクタで指定、またはVLM APIが返すモデル名。 | 結果の品質評価、モデル切り替え時のトレーサビリティ確保。 |
| `vlm_confidence` | `DECIMAL(4,3)` | Yes | VLM処理信頼度（0.000-1.000） | VLM処理結果の信頼度スコア。 | VLM APIレスポンスの `confidence` フィールド。 | 結果品質の判断、信頼度が低い場合の確認・再処理トリガー。 |
| `vlm_processing_time_ms` | `INT UNSIGNED` | Yes | VLM処理時間（ミリ秒） | VLM処理にかかった時間。 | `ProcessVlmExtraction`ジョブ内で計測。 | パフォーマンス監視、ボトルネック特定。 |
| `vlm_processed_at` | `TIMESTAMP` | Yes | VLM処理完了日時 | VLM処理が正常に完了した日時。 | `ProcessVlmExtraction`ジョブ内で `now()` で記録。 | 処理状況の追跡、未処理ファイルのバッチ処理フィルタリング。 |

### 2.2. インデックス追加

以下のインデックスを追加し、検索性能と処理効率を向上させる。

- `idx_vlm_model` (`vlm_model`)
- `idx_vlm_processed_at` (`vlm_processed_at`)
- `idx_status_vlm_processed` (`status`, `vlm_processed_at`)

### 2.3. マイグレーションファイル

- **ファイル名:** `YYYY_MM_DD_HHMMSS_add_vlm_columns_to_attached_files_table.php`
- **内容:** 上記カラム定義とインデックス追加を含むマイグレーションを作成する。

## 3. VLM APIからのデータフロー

`App\Jobs\Ledger\ProcessVlmExtraction`ジョブがVLMコンテナのAPIを呼び出し、そのJSONレスポンスをパースして、`AttachedFile`モデルの`update`メソッドを通じて上記カラムにデータを永続化する。

```php
// ProcessVlmExtraction.php (抜粋)

// VLM APIコール
$vlmOutput = $vlmClient->extract(
    $this->attachedFile->getPhysicalPath(),
    $this->vlmModel,
    timeout: 300
);

// データベース保存
$this->attachedFile->update([
    'vlm_markdown' => $vlmOutput['markdown'] ?? null,
    'vlm_structured_data' => [
        'entities' => $vlmOutput['entities'] ?? [],
        'tables' => $vlmOutput['tables'] ?? [],
    ],
    'vlm_model' => $this->vlmModel,
    'vlm_confidence' => $vlmOutput['confidence'] ?? null,
    'vlm_processing_time_ms' => $processingTimeMs,
    'vlm_processed_at' => now(),
    'status' => AttachedFileStatus::COMPLETED,
]);
```

## 4. 結論

本詳細設計に基づき、`attached_files`テーブルの拡張マイグレーションを作成し、データベース整備を進める。これにより、VLM処理結果を効率的かつ構造的に保存し、RAG機能の基盤を確立する。
