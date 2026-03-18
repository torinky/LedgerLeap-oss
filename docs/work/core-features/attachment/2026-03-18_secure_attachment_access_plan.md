# 添付画像・サムネイルのセキュア配信統一計画

**作成日:** 2026-03-18  
**ステータス:** 実装計画起草済み / 進行中  
**Tracking Issue:** [#110](https://github.com/torinky/LedgerLeap/issues/110)

---

## 1. 目的

画像ダウンロード、サムネイル表示、FilePond の poster、FileInspector のプレビューについて、`storage` 直参照を残さず、必ず権限チェック付きのダウンロード経路を通す。

既存の「安全なダウンロード」方針は過去の調査で確立済みだが、コードベースにはまだ `Storage::disk('public')->url()` や `asset('storage/...')` が残っている。今回はその取りこぼしを回収し、UI からの直アクセスを完全に減らす。

---

## 2. 調査結果サマリ

### 2.1 既に確立している方針

- `AttachedFileDownloadController` で `Gate::authorize('view', $ledger)` を通す。
- `file.download` 系ルートを画像/原本/サムネイルの正規入口とする。
- サムネイルは `?thumbnail=true` で同じダウンロード経路に乗せる。
- これらは `docs/function/Attachment.md` と過去の work 文書で明記済み。

### 2.2 既存の関連文書

- `docs/work/core-features/attachment/2025-07-13_attachment-feature-enhancement.md`
- `docs/work/core-features/attachment/2025-07-19_refactor-filepond-blade-logic.md`
- `docs/work/core-features/attachment/2025-07-19_refactor-attached-file-path.md`
- `docs/work/core-features/attachment/2025-11-22_fix-thumbnail-generation-conflict.md`
- `docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md`
- `docs/work/ui-ux/attachment/2025-12-19_phase3_detailed_plan.md`
- `docs/work/ui-ux/attachment/2026-01-02_official-docs-update-plan.md`

### 2.3 今回確認できた残存経路

- `app/Livewire/AttachedFile/FileInspector.php`
  - `Storage::disk('public')->url(...)` が残っている
- `resources/views/livewire/attached-file/file-inspector/preview.blade.php`
  - `previewUrl` / `originalUrl` をそのまま表示している
- `app/Livewire/Ledger/ModifyColumn.php`
  - FilePond poster 用にサムネイル存在確認をしている
- `app/Services/Ledger/ColumnHtmlService.php`
  - 添付一覧の URL 構築を行っている
- `resources/views/components/ledger/table-row.blade.php`
  - 添付一覧の補助データを組み立てている
- `resources/views/livewire/ledger-define/partials/column-options.blade.php`
  - `asset('storage/...')` で背景画像へ直接アクセスしている
- `app/Livewire/LedgerDefine/Preview.php`
  - 背景画像URLを `asset('storage/...')` で生成している
- `app/Livewire/Traits/HandlesFormInitialization.php`
  - 背景画像URLを `asset('storage/...')` で生成している

### 2.4 結論

「セキュアダウンロードの設計」は実装済みだが、**UI の一部がまだ直参照を出している**。したがって今回は設計刷新ではなく、**残存経路の回収と統一**が中心になる。

---

## 3. スプリント分割

### Sprint 1: FileInspector を権限付き配信へ統一

**対象:**
- `app/Livewire/AttachedFile/FileInspector.php`
- `resources/views/livewire/attached-file/file-inspector/preview.blade.php`
- `resources/views/livewire/attached-file/file-inspector/quick-actions.blade.php`
- `resources/views/livewire/attached-file/file-inspector/tabs/content.blade.php`

**やること:**
- `Storage::disk('public')->url()` を route ベースへ置換
- `thumbnailUrl()` / `originalUrl()` / `previewUrl()` をダウンロード経路へ揃える
- 画像/サムネイルの表示が `file.download` 系のみになることを確認
- 権限なし時の挙動をテストで固定

**完了条件:**
- FileInspector から `storage` 直URL が出ない
- 画像表示とダウンロードの両方で認可が通る

---

### Sprint 2: 添付一覧・FilePond poster の統一

**対象:**
- `app/Livewire/Ledger/ModifyColumn.php`
- `app/Services/Ledger/ColumnHtmlService.php`
- `resources/views/components/ledger/table-row.blade.php`
- `resources/views/components/ledger/attachment-list.blade.php`
- `resources/views/components/ledger/attachment-card.blade.php`

**やること:**
- 添付一覧の URL 生成を route ベースに揃える
- `attachment-list` / `attachment-card` は受け取った URL を描画するだけに寄せる
- FilePond poster の分岐を維持しつつ、実際の表示先は権限付き route に統一する
- 画像/非画像/optimized PDF の回帰を確認する

**完了条件:**
- 一覧 UI から raw storage URL が消える
- poster とダウンロードの期待値がテストで固定される

---

### Sprint 3: ledger-define 背景画像の安全配信化

**対象:**
- `app/Livewire/LedgerDefine/Preview.php`
- `app/Livewire/Traits/HandlesFormInitialization.php`
- `resources/views/livewire/ledger-define/partials/column-options.blade.php`
- `resources/views/livewire/ledger-define/preview.blade.php`

**やること:**
- 背景画像の URL 生成を `asset('storage/...')` から別の安全な配信経路へ切り替える
- 必要なら背景画像専用の route / helper を追加する
- 編集画面とプレビューの両方で差し替える
- 画面崩れがないことを確認する

**完了条件:**
- ledger-define 側の背景画像も storage 直参照しない
- 既存の UI/UX を壊さない

---

## 4. 受け入れ条件

- `storage` 直アクセスで画像やサムネイルが表示されない
- 画像/サムネイル/原本/FilePond poster がすべて権限付き route 経由になる
- 背景画像も安全な配信口へ統一される
- 既存テストが通り、回帰がない

---

## 5. テスト方針

### 優先テスト

- `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
- `tests/Feature/Livewire/Ledger/ModifyColumnTest.php`
- `tests/Feature/Livewire/AttachedFile/TextPreviewModalTest.php`
- 追加が必要なら ledger-define の表示テスト

### 確認観点

- `previewUrl` が storage URL ではなく route になること
- `thumbnailUrl` が `file.download?thumbnail=true` を使うこと
- 権限のないユーザーが 403/404 で止まること
- 背景画像の表示が既存の見た目を壊さないこと

---

## 6. 実装メモ

- まず Sprint 1 を先行する
- 背景画像は別系統なので Sprint 3 に分けてリスクを抑える
- `docs/function/Attachment.md` にも今回の「残存経路回収」の観点を追記候補として残す
- もし route 追加が必要なら、既存の `file.download` の契約を壊さずに helper を追加する方向で進める

---

## 7. 進捗ログ

### 7.1 Sprint 1 開始時点の実装

- `app/Livewire/AttachedFile/FileInspector.php`
  - `Storage::disk('public')->url()` を route ベースへ置換
  - `thumbnailUrl()` / `originalUrl()` / `previewUrl()` を安全な配信経路へ統一
  - `downloadUrl()` を追加し、ダウンロードと表示の URL を分離
- `resources/views/livewire/attached-file/file-inspector/quick-actions.blade.php`
  - ダウンロードとコピーを `downloadUrl` へ切り替え
- `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
  - `previewUrl` / `originalUrl` / `downloadUrl` の route 期待値を追加
  - サムネイル存在時の `thumbnail=true` ルートも検証

### 7.2 直近の検証結果

- `./vendor/bin/sail test tests/Feature/Livewire/AttachedFile/FileInspectorTest.php --stop-on-failure`
  - **31 passed**
  - 画像、PDF、サムネイル、権限拒否、パフォーマンス系の既存テストが継続成功

