# AttachedFileモデル

**最終更新:** 2026年1月3日  
**Phase 1-5実装完了:** 添付ファイル機能統合（2025年12月-2026年1月）

## 1. モデルの目的

台帳レコードに添付された個々のファイルのメタデータと処理状態を管理します。Phase 1-5（2025年12月-2026年1月）でVLM/OCR/Tika統合に伴い大幅に拡張されました。

**主な責務:**
- ファイルの物理的情報（パス、MIMEタイプ、サイズ）の管理
- VLM/OCR/Tika処理の状態管理
- 抽出されたテキストコンテンツの保持
- 処理履歴とタイムラインの提供

**関連テーブル:** `attached_files`

**記載範囲:**
- モデルの属性とリレーション
- 主要メソッドの仕様
- Phase 1-5で追加された機能

**記載しない内容:**
- データベーススキーマ詳細 → `docs/database/schema.md`
- ユーザー向け機能説明 → `docs/function/Attachment.md`
- 非同期処理詳細 → `docs/architecture/QueueProcessing.md`

## 2. 主要な属性

### 2.1. 基本情報

| 属性 | 型 | 説明 |
|------|-----|------|
| `id` | int | プライマリキー |
| `ledger_id` | int | 関連する台帳レコードのID |
| `ledger_define_id` | int | 関連する台帳定義のID |
| `column_id` | int | 関連するカラムID |
| `tenant_id` | string | テナントID（マルチテナント対応） |
| `filename` | string | 元のファイル名 |
| `hashedbasename` | string | ハッシュ化されたファイル名（ストレージ保存用） |
| `mime` | string | 現在のMIMEタイプ |
| `original_mime_type` | string | 元のMIMEタイプ |
| `path` | string | ストレージ内の物理パス |
| `size` | bigint | ファイルサイズ（バイト） |
| `status` | Enum | 処理ステータス（`AttachedFileStatus`） |

### 2.2. VLM処理関連（Phase 2追加）

| 属性 | 型 | 説明 |
|------|-----|------|
| `vlm_markdown` | longtext | VLM抽出結果（Markdown形式、RAG統合用） |
| `vlm_structured_data` | json | VLM構造化データ（エンティティ、テーブル等） |
| `vlm_model` | varchar(100) | 使用VLMモデル名（例: PaddleOCR-VL-0.9B） |
| `vlm_confidence` | decimal(5,4) | VLM信頼度スコア（0.0000-1.0000） |
| `vlm_processing_time_ms` | int unsigned | VLM処理時間（ミリ秒） |
| `vlm_processed_at` | timestamp | VLM処理完了日時 |
| `vlm_failed_at` | timestamp | VLM処理失敗日時 |

### 2.3. OCR/Tika処理関連（Phase 3追加）

| 属性 | 型 | 説明 |
|------|-----|------|
| `ocr_processed_at` | timestamp | OCR処理完了日時 |
| `ocr_failed_at` | timestamp | OCR処理失敗日時 |
| `tika_processed_at` | timestamp | Tika処理完了日時 |
| `optimized` | boolean | PDF最適化フラグ |
| `original_file_path` | string | OCR処理前のオリジナルファイルパス |

### 2.4. 最終化処理関連（Phase 3追加）

| 属性 | 型 | 説明 |
|------|-----|------|
| `processing_finalized_at` | timestamp | 最終化処理完了日時 |
| `finalized_source` | varchar(20) | 採用されたテキストソース（'vlm' \| 'ocr' \| 'tika'） |
| `content` | longtext | 最終化後の採用テキスト（Mroonga全文検索対象） |

**エンジン選択優先順位:** VLM（最優先） > OCR（次点） > Tika（フォールバック）

### 2.5. その他

| 属性 | 型 | 説明 |
|------|-----|------|
| `contain_content` | boolean | テキストコンテンツ存在フラグ |
| `error_message` | text | エラーメッセージ |
| `creator_id` | int | 作成者のユーザーID |
| `modifier_id` | int | 更新者のユーザーID |
| `created_at` | timestamp | 作成日時 |
| `updated_at` | timestamp | 更新日時 |
| `deleted_at` | timestamp | 削除日時（論理削除） |

## 3. リレーションシップ

