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
    1.  **Enumの確認:** `AttachedFileStatus` には既に `VLM_PROCESSING`, `VLM_FAILED`, `PENDING_VLM` が実装済みです。これらのステータスは既存のOCR処理ステータスと並列で管理される設計となっています。
    2.  **モデルの確認と拡張:** `AttachedFile` モデルには既に `hasVlmResult()`, `isVlmProcessing()`, `isVlmFailed()` メソッドが実装済みです。追加で以下を実装します：
        *   信頼度スコアを整形して返すアクセサ `getVlmConfidenceFormattedAttribute()`: パーセント表示（例: "95.3%"）を返します。

**関連する既存実装の確認事項:**
*   `vlm_confidence` カラムは `decimal(4,3)` 型で実装されており、0.000-1.000の範囲の値を格納します。
*   `AttachedFileStatus` enum には既に `icon()`, `colorClass()`, `tooltip()` メソッドが実装されており、VLMステータスの視覚的表現が可能です。

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
        *   `#[Computed] public function previewingFile()`: `$previewingFileId` を基に、プレビュー対象の `AttachedFile` モデルをロードします。これにより、モーダル表示時までDBアクセスを遅延させ、初期表示パフォーマンスを確保します。Livewire 3の `#[Computed]` アトリビュートを使用します（既存の `WorkflowStatusCard` と同様のパターン）。
    3.  **アクション追加 (`Show.php`):**
        *   `public function showVlmPreview(int $fileId)`: プレビューボタンのクリックイベントを処理します。以下の処理を含みます：
            *   ファイルの存在とVLM結果の有無を確認
            *   エラー時は `mary-toast` で通知（既存の `deleteAttachedFile` メソッドと同様のパターン）
            *   成功時は対象のファイルIDをセットしてモーダルを開く
    4.  **認可チェックの実装 (`Show.php`):**
        *   既存の `mount` メソッドでは `Gate::allows('view', [Ledger::class, $this->ledgerRecord])` により台帳レベルの認可が確認されています。
        *   `showVlmPreview` メソッド内では追加の認可チェックは不要です（添付ファイルは親台帳に紐づいており、台帳への閲覧権限があれば添付ファイルも閲覧可能とみなす設計）。
    5.  **ステータス・ボタン表示 (`show.blade.php`):**
        *   添付ファイル一覧のループ内で、ファイルのVLMステータスに応じて以下を表示：
            *   `<x-mary-badge>` を使用したステータスバッジ（`AttachedFileStatus::icon()` と `colorClass()` メソッドを活用）
            *   処理中: 青色、回転アニメーション
            *   失敗: 赤色、警告アイコン
            *   完了: 緑色、チェックアイコン
        *   VLM結果が存在する場合（`$file->hasVlmResult()`）にのみ、「VLM結果をプレビュー」ボタンを表示し、`wire:click="showVlmPreview({{ $file->id }})"`で上記アクションを呼び出します。
    6.  **プレビューモーダル実装 (`show.blade.php`):**
        *   `<x-mary-modal wire:model="showVlmModal" class="w-11/12 max-w-5xl">` を使用してモーダルを実装します（大きめのコンテンツに対応するため幅を広く設定）。
        *   モーダル内では、Computed Property (`$this->previewingFile`) を通じて `vlm_markdown` を取得し、`{!! Str::markdown($this->previewingFile->vlm_markdown, ['html_input' => 'strip']) !!}` を使用してHTMLとして表示します（XSS対策として `html_input => 'strip'` を明示的に指定）。
        *   モーダルのヘッダーにはファイル名と信頼度スコア（`VlmConfidenceFormatted` アクセサ）を表示します。
        *   モーダルコンテンツは `prose max-w-none overflow-y-auto max-h-[70vh]` クラスを適用し、長文にも対応します。

---

### ステップ3: VLM結果ダウンロード機能の実装

ユーザーがVLMの生成物を直接ダウンロードできるようにします。

