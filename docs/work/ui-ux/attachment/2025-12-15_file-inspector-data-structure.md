# FileInspector データ構造設計書

**作成日:** 2025年12月15日  
**最終更新:** 2025年12月16日（未確定事項調査・再処理UI追加・VLM統合方針確定）  
**ステータス:** ✅ Phase 2実装準備完了  
**対象:** LedgerLeap開発チーム

**関連ドキュメント:**
- [親計画: 添付ファイルUI改善計画](/docs/work/ui-ux/attachment/2025-12-13_attachment-ui-improvement-plan.md) ⚠️ 更新済み
- [Phase 1 モックアップ評価報告書](/docs/work/ui-ux/attachment/2025-12-15_phase1_mockup_evaluation_report.md)
- [Phase 1 モックアップ再検証報告書](/docs/work/ui-ux/attachment/2025-12-15_phase1_reverification_report.md)
- [機能仕様書: 添付ファイル](/docs/function/Attachment.md) (要更新)

---

## 1. 目的

Phase 2（基盤改修・ドロワー実装）に進む前に、`FileInspector` Livewire コンポーネントに渡す必要がある変数を網羅的に整理し、既存実装からの流用可能性を評価します。

---

## 2. FileInspector コンポーネント 変数・データ構造一覧

### 2.1. コンポーネント基本プロパティ

| # | 変数名 | 型 | 用途 | 既存実装の流用 | 備考 |
|:--|:------|:---|:-----|:-------------|:-----|
| 1 | `$open` | `bool` | ドロワーの開閉状態 | ❌ 新規 | Alpine.js `@entangle` で制御 |
| 2 | `$fileId` | `?int` | 表示中のファイルID | ✅ `Show.php` の `$previewingFileId` | イベント受信時に設定 |
| 3 | `$file` | `?AttachedFile` | ファイルモデルインスタンス | ✅ `Show.php` の `previewingFile()` Computed | Eager Loading最適化必須 |
| 4 | `$selectedTab` | `string` | 選択中のタブ | ❌ 新規 | `'content'`, `'details'`, `'permissions'`, `'history'` |

### 2.2. ファイル基本情報（Detailsタブ）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 5 | `original_filename` | `?string` | `$file->original_filename` | ✅ モデルアクセサ | `content` から取得 |
| 6 | `size` | `int` | `$file->size` | ✅ DBカラム | バイト単位、KB/MB変換必要 |
| 7 | `mime` | `?string` | `$file->original_mime_type ?? $file->mime` | ✅ DBカラム | 元のMIMEタイプ優先 |
| 8 | `created_at` | `?Carbon` | `$file->created_at` | ✅ モデル標準 | アップロード日時 |
| 9 | `creator` | `?User` | `$file->creator` | ⚠️ リレーション未実装 | **要実装** (BelongsTo) |
| 10 | `modifier` | `?User` | `$file->modifier` | ⚠️ リレーション未実装 | **要実装** (BelongsTo) |
| 11 | `hashedbasename` | `string` | `$file->hashedbasename` | ✅ DBカラム | 内部ファイル名 |
| 12 | `path` | `?string` | `$file->path` | ✅ DBカラム | ストレージパス |

### 2.3. 処理ステータス・状態（Contentタブ）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 13 | `status` | `AttachedFileStatus` | `$file->getDisplayStatus()` | ✅ モデルメソッド | Phase5対応の表示用ステータス |
| 14 | `processing_finalized_at` | `?Carbon` | `$file->processing_finalized_at` | ✅ DBカラム | 最終化日時 |
| 15 | `finalized_source` | `?string` | `$file->finalized_source` | ✅ DBカラム | `'vlm'`, `'ocr'`, `'tika'` |
| 16 | `contain_content` | `bool` | `$file->contain_content` | ✅ DBカラム | テキスト抽出成功フラグ |
| 17 | `hasPreviewableText()` | `bool` | `$file->hasPreviewableText()` | ✅ モデルメソッド | プレビュー可否判定 |
| 18 | `hasExtractionError()` | `bool` | `$file->hasExtractionError()` | ✅ モデルメソッド | エラー判定 |

### 2.4. VLM関連情報（Contentタブ）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 19 | `vlm_markdown` | `?string` | `$file->vlm_markdown` | ✅ DBカラム | VLM抽出テキスト（Markdown形式） |
| 20 | `vlm_structured_data` | `?array` | `$file->vlm_structured_data` | ✅ DBカラム | VLM構造化データ（JSON） |
| 21 | `vlm_confidence` | `?float` | `$file->vlm_confidence` | ✅ DBカラム | VLM信頼度スコア（0.0-1.0） |
| 22 | `vlm_confidence_formatted` | `?string` | `$file->VlmConfidenceFormatted` | ✅ モデルアクセサ | 例: `"85.0%"` |
| 23 | `vlm_model` | `?string` | `$file->vlm_model` | ✅ DBカラム | 使用VLMモデル名 |
| 24 | `vlm_processing_time_ms` | `?int` | `$file->vlm_processing_time_ms` | ✅ DBカラム | VLM処理時間（ミリ秒） |
| 25 | `vlm_processed_at` | `?Carbon` | `$file->vlm_processed_at` | ✅ DBカラム | VLM処理完了日時 |
| 26 | `vlm_failed_at` | `?Carbon` | `$file->vlm_failed_at` | ✅ DBカラム | VLM処理失敗日時 |
| 27 | `hasVlmResult()` | `bool` | `$file->hasVlmResult()` | ✅ モデルメソッド | VLM結果存在判定 |

