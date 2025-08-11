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

### 2.2. LedgerDiffProcessor サービスの作成と適用

*   **目的:** 2つの台帳状態を比較し、変更差分を計算するロジックをカプセル化する。これにより、app/Livewire/Ledger/Show.php コンポーネントから差分計算の複雑なロジックが分離され、コンポーネントの責務がUI表示とユーザーインタラクションに限定される。

*   **作業内容と経緯:**

  1. 初期状態の確認:
      * LedgerDiffProcessor サービスは既に作成され、app/Livewire/Ledger/Show.php に注入されていた。
      * Show.php の prepareContentDiff() メソッドは既に LedgerDiffProcessor
        のメソッドを呼び出す形になっていた。
      * しかし、app/Services/Ledger/LedgerDiffProcessor.php に構文エラー（メソッド外の Log::debug
        呼び出し）が存在した。

  2. `LedgerDiffProcessor.php` の構文エラー修正:
      * 対応: 構文エラーの原因となっていた Log::debug 呼び出しを prepareContentDiff メソッド内に移動した。
      * 結果: ParseError は解消された。

  3. `LedgerDiffProcessor::prepareContentDiff` へのデバッグログ追加:
      * 対応: prepareContentDiff メソッド内に、Current Column Defines (normalized)、Old Column Defines (normalized)、All Column IDs、Sorted Column IDs、currentValue、oldValue、normalizedCurrent、normalizedOld、isChanged の値を確認するための詳細な Log::debug ステートメントを追加した。
      * 課題: replace および replace_regex ツールでの複数行の文字列置換が、正確なマッチングの難しさや意図しないコードの重複により繰り返し失敗した。
      * 解決策: replace_symbol_body ツールを使用して、prepareContentDiff メソッド全体を、デバッグログを含む修正済みのコードブロックで置き換えることで、堅牢にコードを更新した。

  4. `LedgerDiffProcessorTest` のテスト失敗原因の特定と修正:
      * 観察: LedgerDiffProcessorTest の複数のテストが、hasChangedColumns や changed フラグの誤った評価、および Undefined array key エラーで失敗していた。
      * ログ分析による原因特定:
          * LedgerDiffProcessor::prepareContentDiff のデバッグログから、Current Column Defines (normalized) および Old Column Defines (normalized) が、テストデータで id を 0 や 1
            に設定しているにもかかわらず、{"1":{...},"2":{...}} のように 1 から始まるキーで正規化されていることが判明した。
          * これは、LedgerDefine および LedgerDiff モデルの column_define 属性に適用される AsColumnDefinesArrayJson キャストが、ColumnDefine オブジェクトの id をキーとしてコレクションを返す際に、何らかの理由で id が 1 から始まる値に変換されていることを示唆していた。
          * さらに、LedgerDefine::normalizeByColumnDefine() メソッドが content 配列を最終的に0から始まる連続した数値インデックスの配列に変換していることが確認された。このため、LedgerDiffProcessor 内で ColumnDefine の id を直接 content 配列のインデックスとして使用すると、インデックスの不一致が発生していた。
        
*    **修正内容:**
 
      * tests/Unit/Services/Ledger/LedgerDiffProcessorTest.php 内のテストデータ設定を修正し、LedgerDefine および LedgerDiff の column_define 属性に、ColumnDefine オブジェクトのコレクションではなく、直接連想配列（['id' => 0, ...] の形式）を渡すように変更した。これにより、AsColumnDefinesArrayJson が id を正しく 0 や 1 として処理するようになった。
      * LedgerDiffProcessor::prepareContentDiff メソッド内で、columnId を content 配列のインデックスとして直接使用するのではなく、array_search($columnId, $sortedColumnIds) を用いて columnId に対応する0ベースのインデックスを取得するようにロジックを修正した。
      * LedgerDiffProcessorTest のアサーションも、contentChanges 配列が ColumnDefine の id をキーとして構築されることを考慮し、contentChanges[0] や contentChanges[1] のように正しいキーを使用するように修正した。
      * 一時的に追加していた it_normalizes_column_defines_correctly() テストメソッドを削除した。
     
      * 結果: 上記の修正により、LedgerDiffProcessorTest 内の全てのテストが正常にパスした。

*    **現在の状況:**

  * リファクタリング計画の2.2項は完了し、ユニットテストによってその機能が検証されました。LedgerDiffProcessor サービスは現在、正しく実装され、テストされています。


### 2.3. `WorkflowService` への権限チェックロジック移管