*   **担当ファイル:**
    *   `routes/web.php`
    *   `app/Http/Controllers/AttachedFileDownloadController.php`
*   **作業内容:**
    1.  **ルート定義 (`routes/web.php`):**
        *   VLM結果（Markdown, JSON）をダウンロードするためのルート `/files/{attachedFile}/download-vlm` を定義します。
        *   既存の認証ミドルウェアグループ内に配置し、`name('files.download-vlm')` で名前付きルートとして定義します。
    2.  **コントローラー実装 (`AttachedFileDownloadController.php`):**
        *   既存の `download` メソッドと同様のパターンで `downloadVlm(Request $request, AttachedFile $attachedFile)` メソッドを実装します。
        *   **認可チェック:** `Gate::authorize('view', $attachedFile->ledger)` を使用して、既存の `download` メソッドと同様の認可ロジックを適用します。
        *   **VLM結果の存在確認:** `hasVlmResult()` メソッドでVLM結果の存在を確認し、存在しない場合は404を返します。
        *   **フォーマット対応:** `format` クエリパラメータ (`markdown` or `json`) に応じて、`vlm_markdown` または `vlm_structured_data` を取得します。
        *   **レスポンス生成:** 適切な `Content-Type`（`text/markdown` または `application/json`）と `Content-Disposition: attachment` ヘッダーを付けてレスポンスを返します。
        *   **アクティビティログ:** 既存の `download` メソッドと同様に、`activity()` ヘルパーを使用してダウンロード履歴を記録します。
    3.  **リンクの設置 (`show.blade.php`):**
        *   プレビューモーダル内、または添付ファイル一覧に、上記ルートへのダウンロードリンクを設置します。
        *   Markdown形式とJSON形式の両方のダウンロードリンクを提供します。

---

### ステップ4: テストの実装

実装した機能の品質を保証するためのテストを作成します。

*   **担当ファイル:**
    *   `tests/Feature/Livewire/Ledger/ShowTest.php`
    *   `tests/Feature/Http/Controllers/AttachedFileDownloadControllerTest.php` (新規作成)
*   **作業内容:**
    1.  **Livewireコンポーネントテスト (`ShowTest.php`):**
        *   VLM結果が存在する場合、プレビューボタンが表示されることを確認
        *   プレビューボタンをクリックすると `showVlmModal` が `true` になることを確認
        *   VLM結果が存在しない場合、エラートーストが表示されることを確認（`->assertDispatched('mary-toast')` を使用）
        *   既存の `retryProcessing` テストと同様のパターンでテストを実装
    2.  **ダウンロード機能テスト (`AttachedFileDownloadControllerTest.php`):**
        *   正常系: VLM結果のダウンロードが成功することを確認（Markdown/JSON両方）
        *   認可チェック: 権限のないユーザーは403を受け取ることを確認
        *   エラー系: VLM結果が存在しないファイルへのリクエストは404を返すことを確認
        *   アクティビティログが正しく記録されることを確認
    3.  **XSS防御テスト:**
        *   `vlm_markdown` に悪意あるHTMLタグ（`<script>` 等）を含むテストデータを作成し、レンダリング後にスクリプトタグが除去されていることを確認します。

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

## 3. 懸念事項と対策（予備調査結果）

実装に先立ち、懸念事項に関する予備調査を実施しました。以下にその結果と対策を記します。

### 3.1. Markdown表示の品質

*   **懸念:** `Str::markdown()` がVLMが出力する複雑なテーブル等を正しく表示できるか。
*   **調査結果:** `config/markdown.php` を確認したところ、`League\CommonMark\Extension\Table\TableExtension::class` がデフォルトで有効化されています。
*   **結論・対策:** **問題なし。** この設定により、GitHub Flavored Markdownのテーブル構文は正しくレンダリングされます。特別な追加対応は不要です。

### 3.2. セキュリティ (XSS)

