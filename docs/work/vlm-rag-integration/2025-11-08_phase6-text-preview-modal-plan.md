# Phase6: 抽出テキストプレビュー機能実装 計画書（改訂版）

**ドキュメントID:** `2025-11-08_phase6-text-preview-modal-plan.md`  
**改訂日:** 2025-11-08  
**対象:** WBS 9.2 (新規) 抽出テキストプレビュー機能の実装  
**方針:** Phase5で完成した最終化テキストを、ソース（VLM, OCR, Tika）に応じて適切に表示する汎用的なプレビューモーダルを実装する。

**関連ドキュメント:**
- [VLM/OCR並列処理統合アーキテクチャ](../../architecture/vlm-parallel-processing-integration.md)
- [Phase5 実装報告書](./2025-11-08_phase5-implementation-report.md)

---

## 改訂履歴

### 2025-11-08 最終改訂
- **懸念事項の精査と対応方針の明確化**（7件の懸念を全て解消）
- **アーキテクチャの再々検討**: 
  - 一次改訂: 既存VLMパターンに合わせてページ内モーダルに変更
  - 最終決定: コード重複排除のため**グローバルモーダルに回帰**
- **編集画面の除外**: 別コンポーネントが担当するため考慮不要と判明
- **セキュリティ強化**: XSS対策（`@js()`）とエラーハンドリングの明示
- **WBSの最適化**: 実装工数を5.0日→3.5日に削減

---

## 1. 目的と機能名の再定義

### 1.1 目的
Phase5の並列処理を経て、`FinalizeProcessing`コマンドにより最適と判断された**最終的なテキストインデックス**を、ユーザーがモーダル（ダイアログ）で直感的に確認・活用できるUIを提供する。

### 1.2 機能名の変更
- **旧称**: VLMプレビューモーダル
- **新称**: **抽出テキストプレビューモーダル (Text Preview Modal)**
- **理由**: このモーダルはVLMの結果だけでなく、OCRやTikaによって最終化されたテキストも表示対象とするため。

---

## 2. アーキテクチャと設計方針

### 2.1 基本方針（再改訂）
**当初計画**: グローバルモーダルコンポーネント  
**一次改訂**: ページ内モーダルコンポーネント（既存VLMパターンに合わせる）  
**最終決定**: **グローバルLivewireモーダルコンポーネント**

**最終決定の理由:**
1. **コード重複の排除**: `ColumnHtmlService`は複数ページ（Show, RecordsTable, LedgerDiffViewer等）で使用されるため、各ページにモーダルコードを重複実装するのは非効率
2. **保守性向上**: 単一コンポーネントなので、仕様変更時の修正が1箇所で完結
3. **拡張性確保**: 将来的に他のページ（差分表示、一覧画面等）でも即座に利用可能
4. **既存VLMモーダルの問題**: 現在Showページのみに実装されているが、本来は全ページで利用されるべき機能

**技術的実現方法:**
- `app.blade.php`に`@livewire('attached-file.text-preview-modal')`を配置
- 各ページから`$dispatch('showTextPreview', { attachedFileId: ... })`で呼び出し
- モーダル内で必要な権限チェックとデータ取得を完結

### 2.2 設計原則

#### 2.2.1 データアクセスの安全性
- `AttachedFile`モデルに表示用アクセサを追加し、`content_attached`への安全なアクセスをカプセル化
- 構造: `$ledger->content_attached[$column_id][$hashedbasename]['meta']['content']`
- 既存の`LedgerContentProcessor`で実証済みの信頼性の高いパス

#### 2.2.2 セキュリティ対策
- **XSS防止**: Alpine.jsへのデータ渡しは`@js()`ディレクティブを使用
- **エラーログ**: ファイル未検出時は`Log::warning()`でトレース可能に
- **権限チェック**: モーダル側で`hasPreviewableText()`で可否判定