### 2.5. OCR/Tika関連情報（Contentタブ）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 28 | `tika_processed_at` | `?Carbon` | `$file->tika_processed_at` | ✅ DBカラム | Tika処理完了日時 |
| 29 | `ocr_processed_at` | `?Carbon` | `$file->ocr_processed_at` | ✅ DBカラム | OCR処理完了日時 |
| 30 | `ocr_failed_at` | `?Carbon` | `$file->ocr_failed_at` | ✅ DBカラム | OCR処理失敗日時 |
| 31 | `previewable_text` | `?string` | `$file->previewable_text` | ✅ モデルアクセサ | 統合テキストプレビュー |
| 32 | `previewable_text_raw` | `?string` | - | ⚠️ 部分流用 | OCR/Tikaのコードブロック除去版 |

### 2.6. 処理履歴・ログ（Historyタブ）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 33 | `processing_status` | `string` | `$file->processing_status` | ✅ モデルアクセサ | `'finalized'`, `'ready_for_finalization'`, etc. |
| 34 | `activities` | `Collection` | `$file->activities()` | ⚠️ リレーション未実装 | **要実装** (MorphMany) |
| 35 | `processing_timeline` | `array` | - | ❌ 新規計算 | タイムライン表示用データ構造 |

### 2.7. 権限・操作可否（Permissionsタブ & Actions）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 36 | `canDownload` | `bool` | `Gate::allows('view', ...)` | ✅ ポリシー | 台帳の `view` 権限に依存 |
| 37 | `canDelete` | `bool` | `Gate::allows('delete', ...)` | ✅ ポリシー | 台帳の `delete` 権限に依存 |
| 38 | `canUserRequestRetry` | `bool` | `$file->canUserRequestRetry()` | ✅ モデルメソッド | 一般ユーザーの再処理権限 |
| 39 | `canAdminRetry` | `bool` | `$file->canAdminRetry()` | ✅ モデルメソッド | 管理者の再処理権限 |
| 40 | `hasExtractionError` | `bool` | `$file->hasExtractionError()` | ✅ モデルメソッド | テキスト抽出失敗判定 |
| **41** | **folder** | **?Folder** | **`$file->ledger->define->folder`** | ✅ **既存（修正）** | **LedgerDefine経由でアクセス** |
| **42** | **ledger_permissions** | **array** | **`$permissionService->getUserPermissions(auth()->user(), $file->ledger->define)`** | ⚠️ **要確認** | **LedgerDefine経由で取得** |

### 2.8. プレビュー・ダウンロードURL

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 43 | `download_url` | `string` | `route('file.download', ...)` | ✅ ルート定義 | ダイレクトダウンロードリンク（最適化版） |
| 44 | `vlm_markdown_download_url` | `?string` | `route('files.download-vlm', ['format' => 'markdown'])` | ✅ VLMダウンロードルート | VLM結果専用 |
| 45 | `vlm_json_download_url` | `?string` | `route('files.download-vlm', ['format' => 'json'])` | ✅ VLMダウンロードルート | VLM結果専用 |
| **46** | **`original_file_url`** | **`string`** | **`route('file.download', ['original' => true])`** | ✅ **既存実装** | **元ファイルダウンロード（OCR前）** |
| 47 | `thumbnail_url` | `?string` | `route('file.download', ['thumbnail' => true])` | ✅ 既存実装 | サムネイル表示用URL |

### 2.9. 信頼度バッジ情報（UI表示用）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 48 | `confidence_badge` | `?array` | `$file->getConfidenceBadgeInfo()` | ✅ モデルメソッド | `['label', 'color', 'score', 'tooltip']` |

### 2.10. 既存VLMモーダルからの流用機能

| # | 機能 | 既存実装 | 流用可能性 | FileInspectorでの実装 |
|:--|:-----|:---------|:----------|:---------------------|
| 49 | **Markdownレンダリング** | `show.blade.php` L175-180 | ✅ 完全流用 | `Str::markdown($file->vlm_markdown)` |
| 50 | **コピー機能（クリップボード）** | `show.blade.php` L184-229 | ✅ 完全流用 | Alpine.js `copyToClipboard()` 関数 |
| 51 | **フォールバックコピー** | `show.blade.php` L215-228 | ✅ 完全流用 | `document.execCommand('copy')` |
| 52 | **コピー状態表示** | `show.blade.php` L183 | ✅ 完全流用 | `copied` 変数 + アイコン切替 |
| 53 | **Markdownダウンロード** | `show.blade.php` L244 | ✅ 完全流用 | `route('files.download-vlm', ['format' => 'markdown'])` |
| 54 | **JSONダウンロード** | `show.blade.php` L248 | ✅ 完全流用 | `route('files.download-vlm', ['format' => 'json'])` |
| 55 | **信頼度表示** | `show.blade.php` L154-168 | ✅ 完全流用 | 条件分岐 + アイコン表示 |

### 2.11. Livewireアクションメソッド（新規実装）

| # | メソッド名 | 戻り値 | 用途 | 実装元 | 流用可能性 | 備考 |
|:--|:----------|:------|:-----|:------|:---------|:-----|
| 56 | `openInspector(int $id, ?string $tab)` | `void` | ドロワー表示 | FileInspector.php | ❌ 新規 | Eager Loading最適化必須 |
| 57 | `close()` | `void` | ドロワー閉じる | FileInspector.php | ❌ 新規 | `$this->open = false` |
| 58 | `switchTab(string $tab)` | `void` | タブ切替 | FileInspector.php | ❌ 新規 | History遅延読み込み |
| 59 | `copyTextToClipboard()` | `void` | テキストコピー | Alpine.js | ✅ 完全流用 | `text-preview-modal.blade.php` L37-73 |
| 60 | `loadHistoryTab()` | `void` | History遅延読み込み | FileInspector.php | ❌ 新規 | パフォーマンス対策 |
| **61** | **`retryProcessing()`** | **`void`** | **全処理の再実行** | **Show.php L91-100** | ✅ **完全流用** | **Actionsタブで使用** |
| **62** | **`retryVlmProcessing()`** | **`void`** | **VLM処理のみ再実行** | **FileInspector.php** | ❌ **新規** | **管理者専用、Jobディスパッチ** |
| **63** | **`confirmDelete()`** | **`void`** | **削除確認ダイアログ** | **FileInspector.php** | ❌ **新規** | **Livewireイベント発行** |
| **64** | **`deleteFile()`** | **`void`** | **ファイル削除実行** | **FileInspector.php** | ❌ **新規** | **Soft Delete対応** |