*   **懸念:** Markdown内の生HTMLがレンダリングされ、XSS脆弱性が発生するリスク。
*   **調査結果:** `config/markdown.php` を確認したところ、`'html_input' => 'allow'` と設定されており、生HTMLのレンダリングが許可されています。これは意図しないスクリプト実行のリスクを生みます。
*   **結論・対策:** **対策が必要。** VLMの出力は基本的に信頼できますが、安全側に倒すのが原則です。`Str::markdown()` を使用する箇所で、オプションを明示的に上書きし、HTMLを無害化します。Bladeビューで `'html_input' => 'strip'` を指定することで生HTMLタグを完全に除去します。この対策により、XSSのリスクを排除します。テストケースでも悪意あるHTMLタグの除去を確認します。

### 3.3. パフォーマンス

*   **懸念:** サイズの大きい `vlm_markdown` をLivewireプロパティで扱う際のパフォーマンス低下。
*   **調査結果:** Livewireでは、大きなデータペイロードはリクエストごとにサーバーとクライアント間を往復するため、パフォーマンスに影響を与えることが知られています。既存の `WorkflowStatusCard` コンポーネントでは、Livewire 3の `#[Computed]` アトリビュートを使用したパターンが確立されています。
*   **結論・対策:** **段階的な対策を実施。**
    1.  **第一段階（本計画）:** Livewire 3の `#[Computed]` アトリビュートを使用したcomputed property (`previewingFile()`) を実装します。これにより、モーダルが表示されるまでDBからのデータロードが遅延され、かつLivewireの内部キャッシュ機構が適切に機能するため、初期表示のパフォーマンスは確保されます。
    2.  **第二段階（性能問題発生時）:** もしプレビュー表示時の応答性が問題となる場合、モーダルを開くイベントをトリガーにして、`wire:loading` でスピナーを表示しつつ、サーバーにコンテンツを非同期で要求する、より高度な実装を検討します。

### 3.4. 認可チェックの一貫性

*   **懸念:** Livewireコンポーネントとダウンロード機能で認可ロジックが異なる可能性。
*   **調査結果:** 
    *   既存の `Show` コンポーネントの `mount` メソッドでは、`Gate::allows('view', [Ledger::class, $this->ledgerRecord])` により台帳レベルの認可が確認されています。
    *   既存の `AttachedFileDownloadController::download` メソッドでは、`Gate::authorize('view', $attachedFile->ledger)` により同様の認可が行われています。
    *   LedgerLeapの設計思想として、添付ファイルは親台帳に従属するリソースであり、台帳への閲覧権限があれば添付ファイルも閲覧可能とみなされます。
*   **結論・対策:** **既存パターンを踏襲。**
    *   `Show` コンポーネントの `showVlmPreview` メソッド内では、追加の認可チェックは不要です（`mount` 時点で台帳レベルの認可が完了）。
    *   `AttachedFileDownloadController::downloadVlm` メソッドでは、既存の `download` メソッドと同様に `Gate::authorize('view', $attachedFile->ledger)` を実装します。
    *   この一貫性により、認可ロジックが統一され、保守性が向上します。

### 3.5. エラーハンドリング

*   **懸念:** VLM結果が存在しない、または処理失敗時のユーザー体験が不明確。
*   **調査結果:** 既存の `Show` コンポーネントの `deleteAttachedFile` メソッドでは、`mary-toast` による成功/失敗通知が実装されています。このパターンはプロジェクト全体で標準化されています。
*   **結論・対策:** **既存パターンを踏襲。**
    *   `showVlmPreview` メソッド内で以下のエラーハンドリングを実装：
        *   ファイルが存在しない、またはVLM結果が存在しない場合: `mary-toast` でユーザーに通知（タイプ: error、メッセージ: VLM結果が存在しません）
        *   モーダルは開かない
    *   `downloadVlm` メソッドでは：
        *   VLM結果が存在しない場合: 404エラーを返す
        *   認可失敗時: 403エラーを返す
    *   これにより、ユーザーフレンドリーなエラー体験を提供します。

