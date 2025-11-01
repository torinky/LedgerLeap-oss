# VLM/OCR結果ダウンロードUI機能追加計画

**作成日:** 2025年10月25日  
**対象ドキュメント:** 2025-10-23_vlm-ocr-and-indexing-strategy-review.md  
**追加セクション:** 3.2 ユーザーへの処理結果提供戦略

---

## 概要

VLM/OCR処理によって生成される複数の出力形式（Markdown、JSON、プレーンテキスト等）を、ユーザーが直感的にダウンロード・プレビューできるUI機能を追加する。

## UI要件とユースケース

### ユースケース1: 台帳詳細画面での添付ファイル表示

現行の添付ファイル表示エリアに、処理結果のダウンロードオプションを追加:

```
┌─────────────────────────────────────────────────┐
│ 📎 添付ファイル (3件)                          │
├─────────────────────────────────────────────────┤
│ [📄] invoice_2025_10.pdf (2.3 MB)              │
│   ├─ 処理状態: ✅ VLM処理完了 (PaddleOCR-VL)  │
│   │                                             │
│   └─ ダウンロード:                              │
│       [📥 元ファイル] ← 既存                    │
│       [📑 OCR付きPDF] ← 既存                    │
│       [📝 Markdown] ← ★新規（VLM結果）         │
│       [🔢 構造化データ (JSON)] ← ★新規          │
│       [📋 抽出テキスト (TXT)] ← ★新規          │
│                                                 │
│ [🖼️] receipt_scan.jpg (1.8 MB)                 │
│   ├─ 処理状態: ⏳ VLM処理中... (85%)           │
│   └─ ダウンロード: [📥 元ファイル]             │
│                                                 │
│ [📊] report.xlsx (5.1 MB)                       │
│   ├─ 処理状態: ⚠️ VLM処理失敗                  │
│   │   理由: ファイルサイズ上限超過              │
│   └─ ダウンロード: [📥 元ファイル] [🔄 再処理] │
└─────────────────────────────────────────────────┘
```

### ユースケース2: Markdown/JSON プレビュー

ダウンロード前にブラウザ内でプレビュー表示:

```
┌─────────────────────────────────────────────────┐
│ [📝 Markdownプレビュー]           [✖️ 閉じる]  │
├─────────────────────────────────────────────────┤
│ # 請求書                                        │
│                                                 │
│ **請求番号:** INV-2025-001                      │
│ **発行日:** 2025年10月23日                      │
│ **請求先:** 株式会社A商事 御中                  │
│                                                 │
│ ## 請求明細                                     │
│                                                 │
│ | 品名   | 数量 | 単価    | 金額     |         │
│ |--------|------|---------|----------|         │
│ | 製品A  | 10   | ¥1,500  | ¥15,000  |         │
│ | 製品B  | 5    | ¥3,000  | ¥15,000  |         │
│                                                 │
│ **合計:** ¥30,000                               │
│                                                 │
│ [💾 Markdownファイルとしてダウンロード]         │
│ [📋 クリップボードにコピー]                     │
└─────────────────────────────────────────────────┘
```

### ユースケース3: 構造化データの台帳入力自動化

VLMで抽出したエンティティを、台帳フォームに自動入力:

```
┌─────────────────────────────────────────────────┐
│ 📋 請求書台帳 - 新規作成                        │
├─────────────────────────────────────────────────┤
│ 添付ファイルから情報を抽出しました:             │
│ [✅ 自動入力を適用] [❌ キャンセル]             │
├─────────────────────────────────────────────────┤
│ 請求番号: [INV-2025-001] ← 自動入力済み         │
│ 発行日:   [2025-10-23  ] ← 自動入力済み         │
│ 取引先:   [株式会社A商事] ← 自動入力済み (要確認)│
│ 金額:     [¥30,000     ] ← 自動入力済み         │
│                                                 │
│ ⚠️ 確認が必要な項目: 取引先 (信頼度: 87%)      │
│                                                 │
│ [💾 この内容で保存]                             │
└─────────────────────────────────────────────────┘
```