**注:** OCR後PDFダウンロードは既存の `file.download` ルートで対応済みのため、専用メソッドは不要

---

## 3. データ取得戦略（Eager Loading最適化）

### 3.1. 必要なリレーション

```php
AttachedFile::with([
    'ledger:id,content,content_attached,ledger_define_id', // ✅ ledger_define_id追加
    'ledger.define:id,folder_id,title',                    // ✅ LedgerDefine経由
    'ledger.define.folder:id,title,path',                  // ✅ Folder取得（修正）
    'creator:id,name',                                     // アップロード者名
    'modifier:id,name',                                    // 更新者名
    'activities.causer:id,name',                           // 処理履歴のユーザー
])->findOrFail($fileId);
```

### 3.2. N+1問題防止チェックリスト

- ✅ `$file->original_filename` → `ledger.content` Eager Loading必須
- ✅ `$file->previewable_text` → `ledger.content_attached` Eager Loading必須
- ✅ `$file->ledger->define->folder` → **修正済み（LedgerDefine経由）**
- ⚠️ `$file->creator` → リレーション未実装（要追加）
- ⚠️ `$file->modifier` → リレーション未実装（要追加）
- ⚠️ `$file->activities()` → リレーション未実装（要追加）

### 3.3. 新規実装が必要なリレーション

#### 3.3.1. creator / modifier リレーション

**ファイル:** `app/Models/AttachedFile.php`

```php
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'creator_id');
}

public function modifier(): BelongsTo
{
    return $this->belongsTo(User::class, 'modifier_id');
}
```

#### 3.3.2. activities リレーション（Spatie ActivityLog連携）

**ファイル:** `app/Models/AttachedFile.php`

```php
use Spatie\Activitylog\Models\Activity;

public function activities(): MorphMany
{
    return $this->morphMany(Activity::class, 'subject')
        ->orderBy('created_at', 'desc');
}
```

---

## 4. 処理履歴タイムラインの設計

### 4.1. タイムラインデータ構造

```php
[
    [
        'step' => 'upload',
        'label' => 'ファイルアップロード',
        'timestamp' => Carbon,
        'status' => 'completed', // completed, failed, processing
        'icon' => 'fa-upload',
        'color' => 'success',
        'user' => User,
        'duration_ms' => null,
    ],
    [
        'step' => 'tika',
        'label' => 'Tika処理',
        'timestamp' => Carbon,
        'status' => 'completed',
        'icon' => 'fa-file-text',
        'color' => 'success',
        'user' => null, // システム処理
        'duration_ms' => 1523,
    ],
    [
        'step' => 'vlm',
        'label' => 'VLM解析',
        'timestamp' => Carbon,
        'status' => 'completed',
        'icon' => 'fa-robot',
        'color' => 'success',
        'user' => null,
        'duration_ms' => 4821,
        'details' => [
            'model' => 'gpt-4o-mini',
            'confidence' => 0.92,
        ],
    ],
    [
        'step' => 'ocr',
        'label' => 'OCR処理',
        'timestamp' => Carbon,
        'status' => 'completed',
        'icon' => 'fa-text-width',
        'color' => 'success',
        'user' => null,
        'duration_ms' => 3210,
    ],
    [
        'step' => 'finalization',
        'label' => '最終化',
        'timestamp' => Carbon,
        'status' => 'completed',
        'icon' => 'fa-check-circle',
        'color' => 'success',
        'user' => null,
        'duration_ms' => 45,
        'details' => [
            'selected_source' => 'vlm',
        ],
    ],
]
```

### 4.2. タイムライン生成ロジック（新規実装）

**ファイル:** `app/Models/AttachedFile.php`

```php
public function getProcessingTimeline(): array
{
    $timeline = [];
    
    // 1. アップロード
    $timeline[] = [
        'step' => 'upload',
        'label' => __('file.timeline.upload'),
        'timestamp' => $this->created_at,
        'status' => 'completed',
        'icon' => 'fa-upload',
        'color' => 'success',
        'user' => $this->creator,
    ];
    
    // 2. Tika処理
    if ($this->tika_processed_at) {
        $timeline[] = [
            'step' => 'tika',
            'label' => __('file.timeline.tika'),
            'timestamp' => $this->tika_processed_at,
            'status' => 'completed',
            'icon' => 'fa-file-text',
            'color' => 'success',
        ];
    }
    
    // 3. VLM処理
    if ($this->vlm_processed_at) {
        $timeline[] = [
            'step' => 'vlm',
            'label' => __('file.timeline.vlm'),
            'timestamp' => $this->vlm_processed_at,
            'status' => 'completed',
            'icon' => 'fa-robot',
            'color' => 'success',
            'duration_ms' => $this->vlm_processing_time_ms,
            'details' => [
                'model' => $this->vlm_model,
                'confidence' => $this->vlm_confidence,
            ],
        ];
    } elseif ($this->vlm_failed_at) {
        $timeline[] = [
            'step' => 'vlm',
            'label' => __('file.timeline.vlm'),
            'timestamp' => $this->vlm_failed_at,
            'status' => 'failed',
            'icon' => 'fa-exclamation-triangle',
            'color' => 'error',
        ];
    }
    
    // 4. OCR処理
    if ($this->ocr_processed_at) {
        $timeline[] = [
            'step' => 'ocr',
            'label' => __('file.timeline.ocr'),
            'timestamp' => $this->ocr_processed_at,
            'status' => 'completed',
            'icon' => 'fa-text-width',
            'color' => 'success',
        ];
    } elseif ($this->ocr_failed_at) {
        $timeline[] = [
            'step' => 'ocr',
            'label' => __('file.timeline.ocr'),
            'timestamp' => $this->ocr_failed_at,
            'status' => 'failed',
            'icon' => 'fa-exclamation-triangle',
            'color' => 'error',
        ];
    }
    
    // 5. 最終化
    if ($this->processing_finalized_at) {
        $timeline[] = [
            'step' => 'finalization',
            'label' => __('file.timeline.finalization'),
            'timestamp' => $this->processing_finalized_at,
            'status' => 'completed',
            'icon' => 'fa-check-circle',
            'color' => 'success',
            'details' => [
                'selected_source' => $this->finalized_source,
            ],
        ];
    }
    
    return $timeline;
}
```

