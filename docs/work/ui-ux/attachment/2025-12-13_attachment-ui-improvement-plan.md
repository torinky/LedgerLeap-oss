# 添付ファイルUI改善計画: インスペクター・ドロワー導入

**作成日:** 2025年12月13日
**最終更新:** 2025年12月15日 (Phase 2モデル拡張追加、WBS更新)
**ステータス:** ✅ Phase 1完了 → Phase 2準備完了
**対象:** LedgerLeap開発チーム

**関連ドキュメント:**
- [機能仕様書: 添付ファイル](/docs/function/Attachment.md) (要更新)
- [添付ファイル機能強化の記録](/docs/work/core-features/attachment/2025-07-13_attachment-feature-enhancement.md)
- [ペルソナ・ユースケース](/docs/function/PersonaUseCaseScenario.md)
- [FileInspector データ構造設計書](/docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md) ⚠️ 新規

---

## 1. 目的と背景

### 1.1. 現状の課題
LedgerLeapの添付ファイル機能は、OCRやVLM（視覚言語モデル）の導入、非同期処理によるステータス管理など、バックエンド処理が高度化しています。しかし、フロントエンド（UI）は `ColumnHtmlService.php` によるHTML文字列生成に依存しており、以下の課題が顕在化しています。

*   **情報過多と表示崩れ:** 処理ステータス、リトライボタン、プレビューボタン、ダウンロードリンクなどが狭い領域に詰め込まれており、視認性が低い。
*   **詳細情報の欠如:** OCR/VLMで抽出されたテキストや、処理エラーの詳細ログを確認する手段が限定的（ツールチップ等）で、実用性に欠ける。
*   **拡張性の限界:** PHPコード内でHTML文字列を結合する実装手法は、Vue/Alpine.js等のリッチなUIコンポーネントとの連携が難しく、保守性が低い。

### 1.2. 目的
「情報を俯瞰しながら、必要に応じて詳細を確認・操作できる」モダンなUIへ刷新します。
具体的には、ファイルリストをシンプルに保ちつつ、クリック時に画面右側から詳細パネル（**インスペクター・ドロワー**）を展開する方式を採用します。

### 2.3. 考慮事項
*   **RPA/自動化への配慮:** 既存の業務フローでRPA等が利用されている可能性を考慮し、DOM解析によるファイルダウンロード（`href`属性のスクレイピング等）が引き続き機能するよう、ダイレクトリンクを維持します。
*   **「ながら作業」の支援:** 台帳の他の入力項目を参照しながら添付ファイルの内容を確認できるよう、モーダルではなくドロワー（サイドパネル）を採用します。
*   **✅ VLMダイアログの統合方針（確定）:** 既存の `showVlmModal` は FileInspector に完全統合し、廃止します。これにより、UI一貫性を維持し、コード重複を排除します。

#### 2.3.1. VLMダイアログ統合の詳細

**廃止対象:**
- `app/Livewire/Ledger/Show.php` の `showVlmModal` プロパティとメソッド
- `resources/views/livewire/ledger/show.blade.php` のVLMモーダルUI（L150-260付近）
- `showVlmPreviewEvent` イベント

**移行方法:**
- すべての `showVlmPreviewEvent` を `open-file-inspector` に置き換え
- FileInspectorは `tab` パラメータで初期表示タブを指定可能
- VLMテキストは Contentタブで表示（既存のMarkdownレンダリング・コピー機能を流用）

**流用する機能:**
- Alpine.js `copyToClipboard()` 関数（クリップボードコピー）
- `Str::markdown()` によるMarkdownレンダリング
- VLMダウンロードルート（`files.download-vlm`）
- 信頼度バッジ表示ロジック

**実装例:**

```php
// Before (ColumnHtmlService.php等)
wire:click="$dispatch('showVlmPreviewEvent', { fileId: {{ $file->id }} })"

// After
wire:click="$dispatch('open-file-inspector', { id: {{ $file->id }}, tab: 'content' })"
```

---

## 2. UI/UX設計方針 (インスペクター・ドロワー方式)

### 2.1. リスト表示 (ColumnHtmlService / Blade)
`ColumnHtmlService` の役割を「HTML生成」から「データ提供とトリガー」へシフトさせます。