## ルート設計

既存の `AttachedFileDownloadController` を拡張:

```php
// routes/web.php に追加

Route::middleware(['auth', 'verified'])->group(function () {
    // 既存ルート
    Route::get('/files/{attachedFile}/download', [AttachedFileDownloadController::class, 'download'])
        ->name('files.download');
    
    // ★ 新規: VLM結果のダウンロード
    Route::get('/files/{attachedFile}/download-vlm', [AttachedFileDownloadController::class, 'downloadVlm'])
        ->name('files.download.vlm');
    
    // ★ 新規: Markdown プレビュー
    Route::get('/files/{attachedFile}/preview-markdown', [AttachedFileDownloadController::class, 'previewMarkdown'])
        ->name('files.preview.markdown');
    
    // ★ 新規: JSON プレビュー
    Route::get('/files/{attachedFile}/preview-json', [AttachedFileDownloadController::class, 'previewJson'])
        ->name('files.preview.json');
    
    // ★ 新規: VLM再処理トリガー
    Route::post('/files/{attachedFile}/reprocess-vlm', [AttachedFileDownloadController::class, 'reprocessVlm'])
        ->name('files.reprocess.vlm');
});
```

## コントローラー実装

### downloadVlm() - VLM結果のダウンロード

```php
/**
 * VLM処理結果をダウンロード
 * 
 * @param Request $request
 * @param AttachedFile $attachedFile
 * @return Response
 */
public function downloadVlm(Request $request, AttachedFile $attachedFile)
{
    Gate::authorize('view', $attachedFile->ledger);
    
    $vlmData = $this->getVlmStructuredData($attachedFile);
    
    if (!$vlmData) {
        abort(404, 'VLM結果が見つかりません');
    }
    
    $format = $request->query('format', 'markdown');
    
    switch ($format) {
        case 'markdown':
            return $this->downloadMarkdown($attachedFile, $vlmData);
        case 'json':
            return $this->downloadJson($attachedFile, $vlmData);
        case 'text':
            return $this->downloadText($attachedFile, $vlmData);
        default:
            abort(400, 'Invalid format');
    }
}
```

### ヘルパーメソッド

```php
protected function downloadMarkdown(AttachedFile $file, array $vlmData): Response
{
    $markdown = $vlmData['markdown'] ?? '';
    $filename = pathinfo($file->original_filename, PATHINFO_FILENAME) . '_vlm.md';
    
    activity()
        ->performedOn($file)
        ->causedBy(auth()->user())
        ->event('downloaded_vlm_markdown')
        ->log("Downloaded VLM markdown: {$filename}");
    
    return response($markdown, 200)
        ->header('Content-Type', 'text/markdown; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
}

protected function downloadJson(AttachedFile $file, array $vlmData): Response
{
    $filename = pathinfo($file->original_filename, PATHINFO_FILENAME) . '_vlm.json';
    
    activity()
        ->performedOn($file)
        ->causedBy(auth()->user())
        ->event('downloaded_vlm_json')
        ->log("Downloaded VLM JSON: {$filename}");
    
    return response()->json($vlmData, 200, [
        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

protected function getVlmStructuredData(AttachedFile $attachedFile): ?array
{
    $ledger = $attachedFile->ledger;
    $contentAttached = $ledger->content_attached ?? [];
    
    $columnId = $attachedFile->column_id;
    $hashedBasename = $attachedFile->hashedbasename;
    
    return $contentAttached[$columnId][$hashedBasename]['meta']['vlm_structured'] ?? null;
}
```

## AttachedFileモデル拡張