---

## 5. OCR後PDFダウンロード機能（既存実装の活用）

### 5.1. 既存実装の概要

**✅ 新規実装は不要。既存の `file.download` ルートで完全対応済み**

#### 5.1.1. ルート定義（既存）

**ファイル:** `routes/tenant.php` L110-111

```php
Route::get('/files/{attachedFile}/download', [\App\Http\Controllers\AttachedFileDownloadController::class, 'download'])
    ->middleware('auth')
    ->name('file.download');
```

**クエリパラメータ:**
- `?thumbnail=true`: サムネイル画像を取得
- `?original=true`: OCR処理前の元ファイルを取得
- パラメータなし: 最適化版（OCR処理後）を取得

#### 5.1.2. コントローラーロジック（既存）

**ファイル:** `app/Http/Controllers/AttachedFileDownloadController.php` L40-50

```php
elseif ($isOriginalRequest && $attachedFile->original_file_path) {
    // ?original=true の場合: 元ファイル
    $filePath = $attachedFile->original_file_path;
    $fileNameToServe = $attachedFile->original_filename ?? $attachedFile->filename;
} else {
    // 通常: 最適化版（OCR処理後）
    $filePath = $attachedFile->path;
    
    // 最適化PDFの場合、ファイル名を.pdfに自動変換
    if ($attachedFile->optimized && $attachedFile->mime === 'application/pdf') {
        $fileNameToServe = pathinfo($fileNameToServe, PATHINFO_FILENAME).'.pdf';
    }
}
```

**動作:**
- `$attachedFile->optimized = true` かつ `mime = 'application/pdf'` の場合
- 自動的にOCR最適化版PDFがダウンロードされる
- ファイル名も `.pdf` 拡張子に自動変換される

#### 5.1.3. ColumnHtmlService実装（既存）

**ファイル:** `app/Services/Ledger/ColumnHtmlService.php` L343-375

```php
$mainDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);
$originalDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id, 'original' => true]);
$optimizedPdfDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);

// 画像ファイルの場合: 元画像 + OCR後PDF
if (str_starts_with($attachment->original_mime_type, 'image/')) {
    $mainDownloadUrl = $originalDownloadUrl; // 元画像をメインに
    $auxiliaryLinksHtml = <<<HTML
     <a href="{$optimizedPdfDownloadUrl}" target="_blank" class="btn btn-square btn-ghost tooltip" 
 data-tip="{$downloadPdfTooltip}">
         <i class="fa-solid fa-file-pdf w-4 h-4"></i>
     </a>
HTML;
}

// 最適化PDFの場合: 最適化版 + 元PDF
elseif ($attachment->original_mime_type === 'application/pdf' && $attachment->optimized) {
    $mainDownloadUrl = $optimizedPdfDownloadUrl; // 最適化版をメインに
    $auxiliaryLinksHtml = <<<HTML
 <a href="{$originalDownloadUrl}" target="_blank" 
     class="btn btn-square btn-ghost tooltip" 
     data-tip="{$downloadPdfTooltip}">
         <i class="fa-solid fa-file w-4 h-4"></i>
     </a>
HTML;
}
```

### 5.2. FileInspectorでの使用方法

**既存ルートをそのまま使用:**

```blade
@php
    $ocrPdfUrl = route('file.download', [
        'tenant' => tenant()->id,
        'attachedFile' => $file->id
    ]);
    
    $originalFileUrl = route('file.download', [
        'tenant' => tenant()->id,
        'attachedFile' => $file->id,
        'original' => true
    ]);
@endphp

{{-- OCR最適化版PDFダウンロード --}}
<a href="{{ $ocrPdfUrl }}" class="btn btn-primary" download>
    <i class="fa-solid fa-download"></i>
    {{ __('ledger.file_inspector.ocr.download_pdf') }}
</a>

{{-- 元ファイルダウンロード --}}
<a href="{{ $originalFileUrl }}" class="btn btn-outline" download>
    <i class="fa-solid fa-file"></i>
    {{ __('ledger.file_inspector.ocr.download_original') }}
</a>
```

### 5.3. 実装不要の確認

| 項目 | ステータス | 備考 |
|:-----|:---------|:-----|
| ルート定義 | ✅ 実装済み | `file.download` |
| コントローラーメソッド | ✅ 実装済み | `AttachedFileDownloadController@download` |
| 最適化PDF自動判定 | ✅ 実装済み | `optimized` フラグで自動切替 |
| ファイル名自動変換 | ✅ 実装済み | `.pdf` 拡張子に自動変換 |
| リスト表示UI | ✅ 実装済み | `ColumnHtmlService` で補助リンク生成 |
| FileInspector UI | ✅ モックアップ実装済み | `file-inspector.blade.php` |

**結論:** ✅ **Phase 2でOCR後PDFダウンロード機能の新規実装は不要**

