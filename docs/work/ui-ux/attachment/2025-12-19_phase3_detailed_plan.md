# 添付ファイルUI改善 Phase 3 詳細計画: 基盤改修

**作成日:** 2025年12月19日  
**最終更新:** 2025年12月19日  
**親ドキュメント:** [添付ファイルUI改善計画: インスペクター・ドロワー導入](/docs/work/ui-ux/attachment/2025-12-13_attachment-ui-improvement-plan.md)  
**フェーズ:** Phase 3 (基盤改修)  
**ステータス:** 📋 計画中  
**前提条件:** Phase 2完了（`AttachedFile` モデル拡張済み）

---

## 1. 目的と概要

Phase 3では、PHPコード内でHTML文字列を結合しているレガシーな `ColumnHtmlService` をリファクタリングし、モダンな Blade コンポーネント `x-ledger.attachment-list` ベースの実装へ移行します。これにより、保守性の向上、UIの一貫性確保、およびPhase 4以降のインスペクター・ドロワー実装への土台を築きます。

### 1.1. 現状の課題
- `ColumnHtmlService::getFileHtml()` がHTML文字列結合ロジック（280行超）を抱え込んでおり、複雑化・肥大化している。
- `x-ledger.attachment-list` コンポーネントは既に存在し、Phase 1モックアップで `compact` モードのみ実装済みだが、`ColumnHtmlService` からは利用されておらず、ロジックが二重管理されている。
- `icon-only` モード（一覧画面用）が未実装であり、現在は `table-row.blade.php` でモックデータを使って暫定表示している。
- `ColumnHtmlService` は `Show`, `ModifyColumn`, `RecordsTable` など複数画面から呼ばれており、変更のリグレッションリスクが高い。

### 1.2. ゴール
- `ColumnHtmlService` の役割を「HTML生成」から「データ構造の変換」へシフトさせる。
- `x-ledger.attachment-list` を唯一の描画ロジックとし、全画面（詳細、一覧、編集）で統一利用する。
- 既存のRPA/自動化ツールとの互換性（ダイレクトダウンロードリンク）を担保する。
- 段階的な移行により、既存テストの失敗を最小化する。

### 1.3. 実装済み確認事項
- ✅ Phase 1: `x-ledger.attachment-list` コンポーネント（`compact` モード）実装済み
- ✅ Phase 2: `AttachedFile` モデル拡張（`creator`, `modifier`, `activities` リレーション、`getProcessingTimeline()` メソッド）実装済み
- ✅ `table-row.blade.php` でモックデータを使った `icon-only` モードの暫定実装あり（L228）
- ❌ `ColumnHtmlService` は未改修（旧HTML文字列結合方式のまま）

---

## 2. 実装計画 (WBS)

総見積工数: **13h**（精査により+4h）

| ID | タスク名称 | 担当 | 工数 | 依存 | 備考 |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **3.1** | **`x-ledger.attachment-list` コンポーネントの強化** | - | **4h** | - | `icon-only` + エラー状態網羅 |
| **3.2** | **`ColumnHtmlService` のリファクタリング** | - | **5h** | 3.1 | データ変換ロジック + 後方互換 |
| **3.3** | **UIモックアップ全分岐実装検証** | - | **2h** | 3.1 | 全ステータス・全MIMEタイプ |
| **3.4** | **統合テストとRPA互換性検証** | - | **2h** | 3.2, 3.3 | Feature Test + RPA |

---

## 3. 詳細タスク定義

### タスク 3.1: `x-ledger.attachment-list` コンポーネントの強化

**ファイル:** `resources/views/components/ledger/attachment-list.blade.php`

#### 3.1.1. `icon-only` モードの実装
現在の実装では、`$mode === 'compact'` 以外はすべて Full モード（カード表示）として扱われます。`icon-only` モードを追加し、一覧画面 (`RecordsTable`) での最適な表示を実現します。

- **仕様:**
    - ファイルアイコンのみを表示（ファイル名はツールチップで表示）。
    - 複数ファイルがある場合は最初の3-5個を横並びで表示し、残りは「+N」バッジで表示。
    - ステータスインジケータは最小限（processing: 点滅、error: 赤バッジ）。
    - クリックでFileInspectorを開く動作は維持（`handleFileClick()`）。
    - ダウンロードボタンは省略（ドロワーから操作）。