```php
// app/Models/AttachedFile.php に追加

public function hasVlmResult(): bool
{
    return !empty($this->getVlmStructuredData());
}

public function isVlmProcessing(): bool
{
    return $this->status === AttachedFileStatus::VLM_PROCESSING;
}

public function isVlmFailed(): bool
{
    return $this->status === AttachedFileStatus::VLM_FAILED;
}

public function getVlmStructuredData(): ?array
{
    $contentAttached = $this->ledger->content_attached ?? [];
    return $contentAttached[$this->column_id][$this->hashedbasename]['meta']['vlm_structured'] ?? null;
}

public function getVlmModelAttribute(): ?string
{
    $vlmData = $this->getVlmStructuredData();
    return $vlmData['model'] ?? null;
}

public function getVlmConfidenceAttribute(): ?int
{
    $vlmData = $this->getVlmStructuredData();
    $confidence = $vlmData['confidence'] ?? null;
    return $confidence ? (int)($confidence * 100) : null;
}
```

## Bladeコンポーネント拡張

`resources/views/components/ledger/form/files.blade.php` に追加:

```blade
{{-- VLM結果ダウンロードドロップダウン --}}
@if($file->hasVlmResult())
<div class="dropdown">
    <label tabindex="0" class="btn btn-xs btn-primary">
        <i class="fas fa-magic"></i> VLM結果
        <i class="fas fa-caret-down"></i>
    </label>
    <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
        <li>
            <a href="#" onclick="previewMarkdown('{{ route('files.preview.markdown', $file) }}')">
                <i class="fas fa-eye"></i> Markdownプレビュー
            </a>
        </li>
        <li>
            <a href="{{ route('files.download.vlm', ['attachedFile' => $file, 'format' => 'markdown']) }}">
                <i class="fas fa-file-alt"></i> Markdownダウンロード
            </a>
        </li>
        <li>
            <a href="{{ route('files.download.vlm', ['attachedFile' => $file, 'format' => 'json']) }}">
                <i class="fas fa-code"></i> JSON構造化データ
            </a>
        </li>
        <li>
            <a href="{{ route('files.download.vlm', ['attachedFile' => $file, 'format' => 'text']) }}">
                <i class="fas fa-file-text"></i> プレーンテキスト
            </a>
        </li>
    </ul>
</div>
@endif
```

## ユーザーフィードバック収集

```blade
{{-- VLM結果の品質評価UI --}}
@if($file->hasVlmResult())
<div class="mt-2 p-2 bg-base-200 rounded">
    <p class="text-xs text-gray-600">VLM処理結果の品質を評価してください：</p>
    <div class="flex gap-2 mt-1">
        <button wire:click="rateVlmResult({{ $file->id }}, 'good')" 
                class="btn btn-xs btn-success">
            <i class="fas fa-thumbs-up"></i> 良い
        </button>
        <button wire:click="rateVlmResult({{ $file->id }}, 'bad')" 
                class="btn btn-xs btn-error">
            <i class="fas fa-thumbs-down"></i> 悪い
        </button>
    </div>
</div>
@endif
```

## 実装チェックリスト

### フェーズ1: 基本機能（〜2週間）
- [ ] AttachedFileStatusへのVLM関連enum追加
- [ ] AttachedFileモデルへのヘルパーメソッド追加
- [ ] AttachedFileDownloadControllerへのダウンロードメソッド追加
- [ ] ルート定義追加
- [ ] Bladeコンポーネント拡張

### フェーズ2: プレビュー機能（〜1週間）
- [ ] Markdownプレビュー用モーダルコンポーネント作成
- [ ] JSONビューアー実装
- [ ] クリップボードコピー機能

### フェーズ3: 自動入力機能（〜2週間）
- [ ] エンティティ抽出結果のマッピングロジック
- [ ] 自動入力プレビューUI
- [ ] ユーザー修正フロー

### フェーズ4: 品質改善（継続）
- [ ] ユーザーフィードバック収集機能
- [ ] VLM処理結果の品質分析ダッシュボード
- [ ] モデル選定の最適化

---

**このセクションを元のドキュメント（2025-10-23_vlm-ocr-and-indexing-strategy-review.md）の「3.2」として挿入してください。**
