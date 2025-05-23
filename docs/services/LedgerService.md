# LedgerService

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

## 依存する他のクラスや設定

*   **モデル**:
    *   [`App\Models\Ledger`](../models/Ledger.md)
*   **関連サービス**:
    *   [`WorkflowService`](./WorkflowService.md) (台帳のワークフロー状態によっては連携が必要になる場合があります)

## その他

*   より詳細な台帳機能については、[台帳管理機能説明](/docs/function/Ledger.md) も参照してください。