- **表示件数リミット:**
    - `icon-only`: 最大5件（それ以上は「+N」）
    - `compact`: 最大4件（「もっと見る」ボタン）
    - `full`: 最大8件（「もっと見る」ボタン）

#### 3.1.2. 全ステータスとエラー状態の網羅的実装

**対応必須のステータス** （`AttachedFileStatus` Enum全27ケース）:
- **処理中（5種）**: `PENDING_INITIAL_PROCESSING`, `INITIAL_PROCESSING`, `PENDING_OCR`, `OCR_PROCESSING`, `PENDING_VLM`, `VLM_PROCESSING`, `PARALLEL_PROCESSING`
- **完了（4種）**: `COMPLETED`, `FINALIZED`, `FINALIZED_BY_TIKA`, `FINALIZED_BY_OCR`, `FINALIZED_BY_VLM`
- **エラー（5種）**: `TIKA_FAILED`, `OCR_FAILED`, `VLM_FAILED`, `THUMBNAIL_FAILED`, `PROCESSING_FAILED`
- **レガシー（10種）**: `UPLOADED`, `OPTIMIZED`, `OPTIMIZING`, `OPTIMIZE_FAILED`, `EXTRACTED_AND_SAVED`, `EXTRACTION_FAILED`, `EXTRACTING`, `READY_FOR_FINALIZATION`

**実装方針:**
- `AttachedFileStatus::icon()` と `colorClass()` メソッドを活用（既存実装済み）。
- アニメーション: `animate-spin` は処理中ステータスのみ。
- 色覚多様性対応: アイコン形状で識別可能なデザインを維持。

#### 3.1.3. アクセシビリティ検証
- **ARIA属性:** `role="list"`, `role="listitem"`, `aria-label` の適切な設定。
- **キーボード操作:** `tabindex="0"` でフォーカス可能、`Enter`/`Space`キーでドロワー展開。
- **スクリーンリーダー:** ステータスが読み上げられる (`aria-label="ファイル名 (処理中)"`)。

#### 3.1.4. ロジックの修正例
```php
@props([
    'files' => [],
    'mode' => 'compact', // 'full' | 'compact' | 'icon-only'
    'tenantId' => null,
])

@php
    $isCompact = $mode === 'compact';
    $isIconOnly = $mode === 'icon-only';
    $isFull = $mode === 'full';
    
    // 表示件数リミット調整
    $displayLimit = match($mode) {
        'icon-only' => 3,
        'compact' => 4,
        default => 8,
    };
@endphp
```

### タスク 3.2: `ColumnHtmlService` のリファクタリング

**ファイル:** `app/Services/Ledger/ColumnHtmlService.php`

#### 3.2.1. `getFileHtml` メソッドの刷新
既存の文字列結合ロジック（L260-438、約180行）を廃止し、データを整形して Blade コンポーネントをレンダリングする形に変更します。

**変更方針:**
1. `prepareFilesData()` メソッドを新設し、`$this->attachments` から表示用データ配列を構築。
2. 各ファイルのデータ構造を統一（`id`, `filename`, `mime`, `status`, `downloadUrl`, `thumbnailUrl`, etc.）。
3. `view('components.ledger.attachment-list', [...])→render()` で HTML を生成して返す。
4. `show()` メソッドに `$displayMode` 引数を追加し、呼び出し元から指定可能にする。

**データ構造例:**
```php
[
    'id' => $attachment->id,
    'filename' => $attachment->original_filename ?? basename($attachment->filename),
    'mime' => $attachment->original_mime_type ?? $attachment->mime,
    'status' => $attachment->getDisplayStatus()->value,
    'downloadUrl' => route('file.download', [...]),
    'thumbnailUrl' => $attachment->hasThumbnail() ? route('file.download', [..., 'thumbnail' => true]) : null,
    'size' => $attachment->size,
    'error_message' => $attachment->status->isError() ? $this->getErrorMessage($attachment) : null,
]
```

#### 3.2.2. `show` メソッドへの `$displayMode` 引数追加

**現在のシグネチャ:**
```php
public function show(
    object|array $columnDefineData,
    $initialValue,
    bool $canView = true,
    array $attrs = [],
    string $idPrefix = '',
    bool $asCreate = false,
    ?Ledger $record = null,
    ?string $highlight = null,
    ?string $tenantId = null
): HtmlString
```

