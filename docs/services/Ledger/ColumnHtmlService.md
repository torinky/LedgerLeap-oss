# ColumnHtmlService

## 目的

`ColumnHtmlService` は、台帳レコードの詳細表示画面やリスト表示画面において、各カラムに保存されたデータをHTML形式で整形して表示するためのサービスです。カラムのタイプ（テキスト、数値、ファイルなど）に応じて適切なHTML要素を生成し、必要に応じてキーワードのハイライト表示やファイルのサムネイル表示なども行います。

## クラス概要

*   **クラス名**: `App\Services\Ledger\ColumnHtmlService`
*   **役割**: 台帳カラムの値をHTMLとしてレンダリングします。

## 主要な公開メソッド

*   **`show(object|array $columnDefineData, mixed $initialValue, bool $canView = true, array $attrs = [], string $idPrefix = '', bool $asCreate = false): HtmlString`**:
    *   目的・機能: 指定されたカラム定義と値に基づいて、表示用のHTML文字列を生成します。閲覧権限の有無、追加のHTML属性、IDプレフィックス、新規作成モードかどうかに応じて表示を調整します。
    *   引数:
        *   `$columnDefineData`: カラム定義情報 (`ColumnDefine` オブジェクトまたは配列)。
        *   `$initialValue`: 表示するカラムの値。
        *   `$canView`: 閲覧権限があるかどうか。`false` の場合、空文字列を返します。
        *   `$attrs`: 追加のHTML属性の配列。
        *   `$idPrefix`: 生成されるHTML要素のIDに付加するプレフィックス。
        *   `$asCreate`: 新規作成モードかどうか。
    *   戻り値: `Illuminate\Support\HtmlString` - 生成されたHTML文字列。

*   **`setHighlightKeywords(array|string $keywords): self`**:
    *   目的・機能: 表示するHTML内でハイライト表示するキーワードを設定します。検索結果の強調表示などに利用されます。
    *   引数:
        *   `$keywords`: ハイライトするキーワード（文字列または文字列の配列）。
    *   戻り値: `self` - チェーンメソッドのために自身のインスタンスを返します。

*   **`setAttachments(array|string $attachments): self`**:
    *   目的・機能: ファイルタイプのカラム表示時に使用する添付ファイル情報（パス、メタデータなど）を設定します。
    *   引数:
        *   `$attachments`: 添付ファイル情報の配列または文字列。
    *   戻り値: `self` - チェーンメソッドのために自身のインスタンスを返します。

*   **`setAttachmentContents(array $contents): self`**:
    *   目的・機能: 添付ファイルの内容（テキスト抽出結果など）を設定します。ファイルの内容検索結果の表示などに利用されます。
    *   引数:
        *   `$contents`: 添付ファイルの内容の配列。
    *   戻り値: `self` - チェーンメソッドのために自身のインスタンスを返します。

## 依存する他のクラスや設定

*   **モデル**:
    *   `App\Models\ColumnDefine`
*   **ファサード**:
    *   `Illuminate\Support\Facades\Storage`
*   **その他**:
    *   `Illuminate\Support\HtmlString`

## その他

*   `number` 型のカラムの場合、値に単位 (`unit`) を付加して表示します。
*   `files` 型のカラムの場合、添付ファイルのサムネイルやリンクを生成します。
*   `chk` (チェックボックス) や `select` (セレクトボックス) など、配列で値を保持するカラムタイプの場合、値をバッジ形式で表示します。
