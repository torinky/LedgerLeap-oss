# Phase6 WBS 2.0: 抽出テキストプレビューモーダル 詳細設計書

**ドキュメントID:** `2025-11-11_phase6-wbs2-modal-design-details.md`  
**改訂日:** 2025-11-11  
**対象:** WBS 2.0 グローバルモーダル実装  
**作成者:** Gemini

**関連ドキュメント:**
- [Phase6: 抽出テキストプレビュー機能実装 計画書](./2025-11-08_phase6-text-preview-modal-plan.md)

---

## 1. 目的

本ドキュメントは、計画書で定義されたWBS 2.0「グローバルモーダル実装」の具体的な設計を詳細化するものである。`resources/views/livewire/ledger/show.blade.php` に実装されている既存のVLMプレビューモーダルを参考に、より汎用的で保守性の高いコンポーネントを設計する。

---

## 2. Livewireコンポーネント詳細 (`app/Livewire/AttachedFile/TextPreviewModal.php`)

計画書の方針に基づき、以下のプロパティとメソッドを実装する。

### 2.1. プロパティ

```php
use Livewire\Component;
use App\Models\AttachedFile;
use Livewire\Attributes\On;
use Illuminate\Support\Str;

class TextPreviewModal extends Component
{
    public bool $showModal = false;
    public ?AttachedFile $file = null;
    public ?array $badgeInfo = null;
    public ?string $previewText = null;
    public bool $isTruncated = false;

    // パフォーマンス対策定数
    private const MAX_PREVIEW_LENGTH = 500000; // 500KB
}
```
- **`$isTruncated`**: テキストが切り詰められたかどうかを示すフラグ。

### 2.2. メソッド

#### `show(int $attachedFileId)`
`showTextPreview` イベントで呼び出されるメインメソッド。

**処理フロー:**
1.  `AttachedFile::find($attachedFileId)` でファイルを取得。
2.  **ファイル存在チェック**: 存在しない場合は `Log::warning` を記録し、`$this->notifyNotFound()` を呼び出して処理を中断する。
3.  **プレビュー可否チェック**: `!$file->hasPreviewableText()` の場合は `Log::info` を記録し、`$this->notifyNotFound()` を呼び出して中断。
4.  **パフォーマンス対策**:
    - 取得したテキスト (`$file->previewable_text`) の長さをチェック。
    - `MAX_PREVIEW_LENGTH` を超える場合は、テキストを切り詰めて末尾に警告を追加し、`$this->isTruncated = true` とする。
    - ` $this->previewText = Str::limit($originalText, self::MAX_PREVIEW_LENGTH, '... (truncated)');`
5.  **データ設定**:
    - `$this->file = $file;`
    - `$this->badgeInfo = $file->getConfidenceBadgeInfo();`
6.  **モーダル表示**: `$this->showModal = true;`

#### `closeModal()`
モーダルを閉じる。

**処理フロー:**
1.  `$this->showModal = false;`
2.  `$this->reset('file', 'badgeInfo', 'previewText', 'isTruncated');` でプロパティを初期化し、メモリリークを防ぐ。

#### `notify`系メソッド
クリップボードコピーの結果をユーザーに通知するためのヘルパーメソッド。

```php
public function notifyCopySuccess(): void
{
    $this->dispatch('mary-toast', title: __('ledger.text_preview.copy_success'), icon: 'o-check');
}

public function notifyCopyFailed(): void
{
    $this->dispatch('mary-toast', title: __('ledger.text_preview.copy_failed'), icon: 'o-x-mark', type: 'error');
}

private function notifyNotFound(): void
{
    $this->dispatch('mary-toast', title: __('ledger.text_preview.not_found'), icon: 'o-exclamation-triangle', type: 'warning');
}
```

---

## 3. Bladeテンプレート詳細 (`resources/views/livewire/attached-file/text-preview-modal.blade.php`)

`show.blade.php` のVLMモーダルを参考に、MaryUIコンポーネントとAlpine.jsを組み合わせて実装する。

### 3.1. モーダル全体構造

```blade
<x-mary-modal wire:model="showModal" box-class="w-11/12 max-w-4xl">
    {{-- ヘッダー --}}
    <x-slot:title class="flex justify-between items-center">
        <span>{{ __('ledger.text_preview.modal_title') }}</span>
    </x-slot:title>

    {{-- ボディ --}}
    @if($file)
        {{-- 品質情報エリア --}}
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <span class="font-bold"><x-heroicon-o-document class="inline w-5 h-5" /> {{ $file->original_filename }}</span>
                @if($badgeInfo)
                    <x-mary-badge :value="$badgeInfo['label']" :class="$badgeInfo['class']" />
                @endif
            </div>
        </div>

        {{-- テキスト表示エリア --}}
        <div class="prose max-w-none overflow-y-auto max-h-[60vh] bg-base-200 p-4 rounded-lg">
            {!! Illuminate\Support\Str::markdown($previewText ?? '') !!}
        </div>
    @endif

    {{-- フッター (アクション) --}}
    <x-slot:actions>
        {{-- クリップボードコピーボタン --}}
        <div x-data="{ 
            textToCopy: @js($file?->previewable_text),
            fallbackCopy(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    $wire.notifyCopySuccess();
                } catch (err) {
                    $wire.notifyCopyFailed();
                }
                document.body.removeChild(textarea);
            }
        }">
            <x-mary-button 
                :label="$isTruncated ? __('ledger.text_preview.copy_full_text_button') : __('ledger.text_preview.copy_button')" 
                icon="o-clipboard" 
                @click="
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(textToCopy)
                            .then(() => $wire.notifyCopySuccess())
                            .catch(() => fallbackCopy(textToCopy));
                    } else {
                        fallbackCopy(textToCopy);
                    }
                " 
                class="btn-primary"
                :disabled="empty($previewText)"
                :tooltip="empty($previewText) ? __('ledger.text_preview.copy_unavailable') : ''"
            />
        </div>

        @php
            $isVlmSource = $file?->finalized_source === 'vlm';
            $downloadMarkdownUrl = $isVlmSource ? route('files.download-vlm', ['tenant' => tenant('id'), 'attachedFile' => $file->id, 'format' => 'markdown']) : '#';
            $downloadJsonUrl = $isVlmSource ? route('files.download-vlm', ['tenant' => tenant('id'), 'attachedFile' => $file->id, 'format' => 'json']) : '#';
        @endphp

        {{-- ダウンロードボタン --}}
        <x-mary-button 
            label="{{ __('ledger.vlm.download_markdown') }}" 
            link="{{ $downloadMarkdownUrl }}" 
            icon="o-arrow-down-on-square" 
            external="true"
            :disabled="!$isVlmSource"
            :tooltip="!$isVlmSource ? __('ledger.text_preview.download_unavailable_not_vlm') : ''"
        />
        <x-mary-button 
            label="{{ __('ledger.vlm.download_json') }}" 
            link="{{ $downloadJsonUrl }}" 
            icon="o-arrow-down-on-square" 
            external="true"
            :disabled="!$isVlmSource"
            :tooltip="!$isVlmSource ? __('ledger.text_preview.download_unavailable_not_vlm') : ''"
        />

        <x-mary-button label="{{ __('actions.close') }}" @click="$wire.closeModal()" />
    </x-slot:actions>
</x-mary-modal>
```

