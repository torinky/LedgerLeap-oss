# FileInspector データ構造・変数設計書

**作成日:** 2025年12月15日
**最終更新:** 2025年12月15日
**ステータス:** ✅ Phase 2 実装準備資料
**対象:** LedgerLeap開発チーム

**関連ドキュメント:**
- [親計画: 添付ファイルUI改善計画](/docs/work/ui-ux/attachment/2025-12-13_attachment-ui-improvement-plan.md)
- [Phase 1 モックアップ評価報告書](/docs/work/ui-ux/attachment/2025-12-15_phase1_mockup_evaluation_report.md)
- [Phase 1 再検証報告書](/docs/work/ui-ux/attachment/2025-12-15_phase1_reverification_report.md)

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
| 38 | `canRetry` | `bool` | `$file->canUserRequestRetry()` | ✅ モデルメソッド | 一般ユーザーの再処理権限 |
| 39 | `canAdminRetry` | `bool` | `$file->canAdminRetry()` | ✅ モデルメソッド | 管理者の再処理権限 |
| 40 | `ledger_permissions` | `array` | `$file->ledger->folder->permissions` | ⚠️ 複合取得 | Eager Loading必須 |

### 2.8. プレビュー・ダウンロードURL

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 41 | `download_url` | `string` | `route('file.download', ...)` | ✅ ルート定義 | ダイレクトダウンロードリンク |
| 42 | `vlm_markdown_download_url` | `?string` | `route('files.download-vlm', ['format' => 'markdown'])` | ✅ VLMダウンロードルート | VLM結果専用 |
| 43 | `vlm_json_download_url` | `?string` | `route('files.download-vlm', ['format' => 'json'])` | ✅ VLMダウンロードルート | VLM結果専用 |
| 44 | `ocr_pdf_download_url` | `?string` | - | ❌ 新規 | OCR処理後のPDFダウンロード |
| 45 | `thumbnail_url` | `?string` | - | ⚠️ 要確認 | サムネイル表示用URL |

### 2.9. 信頼度バッジ情報（UI表示用）

| # | 変数名 | 型 | ソース | 既存実装の流用 | 備考 |
|:--|:------|:---|:------|:-------------|:-----|
| 46 | `confidence_badge` | `?array` | `$file->getConfidenceBadgeInfo()` | ✅ モデルメソッド | `['label', 'color', 'score', 'tooltip']` |

### 2.10. 既存VLMモーダルからの流用機能

| # | 機能 | 既存実装 | 流用可能性 | FileInspectorでの実装 |
|:--|:-----|:---------|:----------|:---------------------|
| 47 | **Markdownレンダリング** | `show.blade.php` L175-180 | ✅ 完全流用 | `Str::markdown($file->vlm_markdown)` |
| 48 | **コピー機能（クリップボード）** | `show.blade.php` L184-229 | ✅ 完全流用 | Alpine.js `copyToClipboard()` 関数 |
| 49 | **フォールバックコピー** | `show.blade.php` L215-228 | ✅ 完全流用 | `document.execCommand('copy')` |
| 50 | **コピー状態表示** | `show.blade.php` L183 | ✅ 完全流用 | `copied` 変数 + アイコン切替 |
| 51 | **Markdownダウンロード** | `show.blade.php` L244 | ✅ 完全流用 | `route('files.download-vlm', ['format' => 'markdown'])` |
| 52 | **JSONダウンロード** | `show.blade.php` L248 | ✅ 完全流用 | `route('files.download-vlm', ['format' => 'json'])` |
| 53 | **信頼度表示** | `show.blade.php` L154-168 | ✅ 完全流用 | 条件分岐 + アイコン表示 |

---

## 3. データ取得戦略（Eager Loading最適化）

### 3.1. 必要なリレーション

```php
AttachedFile::with([
    'ledger:id,content,content_attached,folder_id', // テキスト取得 + フォルダ権限
    'ledger.folder:id,title,path',                  // パンくずリスト表示
    'creator:id,name',                               // アップロード者名
    'modifier:id,name',                              // 更新者名
    'activities.causer:id,name',                     // 処理履歴のユーザー
])->findOrFail($fileId);
```

### 3.2. N+1問題防止チェックリスト

- ✅ `$file->original_filename` → `ledger.content` Eager Loading必須
- ✅ `$file->previewable_text` → `ledger.content_attached` Eager Loading必須
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

## 5. OCR後PDFダウンロードURL設計

### 5.1. 必要な実装

現在、OCR処理後のPDFファイルは `content_attached` に格納されているが、直接ダウンロードするルートが存在しない可能性がある。

#### 5.1.1. ルート定義（要確認・追加）

**ファイル:** `routes/tenant.php`

```php
Route::get('/files/{attachedFile}/ocr-pdf', [AttachedFileController::class, 'downloadOcrPdf'])
    ->middleware('auth')
    ->name('file.download-ocr-pdf');
```

#### 5.1.2. コントローラーメソッド（新規実装）

**ファイル:** `app/Http/Controllers/AttachedFileController.php`

```php
public function downloadOcrPdf(AttachedFile $attachedFile)
{
    // 権限チェック
    Gate::authorize('view', $attachedFile->ledger);
    
    // OCR処理済み確認
    if (!$attachedFile->ocr_processed_at) {
        abort(404, 'OCR processed PDF not found.');
    }
    
    // ファイルパス取得
    $isImageFile = str_starts_with($attachedFile->original_mime_type ?? '', 'image/');
    
    if ($isImageFile) {
        // 画像→PDF変換: .pdfキーのファイル
        $pdfBasename = pathinfo($attachedFile->hashedbasename, PATHINFO_FILENAME) . '.pdf';
        $pdfPath = dirname($attachedFile->path) . '/' . $pdfBasename;
    } else {
        // PDF→最適化: 元のファイル
        $pdfPath = $attachedFile->path;
    }
    
    if (!Storage::disk('public')->exists($pdfPath)) {
        abort(404, 'OCR PDF file not found in storage.');
    }
    
    $filename = pathinfo($attachedFile->original_filename, PATHINFO_FILENAME) . '.pdf';
    
    return Storage::disk('public')->download($pdfPath, $filename);
}
```

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
    "file.permissions.readonly": "読み取り専用"
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

## 9. まとめ

### 9.1. 既存実装から流用できるもの

- ✅ VLMモーダルのコピー機能（Alpine.js関数）
- ✅ VLM Markdownレンダリングロジック
- ✅ 信頼度バッジ表示ロジック
- ✅ ファイルモデルの豊富なメソッド群
- ✅ 既存ルート定義（ダウンロード、VLM結果）

### 9.2. 新規実装が必要なもの

- ⚠️ `creator`, `modifier`, `activities` リレーション（3つ）
- ⚠️ `getProcessingTimeline()` メソッド
- ❌ OCR後PDFダウンロード機能（ルート・コントローラー）
- ❌ FileInspector Livewireコンポーネント全体

### 9.3. Phase 2への影響

この整理により、以下が明確になりました：

1. **モデル拡張が最優先** → リレーション3つとタイムライン生成メソッド
2. **Eager Loading戦略の重要性** → N+1問題回避のため、コンポーネント設計時に考慮
3. **VLMモーダル統合の方針決定** → Option A（完全統合）を推奨

次のドキュメント（Phase 2 WBS更新）でこれらを反映します。