### 3.6. VLM処理ステータスの状態管理

*   **懸念:** VLMステータスとOCRステータスの共存方法が不明確。
*   **調査結果:** 
    *   `AttachedFileStatus` enum には、VLM関連ステータス（`VLM_PROCESSING`, `VLM_FAILED`, `PENDING_VLM`）とOCR関連ステータス（`OCR_PROCESSING`, `OCR_FAILED` 等）が並列で定義されています。
    *   Phase2の実装計画によれば、VLMはOCRと独立した処理として設計されており、同一の `status` カラムで管理されます。
    *   処理フローは「初期処理 → OCR処理 → VLM処理」の順序で進行し、各ステージで状態が遷移します。
*   **結論・対策:** **現状の設計を維持。**
    *   単一の `status` カラムで両方の処理状態を管理する現状の設計は、シンプルで効率的です。
    *   `AttachedFileStatus` enum に既に実装されている `icon()`, `colorClass()`, `tooltip()` メソッドを活用し、VLMステータスの視覚的表現を実装します。
    *   将来的により複雑な状態管理が必要になった場合は、Phase4の次のフェーズでリファクタリングを検討します。

### 3.7. UI実装の具体性

*   **懸念:** バッジの色分けやモーダルサイズ等、視覚的要素の仕様が不明確。
*   **調査結果:** 
    *   `AttachedFileStatus` enum には既に `colorClass()` メソッドが実装されており、以下のパターンが確立されています：
        *   処理中: `text-warning animate-spin`（黄色、回転アニメーション）
        *   完了: `text-success`（緑色）
        *   失敗: `text-error`（赤色）
        *   待機中: `text-info`（青色）
    *   既存のLivewireコンポーネント（例: `WorkflowStatusCard`）では、`prose` クラスを使用したMarkdownレンダリングパターンが存在します。
*   **結論・対策:** **既存のUI/UXパターンを踏襲。**
    *   ステータスバッジ: `AttachedFileStatus::colorClass()` メソッドを使用し、一貫性のある色分けを実装
    *   モーダルサイズ: VLM結果は長文になる可能性が高いため、`class="w-11/12 max-w-5xl"` で幅広に設定
    *   モーダルコンテンツ: `prose max-w-none overflow-y-auto max-h-[70vh]` で縦スクロール対応
    *   アイコン: `AttachedFileStatus::icon()` メソッドを使用し、FontAwesomeアイコンを表示
    *   これらの仕様を実装セクション（ステップ2）に反映しました。

---

## 4. UI/UXに関する要注意事項

本UI実装は、[ペルソナ、ユースケース、シナリオ](../../../function/PersonaUseCaseScenario.md)で定義された、特に「実務担当者」や「現場リーダー」のニーズを満たすための重要な機能です。実装にあたっては、以下のUI/UX要件を確実に満たすよう注意してください。

### 4.1. VLM処理ステータスの視覚的フィードバック (WBS 3.4)
*   **UI/UX要件:** 共通UI/UX原則 - 即時フィードバック
*   **実装内容:** 添付ファイルごとにVLM処理の状況（処理中、完了、失敗）をバッジやアイコンで明確に表示します。これにより、ユーザーは時間のかかる処理の進捗を把握でき、安心してシステムを利用できます。
*   **具体的実装:** `AttachedFileStatus::icon()` および `colorClass()` メソッドを活用し、一貫性のある視覚的表現を提供します。

### 4.2. 直感的な結果確認 (WBS 3.2)
*   **UI/UX要件:** データ参照の容易性
*   **実装内容:** VLM処理完了後、「プレビュー」ボタンを表示し、クリック一つでモーダル内に整形されたMarkdownを表示します。ファイル名や信頼度スコアも併記し、情報の透明性を高めます。
*   **具体的実装:** `#[Computed]` アトリビュートによる遅延ロードにより、パフォーマンスを犠牲にせず直感的な操作を実現します。