#### 2.2.3 グローバルコンポーネントの独立性
- モーダルコンポーネントは完全に独立したLivewireコンポーネント
- イベント駆動で呼び出し: `$dispatch('showTextPreview', { attachedFileId: ... })`
- 各ページの状態に依存しない設計（ファイルIDのみを受け取る）

#### 2.2.4 疎結合の維持
- `ColumnHtmlService`は表示判定のみを行い、Livewireイベントを発行
- モーダルの表示ロジックはグローバルコンポーネントで完結
- 既存実装パターン: `wire:click="\$dispatch('showTextPreview', { attachedFileId: ... })"`

#### 2.2.5 既存機能との統合
- 現行の`showVlmPreviewEvent`を拡張し、`showTextPreview`に移行
- VLM専用ボタンと抽出テキストボタンを条件分岐で出し分け（段階的移行）
- 最終的には単一のプレビューボタンに統合予定

---

## 3. WBS (Work Breakdown Structure)

| ID | タスク | 見積工数(人日) | 優先度 | 成果物 |
| :--- | :--- | :--- | :--- | :--- |
| **1.0** | **モデル改修** | **1.0** | 高 | `app/Models/AttachedFile.php` |
| 1.1 | プレビュー用アクセサ実装 | 0.3 | 高 | `previewable_text`, `previewable_source` |
| 1.2 | 表示判定メソッド実装 | 0.2 | 高 | `hasPreviewableText()` |
| 1.3 | 品質バッジ情報取得 | 0.3 | 中 | `getConfidenceBadgeInfo()` |
| 1.4 | ユニットテスト | 0.2 | 高 | `tests/Unit/Models/AttachedFileTest.php` |
| **2.0** | **グローバルモーダル実装** | **1.5** | 高 | |
| 2.1 | Livewireコンポーネント作成 | 0.7 | 高 | `app/Livewire/AttachedFile/TextPreviewModal.php` |
| 2.2 | Bladeテンプレート実装 | 0.5 | 高 | `resources/views/livewire/attached-file/text-preview-modal.blade.php` |
| 2.3 | app.blade.phpへの組み込み | 0.1 | 高 | `resources/views/components/layouts/app.blade.php` |
| 2.4 | Livewireコンポーネントテスト | 0.2 | 高 | `tests/Feature/Livewire/AttachedFile/TextPreviewModalTest.php` |
| **3.0** | **ColumnHtmlService改修** | **0.5** | 高 | `app/Services/Ledger/ColumnHtmlService.php` |
| 3.1 | プレビューボタン生成ロジック | 0.3 | 高 | VLMとの条件分岐実装 |
| 3.2 | イベント発行処理 | 0.2 | 高 | Livewireディレクティブ追加 |
| **4.0** | **動作確認と統合テスト** | **0.5** | 中 | ※グローバルコンポーネント完成後 |
| 4.1 | 複数ページでの動作確認 | 0.3 | 中 | Show, RecordsTable, DiffViewer等 |
| 4.2 | 既存VLM機能との統合テスト | 0.2 | 中 | 段階的移行の検証 |
| **5.0** | **ドキュメント・多言語対応** | **0.5** | 低 | |
| 5.1 | 翻訳キー追加 | 0.2 | 低 | `lang/ja/ledger.php`, `lang/en/ledger.php` |
| 5.2 | 実装報告書作成 | 0.3 | 低 | Phase6実装報告書 |
| | **合計（コア実装）** | **3.0** | | |
| | **全体合計** | **3.5** | | |

**実装の段階的アプローチ:**
- **Phase1**: タスク1.0〜3.0（コア機能実装、工数3.0日）
- **Phase2**: タスク4.0〜5.0（統合テスト・ドキュメント、工数0.5日）

**工数削減の理由:**
- グローバルコンポーネントにより、各ページへの個別実装が不要
- 編集画面は考慮不要（別コンポーネントが担当）

---

## 4. 詳細設計

### 4.1. タスク 1.0: `AttachedFile` モデルの改修

**ファイル**: `app/Models/AttachedFile.php`