### 3.1. BelongsTo

| メソッド | 相手モデル | 説明 |
|---------|-----------|------|
| `ledger()` | `Ledger` | この添付ファイルが属する台帳レコード |
| `creator()` | `User` | このファイルレコードを作成したユーザー |
| `modifier()` | `User` | このファイルレコードを最後に更新したユーザー |

**重要:** `getPreviewableText()`や`getOcrTikaFormattedText()`を使用する際は、必ず`ledger`リレーションをEager Loadingしてください。

```php
// ✅ 正しい
$attachments = AttachedFile::with('ledger')->get();

// ❌ N+1クエリが発生
$attachments = AttachedFile::all();
```

## 4. 主要メソッド

### 4.1. アクセサ

#### getOriginalFilenameAttribute()
```php
public function getOriginalFilenameAttribute(): ?string
```
**説明:** 関連する`Ledger`レコードの`content`から、アップロード時のオリジナルファイル名を取得します。

#### getTikaMetadataAttribute()
```php
public function getTikaMetadataAttribute(): array
```
**説明:** Tikaから抽出されたメタデータを取得します。

**戻り値:** メタデータの連想配列

#### getMetadataDateAttribute()
```php
public function getMetadataDateAttribute(): ?\Carbon\Carbon
```
**説明:** メタデータから作成日時を取得します。Tikaの一般的な日付キー（`dcterms:created`、`Creation-Date`等）をチェックします。

### 4.2. VLM/OCR処理判定メソッド（Phase 3追加）

#### hasVlmResult()
```php
public function hasVlmResult(): bool
```
**説明:** VLM抽出結果が存在するかを判定します。

**判定条件:** `vlm_processed_at`が設定されており、`vlm_markdown`が存在する

#### isVlmProcessing()
```php
public function isVlmProcessing(): bool
```
**説明:** VLM処理が進行中かを判定します。

**判定条件:** `vlm_processed_at`と`vlm_failed_at`が両方とも未設定

#### isVlmFailed()
```php
public function isVlmFailed(): bool
```
**説明:** VLM処理が失敗したかを判定します。

**判定条件:** `vlm_failed_at`が設定されている

### 4.3. プレビュー機能関連メソッド（Phase 4追加）

#### hasPreviewableText()
```php
public function hasPreviewableText(): bool
```
**説明:** プレビュー可能なテキストが存在するかを判定します。

**判定条件:**
- 最終化処理が完了している（`processing_finalized_at`が設定）
- `finalized_source`が設定されている
- VLMの場合: `vlm_markdown`が存在
- OCR/Tikaの場合: `ledger.content_attached`に該当テキストが存在

**重要:** OCR/Tikaの判定には`ledger`リレーションのEager Loadingが必要です。

#### getPreviewableTextAttribute()
```php
public function getPreviewableTextAttribute(): ?string
```
**説明:** プレビュー用のテキストを取得します（アクセサ）。

