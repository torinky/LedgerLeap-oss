# サムネイル生成ジョブの競合解消とロジック改善計画

**作成日:** 2025年11月22日
**ステータス:** 計画中

## 1. 概要
本ドキュメントは、OCR処理完了後のファイルに対してサムネイルが生成されない問題を解消するための、`GenerateThumbnail` ジョブの改修計画を定義します。

## 2. 現状と課題

### 2.1 発生している問題
*   OCR処理 (`OcrAndOptimizeFile`) が先行して完了した画像ファイル (ID: 6) において、サムネイルが生成されない事象が発生。
*   `GenerateThumbnail` ジョブ実行時点で、メインのファイルパス (`path`) が既にOCR済みのPDF (`application/pdf`) に更新されており、「画像ファイルではない」と判定されて処理がスキップされている。
*   スキップされたにもかかわらず、ステータスが `completed` に更新され、エラーとして検知されない。

### 2.2 原因
1.  **ソース特定ロジックの不備**: `GenerateThumbnail` ジョブが現在の `path` と `mime` のみを参照しており、OCR処理で退避された「元画像 (`original_file_path`)」を確認していない。
2.  **ステータス更新の競合**: サムネイル生成ジョブが、他のプロセス（OCR, VLM）の進行状況を考慮せず、安易にステータスを上書きしている。

## 3. 解決策

### 3.1 ソースファイルの特定ロジック改善（元画像へのフォールバック）
現在のファイルが画像でない場合（例: OCR済みPDF）、`original_file_path` を確認し、それが画像であればソースとして使用するようロジックを変更します。

**変更前:**
```php
// 画像ファイル以外はスキップ
if (! Str::startsWith($attachedFile->mime, 'image/')) {
    // ... スキップ処理 ...
    return;
}
```

**変更後の方針:**
1.  現在の `mime` が `image/` で始まる場合 → 現在の `path` を使用。
2.  上記以外で、`original_file_path` が存在し、かつ `original_mime_type` が `image/` で始まる場合 → `original_file_path` を使用。
3.  どちらも該当しない場合 → スキップ。

### 3.2 ステータス更新ロジックの適正化（競合防止）
サムネイル生成完了時やスキップ時に、ステータスを `completed` に更新する処理を厳格化します。
OCR処理、VLM処理、または並列処理 (`PARALLEL_PROCESSING`) が進行中の場合は、サムネイル生成ジョブによるステータス更新を行わないようにします。

**制御対象ステータス:**
以下のステータスの場合は、サムネイル生成完了後もステータスを更新しない（または元のステータスを維持する）。
*   `AttachedFileStatus::PENDING_OCR`
*   `AttachedFileStatus::OCR_PROCESSING`
*   `AttachedFileStatus::PENDING_VLM`
*   `AttachedFileStatus::VLM_PROCESSING`
*   `AttachedFileStatus::PARALLEL_PROCESSING`

## 4. 実装詳細計画

### 対象ファイル
`app/Jobs/Ledger/GenerateThumbnail.php`

### 主な変更点

#### 1. ソース特定ロジックの追加
```php
$sourcePath = $attachedFile->path;
$isImage = Str::startsWith($attachedFile->mime, 'image/');

if (!$isImage && $attachedFile->original_file_path && Str::startsWith($attachedFile->original_mime_type, 'image/')) {
    $sourcePath = $attachedFile->original_file_path;
    $isImage = true;
    Log::info("[GenerateThumbnail] Using original file as source: {$sourcePath}");
}

if (!$isImage) {
    // ... スキップログ ...
    // ステータス更新チェック (後述)
    return;
}
```

#### 2. ステータス更新のガード処理追加
```php
// 処理完了後のステータス更新
if (!in_array($attachedFile->status, [
    AttachedFileStatus::PENDING_OCR,
    AttachedFileStatus::OCR_PROCESSING,
    AttachedFileStatus::PENDING_VLM,
    AttachedFileStatus::VLM_PROCESSING,
    AttachedFileStatus::PARALLEL_PROCESSING
])) {
    $attachedFile->update(['status' => AttachedFileStatus::COMPLETED->value]);
} else {
    Log::info("[GenerateThumbnail] Status update skipped due to parallel processing status: {$attachedFile->status->value}");
}
```

## 5. 期待される効果
*   OCR処理によってPDF化された画像ファイルについても、確実にサムネイルが生成されるようになる。
*   サムネイル生成ジョブが、進行中のOCR/VLM処理のステータスを不正に上書きする事故を防げる。