**変更案:** `$attrs` 配列に `mode` キーを追加して対応（互換性重視）。

#### 3.2.3. 呼び出し元の対応

**対応必要な箇所:**
1. **`app/Livewire/Ledger/Show.php`**: `$attrs` に `['mode' => 'full']` を設定。
2. **`app/Livewire/Ledger/ModifyColumn.php`**: 同上。
3. **`resources/views/components/ledger/table-row.blade.php`**: `$attrs` に `['mode' => 'icon-only']` を設定。
4. **`RecordsTable.php`**: データ読み込み時に `setAttachmentCollection()` を呼ぶ際に適切なEager Loadingを確認。

#### 3.2.4. Eager Loading戦略の実装

**N+1クエリ防止:**
```php
// Show/ModifyColumn (詳細表示)
$attachments = $ledger->attachedFiles()
    ->with(['creator:id,name', 'modifier:id,name'])
    ->get();

// RecordsTable (一覧表示) - 最小限
$attachments = AttachedFile::whereIn('ledger_id', $ledgerIds)
    ->select('id', 'ledger_id', 'filename', 'hashedbasename', 'mime', 'original_mime_type', 'status', 'size')
    ->get()
    ->groupBy('ledger_id');
```

#### 3.2.5. 後方互換性の担保

**既存機能の維持:**
- ✅ ダイレクトダウンロードリンク（`direct-download-link` クラス）
- ✅ サムネイル表示（画像ファイル）
- ✅ ステータスアイコン（処理中・エラー）
- ✅ リトライボタン（`retryProcessingEvent` イベント発行）
- ✅ テキストプレビューボタン（`showTextPreview` イベント発行）
- ✅ VLM結果プレビューボタン（`showVlmPreviewEvent` イベント発行）
- ✅ OCR後PDFダウンロードリンク（画像ファイル用）

**移行方針:** 既存の `getFileHtml()` ロジックから各機能を段階的に Blade コンポーネントへ移植。

### タスク 3.3: UIモックアップ全分岐実装検証

**目的:** 現在のモックアップは限定的なステータス・MIMEタイプのみを実装しているため、実運用で発生しうる全ての表示パターンを検証します。

#### 3.3.1. ステータス別表示テスト

**検証すべき27ステータス:**
- 処理中: `PENDING_INITIAL_PROCESSING`, `INITIAL_PROCESSING`, `PENDING_OCR`, `OCR_PROCESSING`, `PENDING_VLM`, `VLM_PROCESSING`, `PARALLEL_PROCESSING`
- 完了: `COMPLETED`, `FINALIZED`, `FINALIZED_BY_TIKA`, `FINALIZED_BY_OCR`, `FINALIZED_BY_VLM`
- エラー: `TIKA_FAILED`, `OCR_FAILED`, `VLM_FAILED`, `THUMBNAIL_FAILED`, `PROCESSING_FAILED`
- レガシー: `UPLOADED`, `OPTIMIZED`, `OPTIMIZING`, `OPTIMIZE_FAILED`, `EXTRACTED_AND_SAVED`, `EXTRACTION_FAILED`, `EXTRACTING`, `READY_FOR_FINALIZATION`

**検証方法:**
- モックデータを作成して各ステータスでの表示を確認。
- アイコン、色、アニメーション、ツールチップが適切に表示されることを検証。

#### 3.3.2. MIMEタイプ別表示テスト

