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
    *   `path`: ストレージ内の物理パス。OCR処理対象のファイルの場合、OCR処理後に生成された最適化済みPDFのパスが格納されます。
    *   `size`: ファイルサイズ（バイト）
    *   `content`: TikaやOCRによって抽出されたテキストコンテンツ。主にMroonga全文検索に使用されます。VLMが有効な場合は、`vlm_markdown`が優先的にRAGに使用されます。
    *   `contain_content`: テキストコンテンツが含まれているかどうかのフラグ
    *   `optimized`: OCR処理により最適化されたかどうかのフラグ
    *   `original_file_path`: OCR処理前のオリジナルファイルのパス。OCR処理対象のファイルの場合、`storage/app/public/Ledger/Attachments/Originals/` ディレクトリに退避されたオリジナルファイルのパスが格納されます。
    *   `original_mime_type`: オリジナルファイルのMIMEタイプ
    *   `vlm_markdown`: VLMによって抽出されたMarkdown形式のテキスト
    *   `vlm_structured_data`: VLMによって抽出された構造化データ（JSON形式）
    *   `vlm_model`: 抽出に使用されたVLMモデル名
    *   `vlm_confidence`: VLMの処理信頼度
    *   `vlm_processing_time_ms`: VLMの処理時間（ミリ秒）
    *   `vlm_processed_at`: VLMの処理完了日時
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
    *   説明: 添付ファイルのサムネイル画像のパスを返します。サムネイルは、`AttachedFileDownloadController` を経由して `/files/{id}/download?thumbnail=true` の形式で提供されます。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。

### VLM/OCR処理関連メソッド（Phase4-5で追加）

*   **`hasVlmResult(): bool`**:
    *   説明: VLM抽出結果が存在するかを判定します。
*   **`isVlmProcessing(): bool`**:
    *   説明: VLM処理が進行中かを判定します。
*   **`isVlmFailed(): bool`**:
    *   説明: VLM処理が失敗したかを判定します。

### プレビュー機能関連メソッド（Phase6で追加）

*   **`hasPreviewableText(): bool`**:
    *   説明: プレビュー可能なテキストが存在するかを判定します。VLMの場合は`vlm_markdown`の存在、OCR/Tikaの場合は`ledger.content_attached`の存在を確認します。
    *   **重要:** OCR/Tikaの場合、`ledger`リレーションがEager Loadingされている必要があります。
*   **`getPreviewableText(): ?string`**:
    *   説明: プレビュー用のテキストを取得します。VLMの場合はMarkdown形式、OCR/Tikaの場合はコードブロック形式で返します。
    *   戻り値: プレビュー用テキスト（存在しない場合は`null`）
    *   **使用例:**
        ```php
        // VLM/OCR/Tikaが混在する場合
        $attachments = AttachedFile::with('ledger')->get();
        foreach ($attachments as $attachment) {
            if ($attachment->hasPreviewableText()) {
                $text = $attachment->getPreviewableText();
            }
        }
        ```
*   **`getConfidenceBadgeInfo(): ?array`**:
    *   説明: プレビューモーダルに表示する品質バッジ情報を生成します。
    *   戻り値構造:
        ```php
        [
            'label' => 'VLM (高精度AI)',      // 抽出方法のラベル
            'color' => 'success',              // バッジの色（success/warning/error/info）
            'score' => '95.0%',                // 信頼度スコア（VLMのみ）
            'tooltip' => '高精度なVLM抽出結果です',  // ツールチップテキスト
        ]
        ```
    *   **色分けルール（VLM）:**
        - `success`（緑）: 信頼度 ≥ 70%
        - `warning`（黄）: 50% ≤ 信頼度 < 70%
        - `error`（赤）: 信頼度 < 50%
    *   **翻訳対応:** 全てのラベルとツールチップは`lang/ja/ledger.php`の翻訳キーを使用

**実装上の注意:**
- `getPreviewableText()`でOCR/Tikaテキストを取得する場合、必ず`with('ledger')`でEager Loadingを行ってください
- `content_attached`へのアクセスには`data_get()`は使用できません（`AsColumnArrayJson`のシリアライゼーション制約）
- 直接配列アクセス（`$ledger->content_attached[$column_id][$filename]['meta']['content']`）を使用してください

## その他

*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `booted()` メソッド内で、モデルの `created` イベント時に `ProcessAttachedFile` ジョブをディスパッチし、非同期でのテキスト抽出処理を開始します。
*   `SoftDeletes` トレイトを利用しており、論理削除に対応しています。
