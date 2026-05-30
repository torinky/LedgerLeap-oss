# NumberingService

## 目的

`NumberingService` は、台帳定義で設定された「自動採番」タイプのカラムに対して、一意の連番を生成する機能を提供します。接頭辞、桁数、改訂記号などの設定に基づいて、自動的に次の番号を計算し、重複を避けるようにします。

## クラス概要

*   **クラス名**: `App\Services\NumberingService`
*   **役割**: 自動採番番号の生成ロジックを提供します。

## 主要な公開メソッド

*   **`getNextNumber(object $columnDefine, int $ledgerDefineId): string`**:
    *   目的・機能: 指定されたカラム定義と台帳定義IDに基づいて、次の自動採番番号を生成します。既存の台帳レコードを走査し、現在の最大番号を特定してインクリメントします。
    *   引数:
        *   `$columnDefine`: 自動採番カラムの定義オブジェクト（`ColumnDefine`）。`options` プロパティに `prefix`, `digits`, `revision` などの設定が含まれます。
        *   `$ledgerDefineId`: 対象の台帳定義ID。
    *   戻り値: `string` - 生成された次の採番番号。

## 依存する他のクラスや設定

*   **モデル**:
    *   `App\Models\Ledger`
*   **ファサード**:
    *   `Illuminate\Support\Str`

## その他

*   採番ロジックは、既存の台帳レコードを検索して最大値を特定するため、大量のレコードがある場合にはパフォーマンスに影響を与える可能性があります。
*   `unique` 設定が有効な場合、採番された番号が既存のレコードと重複しないように考慮されます。
