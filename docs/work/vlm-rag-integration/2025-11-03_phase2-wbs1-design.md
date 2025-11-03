# VLM/RAG統合 Phase2 - WBS1.0 VLMサービスクラス実装 詳細計画

**ドキュメントID:** 2025-11-03_phase2-wbs1-design.md
**担当者:** (担当者名)
**作成日:** 2025年11月3日
**関連ドキュメント:**
- [VLM/RAG統合実装計画書（最終版）](../../architecture/vlm-rag-integration.md)
- [Phase2 VLM処理実装 WBS](./2025-11-03_phase2-wbs.md)

---

## 1. 目的

VLMコンテナのAPIと通信し、添付ファイルからMarkdownと構造化データを抽出する責務を持つ `VlmClientService` を実装するための詳細な計画を定義する。

## 2. 調査概要

- **既存サービスの実装調査:** `app/Services/EmbeddingService.php` を調査し、外部API連携（設定読込、HTTP通信、エラーハンドリング、ロギング、ヘルスチェック）の標準パターンを確認した。本計画はこれを踏襲する。
- **VLMコンテナAPI仕様調査:** VLMコンテナのソースコード `docker/paddle/unified_api.py` を調査し、`/health` 及び `/extract/structured` エンドポイントの仕様（リクエスト/レスポンス形式）を特定した。
- **ファイルパス整合性調査:** `app/Livewire/Ledger/CreateColumn.php` 及び `app/Jobs/Ledger/ProcessAttachedFile.php` を調査し、`AttachedFile` モデルの `path` カラムには、常に `Storage::disk('public')` からの相対パスが一貫して格納されることを確認した。

---

## 3. 実装ステップ

### ステップ1: `AttachedFile` モデルに物理パス取得メソッドを追加

-   **要点:**
    -   可読性とカプセル化のため、`app/Models/AttachedFile.php` に `public function getPhysicalPath(): ?string` メソッドを実装する。
    -   メソッド内部では、`Storage::disk('public')->path($this->path)` を呼び出してファイルの絶対物理パスを返す。
    -   `$this->path` には、ファイルアップロード時やジョブ処理時に設定された、`public` ディスクからの相対パスが格納されている。
    -   ファイルが存在しない場合に備え、`Storage::disk('public')->exists($this->path)` で存在確認を行い、存在しない場合は `null` を返す。

### ステップ2: `VlmClientService` の基本構造とコンストラクタを実装

-   **要点:**
    -   `app/Services/VlmClientService.php` を新規作成する。
    -   `EmbeddingService` を参考に、コンストラクタで `config/vlm.php` からURL、タイムアウト値、デフォルトモデルなどの設定値をプロパティにインジェクトする。
    -   `Illuminate\Support\Facades\Http` と `Illuminate\Support\Facades\Log` をインポートする。

### ステップ3: `extract` メソッドの実装

-   **要点:**
    -   `public function extract(AttachedFile $attachedFile): array` というシグネチャでメソッドを定義する。
    -   `Http::asMultipart()->attach('file', ...)` を使い、ファイルをPOSTリクエストで送信する。
    -   リクエストには `timeout()` を設定し、APIエンドポイントは `/extract/structured` を使用する。
    -   `try-catch` ブロックでAPI呼び出しを囲み、`ConnectionException` と `Exception` を捕捉する。
    -   `$response->successful()` で成功判定を行い、失敗した場合は `RuntimeException` をスローし、`Log::error()` でレスポンスボディを含む詳細を記録する。
    -   成功時には `$response->json()` で結果をデコードして返し、`Log::info()` で処理時間やモデル名などのサマリーを記録する。

### ステップ4: ヘルスチェック機能の実装

-   **要点:**
    -   `EmbeddingService` の `healthCheck()` と `waitUntilReady()` を参考に、VLMコンテナの準備ができるまで待機する機能を実装する。
    -   `healthCheck()` は `/health` エンドポイントにGETリクエストを送信する。
    -   `waitUntilReady()` は、`extract` メソッドの最初に呼び出し、サービスの可用性を確保することで、VLMコンテナの起動遅延による即時失敗を防ぐ。
    -   接続できない場合は `Log::warning()` で記録し、タイムアウトした場合は `RuntimeException` をスローする。

---

## 4. 懸念事項と補足

-   **VLMコンテナのAPI仕様:** **(解決済み)**
    -   **根拠:** `docker/paddle/unified_api.py` のソースコードを確認。
    -   **詳細:**
        -   ヘルスチェックは `/health` (GET) で、`{"status": "healthy", ...}` を返す。
        -   抽出処理は `/extract/structured` (POST) で、`multipart/form-data` 形式で `file` キーにファイルを添付する。
        -   レスポンスは、`markdown` と `structured_data` を含むJSONオブジェクトである。

-   **ファイルパスの整合性:** **(解決済み)**
    -   **根拠:** `app/Livewire/Ledger/CreateColumn.php` と `app/Jobs/Ledger/ProcessAttachedFile.php` のソースコードを確認。
    -   **詳細:** `AttachedFile` モデルの `path` カラムには、ファイルアップロードからジョブ処理まで一貫して `Storage::disk('public')` からの相対パスが格納される設計となっている。そのため、`Storage::disk('public')->path($this->path)` で安全に物理パスを取得できる。

-   **補足事項 (実装上の注意):**
    -   `VlmClientService` の `extract` メソッドでファイルを `attach` する際、`file_get_contents()` でファイル内容をメモリに読み込むため、巨大なファイルを扱うとメモリ使用量が急増する可能性がある。`config/vlm.php` の `max_file_size` で処理対象のファイルサイズを制限する現在の設計は、この観点からも妥当である。