**検証すべきファイルタイプ:**
- 画像: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`
- PDF: `application/pdf`
- Office: `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (Word), `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (Excel), `application/vnd.openxmlformats-officedocument.presentationml.presentation` (PowerPoint)
- アーカイブ: `application/zip`, `application/x-7z-compressed`, `application/x-tar`
- テキスト: `text/plain`, `text/csv`, `text/markdown`
- その他: `application/octet-stream`

**検証項目:**
- アイコンの正確性（`fa-file-image`, `fa-file-pdf`, `fa-file-word`, etc.）
- サムネイル表示の可否（画像ファイルのみ）
- ダウンロードリンクの正常動作
- OCR後PDFダウンロードの表示条件（画像ファイルの場合のみ）

#### 3.3.3. 複合条件テスト

**検証すべきケース:**
- エラー状態の画像ファイル（サムネイル生成失敗）
- 処理中の大容量PDFファイル
- VLM処理済みの画像ファイル（信頼度スコア表示）
- 最適化済みPDFファイル（元ファイルダウンロードリンク表示）
- ファイル数が多い場合の「もっと見る」ボタンの動作
- テナント境界をまたぐファイルアクセス（エラー確認）

#### 3.3.4. モード別表示テスト

**各モードでの表示検証:**
- `full`: カード表示、サムネイル、全ボタン表示
- `compact`: リスト表示、アイコン、ダウンロードボタン
- `icon-only`: アイコンのみ、ステータスバッジ、「+N」バッジ

#### 3.3.5. 実装計画への反映

**タイミング:**
- 3.1（コンポーネント強化）完了後に即座に実施。
- 不足している表示パターンを発見した場合、3.1に戻って追加実装。
- 3.2（ColumnHtmlServiceリファクタリング）開始前に全分岐の実装を完了させることで、リグレッションテストの基準を確立。

### タスク 3.4: 統合テストとRPA互換性検証

**ファイル:** `tests/Feature/Services/ColumnHtmlServiceTest.php` (または新規)

#### 3.3.1. 表示モード確認
- `full` モードでカード形式のHTMLが出力されること。
- `compact` モードでリスト形式が出力されること。
- `icon-only` モードで簡易表示が出力されること。

#### 3.3.2. RPA互換性確認
- 出力されたHTML内に `<a href="..." class="direct-download-link ...">` が存在し、`download` 属性が付与されていることを検証。
- ダウンロードURLが正しい形式（`file.download` ルート）であることを確認。

---

## 4. 懸念事項と対応

### 4.1. `ColumnHtmlService` の依存関係
**懸念:** `ColumnHtmlService` は多くの箇所で使われているため、`getFileHtml` の戻り値が `view()->render()` による文字列になっても、呼び出し元（`toHtml()` を期待している箇所）で問題が起きないか確認が必要です。

**対策:**
- `HtmlString` でラップして返すため、基本的には互換性が保たれます。
- ただし、念のため `tests/Unit/Services/Ledger/ColumnHtmlServiceTest.php` の既存テストケースを全て通過することを確認します。
- リファクタリング後に `./vendor/bin/sail test --filter=ColumnHtmlService` を実行し、全テスト成功を確認します。

### 4.2. パフォーマンス
**懸念:** Blade コンポーネントの `render()` は、純粋な文字列結合より若干オーバーヘッドがあります。大量の行を表示する `RecordsTable` でパフォーマンス低下が起きないか懸念されます。

**対策:**
- `icon-only` モードの実装を軽量に保ち、最小限のHTML出力に抑えます。
- 一覧画面では添付ファイル情報のEager Loadingを最適化し、必要最小限のカラムのみをSELECTします（`id`, `ledger_id`, `filename`, `mime`, `status`, `size`のみ）。
- ベンチマークテストを実施し、100件のレコード表示で2秒以内を目標とします。
- 必要に応じてビューキャッシュ（`config/view.php`）の活用を検討します。

### 4.3. 既存の `getFileHtml` ロジックの複雑性
**懸念:** 現在の `getFileHtml` メソッドは、リトライボタン、テキストプレビュー、VLMプレビュー、OCR後PDFダウンロードなど、多様な機能を含んでおり、これらをBlade コンポーネントに完全移行するのは困難です。

**対策:**
- **段階的移行:** まず基本的なファイル表示のみをコンポーネント化し、リトライやプレビュー機能は Phase 4（ドロワー実装）で統合します。
- **機能フラグ:** `$attrs['legacy_mode'] = true` のような形で、既存のHTML生成ロジックに戻れるフォールバックを用意します。
- **優先度付け:** 詳細画面（Show）→編集画面（ModifyColumn）→一覧画面（RecordsTable）の順に移行し、各段階でテストを実施します。

### 4.4. テスト戦略の不足
**懸念:** 現在の `ColumnHtmlServiceTest.php` は基本的なHTML出力テストのみで、全ステータス・全MIMEタイプのカバレッジが不足しています。

**対策:**
- **新規テストケース追加:** 
  - `tests/Feature/Services/ColumnHtmlServiceAttachmentDisplayTest.php` を作成し、各ステータス・MIMEタイプでの表示を検証します。
  - データプロバイダを活用して、27ステータス × 主要MIMEタイプのマトリクステストを実施します。
- **視覚的回帰テスト:** 
  - Percy.io やChromatic等のビジュアルリグレッションテストツールの導入を検討します（Phase 3では手動確認で代用可）。
- **RPA互換性テスト:** 
  - `direct-download-link` クラスを持つ `<a>` タグが正しい `href` 属性を持つことをアサートします。
  - DOM解析ツール（PHPUnit の `DOMDocument` または Laravel Dusk）で検証します。

### 4.5. レガシーステータスの混在
**懸念:** `AttachedFileStatus` Enum には、新しいステータス（`PARALLEL_PROCESSING`, `FINALIZED_BY_TIKA` 等）とレガシーステータス（`UPLOADED`, `EXTRACTING` 等）が混在しており、将来的な統合が必要です。

**対策:**
- **Phase 3では現状維持:** 全27ステータスに対応し、既存データの表示を保証します。
- **Phase 5以降で統合:** レガシーステータスを新ステータスへマイグレーションする計画を策定します（別ドキュメント化）。
- **`getDisplayStatus()` メソッドの活用:** このメソッドがレガシーステータスを新ステータスへマッピングしているため、表示ロジックでは `getDisplayStatus()` の戻り値のみを利用します。

### 4.6. タイムゾーンとタイムスタンプの扱い
**懸念:** `tika_processed_at`, `ocr_processed_at` 等のタイムスタンプが UTC で保存されており、表示時のローカライゼーションが必要です。

**対策:**
- **Carbon のフォーマット:** `$attachment->tika_processed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s')` で表示します。
- **多言語対応:** `diffForHumans()` を活用し、「3時間前」のような相対表記を優先します。
- **タイムゾーン設定:** `config/app.php` の `timezone` 設定がテナントごとに異なる場合は、テナントのタイムゾーンを適用します。

