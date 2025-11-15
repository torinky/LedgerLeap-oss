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

### 3.1. 方針1: `selectBestContent`の判定ロジック修正 ✅ 既に実装済み

**対象:** `app/Console/Commands/Ledger/FinalizeAttachedFileProcessing.php`

**現状:** 既に正しく実装されています（168-179行目）
```php
if ($file->ocr_processed_at) {
    $pdfHashedbasename = pathinfo($file->hashedbasename, PATHINFO_FILENAME).'.pdf';
    $ocrText = $file->ledger?->content_attached[$file->column_id][$pdfHashedbasename]['meta']['content'] ?? null;
    
    if (! empty($ocrText)) {
        return [
            'source' => 'ocr',
            'text' => $ocrText,
        ];
    }
}
```

**追加修正が必要な点:**
- **問題:** PDFファイルの場合、OCRは`document.pdf` → `document.pdf`と同名で処理されるため、`.pdf`付きキーは存在しない
- **解決策:** 元ファイルの種類を判定し、画像ファイルの場合のみ`.pdf`付きキーをチェックする

**修正コード:**
```php
private function selectBestContent(AttachedFile $file): array
{
    // 1. VLM結果（最優先）
    if ($file->vlm_processed_at && !empty($file->vlm_markdown)) {
        return ['source' => 'vlm', 'text' => $file->vlm_markdown];
    }
    
    // 2. OCR結果の厳密確認
    if ($file->ocr_processed_at) {
        $originalExt = pathinfo($file->hashedbasename, PATHINFO_EXTENSION);
        $isImageFile = str_starts_with($file->original_mime_type ?? '', 'image/');
        
        // 画像ファイルの場合のみ .pdf キーをチェック
        if ($isImageFile && $originalExt !== 'pdf') {
            $pdfHashedbasename = pathinfo($file->hashedbasename, PATHINFO_FILENAME) . '.pdf';
            $ocrText = $file->ledger?->content_attached[$file->column_id][$pdfHashedbasename]['meta']['content'] ?? null;
            
            if (!empty($ocrText)) {
                return ['source' => 'ocr', 'text' => $ocrText];
            }
        }
        
        // PDFファイルの場合は元のキーをチェック
        // OCRは最適化のみなので、Tika再処理で更新されたテキストを使用
    }
    
    // 3. Tika結果（フォールバック）
    $tikaText = $this->extractTikaTextFromContentAttached($file);
    return ['source' => 'tika', 'text' => $tikaText ?? ''];
}
```

**効果:**
- 画像ファイル（`.jpg` → `.pdf`）: OCR結果を正しく検出
- PDFファイル（`.pdf` → `.pdf`）: Tika再処理結果を使用（OCRは最適化のみ）

### 3.2. 方針2: `hasPreviewableText`の堅牢化 ✅ 既に実装済み

**対象:** `app/Models/AttachedFile.php`

**現状:** 既に正しく実装されています（282-296行目）

**追加修正は不要です。**

### 3.3. 方針3: `tenantId`の取得方法変更 ✅ 既に実装済み

**対象:** `app/Livewire/AttachedFile/TextPreviewModal.php`

**現状:** 既に正しく実装されています（68行目）
```php
$this->tenantId = $file->tenant_id;
```

**追加修正は不要です。**

### 3.4. 方針4: `getOcrTikaFormattedText`の修正 🔴 新規修正が必要

**対象:** `app/Models/AttachedFile.php`

**問題:** `hasPreviewableText`は`.pdf`付きキーもチェックするが、`getOcrTikaFormattedText`は元のキーしか見ない

**修正コード:**
```php
private function getOcrTikaFormattedText(): ?string
{
    if (!$this->relationLoaded('ledger') || !$this->ledger) {
        return null;
    }
    
    $columnId = $this->column_id;
    $hashedbasename = $this->hashedbasename;
    
    // OCRの場合は .pdf キーもチェック
    if ($this->finalized_source === 'ocr') {
        $originalExt = pathinfo($hashedbasename, PATHINFO_EXTENSION);
        if ($originalExt !== 'pdf') {
            $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME) . '.pdf';
            $text = $this->ledger->content_attached[$columnId][$pdfHashedbasename]['meta']['content'] ?? null;
            if ($text) {
                return "```
{$text}
```";
            }
        }
    }
    
    // 元のキーをチェック
    $text = $this->ledger->content_attached[$columnId][$hashedbasename]['meta']['content'] ?? null;
    return $text ? "```
{$text}
```" : null;
}
```