**戻り値:**
- VLMの場合: Markdown形式のテキスト
- OCR/Tikaの場合: コードブロック（```）で囲まれたテキスト
- 存在しない場合: `null`

**使用例:**
```php
$attachments = AttachedFile::with('ledger')->get();
foreach ($attachments as $attachment) {
    if ($attachment->hasPreviewableText()) {
        $text = $attachment->previewable_text; // アクセサ使用
    }
}
```

#### getOcrTikaFormattedText()
```php
public function getOcrTikaFormattedText(?string $source = null): ?string
```
**説明:** OCRまたはTikaの抽出テキストを取得します。

**引数:**
- `$source` (string|null): 指定のソース（'ocr' または 'tika'）。省略時は`finalized_source`を使用

**戻り値:** コードブロック形式のテキスト、または`null`

**重要な実装詳細:**
- OCRの場合、画像ファイルは`.pdf`キーをチェック（例: `image.jpg` → `image.pdf`）
- `ledger`リレーションのEager Loadingが必須（N+1防止）
- `content_attached`へのアクセスには直接配列アクセスを使用（`data_get()`は非対応）

#### getConfidenceBadgeInfo()
```php
public function getConfidenceBadgeInfo(): ?array
```
**説明:** プレビューモーダルに表示する品質バッジ情報を生成します。

**戻り値構造:**
```php
[
    'label' => 'VLM (高精度AI)',      // 抽出方法のラベル
    'color' => 'success',              // バッジの色
    'score' => '95.0%',                // 信頼度スコア（VLMのみ）
    'tooltip' => '高精度なVLM抽出結果です',  // ツールチップテキスト
]
```

**色分けルール（VLM）:**
- `success`（緑）: 信頼度 ≥ 70%
- `warning`（黄）: 50% ≤ 信頼度 < 70%
- `error`（赤）: 信頼度 < 50%

**翻訳対応:** 全てのラベルとツールチップは`lang/ja/ledger.php`の翻訳キーを使用

### 4.4. 処理履歴メソッド（Phase 4追加）

#### getProcessingTimelineAttribute()
```php
public function getProcessingTimelineAttribute(): array
```
**説明:** 処理履歴をタイムライン形式で取得します（アクセサ）。

**戻り値:** タイムラインステップの配列（各ステップには`label`、`timestamp`、`status`、`icon`が含まれる）

## 5. Enum

### AttachedFileStatus

**ファイル:** `app/Enums/AttachedFileStatus.php`

**Phase 3で拡張された処理ステータス:**

| 値 | 説明 |
|----|------|
| `PENDING` | 処理待ち（初期状態） |
| `TIKA_PROCESSING` | Tika処理中 |
| `VLM_PROCESSING` | VLM処理中 |
| `OCR_PROCESSING` | OCR処理中 |
| `COMPLETED` | 全処理完了 |
| `TIKA_FAILED` | Tika処理失敗 |
| `VLM_FAILED` | VLM処理失敗 |
| `OCR_FAILED` | OCR処理失敗 |

## 6. トレイト

- **`HasFactory`**: ファクトリパターン対応
- **`SoftDeletes`**: 論理削除対応
- **`BelongsToTenant`**: マルチテナント対応（stancl/tenancy）

## 7. 重要な実装注意点

### 7.1. AsColumnArrayJsonキャストの制約

`ledger.content_attached`へのアクセスには、Laravelの`data_get()`ヘルパーが使用できません（Phase 6で判明）。

```php
// ❌ 動作しない
$text = data_get($ledger->content_attached, "$columnId.$filename.meta.content");

// ✅ 正しい方法
$text = $ledger->content_attached[$columnId][$filename]['meta']['content'] ?? null;
```

**理由:** `AsColumnArrayJson`キャストは内部でシリアライゼーション（`___serialized___`プレフィックス）を使用しているため

### 7.2. Eager Loadingの必須化

`getPreviewableText()`や`getOcrTikaFormattedText()`を使用する際は、N+1クエリを防ぐため、必ず`ledger`リレーションをEager Loadingしてください。

```php
// ✅ 推奨
$attachments = AttachedFile::with('ledger')->where(...)->get();

// ❌ N+1クエリが発生
$attachments = AttachedFile::where(...)->get();
foreach ($attachments as $attachment) {
    $text = $attachment->previewable_text; // 各ループでLedgerクエリが発行
}
```

### 7.3. OCRファイルのキー変換

OCR処理後、画像ファイルのキーは`.pdf`に変換されます。

```php
// 元のファイル: image.jpg
// OCR後のキー: image.pdf

// 実装例（getOcrTikaFormattedText）
$pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME) . '.pdf';
$text = $ledger->content_attached[$columnId][$pdfHashedbasename]['meta']['content'] ?? null;
```

## 8. 関連ドキュメント

### データベース
- **[データベーススキーマ](../database/schema.md)** - `attached_files`テーブルの詳細

### アーキテクチャ
- **[VLM-OCR技術選定](../architecture/vlm-ocr-technology-selection.md)** - 技術選定理由と実測ベンチマーク
- **[非同期処理](../architecture/QueueProcessing.md)** - ジョブフローとエラーハンドリング

### 機能仕様
- **[添付ファイル機能](../function/Attachment.md)** - ユーザー向け機能説明

### 開発ガイド
- **[VLM/OCR開発者ガイド](../development/vlm-ocr.md)** - VLM/OCR機能の実装ガイド
- **[テストのベストプラクティス](../development/Testing-Best-Practices.md)** - テストの書き方