#### 4.1.1 プレビュー用テキスト取得アクセサ
```php
public function getPreviewableTextAttribute(): ?string
```
**実装のポイント:**
- `finalized_source`に基づき表示テキストを返す
- VLM: `vlm_markdown`をそのまま返す（Markdown形式）
- OCR/Tika: `content_attached[$column_id][$hashedbasename]['meta']['content']`から取得
  - プレーンテキストはMarkdownコードブロック形式で返す: `"```
{$content}
```"`
- 安全なnullチェックとエスケープ処理

#### 4.1.2 プレビュー可否判定メソッド
```php
public function hasPreviewableText(): bool
```
**判定条件:**
- `processing_finalized_at`が設定済み（最終化完了）
- `contain_content`がtrue（コンテンツ存在）

#### 4.1.3 品質バッジ情報取得メソッド
```php
public function getConfidenceBadgeInfo(): ?array
```
**返却値の構造:**
```php
[
    'label' => '信頼度 95.3%',  // 表示テキスト
    'level' => 'success',       // バッジカラー: success/info/warning/neutral
    'icon' => 'heroicon-s-check-badge',  // Heroicon名
    'source' => 'VLM'          // 抽出ソース名
]
```

**ソース別の返却値:**
- **VLM**: 信頼度に応じた動的バッジ（≥90%: success, ≥70%: info, else: warning）
- **OCR**: 固定バッジ（info、ラベル「標準抽出」）
- **Tika**: 固定バッジ（neutral、ラベル「基本抽出」）

---

### 4.2. タスク 2.0: グローバルモーダル実装

#### 4.2.1 Livewireコンポーネント作成

**コマンド:**
```bash
./vendor/bin/sail artisan make:livewire AttachedFile/TextPreviewModal
```

**ファイル:** `app/Livewire/AttachedFile/TextPreviewModal.php`

**プロパティ:**
```php
public bool $showModal = false;
public ?AttachedFile $file = null;
```

**メソッド:**
```php
#[On('showTextPreview')]
public function show(int $attachedFileId): void
```
**処理内容:**
1. `AttachedFile::with('ledger:id,content_attached')->find($attachedFileId)`でファイル取得（必要なカラムのみ）
2. ファイル未検出時: `Log::warning()`でログ記録 + トースト通知して早期return
3. `hasPreviewableText()`で表示可否チェック
4. OKの場合: `$this->file = $file; $this->showModal = true;`

**クローズメソッド:**
```php
public function closeModal(): void
{
    $this->showModal = false;
    $this->file = null;
}
```

**セキュリティと最適化:**
- Eager Loading: `with('ledger:id,content_attached')`で必要なカラムのみ取得
- エラーログ: 本番環境でのデバッグ容易性
- イベント駆動: ページ状態に依存しない独立性

---

#### 4.2.2 Bladeテンプレート実装

**ファイル:** `resources/views/livewire/attached-file/text-preview-modal.blade.php`

**モーダル構造の要点:**

- `<x-mary-modal wire:model="showModal">`でモーダル制御
- ヘッダー: ファイル名とソースバッジの表示
- ボディ:
  - 品質情報表示エリア（バッジとスコア）
  - クリップボードコピーボタン（Alpine.js実装）
  - Markdownレンダリングエリア（`Str::markdown()`使用）
- フッター: 閉じるボタン

**セキュリティ実装:**
```blade
{{-- XSS対策: @js()ディレクティブで安全にデータ渡し --}}
<div x-data="{ 
    copied: false, 
    textToCopy: @js($file?->previewable_text ?? '') 
}">
```

**レスポンシブ対応:**
- モーダル幅: `w-11/12 max-w-4xl`
- コンテンツ高さ: `max-h-[60vh] overflow-y-auto`

---

#### 4.2.3 app.blade.phpへの組み込み

**ファイル:** `resources/views/components/layouts/app.blade.php`

**追加位置:** `</body>`タグの直前

```blade
{{-- グローバルモーダル群 --}}
@livewire('attached-file.text-preview-modal')
```

**理由:**
- アプリケーション全体で利用可能
- ページ遷移時も状態がリセットされる
- 他のグローバルコンポーネント（通知等）と同じ配置

