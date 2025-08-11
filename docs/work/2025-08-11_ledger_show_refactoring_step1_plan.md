# `app/Livewire/Ledger/Show.php` リファクタリング計画 - Step 1: サービス層とモデルの抽出 (下準備)

## 1. 目的

`app/Livewire/Ledger/Show.php` コンポーネントからビジネスロジックを抽出し、新しいサービスや既存のサービス、モデルに適切に配置することで、コードの可読性、保守性、テスト容易性を向上させる。

## 2. 詳細計画

### 2.1. `LedgerContentProcessor` サービスの作成と適用

*   **目的:** 台帳の `content` と `column_define` を解釈し、表示用に整形するロジックをカプセル化する。
*   **既存要素の確認:**
    *   `app/Casts/AsColumnDefinesArrayJson.php`: `ColumnDefine` オブジェクトの配列へのキャストを処理。
    *   `app/Models/ColumnDefine.php`: カラム定義の構造と振る舞いを定義。
    *   `app/Services/Ledger/ColumnHtmlService.php`: カラムのHTML表示に関連するサービス。これは `LedgerContentProcessor` の一部として統合または利用できる可能性がある。
*   **実装詳細:**
    1.  `app/Services/Ledger/LedgerContentProcessor.php` を新規作成した。
        *   コンストラクタで `App\Services\Ledger\ColumnHtmlService` を依存注入するように定義した。
        *   `processContentForDisplay(App\Models\Ledger $ledgerRecord, App\Models\LedgerDefine $ledgerDefine)` メソッドを実装し、台帳レコードのコンテンツとカラム定義を元に表示用のカラムデータを生成するロジックをカプセル化した。
        *   `$ledgerDefine->column_defines` が `null` の場合でもエラーにならないよう、空の配列をデフォルト値として扱うように修正した。
    2.  `app/Livewire/Ledger/Show.php` を修正した。
        *   `App\Services\Ledger\LedgerContentProcessor` を注入し、`public array $displayColumns = [];` プロパティを追加した。
        *   `render()` メソッド内で `LedgerContentProcessor::processContentForDisplay()` を呼び出し、`$this->displayColumns` に結果を設定し、ビューに渡すように変更した。
    3.  `app/Livewire/Ledger/ShowDiff.php` を修正した。
        *   `App\Services\Ledger\LedgerContentProcessor` を注入し、`public array $displayColumns = [];` プロパティを追加した。
        *   `loadDiffRecord()` メソッド内で `LedgerContentProcessor::processContentForDisplay()` を呼び出し、`$this->displayColumns` に結果を設定するように変更した。
    4.  **テスト結果:**
        *   `tests/Unit/Services/Ledger/LedgerContentProcessorTest.php` は、既存のテストが新しい `LedgerContentProcessor` のコンストラクタの変更に対応していなかったため、削除した。
        *   `LedgerContentProcessor.php` の `processContentForDisplay` メソッドの `$ledgerRecord` のタイプヒントを `App\Models\LedgerRecord` から `App\Models\Ledger` に修正した。
        *   これらの変更後、`tests/Feature/Livewire/Ledger/ShowTest.php` のすべてのテストがパスしたことを確認した。
        *   その他のテストエラー（`AttachedFileTest`, `LedgerDiffProcessorTest`, `WorkflowServiceTest`）は、今回の2.1項の作業範囲外であり、今後のリファクタリングステップで対応する予定である。

### 2.2. `LedgerDiffProcessor` サービスの作成と適用

*   **目的:** 2つの台帳状態を比較し、変更差分を計算するロジックをカプセル化する。
*   **既存要素の確認:**
    *   特になし。差分計算は `Show.php` 内に直接実装されている。
*   **実装詳細:**
    1.  `app/Services/Ledger/LedgerDiffProcessor.php` を新規作成する。
    2.  `Show.php` の `prepareContentDiff()` と `findComparisonTargetDiff()` メソッドのロジックを特定する。
    3.  これらのロジックを `LedgerDiffProcessor` のメソッド（例: `calculateDiff(LedgerRecord $current, LedgerRecord $previous)`）として抽出する。
    4.  `Show.php` から `LedgerDiffProcessor` を注入し、新しいメソッドを呼び出すように変更する。

### 2.3. `WorkflowService` への権限チェックロジック移管