---

## 6. 既存VLMモーダルとの統合戦略

### 6.1. 統合方針

**Option A（推奨）:** VLMプレビューモーダルを廃止し、FileInspectorに統合

- **メリット:** UI一貫性、コード重複排除
- **デメリット:** 既存の `showVlmPreviewEvent` イベントの変更必要
- **実装:** `showVlmPreviewEvent` を `open-file-inspector` に置き換え、Contentタブを自動選択

**Option B:** 両方を併存（暫定）

- **メリット:** 後方互換性維持
- **デメリット:** コード重複、UX不統一
- **実装:** FileInspectorにもVLM専用表示を実装

### 6.2. 推奨実装（Option A）

#### 6.2.1. イベント名の統一

**変更箇所:** `app/Services/ColumnHtmlService.php` 等でVLMプレビューボタンを生成している箇所

```php
// Before
wire:click="$dispatch('showVlmPreviewEvent', { fileId: {{ $file->id }} })"

// After
wire:click="$dispatch('open-file-inspector', { id: {{ $file->id }}, tab: 'content' })"
```

#### 6.2.2. FileInspectorでのタブ自動選択

**ファイル:** `app/Livewire/AttachedFile/FileInspector.php`

```php
#[On('open-file-inspector')]
public function openInspector(int $id, ?string $tab = null): void
{
    $this->fileId = $id;
    $this->open = true;
    
    if ($tab) {
        $this->selectedTab = $tab;
    }
}
```

---

## 7. 多言語対応（翻訳キー追加）

### 7.1. 新規追加が必要な翻訳キー

**ファイル:** `lang/ja.json`

```json
{
    "file.timeline.upload": "ファイルアップロード",
    "file.timeline.tika": "Tika処理",
    "file.timeline.vlm": "VLM解析",
    "file.timeline.ocr": "OCR処理",
    "file.timeline.finalization": "最終化",
    "file.timeline.processing": "処理中",
    "file.timeline.completed": "完了",
    "file.timeline.failed": "失敗",
    "file.ocr.download_pdf": "OCR処理後のPDFをダウンロード",
    "file.ocr.download_optimized": "最適化版PDFをダウンロード",
    "file.permissions.can_download": "ダウンロード可能",
    "file.permissions.can_delete": "削除可能",
    "file.permissions.can_retry": "再処理可能",
    "file.permissions.readonly": "読み取り専用",
    
    "ledger.file_inspector.tabs.actions": "アクション",
    "ledger.file_inspector.actions.reprocess_title": "ファイルの再処理",
    "ledger.file_inspector.actions.reprocess_description": "OCRやVLM解析を再実行して、テキスト抽出の精度を改善します。",
    "ledger.file_inspector.actions.retry_all": "全処理を再実行",
    "ledger.file_inspector.actions.retry_vlm_only": "VLM解析のみ再実行",
    "ledger.file_inspector.actions.admin_retry_note": "管理者権限により、低信頼度ファイルの再処理が可能です。",
    "ledger.file_inspector.actions.delete_title": "ファイルの削除",
    "ledger.file_inspector.actions.delete_description": "このファイルを完全に削除します。この操作は取り消せません。",
    "ledger.file_inspector.actions.delete_file": "ファイルを削除",
    "ledger.file_inspector.actions.no_actions_available": "実行可能なアクションがありません。",
    
    "ledger.file_inspector.messages.retry_queued": "再処理リクエストを受け付けました。",
    "ledger.file_inspector.messages.vlm_retry_queued": "VLM解析の再実行を開始しました。",
    "ledger.file_inspector.messages.delete_confirmed": "ファイルを削除しました。",
    
    "ledger.file_inspector.errors.retry_not_allowed": "再処理の権限がありません。",
    "ledger.file_inspector.errors.retry_failed": "再処理の開始に失敗しました。",
    "ledger.file_inspector.errors.admin_only": "この操作は管理者のみ実行可能です。",
    "ledger.file_inspector.errors.vlm_retry_failed": "VLM解析の再実行に失敗しました。",
    "ledger.file_inspector.errors.delete_failed": "ファイルの削除に失敗しました。",
    
    "ledger.file_inspector.ocr.image_to_pdf_title": "OCR処理によりPDF化されたファイル",
    "ledger.file_inspector.ocr.image_to_pdf_desc": "画像ファイルをOCR処理し、検索可能なPDFに変換しました。",
    "ledger.file_inspector.ocr.optimized_pdf_title": "OCR最適化版PDF",
    "ledger.file_inspector.ocr.optimized_pdf_desc": "PDFファイルをOCR処理し、テキスト抽出を最適化しました。",
    "ledger.file_inspector.ocr.download_pdf": "PDFをダウンロード",
    "ledger.file_inspector.ocr.download_optimized": "最適化版をダウンロード",
    "ledger.file_inspector.ocr.converted_pdf": "OCR処理後のPDF"
}
```

---

## 8. 実装チェックリスト

### 8.1. モデル拡張

- [ ] `AttachedFile::creator()` リレーション追加
- [ ] `AttachedFile::modifier()` リレーション追加
- [ ] `AttachedFile::activities()` リレーション追加
- [ ] `AttachedFile::getProcessingTimeline()` メソッド追加

### 8.2. ルート・コントローラー

- [ ] `file.download-ocr-pdf` ルート追加（要確認）
- [ ] `AttachedFileController::downloadOcrPdf()` メソッド追加（必要に応じて）

### 8.3. Livewireコンポーネント

- [ ] `FileInspector` コンポーネント作成
- [ ] Eager Loading最適化（`with()` 句）
- [ ] イベント受信 (`#[On('open-file-inspector')]`)
- [ ] タブ自動選択機能
- [ ] VLMコピー機能の流用

### 8.4. View実装