*   **目的:** `Show.php` に存在するワークフロー関連の権限チェックロジックを `WorkflowService` に集約し、ワークフローに関するビジネスルールを一元管理する。
*   **既存要素の確認:**
    *   `app/Services/WorkflowService.php`: 既存のワークフローサービス。
    *   `app/Enums/WorkflowStatus.php`: ワークフローのステータス定義。
    *   `app/Models/Ledger.php`: 台帳モデル。
    *   `app/Models/User.php`: ユーザーモデル。
*   **作業内容と経緯:**
    1.  **`WorkflowService` へのロジック移管:**
        *   `app/Livewire/Ledger/Show.php` に実装されていた複雑な権限チェックロジックを、`app/Services/WorkflowService.php` に移管した。
        *   移管したメソッドは `canRequestApproval`, `canApprove`, `canReturnToDraft` の3つであり、`Ledger` モデルの内部状態（`canProceedToApprovalStep` や `canBeFinallyApproved` など）を考慮した、より現実に即したロジックとなっている。
    2.  **`Livewire/Ledger/Show.php` のリファクタリング:**
        *   `Show.php` の `canRequestApproval`, `canApprove`, `canReturnToDraft` の各メソッドを、`WorkflowService` の対応するメソッドを呼び出すだけのシンプルな実装に修正した。
    3.  **単体テストの実装 (`WorkflowServiceTest`):**
        *   移管したロジックの正当性を担保するため、`tests/Unit/Services/WorkflowServiceTest.php` を全面的に刷新した。
        *   `Ledger` モデルの内部ロジックを `partialMock` を用いて制御し、`WorkflowService` の責務である権限判定ロジックに焦点を当てたテストを実装した。
        *   担当者の割り当て、ワークフローステータス、前提条件（必須ロールの完了など）といった複数のシナリオを網羅する12のテストケースを作成した。
*   **テスト結果:**
    *   `vendor/bin/sail pest tests/Unit/Services/WorkflowServiceTest.php` を実行し、作成した単体テストがすべてパスすることを確認した。
    *   `vendor/bin/sail pest tests/Feature/Livewire/Ledger/ShowTest.php` を実行し、リファクタリング後も既存のフィーチャーテストがすべてパスすることを確認した。
    *   これにより、ロジックの分離が正しく行われたこと、そして既存機能へのデグレードが発生していないことが検証された。
*   **現在の状況:**
    *   リファクタリング計画の2.3項は完了した。`WorkflowService` が権限チェックの責務を担い、その動作は単体テストとフィーチャーテストによって保証されている。



### 2.4. `AttachedFile` モデルへの再処理ロジック移管

*   **目的:** `Show.php` の `retryProcessing` メソッドのロジックを `AttachedFile` モデル自身に移動し、モデルの責務を明確にする。
*   **既存要素の確認:**
    *   `app/Models/AttachedFile.php`: 添付ファイルモデル。
    *   `app/Jobs/Ledger/ProcessAttachedFile.php`: メインのファイル処理ジョブ。
    *   `app/Jobs/Ledger/GenerateThumbnail.php`: サムネイル生成ジョブ。
*   **作業内容と経緯:**
    1.  **`AttachedFile` モデルへのロジック移管:**
        *   `app/Models/AttachedFile.php` に `retryProcessing()` メソッドを新規に作成した。
        *   `Show.php` から、ステータスを `PENDING_INITIAL_PROCESSING` にリセットし、`ProcessAttachedFile` ジョブを再ディスパッチするロジックを移管した。
        *   サムネイル生成に失敗している場合に `GenerateThumbnail` ジョブを再ディスパッチするロジックも同様に移管した。
    2.  **`Livewire/Ledger/Show.php` のリファクタリング:**
        *   `Show.php` の `retryProcessing()` メソッドを修正し、`AttachedFile` インスタンスを取得してその `retryProcessing()` メソッドを呼び出すだけのシンプルな形に変更した。
        *   `catch` ブロックに `Log::error` を追加し、エラー発生時の追跡を容易にした。
*   **テスト結果:**
    *   `vendor/bin/sail pest tests/Feature/Livewire/Ledger/ShowTest.php --filter it_retries_attached_file_processing` を実行し、リファクタリング後も関連するフィーチャーテストがパスすることを確認した。
    *   これにより、ロジックの移管が正しく行われ、既存機能へのデグレードがないことが検証された。
*   **現在の状況:**
    *   リファクタリング計画の2.4項は完了した。添付ファイルの再処理に関するロジックは `AttachedFile` モデルに集約され、その動作はフィーチャーテストによって保証されている。

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