### 3.2. 設計の要点

- **クリップボードコピーボタンの制御**:
    - コピー対象のテキスト (`$previewText`) が空の場合、ボタンを `disabled`（不活性）状態にする。
    - 不活性の場合、ツールチップで理由 (`__('ledger.text_preview.copy_unavailable')`) を表示する。
- **ダウンロードボタンの制御**:
    - 抽出ソースがVLMでない場合 (`$file->finalized_source !== 'vlm'`)、MarkdownおよびJSONダウンロードボタンを `disabled` 状態にする。
    - 不活性の場合、ツールチップで理由 (`__('ledger.text_preview.download_unavailable_not_vlm')`) を表示する。
- **クリップボードコピーのフォールバック**: `show.blade.php` の堅牢な実装を参考に、`navigator.clipboard` が利用できない環境（HTTP接続時など）のために `document.execCommand` を使うフォールバック処理を実装する。
- **表示の最適化**: テキストが切り詰められた場合 (`$isTruncated`) は、コピーボタンのラベルを「全文をクリップボードにコピー」に変更し、ユーザーに全文がコピーされることを明示する。

### 3.3. 必要な翻訳キーの追加
この仕様変更に伴い、`lang/**/ledger.php` に以下の翻訳キーを追加する必要がある。
- `text_preview.copy_unavailable`: コピー対象のテキストがありません
- `text_preview.download_unavailable_not_vlm`: VLM抽出データではないためダウンロードできません

---

## 4. `app.blade.php` への組み込み

計画通り、`resources/views/components/layouts/app.blade.php` の `</body>` タグ直前に以下のコードを追加する。

```blade
    ...
    @livewire('notifications')
    @livewire('attached-file.text-preview-modal') {{-- ← ここに追加 --}}
    </body>
</html>
```

---

## 5. `ColumnHtmlService` への考慮事項

台帳詳細画面などでファイルアイコンの横に表示されるプレビューボタンは、**プレビュー可能なテキストが存在しない場合は表示しない**という当初の仕様を維持する。

- **表示条件**: `ColumnHtmlService` は、ボタンをレンダリングする前に `$attachment->hasPreviewableText()` と `!empty($attachment->previewable_text)` の両方をチェックし、条件を満たす場合のみボタンのHTMLを生成する。
- **連続クリック防止**: 表示されるボタンには `wire:loading.attr="disabled"` を追加し、イベント処理中の連続クリックを無効化する。

---

## 6. 懸念事項と対策まとめ

| 懸念事項 | 影響 | 対策 |
| :--- | :--- | :--- |
| **大容量テキストの表示** | 中 | Livewireのパフォーマンス低下を防ぐため、`show()`メソッド内でテキストが500KBを超える場合は切り詰めて表示。コピー時は全文を対象とする。 |
| **クリップボードAPIの互換性** | 低 | `navigator.clipboard`が使えない環境のため、`document.execCommand`によるフォールバックコピー機能を実装する。 |
| **モーダル内ボタンのUX** | 低 | コピー対象がない場合やダウンロード対象でない場合、関連ボタンを不活性化し、ツールチップで理由を明示する。 |
| **イベントの連続発行** | 中 | `ColumnHtmlService`で生成するボタンに`wire:loading.attr="disabled"`を追加し、処理中の多重クリックを防止する。 |
| **XSS脆弱性** | 低 | `league/commonmark`のデフォルト設定で安全なHTMLのみが許可されるため、リスクは低い。`@js()`ディレクティブも併用し、安全性を確保。 |

---

## 7. まとめ

本設計は、`show.blade.php` の既存モーダルの実装パターンを継承しつつ、パフォーマンス、UX、データ整合性に関する懸念事項への対策を盛り込んだ、より堅牢なグローバルコンポーネントを目指すものである。ユーザーからのフィードバックに基づき、台帳画面のボタンは「非表示」、モーダル内の各ボタンはコンテキストに応じて「不活性＋ツールチップ」と、最適なUIを提供する。

この設計に基づき実装を進めることで、計画書で定めた要件を確実に満たすことができる。