**効果:** OCRで変換されたファイル（`.jpg` → `.pdf`）のテキストが正しく取得できる

---

## 4. テスト拡充計画

### 4.1. `FinalizeAttachedFileProcessingTest`の拡充

**対象:** `tests/Feature/Console/FinalizeAttachedFileProcessingTest.php`

**追加テストケース:**

#### ケース1: 画像ファイルのOCR処理
```php
#[Test]
public function command_correctly_selects_ocr_for_image_files()
{
    // 画像ファイル（.jpg → .pdf）のOCR結果を正しく検出
    $file = AttachedFile::create([
        'hashedbasename' => 'test.jpg',
        'original_mime_type' => 'image/jpeg',
        'ocr_processed_at' => now(),
        // ...
    ]);
    
    $ledger->content_attached = [
        0 => [],
        1 => [
            'test.pdf' => [ // OCR後のキー
                'meta' => ['content' => 'OCR extracted text from image'],
            ],
        ],
    ];
    
    $this->artisan('ledger:finalize-processing')->assertExitCode(0);
    
    $file->refresh();
    $this->assertEquals('ocr', $file->finalized_source);
}
```

#### ケース2: PDFファイルのOCR処理（skip-text）
```php
#[Test]
public function command_correctly_handles_pdf_with_skip_text_ocr()
{
    // PDFファイル（.pdf → .pdf）のOCR処理（最適化のみ）
    $file = AttachedFile::create([
        'hashedbasename' => 'document.pdf',
        'original_mime_type' => 'application/pdf',
        'ocr_processed_at' => now(),
        // ...
    ]);
    
    $ledger->content_attached = [
        0 => [],
        1 => [
            'document.pdf' => [ // 元のキーが上書きされる
                'meta' => ['content' => 'Tika re-extracted text after OCR optimization'],
            ],
        ],
    ];
    
    $this->artisan('ledger:finalize-processing')->assertExitCode(0);
    
    $file->refresh();
    $this->assertEquals('tika', $file->finalized_source); // OCRは最適化のみなのでTika扱い
}
```

#### ケース3: VLM失敗、OCR成功（画像ファイル）
```php
#[Test]
public function command_selects_ocr_when_vlm_fails_for_image()
{
    // VLM失敗、OCR成功のケース
    $file = AttachedFile::create([
        'hashedbasename' => 'photo.jpg',
        'original_mime_type' => 'image/jpeg',
        'vlm_failed_at' => now(),
        'ocr_processed_at' => now(),
        // ...
    ]);
    
    $ledger->content_attached = [
        0 => [],
        1 => [
            'photo.pdf' => [
                'meta' => ['content' => 'OCR fallback text'],
            ],
        ],
    ];
    
    $this->artisan('ledger:finalize-processing')->assertExitCode(0);
    
    $file->refresh();
    $this->assertEquals('ocr', $file->finalized_source);
}
```

### 4.2. `AttachedFileTest`の拡充

**対象:** `tests/Unit/Models/AttachedFileTest.php`

**追加テストケース:**

```php
#[Test]
public function has_previewable_text_returns_true_for_ocr_with_pdf_key()
{
    $ledger = Ledger::factory()->create([
        'content_attached' => [
            0 => [],
            1 => [
                'image.pdf' => [ // OCR後のキー
                    'meta' => ['content' => 'OCR text'],
                ],
            ],
        ],
    ]);
    
    $file = AttachedFile::factory()->make([
        'ledger_id' => $ledger->id,
        'column_id' => 1,
        'hashedbasename' => 'image.jpg', // 元のファイル名
        'processing_finalized_at' => now(),
        'finalized_source' => 'ocr',
    ]);
    
    $file->setRelation('ledger', $ledger);
    $this->assertTrue($file->hasPreviewableText());
}

#[Test]
public function get_previewable_text_returns_ocr_text_for_image_files()
{
    $ledger = Ledger::factory()->create([
        'content_attached' => [
            0 => [],
            1 => [
                'scan.pdf' => [
                    'meta' => ['content' => 'OCR extracted text'],
                ],
            ],
        ],
    ]);
    
    $file = AttachedFile::factory()->make([
        'ledger_id' => $ledger->id,
        'column_id' => 1,
        'hashedbasename' => 'scan.jpg',
        'processing_finalized_at' => now(),
        'finalized_source' => 'ocr',
    ]);
    
    $file->setRelation('ledger', $ledger);
    $text = $file->previewable_text;
    $this->assertStringContainsString('OCR extracted text', $text);
}
```