*   **表示内容:**
    *   サムネイル（画像の場合）またはファイルタイプアイコン
    *   ファイル名（省略あり）
    *   主要ステータスアイコン（処理中、エラー、完了）
    *   **【RPA用】** 隠し要素または目立たない形でのダイレクトダウンロードリンク (`<a href="..." class="direct-download-link">`)
*   **インタラクション:**
    *   ファイル領域（リンク以外）をクリックすると、`Livewire` イベント `open-file-inspector` をディスパッチします。

### 2.2. 詳細ドロワー (FileInspector Component)
画面右側からスライドインするパネル (`x-mary-drawer`) を実装します。

*   **ヘッダー:**
    *   ファイル名（フル表示）、閉じるボタン。
    *   アクション: ダウンロード、削除、再処理（リトライ）。
*   **プレビューエリア:**
    *   画像/PDFの大きめのプレビュー。
*   **タブ切り替えコンテンツ:**
    1.  **基本情報:** サイズ、MIMEタイプ、アップロード日時、アップロード者、オリジナルファイル名。
    2.  **テキスト解析:** OCR/VLMで抽出されたテキスト全文（コピーボタン付き）、信頼度スコア（あれば）。
    3.  **処理履歴:** Tika → OCR → VLM の処理フロー状況、エラーログ、各ステップの所要時間。

---

## 3. 実装計画 (WBS)

**最終更新:** 2025年12月16日（OCR後PDFダウンロード機能が既存実装で対応済みと判明）

総見積工数: **7.25日 (58h)** ← OCR後PDFダウンロード実装タスク削除で-2h

| Phase | ID | タスク名称 | 担当 | 工数 | 依存 | 備考 |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1. モックアップ** | 1.1 | 新UI (リスト表示 & ドロワー) のモックアップ作成 (Blade) | - | 3h | - | ✅ 完了 |
| | 1.2 | チーム/ステークホルダーによるUX評価・フィードバック | - | 1h | 1.1 | ✅ 完了 |
| | 1.3 | UI仕様の確定と修正計画への反映 | - | 1h | 1.2 | ✅ 完了 |
| | **1.4** | **データ構造・変数の網羅的整理** | - | **2h** | **1.3** | ✅ 完了 |
| **2. モデル拡張** | **2.1** | **`AttachedFile` リレーション追加（creator/modifier/activities）** | - | **2h** | **1.4** | ⚠️ 新規 |
| | **2.2** | **`AttachedFile::getProcessingTimeline()` メソッド実装** | - | **3h** | **2.1** | ⚠️ 新規 |
| | **2.3** | **モデル拡張のテスト実装（Unit Test）** | - | **2h** | **2.1-2.2** | ⚠️ 新規 |
| **3. 基盤改修** | 3.1 | `ColumnHtmlService` のリファクタリング (Bladeコンポーネント化) | - | 4h | 2.3 | 依存更新 |
| | 3.2 | リスト表示用Bladeコンポーネントの実装 (`x-ledger.attachment-list`) | - | 4h | 3.1 | - |
| | 3.3 | 一覧画面 (`RecordsTable`) での簡易表示モード実装 | - | 2h | 3.2 | - |
| **4. ドロワー実装** | 4.1 | `FileInspector` Livewireコンポーネントの作成（基本構造） | - | 3h | 2.3 | 依存更新 |
| | **4.2** | **Eager Loading戦略の実装（`with()` 句最適化）** | - | **2h** | **4.1** | Phase5から移動 |
| | 4.3 | ドロワーUIの基本実装 (DaisyUI Drawer, タブ, レスポンシブ) | - | 5h | 4.1 | - |
| | 4.4 | 親コンポーネント (`Show`, `ModifyColumn`) とのイベント連携 | - | 2h | 3.2, 4.1 | - |
| | 4.5 | アクセス権限チェックの実装（ポリシー連携） | - | 2h | 4.1 | - |
| **5. コンテンツ実装** | 5.1 | Contentタブ: VLM/OCR/Tikaテキスト表示 | - | 4h | 4.3, 2.2 | 依存追加 |
| | **5.2** | **VLMコピー機能の流用・統合（Alpine.js）** | - | **2h** | **5.1** | ⚠️ 既存流用 |
| | 5.3 | Detailsタブ: ファイル基本情報表示 | - | 2h | 4.3, 2.1 | 依存追加 |
| | 5.4 | Historyタブ: タイムライン表示 | - | 3h | 4.3, 2.2 | 依存追加 |
| | **5.5** | **Permissionsタブ: 権限情報表示** | - | **1h** | **4.5** | ⚠️ 新規 |
| | **5.6** | **Actionsタブ: 再処理・削除UI実装** | - | **3h** | **4.5, 5.1** | ⚠️ 新規 |
| | **5.7** | **再処理メソッド実装（retryVlmProcessing等）** | - | **2h** | **5.6** | ⚠️ 新規 |
| | 5.8 | 多言語対応 (ja.json への翻訳キー追加) | - | 1h | 5.1-5.7 | - |
| **6. VLM統合** | **6.1** | **既存VLMモーダルのイベント移行（showVlmPreviewEvent → open-file-inspector）** | - | **2h** | **5.1** | ⚠️ 新規 |
| | **6.2** | **VLMモーダル廃止とFileInspector統合テスト** | - | **2h** | **6.1** | ⚠️ 新規 |
| **7. パフォーマンス最適化** | 7.1 | キャッシング機構の検討・実装（大量ファイル対策） | - | 2h | 4.2 | - |
| | **7.2** | **N+1クエリ検証とデバッグ** | - | **1h** | **7.1** | ⚠️ 新規 |
| **8. 仕上げ** | 8.1 | RPA互換性検証 (ダイレクトリンクの確認) | - | 1h | 3.2 | - |
| | 8.2 | Feature Test: FileInspectorイベント連携・権限 | - | 2h | 6.2 | - |
| | 8.3 | Livewire Test: タブ切り替え・コピー機能 | - | 2h | 5.2, 5.4 | - |
| | 8.4 | モバイル・タブレット実機検証 | - | 2h | 4.3 | - |
| | 8.5 | Pint実行・コミット準備 | - | 0.5h | ALL | - |