- [ ] Contentタブ: VLM/OCR/Tikaテキスト表示
- [ ] Detailsタブ: ファイル基本情報表示
- [ ] Historyタブ: タイムライン表示
- [ ] Permissionsタブ: 権限情報表示
- [ ] Alpine.jsコピー機能の統合
- [ ] レスポンシブ対応確認

### 8.5. 既存コードの変更

- [ ] `showVlmPreviewEvent` → `open-file-inspector` 移行
- [ ] `Show.php` の `showVlmModal` 廃止（または共存）
- [ ] `ColumnHtmlService` のリファクタリング

### 8.6. テスト

- [ ] Feature Test: FileInspectorイベント連携
- [ ] Feature Test: 権限チェック
- [ ] Livewire Test: タブ切り替え
- [ ] Livewire Test: コピー機能
- [ ] N+1クエリ検証

---

## 9. 未確定事項の調査結果

### 9.1. OCR後PDFダウンロード機能の実装状況

**調査結果:** ✅ **完全実装済み（バックエンド・フロントエンド両方）**

**既存実装の詳細調査により、以下が確認されました：**

#### フロントエンド実装（Phase 1モックアップ）
- **UI実装:** `file-inspector.blade.php` L234-263にOCR処理後のPDFダウンロードUIが存在
- **表示条件:**
  - 画像ファイル（`image/*`）: "OCR処理によりPDF化されたファイル" として表示
  - PDFファイル（`application/pdf` + `optimized=true`）: "OCR最適化版PDF" として表示

#### バックエンド実装（既存機能）
- **ルート:** `file.download` ルート（`/files/{attachedFile}/download`）が既に存在
- **コントローラー:** `AttachedFileDownloadController@download` で実装済み
- **自動判定ロジック:** `AttachedFileDownloadController.php` L48-50で実装

```php
// 最適化PDFの場合、自動的にファイル名を.pdfに変更
if ($attachedFile->optimized && $attachedFile->mime === 'application/pdf') {
    $fileNameToServe = pathinfo($fileNameToServe, PATHINFO_FILENAME).'.pdf';
}
```

#### ColumnHtmlService実装（リスト表示）
- **L343-346:** 各種ダウンロードURLを生成
  - `$mainDownloadUrl`: メインダウンロードリンク
  - `$originalDownloadUrl`: 元ファイルダウンロード（`?original=true`）
  - `$optimizedPdfDownloadUrl`: 最適化PDF（通常のダウンロード）
  
- **L352-375:** ファイルタイプ別の表示ロジック
  - **画像ファイル（L352-359）:** 
    - メイン: 元画像ダウンロード
    - 補助: OCR後PDFダウンロードボタン（`fa-file-pdf` アイコン）
  - **最適化PDF（L360-369）:**
    - メイン: 最適化版PDFダウンロード
    - 補助: 元PDFダウンロードボタン（`fa-file` アイコン）

**結論:** ✅ **新規実装は不要。既存機能で完全にカバーされている**

FileInspectorでは、既存の `file.download` ルートを使用するだけで、OCR最適化後PDFのダウンロードが可能です。

### 9.2. 再処理（Retry）UIの実装状況

**調査結果:** ⚠️ **ドロワーに再処理UIが存在しない**

現在の実装状況：

- **既存:** `ColumnHtmlService.php` L303でリストビューに再処理ボタンを生成
- **イベント:** `retryProcessingEvent` と `retryVlmProcessingEvent` の2種類
- **権限判定:** `canUserRequestRetry()`, `canAdminRetry()` メソッド（AttachedFile.php L255-266）
- **ドロワー:** `file-inspector.blade.php` に再処理UIが**未実装**

**追加実装が必要な項目:**

1. **Actionsタブの追加** → 再処理、削除などの破壊的操作を集約
2. **再処理ボタンの実装** → 権限に応じた表示制御
3. **再処理の種類:**
   - 全処理の再実行（`retryProcessingEvent`）
   - VLM処理のみ再実行（`retryVlmProcessingEvent`）

### 9.3. Folderモデルのリレーション構造（重大な設計誤り）

**指摘内容:** `Ledger` モデルは `Folder` モデルに直接紐づかず、`LedgerDefine` モデルを経由する

**調査結果:** ✅ **指摘は正しい。ドキュメントの修正が必要**

```php
// app/Models/Ledger.php
// ❌ 誤: $ledger->folder のような直接リレーションは存在しない

// app/Models/LedgerDefine.php L54-56
public function folder()
{
    return $this->belongsTo(Folder::class);
}

// ✅ 正しいアクセス方法
$folder = $ledger->define->folder;
```

**影響範囲:**

- セクション2.7（権限・操作可否）の `ledger_permissions` 取得方法が誤り
- セクション3.1（Eager Loading）の記述が誤り
- `file-inspector.blade.php` L51の表示ロジックも要確認

---

## 10. 懸念事項の検討結果

### 10.1. VLMダイアログとの機能重複

**懸念:** 既存の `showVlmModal` と FileInspector の機能が重複し、ユーザーの混乱を招く

**決定事項:** ✅ **既存VLMダイアログは廃止し、FileInspectorに統合する方針とする**

**統合の詳細:**

1. **廃止対象:**
   - `Show.php` の `showVlmModal` プロパティとメソッド（L90-100付近）
   - `show.blade.php` のVLMモーダルUI（L150-260）
   - `showVlmPreviewEvent` イベント

2. **移行方法:**
   - すべての `showVlmPreviewEvent` を `open-file-inspector` に置き換え
   - `ColumnHtmlService.php` でVLMプレビューボタンのイベント名を変更
   - FileInspectorは `tab` パラメータで初期表示タブを指定可能にする