### 4.3. データの再利用性 (WBS 3.3)
*   **UI/UX要件:** データ活用
*   **実装内容:** プレビュー画面や添付ファイル一覧から、解析結果（Markdown, JSON）を直接ダウンロードできる機能を提供します。これにより、ユーザーはVLMの生成物を他のツールや報告書作成に容易に活用できます。
*   **具体的実装:** 既存の `AttachedFileDownloadController` の設計パターンを踏襲し、一貫性のあるダウンロード体験を提供します。

### 4.4. エラー時のユーザー体験
*   **UI/UX要件:** わかりやすいエラーメッセージ
*   **実装内容:** VLM結果が存在しない場合や処理に失敗した場合、明確なエラーメッセージを表示します。
*   **具体的実装:** `mary-toast` による通知システムを活用し、既存の `deleteAttachedFile` メソッドと同様のパターンでエラー通知を実装します。

### 4.5. 操作の効率化 (追加検討)
*   **UI/UX要件:** 直感的な操作
*   **実装内容:** プレビューモーダル内に「クリップボードにコピー」ボタンを追加することを推奨します。これにより、ユーザーは解析結果の一部を他のアプリケーションに素早く転記でき、作業効率が大幅に向上します。
*   **優先度:** Phase4では必須ではありませんが、ユーザーフィードバックに基づき次フェーズでの実装を検討します。

---

## 5. Phase4内の他タスクとの依存関係

### 5.1. ID 1.0（自動更新フローの不整合修正）との関係
*   **依存関係:** 本UI実装（ID 3.0）は、ID 1.0が完了していなくても並行して開発可能です。ただし、エンドツーエンドのテストは ID 1.0 完了後に行うことを推奨します。
*   **開発アプローチ:** VLM結果が既に存在する添付ファイルのテストデータを使用することで、ID 1.0の完了を待たずにUI開発を進めることができます。

### 5.2. ID 2.0（Embedding生成処理の統合テスト）との関係
*   **依存関係:** 本UI実装と ID 2.0 は完全に独立しています。
*   **理由:** ID 2.0はバックエンドのRAGパイプラインのテストであり、UI層とは直接的な関連がありません。

---

## 6. 実装後の検証項目

実装完了後、以下の項目を確認してください：

### 6.1. 機能検証
- [ ] VLM処理完了ファイルに「プレビュー」ボタンが表示される
- [ ] プレビューボタンをクリックするとモーダルが開き、Markdown形式の結果が表示される
- [ ] モーダルにファイル名と信頼度スコアが表示される
- [ ] Markdownのテーブルが正しくレンダリングされる
- [ ] Markdown/JSON形式のダウンロードリンクが機能する
- [ ] VLM処理中/失敗/待機中のステータスバッジが適切に表示される

### 6.2. セキュリティ検証
- [ ] 悪意あるHTMLタグがMarkdownから除去されることをテストで確認
- [ ] 認可チェックが適切に機能することをテストで確認
- [ ] ダウンロード時のアクティビティログが正しく記録される

### 6.3. パフォーマンス検証
- [ ] 大きなVLM結果（10KB以上のMarkdown）でもモーダル表示が快適
- [ ] 複数の添付ファイルを持つ台帳の詳細画面が快適に動作
- [ ] Computed propertyによる遅延ロードが正しく機能

### 6.4. UI/UX検証
- [ ] ステータスバッジの色と状態が直感的に理解できる
- [ ] モーダルのサイズが適切で、長文も快適に閲覧できる
- [ ] エラーメッセージが明確でユーザーフレンドリー
- [ ] ダウンロードしたファイルが適切なファイル名で保存される

*   **UI/UX要件:** 直感的な操作
*   **実装内容:** プレビューモーダル内に「クリップボードにコピー」ボタンを追加することを推奨します。これにより、ユーザーは解析結果の一部を他のアプリケーションに素早く転記でき、作業効率が大幅に向上します。