---

## 4. 詳細設計とタスク

### Phase 1: モックアップとUX評価（✅ 完了）

#### 1.1 モックアップ作成
*   ロジックを実装せず、BladeとTailwind CSSのみで、新しいリスト表示とドロワーの見た目を作成します。
*   既存の `Show` 画面の一部を一時的に置き換えるか、専用のモックアップ用ルート (`/mock/attachment-ui`) を作成して確認できるようにします。

#### 1.2 UX評価
*   クリック時の反応速度、ドロワーが開いた状態での他項目の視認性、モバイルでの表示崩れなどを確認します。

#### 1.4 データ構造・変数の網羅的整理（✅ 完了）
*   FileInspectorコンポーネントに必要な変数を52項目に整理。
*   既存実装からの流用可能性を評価（VLMコピー機能等）。
*   **成果物:** `docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md`

---

### Phase 2: モデル拡張（⚠️ 新規追加）

#### 2.1 `AttachedFile` リレーション追加

**目的:** N+1クエリを防ぐため、必要なリレーションを実装する。

**ファイル:** `app/Models/AttachedFile.php`

**実装内容:**

```php
/**
 * ファイルをアップロードしたユーザー
 */
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'creator_id');
}

/**
 * ファイルを最後に更新したユーザー
 */
public function modifier(): BelongsTo
{
    return $this->belongsTo(User::class, 'modifier_id');
}

/**
 * ファイル処理の履歴（Spatie ActivityLog連携）
 */
public function activities(): MorphMany
{
    return $this->morphMany(\Spatie\Activitylog\Models\Activity::class, 'subject')
        ->orderBy('created_at', 'desc');
}
```

**テスト要件:**
- リレーションが正しくロードされることを確認
- Eager Loadingでのクエリ数削減を検証

#### 2.2 `AttachedFile::getProcessingTimeline()` メソッド実装

**目的:** Historyタブでタイムライン表示するためのデータ構造を生成する。

**ファイル:** `app/Models/AttachedFile.php`

**実装内容:**

