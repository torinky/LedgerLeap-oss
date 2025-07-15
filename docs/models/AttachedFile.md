# AttachedFileモデル

## モデルの目的
台帳レコードに添付された個々のファイルに関するメタデータを管理します。ファイルの物理的な情報（パス、MIMEタイプ、サイズ）に加え、非同期で行われるテキスト抽出やOCR処理の状態、および抽出されたテキストコンテンツそのものを保持します。

## 関連テーブル
`attached_files` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `ledger_id`: 関連する台帳レコードのID
    *   `ledger_define_id`: 関連する台帳定義のID
    *   `column_id`: 関連する台帳定義内のカラムID
    *   `filename`: オリジナルのファイル名
    *   `hashedbasename`: ハッシュ化されたファイル名（拡張子なし）
    *   `status`: ファイルの処理状態 (`App\Enums\AttachedFileStatus`)
    *   `mime`: ファイルのMIMEタイプ
    *   `path`: ストレージ内の物理パス
    *   `size`: ファイルサイズ（バイト）
    *   `content`: TikaやOCRによって抽出されたテキストコンテンツ
    *   `contain_content`: テキストコンテンツが含まれているかどうかのフラグ
    *   `optimized`: OCR処理により最適化されたかどうかのフラグ
    *   `original_file_path`: OCR処理前のオリジナルファイルのパス
    *   `original_mime_type`: オリジナルファイルのMIMEタイプ
    *   `creator_id`: 作成者のユーザーID
    *   `modifier_id`: 更新者のユーザーID
*   **`$casts`**:
    *   `status`: `App\Enums\AttachedFileStatus::class`
    *   `contain_content`: `boolean`
    *   `optimized`: `boolean`

## リレーションシップ

*   **`ledger()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\Ledger`
    *   説明: この添付ファイルが属する台帳レコード。
*   **`creator()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: このファイルレコードを作成したユーザー。
*   **`modifier()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\User`
    *   説明: このファイルレコードを最後に更新したユーザー。

## 関連するEnum

*   **`App\Enums\AttachedFileStatus`**:
    *   説明: ファイルの非同期処理における状態（例: `PENDING_INITIAL_PROCESSING`, `PENDING_OCR`, `COMPLETED`, `TIKA_FAILED`, `OCR_FAILED`）を定義します。

## 主要なスコープやメソッド

*   **`getOriginalFilenameAttribute()` (アクセサ)**:
    *   説明: 関連する `Ledger` レコードの `content` カラムに保存されているJSONから、アップロード時のオリジナルファイル名を取得します。
*   **`getThumbnailPathAttribute()` (アクセサ)**:
    *   説明: 添付ファイルのサムネイル画像のパスを返します。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

## その他

*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `booted()` メソッド内で、モデルの `created` イベント時に `ProcessAttachedFile` ジョブをディスパッチし、非同期でのテキスト抽出処理を開始します。
*   `SoftDeletes` トレイトを利用しており、論理削除に対応しています。