3. **流用する機能:**
   - VLM Markdownレンダリング: `Str::markdown($file->vlm_markdown)`
   - コピー機能: Alpine.js `copyToClipboard()` 関数（L184-229）
   - ダウンロード機能: `route('files.download-vlm', ['format' => 'markdown|json'])`

**実装例:**

```php
// Before (ColumnHtmlService.php)
wire:click="$dispatch('showVlmPreviewEvent', { fileId: {{ $file->id }} })"

// After
wire:click="$dispatch('open-file-inspector', { id: {{ $file->id }}, tab: 'content' })"
```

### 10.2. N+1クエリのリスク

**懸念:** FileInspectorが `$file->ledger->define->folder` のようなチェーンアクセスを行うため、N+1問題が発生する

**対策:**

1. **Eager Loadingの徹底:**

```php
AttachedFile::with([
    'ledger:id,content,content_attached,ledger_define_id',
    'ledger.define:id,folder_id,title',
    'ledger.define.folder:id,title,path',
    'creator:id,name',
    'modifier:id,name',
])->findOrFail($fileId);
```

2. **Livewireコンポーネントでの実装:**

```php
// app/Livewire/AttachedFile/FileInspector.php
public function openInspector(int $id): void
{
    $this->fileId = $id;
    
    // Eager Loading最適化
    $this->file = AttachedFile::with([
        'ledger:id,content,content_attached,ledger_define_id',
        'ledger.define:id,folder_id,title',
        'ledger.define.folder:id,title,path',
        'creator:id,name',
        'modifier:id,name',
    ])->findOrFail($id);
    
    $this->open = true;
}
```

### 10.3. 再処理UIの権限制御

**懸念:** 一般ユーザーに不要な再処理ボタンが表示されると、サポート問い合わせが増加する

**対策:**

1. **表示条件の明確化:**
   - 一般ユーザー: エラー状態のファイルのみ再処理ボタンを表示（`canUserRequestRetry()`）
   - 管理者: 低信頼度ファイルも再処理可能（`canAdminRetry()`）

2. **UI設計:**
   - エラー状態: Content タブ内に警告とともに再処理ボタンを配置
   - 管理者専用: Actionsタブに配置し、一般ユーザーには非表示

3. **フィードバック:**
   - 再処理リクエスト後は即座にトースト通知を表示
   - 処理完了はLivewireイベントで通知し、ドロワーの内容を自動更新

### 10.4. 大量ファイルのパフォーマンス

**懸念:** 1つの台帳に数十ファイルが添付された場合、ドロワーの開閉やタブ切り替えが遅延する

**対策:**

1. **遅延読み込み:**
   - ドロワー初回表示時は基本情報のみ取得
   - Historyタブは初回クリック時にLazyロード

2. **キャッシュ戦略:**
   - `previewable_text` はRedisキャッシュを検討
   - タイムラインデータは計算結果をキャッシュ（5分TTL）

3. **フロントエンド最適化:**
   - Alpine.js の `x-show` ではなく `x-if` でDOMを削減
   - 画像プレビューは `loading="lazy"` 属性を使用

---

## 11. 再処理UIモックアップ追加

### 11.1. Actionsタブの追加

FileInspector に **Actionsタブ** を追加し、破壊的操作を集約します。

```blade
<x-mary-tab name="actions" label="{{ __('ledger.file_inspector.tabs.actions') }}" 
            icon="o-cog" class="tab-lg gap-2">
    <div class="p-4 space-y-4">
        
        {{-- 再処理セクション --}}
        @if($file && ($file->canUserRequestRetry() || $file->canAdminRetry()))
            <div class="card bg-warning/10 border border-warning">
                <div class="card-body p-4">
                    <h3 class="card-title text-sm flex items-center gap-2">
                        <i class="fa-solid fa-arrow-rotate-right text-warning"></i>
                        {{ __('ledger.file_inspector.actions.reprocess_title') }}
                    </h3>
                    <p class="text-xs text-base-content/70 mb-3">
                        {{ __('ledger.file_inspector.actions.reprocess_description') }}
                    </p>
                    
                    <div class="flex flex-col gap-2">
                        @if($file->hasExtractionError())
                            <button wire:click="retryProcessing" 
                                    class="btn btn-warning btn-sm gap-2">
                                <i class="fa-solid fa-arrow-rotate-right"></i>
                                {{ __('ledger.file_inspector.actions.retry_all') }}
                            </button>
                        @endif
                        
                        @if($file->canAdminRetry() && $file->finalized_source !== 'vlm')
                            <button wire:click="retryVlmProcessing" 
                                    class="btn btn-outline btn-warning btn-sm gap-2">
                                <i class="fa-solid fa-robot"></i>
                                {{ __('ledger.file_inspector.actions.retry_vlm_only') }}
                            </button>
                        @endif
                    </div>
                    
                    @if($file->canAdminRetry() && !$file->hasExtractionError())
                        <div class="alert alert-sm mt-2">
                            <i class="fa-solid fa-info-circle"></i>
                            <span class="text-xs">{{ __('ledger.file_inspector.actions.admin_retry_note') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        
        {{-- 削除セクション --}}
        @can('delete', $file->ledger)
            <div class="card bg-error/10 border border-error">
                <div class="card-body p-4">
                    <h3 class="card-title text-sm flex items-center gap-2">
                        <i class="fa-solid fa-trash text-error"></i>
                        {{ __('ledger.file_inspector.actions.delete_title') }}
                    </h3>
                    <p class="text-xs text-base-content/70 mb-3">
                        {{ __('ledger.file_inspector.actions.delete_description') }}
                    </p>
                    <button wire:click="confirmDelete" 
                            class="btn btn-error btn-sm gap-2">
                        <i class="fa-solid fa-trash"></i>
                        {{ __('ledger.file_inspector.actions.delete_file') }}
                    </button>
                </div>
            </div>
        @endcan
        
        {{-- 権限がない場合の説明 --}}
        @if(!$file->canUserRequestRetry() && !$file->canAdminRetry() && !Gate::allows('delete', $file->ledger))
            <div class="alert">
                <i class="fa-solid fa-lock"></i>
                <span class="text-sm">{{ __('ledger.file_inspector.actions.no_actions_available') }}</span>
            </div>
        @endif
        
    </div>
</x-mary-tab>
```