```php
/**
 * ファイル処理の全工程をタイムライン形式で取得
 *
 * @return array 各処理ステップの情報を含む配列
 */
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

**テスト要件:**
- 各処理状態でタイムラインが正しく生成されることを確認
- 失敗状態のファイルでも適切に表示されることを検証

#### 2.3 OCR後PDFダウンロード機能

**目的:** OCR処理後のPDFファイルを直接ダウンロードできるようにする。

**ルート定義:** `routes/tenant.php`

```php
Route::get('/files/{attachedFile}/ocr-pdf', [AttachedFileController::class, 'downloadOcrPdf'])
    ->middleware('auth')
    ->name('file.download-ocr-pdf');
```

**コントローラーメソッド:** `app/Http/Controllers/AttachedFileController.php`

```php
/**
 * OCR処理後のPDFファイルをダウンロード
 */
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

**テスト要件:**
- 画像ファイルのOCR→PDF変換版がダウンロードできることを確認
- PDFファイルのOCR最適化版がダウンロードできることを確認
- 権限のないユーザーはダウンロードできないことを確認

#### 2.4 モデル拡張のテスト実装

**ファイル:** `tests/Unit/AttachedFileTest.php`

**テスト項目:**
- リレーション（creator, modifier, activities）の動作確認
- `getProcessingTimeline()` の各状態での出力検証
- OCR後PDFダウンロードの権限チェック

---

### Phase 3: 基盤改修

#### 3.1 `ColumnHtmlService` のリファクタリング
*   **現状:** `getFileHtml` メソッド内でHTMLタグを文字列結合している。
*   **変更:**
    *   HTML生成ロジックを廃止し、データ構造（`AttachedFile` モデルのコレクションやメタデータ）を整理して Blade コンポーネント `resources/views/components/ledger/attachment-list.blade.php` を `render()` する形に変更する。
    *   または、`ColumnHtmlService` はあくまで `HtmlString` を返すが、その中身は `<livewire:ledger.attachment-list ... />` や `<x-ledger.attachment-list ... />` を呼び出すだけのシンプルなものにする。
    *   **重要:** `ModifyColumn` (編集画面) と `Show` (詳細画面) の両方で動作することを考慮する。

#### 2.2 リスト表示用Bladeコンポーネント (`x-ledger.attachment-list`)
*   **デザイン:**
    *   `flex flex-wrap gap-4` でカード形式、または `flex-col` でリスト形式（設定で切替または固定）。
    *   サムネイルをクリックした際の `wire:click="$dispatch('open-file-inspector', { id: {{ $file->id }} })"` を実装。
*   **RPA対応:**
    *   各アイテム内に `<a href="{{ route('file.download', ...) }}" class="opacity-0 absolute inset-0 ...">Download</a>` のような形で、DOM上にリンクを残すか、明示的なダウンロードアイコンボタンを配置する。

#### 2.3 一覧画面対応
*   **課題:** `RecordsTable` (一覧画面) では詳細情報が不要で、アイコンまたはファイル数のみの表示が望ましい。
*   **対応:**
    *   `ColumnHtmlService::show()` メソッドに `$displayMode` パラメータ（`'full'`, `'compact'`, `'icon-only'`）を追加。
    *   `RecordsTable` では `icon-only` モードでシンプルなアイコン表示のみを行う。
    *   詳細画面・編集画面では `full` モードでインスペクター対応のリッチな表示を行う。

### Phase 3: インスペクター・ドロワーの実装

#### 3.1 `FileInspector` Livewireコンポーネント
*   **ファイル:** `app/Livewire/Ledger/FileInspector.php`
*   **機能:**
    *   `#[On('open-file-inspector')]` でファイルIDを受け取る。
    *   `AttachedFile` モデルをロードし、プロパティにセット。
    *   ドロワーの表示フラグ `$show` を `true` にする。

#### 3.2 ドロワーUIの実装
*   **使用ライブラリ:** MaryUI (`x-mary-drawer`, `x-mary-tabs`, `x-mary-card`).
*   **レイアウト:**
    *   右側スライドイン。
    *   幅は `w-1/3` または `w-96` 程度（レスポンシブ対応）。

#### 3.3 イベント連携
*   `resources/views/livewire/ledger/show.blade.php` および `modify-column.blade.php` の最下部に `<livewire:ledger.file-inspector />` を配置する。