### 4.3. `TextPreviewModalTest`の拡充

**対象:** `tests/Feature/Livewire/AttachedFile/TextPreviewModalTest.php`

**追加テストケース:**

```php
#[Test]
public function it_generates_correct_download_urls_for_vlm_files()
{
    $file = AttachedFile::factory()->create([
        'processing_finalized_at' => now(),
        'finalized_source' => 'vlm',
        'vlm_markdown' => '# VLM Content',
    ]);
    
    Livewire::test(TextPreviewModal::class)
        ->dispatch('showTextPreview', attachedFileId: $file->id)
        ->assertSet('showModal', true)
        ->assertSee(route('files.download-vlm', [
            'tenant' => $file->tenant_id,
            'attachedFile' => $file->id,
            'format' => 'markdown',
        ]));
}

#[Test]
public function it_disables_download_buttons_for_non_vlm_files()
{
    $file = AttachedFile::factory()->create([
        'processing_finalized_at' => now(),
        'finalized_source' => 'ocr',
        'contain_content' => true,
    ]);
    
    Livewire::test(TextPreviewModal::class)
        ->dispatch('showTextPreview', attachedFileId: $file->id)
        ->assertSet('showModal', true)
        ->assertSee('disabled'); // ダウンロードボタンが無効化されている
}
```

---

## 5. 結論

### 5.1. 修正が必要な箇所

**既に実装済み:**
- ✅ `TextPreviewModal`のテナントID取得（方針3）
- ✅ `hasPreviewableText`のロジック（方針2）

**新規修正が必要:**
- 🔴 `selectBestContent`の画像/PDF判定ロジック追加（方針1の改善）
- 🔴 `getOcrTikaFormattedText`のOCR対応（方針4）

### 5.2. ファイルタイプ別の処理フロー

| ファイルタイプ | OCR処理 | ファイル名変更 | キー | finalized_source |
|--------------|---------|---------------|------|------------------|
| 画像（JPG/PNG） | PDF化+テキスト抽出 | ✅ `image.jpg` → `image.pdf` | `content_attached[1]['image.pdf']` | `ocr` (VLM失敗時) |
| テキスト付きPDF | 最適化のみ（`--skip-text`） | ❌ `doc.pdf` → `doc.pdf` | `content_attached[2]['doc.pdf']` | `tika` |
| 画像のみPDF | PDF化+テキスト抽出 | ❌ `scan.pdf` → `scan.pdf` | `content_attached[3]['scan.pdf']` | `ocr` (VLM失敗時) |

### 5.3. 重要な知見

**Phase5/6で判明した重要事項:**
1. **OCRの挙動:** 画像ファイルは`.pdf`に変換されるが、PDFファイルは同名のまま最適化のみ
2. **キー管理:** `content_attached`のキーは画像ファイルのみ変更される
3. **finalized_sourceの判定:** 元ファイルのMIMEタイプを参照する必要がある
4. **AsColumnArrayJson制約:** `data_get()`は使用不可、直接配列アクセス必須

### 5.4. 次のステップ

1. **Week 1:** `selectBestContent`と`getOcrTikaFormattedText`の修正
2. **Week 1-2:** テストケースの追加と実行
3. **Week 2:** ドキュメント更新（本Hotfix計画書の完了報告書化）
4. **Week 2:** Phase7（次期機能）への知見引き継ぎ

上記の修正とテスト拡充により、Phase6で報告された不具合を完全に解消し、Phase5並列処理アーキテクチャとの整合性を高め、将来の類似問題発生を防ぐことができます。