### 11.2. 必要な変数とメソッド

| # | 変数名/メソッド名 | 型 | 用途 | 既存実装の流用 |
|:--|:----------------|:---|:-----|:-------------|
| 53 | `retryProcessing()` | `void` | 全処理の再実行 | ✅ `Show.php` L91-100 |
| 54 | `retryVlmProcessing()` | `void` | VLM処理のみ再実行 | ⚠️ 要新規実装 |
| 55 | `confirmDelete()` | `void` | 削除確認ダイアログ表示 | ⚠️ 要新規実装 |
| 56 | `deleteFile()` | `void` | ファイル削除実行 | ⚠️ 要新規実装 |

**Livewireメソッド実装例:**

```php
// app/Livewire/AttachedFile/FileInspector.php

public function retryProcessing(): void
{
    if (!$this->file || !$this->file->canUserRequestRetry()) {
        $this->addError('retry', __('ledger.file_inspector.errors.retry_not_allowed'));
        return;
    }
    
    try {
        $this->file->retryProcessing();
        $this->dispatch('mary-toast', [
            'type' => 'success',
            'title' => __('ledger.file_inspector.messages.retry_queued'),
        ]);
        $this->close();
    } catch (\Exception $e) {
        Log::error("FileInspector retryProcessing failed: " . $e->getMessage());
        $this->addError('retry', __('ledger.file_inspector.errors.retry_failed'));
    }
}

public function retryVlmProcessing(): void
{
    if (!$this->file || !$this->file->canAdminRetry()) {
        $this->addError('retry', __('ledger.file_inspector.errors.admin_only'));
        return;
    }
    
    try {
        // VLM処理のみ再実行（Phase6の実装参照）
        \App\Jobs\Ledger\ProcessVlm::dispatch($this->file);
        
        $this->dispatch('mary-toast', [
            'type' => 'info',
            'title' => __('ledger.file_inspector.messages.vlm_retry_queued'),
        ]);
    } catch (\Exception $e) {
        Log::error("FileInspector retryVlmProcessing failed: " . $e->getMessage());
        $this->addError('retry', __('ledger.file_inspector.errors.vlm_retry_failed'));
    }
}

public function confirmDelete(): void
{
    $this->dispatch('confirm-delete', [
        'fileId' => $this->file->id,
        'filename' => $this->file->original_filename,
    ]);
}
```

---

## 12. 修正版データ構造一覧

### 12.1. Folderアクセス修正

| # | 変数名 | 型 | **修正前（誤り）** | **修正後（正しい）** | 備考 |
|:--|:------|:---|:----------------|:-------------------|:-----|
| 40 | `folder` | `?Folder` | `$file->ledger->folder` | `$file->ledger->define->folder` | Eager Loadingも修正必須 |
| 41 | `folder_path` | `?string` | `$file->ledger->folder->path` | `$file->ledger->define->folder->path` | パンくずリスト用 |

### 12.2. 修正版Eager Loading

```php
AttachedFile::with([
    'ledger:id,content,content_attached,ledger_define_id', // ✅ ledger_define_id追加
    'ledger.define:id,folder_id,title',                    // ✅ define経由
    'ledger.define.folder:id,title,path',                  // ✅ folder取得
    'creator:id,name',
    'modifier:id,name',
    'activities.causer:id,name',
])->findOrFail($fileId);
```

---

## 13. まとめ

### 13.1. 既存実装から流用できるもの

- ✅ VLMモーダルのコピー機能（Alpine.js関数）
- ✅ VLM Markdownレンダリングロジック
- ✅ 信頼度バッジ表示ロジック
- ✅ ファイルモデルの豊富なメソッド群
- ✅ 既存ルート定義（ダウンロード、VLM結果）
- ✅ 再処理ロジック（`retryProcessing()` メソッド）
- ✅ **OCR後PDFダウンロード機能（`file.download` ルート + `?original=true` パラメータ）**
- ✅ **ColumnHtmlServiceの画像/PDF別ダウンロードリンク生成ロジック**

### 13.2. 新規実装が必要なもの

- ⚠️ `creator`, `modifier`, `activities` リレーション（3つ）
- ⚠️ `getProcessingTimeline()` メソッド
- ⚠️ FileInspector Livewireコンポーネント全体
- ⚠️ Actionsタブ（再処理・削除UI）
- ⚠️ VLMのみ再処理メソッド（`retryVlmProcessing()`）
- ⚠️ 削除機能（`confirmDelete()`, `deleteFile()`）

### 13.3. Phase 2への影響

この整理により、以下が明確になりました：

1. **モデル拡張が最優先** → リレーション3つとタイムライン生成メソッド
2. **Eager Loading戦略の重要性** → N+1問題回避のため、`define.folder` のチェーンを考慮
3. **VLMモーダル統合の方針決定** → ✅ **完全統合（廃止）を確定**
4. **Actionsタブの追加** → 再処理UIをドロワーに統合
5. **Folderリレーション修正** → `ledger->folder` は存在しない（`ledger->define->folder`）
6. **✅ OCR後PDFダウンロード機能** → **既存実装で完全対応済み、新規実装不要**

**Phase 2実装タスクの削減:** OCR後PDFダウンロード機能の実装タスク（2h）が不要となり、総工数が **60h → 58h** に減少。

次のドキュメント（Phase 2 WBS更新）でこれらを反映します。