#### 3.4 アクセス権限チェック
*   **要件:** ファイルへのアクセス、削除、再処理などの操作は、台帳への権限に基づいて制御する必要がある。
*   **実装:**
    *   `FileInspector` コンポーネントで `AttachedFilePolicy` を呼び出し、操作ボタンの表示/非表示を制御する。
    *   削除・再処理などの操作メソッドには `$this->authorize('delete', $file)` を追加する。
    *   台帳に対する `view` 権限がない場合は、ファイルインスペクター自体を表示しない。

### Phase 4: 情報表示の拡張

#### 4.1 モデル拡張
*   `AttachedFile` モデルに、関連する `ActivityLog` や `Job` のステータスを取得するアクセサまたはリレーションを追加する。
    *   例: `processing_logs` (アクティビティログから抽出)
*   **Activity Log連携:**
    *   `Spatie\Activitylog` パッケージを使用して、ファイル処理の履歴を取得する。
    *   `activity()->causedBy($user)->performedOn($file)->log('ocr_started')` のような形式で記録されたログを取得する。
    *   `$file->activities()` リレーションメソッドを追加し、Eager Loadingを可能にする。

#### 4.2 コンテンツ実装
*   **テキストタブ:** `content_attached` カラムから該当ファイルの抽出テキストを表示。Markdownとしてレンダリングするか、生テキストを表示するか検討（コピーしやすさ優先で生テキスト推奨）。
*   **履歴タブ:** `Timeline` UIコンポーネント（MaryUI または独自実装）を使って処理の流れを可視化。
    *   Tika処理開始 → 完了
    *   OCR処理開始 → 完了/失敗
    *   VLM処理開始 → 完了/失敗
    *   最終化処理
    *   各ステップのタイムスタンプと所要時間を表示。

#### 4.3 多言語対応
*   `lang/ja.json` に以下の翻訳キーを追加:
    *   `file_inspector.title`: ファイル詳細
    *   `file_inspector.tabs.basic_info`: 基本情報
    *   `file_inspector.tabs.text`: テキスト解析
    *   `file_inspector.tabs.history`: 処理履歴
    *   `file_inspector.actions.download`: ダウンロード
    *   `file_inspector.actions.delete`: 削除
    *   `file_inspector.actions.retry`: 再処理
    *   `file_inspector.basic_info.size`: ファイルサイズ
    *   `file_inspector.basic_info.mime_type`: MIMEタイプ
    *   `file_inspector.basic_info.uploaded_at`: アップロード日時
    *   `file_inspector.basic_info.uploader`: アップロード者
    *   その他必要なキー

### Phase 5: パフォーマンス最適化

#### 5.1 Eager Loading戦略
*   **課題:** `AttachedFile` の処理履歴やテキスト取得時に N+1 クエリ問題が発生する可能性がある。
*   **対応:**
    *   `FileInspector` コンポーネントでファイル情報をロードする際、必要なリレーションを事前ロードする。
    ```php
    $this->file = AttachedFile::with([
        'ledger:id,content_attached',
        'creator:id,name',
        'modifier:id,name',
        'activities.causer:id,name'
    ])->findOrFail($fileId);
    ```
    *   `Show` および `ModifyColumn` コンポーネントでも、添付ファイルコレクションをロードする際に必要なリレーションを追加する。

#### 5.2 キャッシング機構
*   **課題:** 大量のファイルが添付されている台帳では、ドロワーの表示が遅くなる可能性がある。
*   **対応:**
    *   ファイルのプレビューテキストや処理履歴は変更が少ないため、Redis キャッシュを活用する。
    *   キャッシュキー: `file_inspector:{file_id}:preview_text`, `file_inspector:{file_id}:history`
    *   ファイルの再処理やステータス変更時にキャッシュをクリアする。
    *   キャッシュTTL: 1時間（調整可能）

---

## 5. 懸念事項と対応策

### 5.1. 技術的懸念

