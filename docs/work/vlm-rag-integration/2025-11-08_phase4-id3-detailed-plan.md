# VLM結果表示UI実装 詳細計画書

**ドキュメントID:** `VLM-UI-PLAN-20251108`  
**作成日:** 2025年11月8日  
**ステータス:** 計画確定
**関連WBS:** [Phase4 Embedding生成とUI実装 WBS](./2025-11-07_phase4-wbs.md) (ID: 3.0)

---

## 1. 概要

本ドキュメントは、WBS `ID3.0 VLM結果表示UIの実装` の詳細な実装計画を定めるものです。台帳詳細画面において、VLM（Visual Language Model）による解析結果（Markdown、構造化データ）をユーザーが直感的に確認・利用できるUIを実装します。

本計画は、既存コードの調査および、実装に伴う懸念事項の予備調査結果に基づいています。

---

## 2. 実装ステップ

### ステップ1: モデルとEnumの準備 (前提条件)

VLM結果を扱うための基本的なデータ構造とロジックを整備します。

*   **担当ファイル:**
    *   `app/Enums/AttachedFileStatus.php`
    *   `app/Models/AttachedFile.php`
*   **作業内容:**
    1.  **Enumの拡張:** `AttachedFileStatus` に、VLM処理の状態を示す `VLM_PROCESSING`, `VLM_FAILED`, `VLM_COMPLETED` 等のcaseを追加します。
    2.  **モデルの拡張:** `AttachedFile` モデルに、VLM結果の有無や状態を容易に判定するためのヘルパーメソッド (`hasVlmResult()`, `isVlmProcessing()` 等) および、信頼度スコアを整形して返すアクセサ (`VlmConfidenceFormatted`) を実装します。

---

### ステップ2: Livewireコンポーネントとビューの実装

ユーザーインターフェースの中核となる、LivewireコンポーネントのロジックとBladeビューの表示を実装します。

*   **担当ファイル:**
    *   `app/Livewire/Ledger/Show.php`
    *   `resources/views/livewire/ledger/show.blade.php`
*   **作業内容:**
    1.  **プロパティ追加 (`Show.php`):**
        *   `public bool $showVlmModal = false;` : プレビューモーダルの表示状態を管理します。
        *   `public ?int $previewingFileId = null;` : プレビュー対象の添付ファイルIDを保持します。
    2.  **Computed Property追加 (`Show.php`):**
        *   `public function getPreviewingFileProperty()`: `$previewingFileId` を基に、プレビュー対象の `AttachedFile` モデルをロードします。これにより、モーダル表示時までDBアクセスを遅延させ、初期表示パフォーマンスを確保します。
    3.  **アクション追加 (`Show.php`):**
        *   `public function showVlmPreview(int $fileId)`: プレビューボタンのクリックイベントを処理し、対象のファイルIDをセットしてモーダルを開きます。
    4.  **ステータス・ボタン表示 (`show.blade.php`):**
        *   添付ファイル一覧のループ内で、ファイルのVLMステータスに応じて `<x-mary-badge>` を使用し、「処理中」「失敗」等のバッジを表示します。
        *   VLM結果が存在する場合にのみ、「VLM結果をプレビュー」ボタン (`<x-mary-button>`) を表示し、`wire:click`で上記アクションを呼び出します。
    5.  **プレビューモーダル実装 (`show.blade.php`):**
        *   `<x-mary-modal wire:model="showVlmModal">` を使用してモーダルを実装します。
        *   モーダル内では、Computed Property (`$this->previewingFile`) を通じて `vlm_markdown` を取得し、`{!! Illuminate\Support\Str::markdown($this->previewingFile->vlm_markdown, ['html_input' => 'strip']) !!}` を使用してHTMLとして表示します。（詳細は後述のセキュリティの項を参照）
        *   モーダルのヘッダーにはファイル名と信頼度スコアを表示し、利便性を高めます。

---

### ステップ3: VLM結果ダウンロード機能の実装

ユーザーがVLMの生成物を直接ダウンロードできるようにします。

*   **担当ファイル:**
    *   `routes/web.php`
    *   `app/Http/Controllers/AttachedFileDownloadController.php`
*   **作業内容:**
    1.  **ルート定義 (`routes/web.php`):**
        *   VLM結果（Markdown, JSON）をダウンロードするためのルート `/files/{attachedFile}/download-vlm` を定義します。
    2.  **コントローラー実装 (`AttachedFileDownloadController.php`):**
        *   `downloadVlm(Request $request, AttachedFile $attachedFile)` メソッドを実装します。
        *   `Gate::authorize` を使用して、ユーザーが対象ファイルへのアクセス権を持つことを検証します。
        *   `format` クエリパラメータ (`markdown` or `json`) に応じて、`vlm_markdown` または `vlm_structured_data` を取得し、適切な`Content-Type`と`Content-Disposition`ヘッダーを付けてレスポンスを返します。
    3.  **リンクの設置 (`show.blade.php`):**
        *   プレビューモーダル内、または添付ファイル一覧に、上記ルートへのダウンロードリンクを設置します。

---

### 3. 懸念事項と対策（予備調査結果）

実装に先立ち、懸念事項に関する予備調査を実施しました。以下にその結果と対策を記します。

#### 3.1. Markdown表示の品質

*   **懸念:** `Str::markdown()` がVLMが出力する複雑なテーブル等を正しく表示できるか。
*   **調査結果:** `config/markdown.php` を確認したところ、`League\CommonMark\Extension\Table\TableExtension::class` がデフォルトで有効化されています。
*   **結論・対策:** **問題なし。** この設定により、GitHub Flavored Markdownのテーブル構文は正しくレンダリングされます。特別な追加対応は不要です。

#### 3.2. セキュリティ (XSS)

*   **懸念:** Markdown内の生HTMLがレンダリングされ、XSS脆弱性が発生するリスク。
*   **調査結果:** `config/markdown.php` を確認したところ、`'html_input' => 'allow'` と設定されており、生HTMLのレンダリングが許可されています。これは意図しないスクリプト実行のリスクを生みます。
*   **結論・対策:** **対策が必要。** VLMの出力は基本的に信頼できますが、安全側に倒すのが原則です。`Str::markdown()` を使用する箇所で、オプションを明示的に上書きし、HTMLを無害化します。
    ```php
    // Bladeビューでの使用例
    {!! Illuminate\Support\Str::markdown($markdownContent, [
        'html_input' => 'strip', // 生HTMLタグを完全に除去
    ]) !!}
    ```
    この対策により、XSSのリスクを排除します。

#### 3.3. パフォーマンス

*   **懸念:** サイズの大きい `vlm_markdown` をLivewireプロパティで扱う際のパフォーマンス低下。
*   **調査結果:** Livewireでは、大きなデータペイロードはリクエストごとにサーバーとクライアント間を往復するため、パフォーマンスに影響を与えることが知られています。
*   **結論・対策:** **段階的な対策を実施。**
    1.  **第一段階（本計画）:** Computed Property (`getPreviewingFileProperty`) を使用します。これにより、モーダルが表示されるまでDBからのデータロードが遅延されるため、初期表示のパフォーマンスは確保されます。
    2.  **第二段階（性能問題発生時）:** もしプレビュー表示時の応答性が問題となる場合、モーダルを開くイベントをトリガーにして、`wire:loading` でスピナーを表示しつつ、サーバーにコンテンツを非同期で要求する、より高度な実装を検討します。

---
以上