---

#### 4.2.2 Bladeテンプレート実装

**ファイル**: `resources/views/livewire/ledger/show.blade.php`

**モーダル構造（概要）:**
- `<x-mary-modal wire:model="showTextPreviewModal">`でモーダル制御
- ヘッダー: ファイル名表示
- ボディ:
  - 品質バッジ（ソース名、信頼度）
  - クリップボードコピーボタン（Alpine.js）
  - Markdownレンダリングエリア（`Str::markdown()`使用）
- フッター: 閉じるボタン

**セキュリティ対策:**
- Alpine.jsへのデータ渡し: `@js($file->previewable_text)`（自動エスケープ）
- Markdown表示: `{!! Str::markdown() !!}`（sanitize済み）

**アクセシビリティ:**
- 品質バッジにツールチップ（`data-tip`属性）
- キーボード操作対応（Escキーで閉じる）

---

### 4.3. タスク 3.0: `ColumnHtmlService` 改修

**ファイル**: `app/Services/Ledger/ColumnHtmlService.php`

#### プレビューボタン生成ロジック（`getFileHtml()`メソッド内）

**実装方針:**
1. `$attachment->hasPreviewableText()`で表示可否判定
2. 条件分岐:
   - VLM結果あり: VLMプレビューボタン（既存）
   - VLM結果なし + プレビュー可能: 抽出テキストプレビューボタン（新規）

**ボタン生成コードの要点:**
- ツールチップ: 翻訳キー`ledger.text_preview.button_tooltip`
- イベント: `wire:click="\$dispatch('showTextPreview', { attachedFileId: ... })"`
- アイコン: `fa-solid fa-eye`（既存VLMと同じ）

**既存コードとの統合:**
- VLM専用ボタンと並列表示（段階的移行）
- 最終的にはVLM専用ボタンを削除し、統一予定

---

## 5. テスト戦略

### 5.1 ユニットテスト（`AttachedFileTest.php`）

**テスト対象:**
1. `getPreviewableTextAttribute()`
   - VLM結果の正常取得
   - OCR/Tika結果の正常取得とコードブロック化
   - データ不足時のnull返却
2. `hasPreviewableText()`
   - 最終化済み + コンテンツありでtrue
   - 条件不足でfalse
3. `getConfidenceBadgeInfo()`
   - VLM信頼度別の正しいバッジ情報
   - OCR/Tikaの固定バッジ情報

### 5.2 機能テスト（`AttachedFile/TextPreviewModalTest.php`）

**テスト対象:**
1. `showTextPreview`イベント処理
   - 正常系: モーダル表示成功
   - 異常系: ファイル未検出時のエラーハンドリング
   - 異常系: プレビュー不可ファイルの拒否
2. モーダル内容の表示確認
   - ファイル名、品質バッジの表示
   - Markdownレンダリング結果
   - VLM/OCR/Tikaごとの表示分岐
3. モーダルのクローズ処理
   - `closeModal()`で状態がリセットされる

**グローバルコンポーネントのテスト戦略:**
```php
// 任意のページから呼び出し可能であることを検証
Livewire::test(TextPreviewModal::class)
    ->dispatch('showTextPreview', attachedFileId: $file->id)
    ->assertSet('showModal', true)
    ->assertSet('file.id', $file->id);
```

### 5.3 統合テスト

**手動テスト項目:**
1. 各ページでのプレビューボタン表示確認
   - Show画面（台帳詳細）
   - RecordsTable（一覧画面）
   - LedgerDiffViewer（差分表示）
2. 各抽出ソース（VLM, OCR, Tika）でのプレビュー内容確認
3. 複数ファイルの連続プレビュー（モーダル状態のリセット確認）
4. レスポンシブデザイン確認（モーダルサイズ調整）
5. クリップボードコピー機能の動作確認（ブラウザAPI）

---

## 6. 多言語対応

### 翻訳キー追加（`lang/ja/ledger.php`）