#### **(1) ColumnHtmlService の影響範囲**
*   **懸念:** `ColumnHtmlService` は詳細画面、一覧画面、差分表示など多岐にわたって利用されており、変更によるリグレッション（意図しない表示崩れ）のリスクが高い。
*   **対応策:**
    *   `getFileHtml` メソッドをいきなり書き換えるのではなく、新メソッド `getFileComponent` を作成し、段階的に移行する。または、フィーチャーフラグ的な引数で挙動を切り替える。
    *   一覧画面 (`RecordsTable`) では、従来通りの簡易表示（アイコンのみ等）が望ましいため、コンテキストに応じた表示モードの切り替えを実装する。

#### **(2) MaryUI Drawer コンポーネントの非存在**
*   **懸念:** MaryUI パッケージには `x-mary-drawer` コンポーネントが存在しない可能性が高い（現在のコードベースで使用例が見つからない）。
*   **対応策:**
    *   **Option A (推奨):** Alpine.js と Tailwind CSS で独自のドロワーコンポーネントを実装する。
        *   MaryUI の `x-modal` を参考にしつつ、右側からスライドインするアニメーションを追加。
        *   `@keydown.escape.window="close()"` などのキーボードショートカット対応。
    *   **Option B:** DaisyUI の `drawer` コンポーネントを活用する（LedgerLeap は DaisyUI も使用している）。
    *   **Option C:** MaryUI に PR を出して Drawer コンポーネントを追加する（長期的）。

#### **(3) z-index 問題**
*   **懸念:** 既存のヘッダーやモーダル、トースト通知とドロワーの `z-index` が競合し、ドロワーが隠れたり、逆に重要な要素を隠してしまう可能性がある。
*   **対応策:**
    *   モックアップ作成段階（Phase 1）で、実際のアプリケーションレイアウト内に配置して重なり順を確認する。必要に応じて `Tailwind` の `z-` クラスで調整する。
    *   既存の MaryUI モーダルは `z-50` を使用しているため、ドロワーは `z-40` または `z-45` を使用し、オーバーレイは `z-39` または `z-44` にする。

#### **(4) RPA/自動化ツールへの影響**
*   **懸念:** 既存のRPAツールが、特定のDOM構造（例: `a.btn-ghost` 内の `href`）に依存してファイルをダウンロードしている場合、UI変更で動作しなくなる。
*   **対応策:**
    *   新UIにおいても、ダウンロードリンク (`a`タグ) には、従来と同じか、より明確なクラス名（例: `download-link` または `direct-download-link`）を付与し、`href` 属性には直接ダウンロードURLを設定する。JavaScript (`wire:click`) だけに依存しない構造にする。
    *   RPA互換性検証タスク（6.1）で、実際のDOM構造を確認し、スクレイピング可能性を検証する。

#### **(5) テナント対応の複雑性**
*   **懸念:** LedgerLeap はマルチテナント対応しており、`FileInspector` コンポーネントが異なるテナントのファイルにアクセスしないよう、適切なスコープ設定が必要。
*   **対応策:**
    *   `FileInspector` コンポーネントで `AttachedFile` をロードする際、自動的にテナントスコープが適用されることを確認する。
    *   `AttachedFile` モデルは `BelongsToTenant` トレイトを使用しているため、基本的には問題ないが、テストで異なるテナント間のアクセスが拒否されることを検証する。

#### **(6) 既存のVLMプレビューモーダルとの競合**
*   **懸念:** 現在、VLM結果のプレビューには専用のモーダル (`showVlmModal`) が使用されている。ドロワーと併用する場合、UXの一貫性が損なわれる可能性がある。
*   **対応策:**
    *   **Option A:** VLM結果もドロワーの「テキスト解析」タブに統合し、既存のモーダルを廃止する。
    *   **Option B:** VLMプレビューボタンをクリックした場合はドロワーを開き、「テキスト解析」タブを自動選択する形に統一する。
    *   実装はPhase 3.3（イベント連携）で対応。

### 5.2. UX/運用上の懸念

#### **(1) モバイルでの表示**
*   **懸念:** 狭い画面でドロワーを開くと画面全体が覆われ、元のコンテキスト（台帳のどの行を見ていたか）を見失う可能性がある。
*   **対応策:**
    *   モバイルではドロワーを全画面モーダルとして振る舞わせるか、あるいは下部からのスライドアップ（ボトムシート）形式を検討する。
    *   Phase 6.3（モバイル実機検証）で実際のユーザビリティを確認する。

