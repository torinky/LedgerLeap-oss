# VLM処理におけるファイル拡張子不整合の修正

**作成日:** 2025年11月22日
**ステータス:** 計画中

## 1. 概要

本ドキュメントは、OCR処理によってPDF化された画像ファイルが、VLM（Vision-Language Model）処理においてエラーとなる問題を解消するための調査結果と修正方針を定義します。

## 2. 現状と課題

### 2.1 発生している問題
*   同じファイル（`receipt_01.jpg`）をアップロードした際、ID: 6 はVLM処理に成功したが、ID: 8 は失敗した。
*   **エラー内容:** VLMコンテナ（`unified_api.py`）にて `PIL.UnidentifiedImageError: cannot identify image file '/tmp/tmp...jpg'` が発生。
*   **状況:** ID: 8 のファイルは、VLM処理時点で既にOCR処理が完了しており、実体は PDF ファイル（拡張子 `.pdf`）に変換されていた。

### 2.2 原因調査
1.  **ファイルの実体とメタデータの乖離:**
    *   システムはアップロードされた画像（JPEG）をOCR処理で PDF に変換し、`AttachedFile` の `path` は `.pdf` を指すよう更新される。
    *   しかし、`VlmClientService` は VLM への送信時に `AttachedFile` モデルの `getOriginalFilenameAttribute()` を使用している。
    *   `getOriginalFilenameAttribute()` は `ledgers` テーブルの `content` カラム（JSON）に保存された「アップロード時の元のファイル名（`receipt_01.jpg`）」を返す。

2.  **VLMサービスの挙動:**
    *   Laravel側から `receipt_01.jpg` という名前で送信されたファイルを受け取った VLM サービス（`unified_api.py`）は、拡張子 `.jpg` を信じて `Image.open()` で開こうとする。
    *   しかし、送信されたファイルの中身（バイナリデータ）は、直前のOCR処理によって変換された **PDF** である。
    *   Pillow ライブラリは PDF を画像として直接開けない（またはヘッダーが一致しない）ため、エラーが発生する。

3.  **ID: 6 が成功した理由:**
    *   ログ分析の結果、ID: 6 は OCR処理（PDF化）よりも **先** に VLM 処理が実行されていた。
    *   その時点ではディスク上のファイルも `.jpg` であり、送信ファイル名も `.jpg` であったため、整合性が取れており成功した。
    *   ID: 8 はタイミング（リトライや並列処理の順序）により、PDF化 **後** に VLM 処理が走ったため不整合が生じた。

## 3. 解決策

`app/Services/VlmClientService.php` を修正し、VLM に送信するファイル名の拡張子を、**実際の物理ファイル（`$filePath`）の拡張子に強制的に合わせる** ように変更します。

**変更方針:**
*   送信ファイル名を決定する際、`getOriginalFilenameAttribute()` で取得した元のファイル名（ベース名）を使用しつつ、拡張子部分のみを `pathinfo($filePath, PATHINFO_EXTENSION)` で取得した「現在のファイルの拡張子」に置換する。
*   これにより、中身が PDF であれば `.pdf` として送信され、VLM 側で正しく PDF として処理（`fitz` 等を使用）されるようになる。

## 4. 実装詳細計画

### 対象ファイル
`app/Services/VlmClientService.php`

### 修正内容 (`extract` メソッド)

**変更前:**
```php
->attach(
    'file',
    file_get_contents($filePath),
    $attachedFile->getOriginalFilenameAttribute() ?? basename($filePath)
)
```

**変更後:**
```php
// 物理ファイルの拡張子を取得
$realExtension = pathinfo($filePath, PATHINFO_EXTENSION);

// 送信用のファイル名を構築
// オリジナル名のベース部分 + 物理ファイルの拡張子
$originalFilename = $attachedFile->getOriginalFilenameAttribute() ?? basename($filePath);
$filenameToSend = pathinfo($originalFilename, PATHINFO_FILENAME) . '.' . $realExtension;

// ...

->attach(
    'file',
    file_get_contents($filePath),
    $filenameToSend
)
```

## 5. 期待される効果
*   OCR処理によってファイル形式が変更された場合でも、VLMサービスが正しいファイル形式として認識・処理できるようになる。
*   「同じファイルなのにタイミングによって成功したり失敗したりする」という不安定な挙動が解消される。
