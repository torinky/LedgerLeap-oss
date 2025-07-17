# 台帳再編集時の添付ファイル (FilePond) の不具合修正計画 (改訂版)

## 1. 目的

台帳レコードの再編集画面において、添付ファイルコンポーネント (FilePond) が正しく機能しない問題を、FilePondの公式ドキュメントに沿ったベストプラクティスで修正する。これにより、ユーザーは既存の添付ファイルを再アップロードすることなく、スムーズにレコードを編集・保存できるようになる。

## 2. 現状の不具合

1.  **リンクとプレビューの不全:** 既存の添付ファイルのダウンロードリンクや画像プレビューが機能しない ("Not Found" と表示される)。
2.  **不必要なバリデーションエラー:** ファイルカラムが必須入力の場合、既存のファイルが添付されているにもかかわらず、ファイルを変更せずに保存しようとすると「必須項目です」というバリデーションエラーが発生し、不要な再添付を強いられる。

## 3. 原因

- **リンクの問題 (根本原因):** FilePondの `load` 機能は、指定されたURLから**ファイルコンテンツそのもの**が返されることを期待する。しかし、既存のダウンロードルート (`/files/{id}/download`) は、ブラウザにダウンロードを促すための `Content-Disposition: attachment` ヘッダーを付与して応答するため、FilePondがファイル内容を正しく解釈できず、"Not Found" と表示してしまう。
- **バリデーションの問題:** Livewireコンポーネント (`ModifyColumn`) が、保存時に「新しくアップロードされたファイル」のみを認識し、「削除されずに残っている既存のファイル」を`content`プロパティに反映できていないため。

## 4. 改修計画 (FilePond公式ドキュメント準拠)

### ステップ 1: FilePond専用のファイルロードエンドポイントの作成

**目的:** FilePondがファイル内容を直接取得できる、認可チェックのみを行うシンプルなAPIエンドポイントを新設する。

- **タスク:**
    1.  **コントローラーの作成:**
        -   `php artisan make:controller FilePondController` を実行し、`app/Http/Controllers/FilePondController.php` を作成する。(完了)
    2.  **メソッドの実装 (`FilePondController.php`):**
        -   `public function load(AttachedFile $attachedFile)` メソッドを実装する。(完了)
        -   メソッド内では、`Gate::authorize('view', $attachedFile->ledger)` を用いて認可チェックのみを行う。(完了)
        -   `Storage::disk('public')->response($attachedFile->path)` を返し、ファイルの内容を `Content-Disposition` ヘッダーなしで直接レスポンスする。(完了)
    3.  **ルートの定義 (`routes/web.php`):**
        -   `Route::get('/filepond/load/{attachedFile}', [FilePondController::class, 'load'])->name('filepond.load');` を追加し、新しいエンドポイントを定義する。(完了)

### ステップ 2: FilePondコンポーネント (Blade) の修正

**目的:** FilePondの初期化設定を修正し、ステップ1で作成した新しいロードエンドポイントと、サムネイル表示用の既存ダウンロードルートを正しく使い分けるようにする。

- **タスク:**
    1.  **`resources/views/components/ledger/form/files.blade.php` の修正:**
        -   FilePondの `setOptions` 内に `server` オブジェクトを追加（または修正）する。(完了)
        -   `server` オブジェクトに `load` プロパティを追加し、`attachedFile` パラメータを正しく渡すように設定する。(完了)
        -   `files` オプションのループ内で、`source` には `AttachedFile` のID (`$attachmentId`) のみを設定する。(完了)
        -   `metadata.poster` には、サムネイル画像を表示するため、既存のセキュアなダウンロードルート `route('file.download', ['attachedFile' => $attachmentId, 'thumbnail' => true])` を引き続き使用する。(完了)
        -   `window.addEventListener('load', ...)` ラッパーを削除し、FilePondの初期化を `x-init` の直下で行うように修正する。(完了)

### ステップ 3: バリデーションロジックの修正

**目的:** ファイルの変更がない場合に、必須入力エラーが発生しないようにする。

- **タスク:**
    1.  **`app/Livewire/Ledger/ModifyColumn.php` の修正:**
        -   Livewireのライフサイクルフックである `prepareForValidation()` メソッドをオーバーライドする。(完了)
        -   このメソッド内で、ファイルカラムの `content` プロパティを動的に再構築するロジックを実装する。(完了)
            -   **再構築する内容 = (新規アップロードファイル) + (画面上で削除されずに残っている既存ファイル)**
        -   これにより、バリデーション実行時には `content` プロパティに既存のファイル情報が含まれる状態になり、必須項目チェックを正しくパスできるようになる。(完了)

## 5. 期待される効果

- 再編集画面で、既存ファイルのプレビューが正しく表示され、ダウンロードリンクも（FilePondのUIまたは別途用意したリンクから）正常に機能する。
- ファイルが必須の項目で、添付ファイルを変更しない場合でも、バリデーションエラーが発生しなくなる。
- FilePondの公式ドキュメントに準拠した、より堅牢で保守性の高い実装になる。
