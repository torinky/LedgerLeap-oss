# Phase6 Hotfix: プレビュー機能の不具合修正とテスト拡充 計画書

**作成日:** 2025-11-15
**プロジェクト:** VLM/RAG統合 - Phase6 Hotfix
**ステータス:** 計画確定
**関連ドキュメント:**
- [Phase6: 抽出テキストプレビュー機能実装 計画書](./2025-11-08_phase6-text-preview-modal-plan.md)
- [Phase5: VLM/OCR並列処理統合 実装報告書](./2025-11-08_phase5-implementation-report.md)

---

## 1. 概要

### 1.1. 問題の概要
Phase6で実装された「抽出テキストプレビュー機能」において、以下の2つの不具合が報告された。

1.  **プレビューボタンの不表示:** OCR不要のPDFファイル（Tikaでテキスト抽出）の場合、プレビューボタンが表示されない。
2.  **ダウンロードボタンの不機能:** VLMで処理されたファイルにも関わらず、プレビューモーダル内のダウンロードボタンが機能しない。

### 1.2. 目的
上記不具合の根本原因を特定・修正し、再発防止のためのテストを拡充する。また、修正内容が既存のアーキテクチャ（特にPhase5の並列処理）と矛盾しないことを保証する。

---

## 2. 根本原因分析

### 2.1. プレビューボタン不表示問題

- **直接原因:** `AttachedFile::hasPreviewableText()` が `false` を返していた。
- **根本原因:** `FinalizeAttachedFileProcessing` コマンドの `selectBestContent` メソッドが、**Tikaで抽出されたテキストをOCRの結果として誤判定**し、`finalized_source` を `'ocr'` に設定していた。しかし、`hasPreviewableText` はOCR処理後のファイル名（`...pdf`）を期待していたため、キーの不一致が発生しテキストを見つけられなかった。

### 2.2. ダウンロードボタン不機能問題

- **直接原因:** ダウンロードボタンの `href` 属性が `'#'` になっていた。
- **根本原因:** `TextPreviewModal` コンポーネント内で、URL生成に必要な**テナントID (`$tenantId`) が `null` になっていた**。これは、Livewireモーダルのコンテキストで `tenant()` ヘルパーが正しく機能しない場合があるため。

### 2.3. `finalized_source` 誤判定の深掘り

- **背景:** Phase5の並列処理アーキテクチャでは、画像やPDFはVLMとOCRの両方のジョブがディスパッチされる。
- **問題シナリオ:**
    1. テキスト付きPDFがアップロードされる。
    2. `OcrAndOptimizeFile` ジョブは `--skip-text` により実質的なOCRを実行しないが、`ocr_processed_at` タイムスタンプは更新される。
    3. `FinalizeAttachedFileProcessing` は `ocr_processed_at` の存在をもって「OCR処理成功」と判断し、Tikaが抽出したテキストをOCRの結果として採用してしまう。
- **課題:** `content_attached` 内に、テキストの由来（TikaかOCRか）を明確に示す情報が欠落している。

---

## 3. 修正方針と実装計画

### 3.1. 方針1: `finalized_source` の判定ロジック修正

**対象:** `app/Console/Commands/Ledger/FinalizeAttachedFileProcessing.php`

**修正内容:** `selectBestContent` メソッドを修正し、OCRの結果と見なす条件を厳格化する。
- **新ロジック:** OCR処理によって**ファイル名が変更された場合**（例: `.jpg` → `.pdf`）にのみ、その結果を「OCRのテキスト」として採用する。元のファイルがPDFの場合は、Tikaの結果と区別がつかないため、Tikaとして扱う。
- **効果:** これにより、テキスト付きPDFが誤って `finalized_source = 'ocr'` となることを防ぎ、`'tika'` として正しく分類される。

### 3.2. 方針2: `hasPreviewableText` の堅牢化

**対象:** `app/Models/AttachedFile.php`

**修正内容:** `hasPreviewableText` メソッドを修正し、`finalized_source` の値に応じて、チェックするキーを動的に変更する。
- **新ロジック:**
    - `source` が `'ocr'` の場合: 元のファイル名と、OCR処理後の `.pdf` 付きファイル名の両方のキーをチェックする。
    - `source` が `'tika'` の場合: 元のファイル名のキーのみをチェックする。
- **効果:** `finalized_source` が（修正前のロジックで）誤判定された場合でも、UIが正しくテキストの存在を検知できるようになる。

### 3.3. 方針3: `tenantId` の取得方法変更

**対象:** `app/Livewire/AttachedFile/TextPreviewModal.php`

**修正内容:** `show()` メソッド内の `tenantId` の取得方法を変更する。
- **旧:** `$this->tenantId = tenant('id');`
- **新:** `$this->tenantId = $file->tenant_id;`
- **効果:** Livewireのコンテキストに依存せず、`AttachedFile` モデルから直接テナントIDを取得することで、URL生成が確実に行われるようになる。

---

## 4. テスト拡充計画

### 4.1. `FinalizeAttachedFileProcessingTest` の拡充

**対象:** `tests/Feature/Console/FinalizeAttachedFileProcessingTest.php`

**追加テストケース:**
- **`command_correctly_selects_tika_when_ocr_is_empty`:**
    - **シナリオ:** VLMが失敗し、OCRは完了したがテキストは空（Tikaテキストのみ存在）という、今回の問題の核心となるケースをシミュレートする。
    - **アサーション:** `finalized_source` が正しく `'tika'` と判定されることを検証する。

### 4.2. `AttachedFileTest` の拡充

**対象:** `tests/Unit/Models/AttachedFileTest.php`

**追加テストケース:**
- `has_previewable_text_returns_true_for_ocr_with_original_basename`: `finalized_source` が `'ocr'` で、元のファイル名キーにテキストがある場合に `true` を返すことを検証。
- `has_previewable_text_returns_true_for_ocr_with_pdf_basename`: `finalized_source` が `'ocr'` で、`.pdf` 付きのキーにテキストがある場合に `true` を返すことを検証。

### 4.3. `TextPreviewModalTest` の拡充

**対象:** `tests/Feature/Livewire/AttachedFile/TextPreviewModalTest.php`

**追加テストケース:**
- **`it_generates_correct_download_urls`:**
    - **シナリオ:** VLMファイルを表示した際に、モーダルがレンダリングされる。
    - **アサーション:** レンダリングされたHTMLに、`tenant_id` と `attachedFile->id` を含む正しいダウンロードURL (`route('files.download-vlm', ...)` の結果) が含まれていることを `assertSee` で検証する。

---

## 5. 結論

上記の方針に基づき修正とテスト拡充を行うことで、報告された不具合を解消し、システムの堅牢性を向上させる。
特に、`finalized_source` の判定ロジックを改善することで、Phase5で導入された並列処理アーキテクチャとの整合性を高め、将来の類似の問題発生を防ぐ。