#### **(2) 操作手数の増加**
*   **懸念:** 「ダウンロードしたいだけ」の場合、従来はワンクリックだったのが「クリックしてドロワーを開く → ダウンロードボタンを押す」という2ステップになるのはUX低下となる。
*   **対応策:**
    *   リスト表示の各アイテムに、ドロワーを開かずにダウンロードできる小さな「ダウンロードアイコンボタン」を配置し、ワンクリック性を維持する。
    *   現在の実装と同様に、サムネイルまたはファイル名自体をダウンロードリンクとして機能させる。

#### **(3) 処理履歴情報の不足**
*   **懸念:** 現在の `AttachedFile` モデルには各処理の開始/完了タイムスタンプがあるが、詳細なログ（エラーメッセージ、所要時間など）は `ActivityLog` や Horizon のジョブログに分散している。
*   **対応策:**
    *   Phase 4.1でモデル拡張を行い、`activities()` リレーションを追加する。
    *   Activity Log に記録されている処理ステップ（`ocr_started`, `ocr_completed`, `vlm_failed` など）を集約してタイムラインを構築する。
    *   エラーメッセージは `properties` カラムに JSON 形式で保存されていることを想定し、適切にパースして表示する。

---

## 6. 作業ログ

### 2025/12/13 (初版作成)
- 計画書作成完了。
- 基本的なWBS、UI/UX設計方針を策定。

### 2025/12/13 (レビュー反映)
- 包括的なレビューを実施し、以下の項目を追加・強化:
  - **WBSの拡充:** 工数見積を4日→5.5日に修正。Phase 5（パフォーマンス最適化）、Phase 6（仕上げ）を追加。
  - **一覧画面対応:** `RecordsTable` での簡易表示モード実装タスクを明記（2.3）。
  - **アクセス権限チェック:** ポリシー連携の詳細を追加（3.4）。
  - **Activity Log連携:** 処理履歴取得のための実装戦略を追加（4.1）。
  - **多言語対応:** 必要な翻訳キーの一覧を明記（4.3）。
  - **パフォーマンス最適化:** Eager Loading戦略とキャッシング機構を追加（Phase 5）。
  - **懸念事項の拡充:**
    - MaryUI Drawer コンポーネントの非存在問題と代替案。
    - テナント対応の複雑性への対処。
    - 既存VLMプレビューモーダルとの統合方針。
    - 処理履歴情報の集約戦略。
    - z-index 問題への具体的な対応値（z-40, z-45）。
  - **テスト戦略:** Feature/Livewireテスト実装タスクを明記（6.2）。
- 計画確定、実装準備完了。

### 2025/12/15 (Phase 1完了・Phase 2準備)
- **Phase 1 完了:**
  - モックアップ作成完了（`file-inspector.blade.php`）
  - UX評価・フィードバック反映完了（評価報告書・再検証報告書作成）
  - データ構造・変数の網羅的整理完了（52項目、既存流用可能性評価）
- **WBS大幅更新:**
  - Phase 2（モデル拡張）を最優先に移動
  - Eager Loading戦略をPhase 4に前倒し
  - VLMコピー機能流用タスクを明記（5.2）
  - Permissionsタブ追加（5.5）
- **次フェーズ:** Phase 2（モデル拡張）の実装開始準備完了

### 2025/12/16 (未確定事項調査・再処理UI追加・VLM統合方針確定)
- **未確定事項の調査完了:**
  - OCR後PDFダウンロード機能: ✅ **既存実装で完全対応済み**（`file.download` ルート + `AttachedFileDownloadController`）
  - 再処理UI: ⚠️ ドロワーに未実装（Actionsタブの追加が必要）
  - Folderモデルのリレーション: ✅ 設計誤りを発見・修正（`ledger->folder` → `ledger->define->folder`）
- **既存実装の詳細調査:**
  - `AttachedFileDownloadController@download`: `?original=true` で元ファイル、パラメータなしで最適化版を自動判定
  - `ColumnHtmlService` L343-375: 画像ファイルとPDFファイルで異なるダウンロードリンク生成ロジックを実装済み
  - 最適化PDFの自動判定: `$attachedFile->optimized && $attachedFile->mime === 'application/pdf'` で自動切替