```php
'text_preview' => [
    'modal_title' => '抽出テキストプレビュー',
    'button_tooltip' => '抽出テキストをプレビュー',
    'quality_label' => '品質',
    'source_vlm' => 'VLM',
    'source_ocr' => '標準抽出',
    'source_tika' => '基本抽出',
    'copy_button' => 'クリップボードにコピー',
    'copy_success' => 'コピーしました',
    'copy_failed' => 'コピーに失敗しました',
    'not_found' => 'プレビュー可能なテキストがありません',
    'file_not_found' => 'ファイルが見つかりません',
],
```

**英語版（`lang/en/ledger.php`）も同様に追加**

---

## 7. リスクと対策

### 7.1 識別されたリスク

| リスク | 影響度 | 対策 | 担当 |
| :--- | :--- | :--- | :--- |
| `content_attached`構造の変更 | 高 | ユニットテストで構造検証、変更検知 | Backend |
| 大容量テキストの表示遅延 | 中 | 最大表示文字数制限の検討 | Frontend |
| XSS脆弱性 | 高 | `@js()`と`Str::markdown()`の正しい使用 | Backend |
| 既存VLM機能との干渉 | 中 | 段階的移行、並列運用期間の設定 | Backend |

### 7.2 後方互換性

- 既存の`showVlmPreviewEvent`は削除せず、段階的に`showTextPreview`に移行
- VLM専用ボタンは当面残し、プレビューボタンと並列表示
- Phase2完了後、統一版に移行する計画

---

## 8. 成功基準

### 8.1 機能要件
- ✅ 全抽出ソース（VLM, OCR, Tika）のテキストが正しく表示される
- ✅ 品質バッジが正確に表示される
- ✅ クリップボードコピーが正常動作する
- ✅ ファイル未検出時に適切なエラー処理が行われる

### 8.2 非機能要件
- ✅ モーダル表示が1秒以内に完了する
- ✅ XSS脆弾性テストで問題が検出されない
- ✅ 全ユニットテスト・機能テストが合格する
- ✅ レスポンシブデザインで各デバイスで正常表示

### 8.3 ドキュメント要件
- ✅ 実装報告書の作成完了
- ✅ 翻訳キーの日英両言語追加完了

---

## 9. 今後の展開

### Phase2以降の計画
1. **VLM機能の統合**
   - `showVlmPreviewEvent`の廃止
   - 既存VLMモーダルを`TextPreviewModal`に統合
   - 単一のプレビューボタンへの完全移行
2. **パフォーマンス最適化**
   - 大容量テキスト（>10KB）の表示制限
   - プレビュー用キャッシュ機構の検討
   - 遅延ロードの実装
3. **利用者フィードバックの収集**
   - 各ページでの利用頻度の分析
   - UI/UXの改善点の洗い出し

---

## 10. まとめ

本計画では、以下の改訂プロセスを経て最適なアーキテクチャを決定しました：

**改訂プロセス:**
1. **当初計画**: グローバルモーダルコンポーネント
2. **一次改訂**: 既存VLMパターンに合わせてページ内モーダルに変更
3. **最終決定**: コード重複排除のため**グローバルモーダルに回帰**

**最終アーキテクチャのメリット:**
1. **DRY原則の徹底**: 単一実装で全ページから利用可能
2. **保守性向上**: 仕様変更時の修正が1箇所で完結
3. **安全性強化**: XSS対策の徹底、エラーハンドリングの充実
4. **拡張性確保**: 新規ページでも即座に利用可能
5. **段階的移行**: 既存VLM機能との共存と統合パス

**重要な設計判断:**
- 編集画面は別コンポーネントが担当するため考慮不要
- `ColumnHtmlService`が複数ページで使用されるため、グローバルコンポーネントが最適
- イベント駆動設計により、ページ間の疎結合を維持

**実装工数:** Phase1（3.0日） + Phase2（0.5日） = **合計3.5日**

これにより、保守性と拡張性を兼ね備えた、持続可能なプレビュー機能を提供します。