---

## 5. 品質保証チェックリスト

Phase 3完了時に以下の項目を全て満たすことを確認します。

### 5.1. 機能確認
- [ ] `icon-only` モードでファイルが正しく表示される
- [ ] `compact` モードでファイルが正しく表示される（既存）
- [ ] `full` モードでファイルが正しく表示される（既存）
- [ ] 全27ステータスが適切なアイコン・色・アニメーションで表示される
- [ ] 全主要MIMEタイプが適切なアイコンで表示される
- [ ] ダウンロードリンクが正常に動作する
- [ ] サムネイル表示が画像ファイルで正常動作する
- [ ] 「もっと見る」ボタンが正常動作する
- [ ] エラー状態のファイルでエラーメッセージが表示される

### 5.2. テスト確認
- [ ] `ColumnHtmlServiceTest.php` の全テストが成功する
- [ ] 新規追加の統合テストが成功する
- [ ] RPA互換性テストが成功する
- [ ] `./vendor/bin/sail pint` でコードスタイルエラーがない
- [ ] N+1クエリが発生していない（Laravel Debugbarで確認）

### 5.3. ドキュメント確認
- [ ] Phase 3 完了報告書を作成する
- [ ] 移行した機能の一覧を文書化する
- [ ] 後方互換性の維持事項を記録する
- [ ] Phase 4への引き継ぎ事項を明確化する

---

## 6. 参考資料

- **既存実装:**
  - `app/Services/Ledger/ColumnHtmlService.php` (L260-438)
  - `resources/views/components/ledger/attachment-list.blade.php`
  - `app/Models/AttachedFile.php` (`getDisplayStatus()` メソッド)
  - `app/Enums/AttachedFileStatus.php` (`icon()`, `colorClass()`, `tooltip()` メソッド)

- **関連ドキュメント:**
  - [Phase 2詳細計画: モデル拡張](/docs/work/ui-ux/attachment/2025-12-16_phase2_model_extension_plan.md)
  - [FileInspector データ構造設計書](/docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md)
  - [添付ファイルUI改善計画](/docs/work/ui-ux/attachment/2025-12-13_attachment-ui-improvement-plan.md)

- **テスト参考:**
  - `tests/Unit/Services/Ledger/ColumnHtmlServiceTest.php`
  - `tests/Unit/Models/AttachedFileTest.php` (`getDisplayStatus()` のテスト)

- **Copilot指示書:**
  - `.github/copilot-instructions.md` (セクション「重要な実装教訓」)