- **懸念事項の検討完了:**
  - **VLMダイアログ統合方針確定:** ✅ 既存VLMモーダルを廃止し、FileInspectorに完全統合
  - **N+1クエリ対策:** Eager Loading戦略を明確化（`ledger.define.folder` チェーン）
  - **再処理UIの権限制御:** 一般ユーザー/管理者の表示条件を定義
  - **大量ファイルのパフォーマンス:** 遅延読み込み・キャッシュ戦略を策定
- **再処理UIモックアップ追加:**
  - Actionsタブの追加（再処理・削除の破壊的操作を集約）
  - 再処理ボタンの権限制御デザイン
  - 必要な変数・メソッドの特定（4項目追加: 53-56番）
- **WBS更新:**
  - Actionsタブ実装タスク追加（5.6, 5.7）
  - VLM統合タスク追加（6.1, 6.2）
  - **OCR後PDFダウンロード実装タスク削除（2.3）** → 既存実装で対応済み
  - 総工数: 56h → 60h → **58h**（+4h -2h = +2h）
- **ドキュメント更新:**
  - データ構造設計書に未確定事項・懸念事項のセクション追加（セクション9-13）
  - セクション5をOCR後PDFダウンロードの既存実装説明に変更
  - 翻訳キーに再処理UI用キーを追加（20項目追加）
  - VLM統合方針をメイン計画書に明記（セクション2.3.1）
  - Folderモデルのリレーション修正を全体に反映
- **次フェーズ:** Phase 2（モデル拡張）実装準備完全完了、工数削減により効率化
  - Phase 2「モデル拡張」を新規追加（リレーション3つ、タイムライン生成、OCR-PDFダウンロード）
  - Phase 3-8の番号を再調整
  - Phase 6「VLM統合」を新規追加（既存VLMモーダルの廃止と統合）
  - 工数見積を5.5日→7日（56h）に修正
- **新規ドキュメント作成:**
  - `docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md`
  - `docs/work/ui-ux/attachment/2025-12-15_phase1_reverification_report.md`
- **次のステップ:** Phase 2（モデル拡張）の実装開始

---

## 7. 技術メモ (開発者向け)

*   **MaryUI Drawer:** `x-mary-drawer` コンポーネントは現在のMaryUIパッケージに存在しない。Alpine.js + Tailwind CSS で独自実装するか、DaisyUI の `drawer` コンポーネントを活用すること。
*   **ColumnHtmlService:** このサービスは歴史的経緯により複雑化している。今回の改修で、可能な限りロジックを View Component または Livewire Component に移譲し、Service は「データの準備」に徹するようにリファクタリングすることを推奨する。
*   **AttachedFile モデルの拡張:** 
    *   `activities()` リレーションを追加し、Spatie ActivityLog との連携を強化する。
    *   `hasPreviewableText()` メソッドは既に実装済みだが、`getPreviewableTextAttribute` も活用してドロワー表示を最適化する。
    *   テナントスコープは `BelongsToTenant` トレイトにより自動適用されるが、クロステナントアクセスを防ぐテストを必ず実装すること。
*   **テスト戦略:**
    *   Feature Test: `FileInspectorTest.php` を作成し、イベント連携、権限チェック、表示内容を検証。
    *   Livewire Test: `Livewire::test(FileInspector::class)` でコンポーネントの動作を検証。
    *   テナント初期化: 全Featureテストで `tenancy()->initialize($tenant)` を実行すること（Phase6で確立されたベストプラクティス）。
*   **RPA互換性:**
    *   `direct-download-link` クラスを持つ `<a>` タグを必ず配置すること。
    *   DOM構造の変更は段階的に行い、既存の自動化スクリプトへの影響を最小限に抑える。
*   **パフォーマンス:**
    *   `AttachedFile::with(['ledger', 'creator', 'modifier', 'activities.causer'])` のような Eager Loading を徹底する。
    *   キャッシュは Redis を使用し、ファイル再処理時に必ずクリアすること（`Cache::tags(['file_inspector', "file_{$fileId}"])->flush()`）。