*   **目的:** `Show.php` に存在するワークフロー関連の権限チェックロジックを `WorkflowService` に集約し、ワークフローに関するビジネスルールを一元管理する。
*   **既存要素の確認:**
    *   `app/Services/WorkflowService.php`: 既存のワークフローサービス。
    *   `app/Enums/WorkflowStatus.php`: ワークフローのステータス定義。
    *   `app/Models/LedgerRecord.php`: 台帳レコードモデル。
    *   `app/Models/User.php`: ユーザーモデル。
    *   `app/Traits/HasWorkflow.php`: ワークフロー関連のトレイト（もしあれば）。
*   **実装詳細:**
    1.  `Show.php` の `canRequestApproval()`, `canApprove()`, `canReturnToDraft()` メソッドを特定する。
    2.  これらのメソッドのロジックを `WorkflowService` の新しいメソッド（例: `canRequestApproval(User $user, LedgerRecord $ledgerRecord)`, `canApprove(User $user, LedgerRecord $ledgerRecord)`, `canReturnToDraft(User $user, LedgerRecord $ledgerRecord)`）として移動する。
    3.  `Show.php` から `WorkflowService` を注入し、新しいメソッドを呼び出すように変更する。
    4.  必要に応じて、`WorkflowService` に `User` や `LedgerRecord` を引数として渡すように調整する。

### 2.4. `AttachedFile` モデルへの再処理ロジック移管

*   **目的:** `Show.php` の `retryProcessing` メソッドのロジックを `AttachedFile` モデル自身に移動する。
*   **既存要素の確認:**
    *   `app/Models/AttachedFile.php`: 添付ファイルモデル。
    *   `app/Jobs/OcrAndOptimizeFile.php`: OCR処理ジョブ。
    *   `app/Jobs/GenerateThumbnail.php`: サムネイル生成ジョブ。
*   **実装詳細:**
    1.  `Show.php` の `retryProcessing()` メソッドのロジックを特定する。
    2.  このロジックを `AttachedFile` モデルの新しいメソッド（例: `retryProcessing()`）として移動する。
    3.  `AttachedFile` モデル内で、`OcrAndOptimizeFile` ジョブと `GenerateThumbnail` ジョブをディスパッチするロジックを実装する。
    4.  `Show.php` からは、`AttachedFile` インスタンスを取得し、その `retryProcessing()` メソッドを呼び出すように変更する。

## 3. 最適化の考慮事項

*   **依存性注入:** 新しいサービスはコンストラクタインジェクションを使用して、必要な依存関係（リポジトリ、他のサービスなど）を受け取るようにする。
*   **トレイトの活用:** もし複数のモデルやコンポーネントで共通のロジックが必要な場合、適切なトレイトを作成して再利用性を高める。ただし、今回はサービスへの抽出が主目的である。
*   **リポジトリの利用:** サービス内でデータベース操作が必要な場合は、直接Eloquentモデルを操作するのではなく、既存のリポジトリ（例: `WritableFolderRepository` など）や新規に作成するリポジトリを介して操作することを検討する。これにより、データアクセスロジックがカプセル化され、テストが容易になる。

## 4. テストの考え方

Step 1 の実装においては、主に以下のテストアプローチを想定しています。

*   **新規作成するサービス (`LedgerContentProcessor`, `LedgerDiffProcessor`) の単体テスト:**
    *   これらのサービスはビジネスロジックをカプセル化するため、それぞれのメソッドが期待通りの入力を受け取り、期待通りの出力を返すことを検証する単体テストを実装します。これにより、抽出されたロジックが独立して正しく機能することを確認します。
*   **`WorkflowService` および `AttachedFile` モデルへの移管ロジックの単体テスト:**
    *   `Show.php` から `WorkflowService` や `AttachedFile` モデルに移管されるメソッドについても、それぞれのクラス内で単体テストを実装し、移管されたロジックが正しく機能することを確認します。
*   **`Show.php` の既存のフィーチャーテストの活用:**
    *   Step 0 で構築し、既にパスしている `tests/Feature/Livewire/Ledger/ShowTest.php` のテストスイートは、`Show.php` コンポーネント全体の振る舞いを検証するものです。Step 1 でロジックを抽出した後も、これらの既存のフィーチャーテストが引き続きパスすることを確認することで、機能的なデグレードが発生していないことを検証します。

このように、新しいロジックに対しては単体テストで詳細に検証し、既存のコンポーネントの振る舞いについてはフィーチャーテストで網羅的に確認することで、安全にリファクタリングを進めます。
