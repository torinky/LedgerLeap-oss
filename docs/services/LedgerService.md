# LedgerService

## 目的

`LedgerService` は、台帳データ (`App\Models\Ledger`) の取得、検索、および一般的なビジネスロジックをカプセル化する役割を担います。台帳のライフサイクル管理（作成、更新、削除）や、複雑な検索ロジック、データ変換などを提供し、コントローラーやLivewireコンポーネントからビジネスロジックを分離します。

## サービスの責務
台帳 ([`Ledger`](../models/Ledger.md)) モデルに関連するビジネスロジックを担当します。主に台帳データの取得や検索機能を提供します。
台帳のステータス変更やワークフロー関連の操作については、[`WorkflowService`](./WorkflowService.md) が担当します。

## 主要な公開メソッド

*   **`getLedgers()`**:
    *   目的・機能: 全ての台帳データを取得します。作成日時の降順でソートされます。
    *   引数: なし
    *   戻り値: `Illuminate\Database\Eloquent\Collection|Illuminate\Database\Eloquent\Builder[]` - Ledgerモデルのコレクション。
*   **`searchLedgers(string $keyword)`**:
    *   目的・機能: 指定されたキーワードに基づいて台帳データを全文検索します。作成日時の降順でソートされます。内部的に `Ledger::scopeSearch()` を利用しているようです。
    *   引数:
        *   `$keyword`: 検索キーワード (文字列)
    *   戻り値: `Illuminate\Database\Eloquent\Collection|Illuminate\Database\Eloquent\Builder[]` - 検索結果に一致したLedgerモデルのコレクション。
*   **`createLedger(array $data)`**:
    *   目的・機能: 新しい台帳レコードを作成します。バリデーション、ファイル処理、初期のLedgerDiff作成などを含みます。
    *   引数: `$data` (台帳データ、ファイル情報などを含む配列)
    *   戻り値: `App\Models\Ledger` - 作成されたLedgerモデルのインスタンス。
*   **`updateLedger(Ledger $ledger, array $data)`**:
    *   目的・機能: 既存の台帳レコードを更新します。バリデーション、ファイル処理、LedgerDiffの更新などを含みます。
    *   引数: `$ledger` (更新対象のLedgerモデル), `$data` (更新データ)
    *   戻り値: `App\Models\Ledger` - 更新されたLedgerモデルのインスタンス。
*   **`deleteLedger(Ledger $ledger)`**:
    *   目的・機能: 指定された台帳レコードを削除します。関連するファイルやLedgerDiffも削除します。
    *   引数: `$ledger` (削除対象のLedgerモデル)
    *   戻り値: `bool` - 削除が成功したかどうか。

## 依存する他のクラスや設定

*   **モデル**:
    *   [`App\Models\Ledger`](../models/Ledger.md)
*   **関連サービス**:
    *   [`WorkflowService`](./WorkflowService.md) (台帳のワークフロー状態によっては連携が必要になる場合があります)

## その他

*   より詳細な台帳機能については、[台帳管理機能説明](/docs/function/Ledger.md) も参照してください。
