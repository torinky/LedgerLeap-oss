# Phase6 WBS 1.0 モデル改修 詳細計画書

**ドキュメントID:** `2025-11-11_phase6-wbs1-model-refactor-plan.md`  
**作成日:** 2025-11-11  
**更新日:** 2025-11-11 (重大な誤認を修正)  
**対象:** WBS 1.0 (モデル改修)  
**親ドキュメント:** [Phase6: 抽出テキストプレビュー機能実装 計画書](./2025-11-08_phase6-text-preview-modal-plan.md)

---

## 1. はじめに

### 1.1 目的
本ドキュメントは、抽出テキストプレビュー機能実装（Phase6）におけるWBS 1.0「モデル改修」の詳細設計と実装計画を定義する。

### 1.2 データ構造の正確な理解

**重要:** 当初計画では`content_attached`（Ledgerモデル）内に`finalized_source`や`vlm_markdown`が存在すると誤認していた。コード調査の結果、以下の構造が確定した。

#### 実際のデータ構造

1. **`AttachedFile`モデル（`attached_files`テーブル）のカラム:**
   - `column_id`: 所属する台帳カラムのID（既存、fillable）
   - `filename`: 元ファイル名
   - `hashedbasename`: ハッシュ化されたファイル名
   - `vlm_markdown`: VLM抽出結果（longtext）
   - `vlm_confidence`: VLM信頼度スコア（0.000-1.000）
   - `finalized_source`: 最終化時の採用ソース（'vlm' | 'ocr' | 'tika'）
   - `processing_finalized_at`: 最終化処理完了日時
   - `tika_processed_at`, `ocr_processed_at`, `vlm_processed_at`: 各処理の完了日時

2. **`Ledger`モデルの`content_attached`（JSON）:**
   - 構造: `{column_id: {filename: {meta: {content: "text"}}}}`
   - **OCR/Tikaで抽出されたテキスト**を格納
   - VLMの結果は`AttachedFile`モデルに格納され、最終化処理で採用された場合のみ`content_attached`にも保存される

3. **既存の実装:**
   - `AttachedFile::getOriginalFilenameAttribute()`: `ledger->content`から元ファイル名を逆引き
   - `hasVlmResult()`, `isVlmProcessing()`, `isVlmFailed()`: VLM処理ステータス判定メソッド

#### 設計への影響

- **シンプルな実装:** `AttachedFile`の**既存カラムを直接参照**すればよい
- **N+1問題の軽減:** OCR/Tikaテキスト取得時のみ`ledger`リレーションが必要
- **既存ロジックとの整合:** 最終化処理（Phase5）と完全に整合

---

## 2. 詳細設計

**対象ファイル**: `app/Models/AttachedFile.php`

### 2.1 新規追加メソッド

#### 2.1.1 `hasPreviewableText()`: プレビュー可否判定

**型:** `bool`  
**説明:** プレビュー可能なテキストが存在するかを判定する。

**実装要点:**
```php
public function hasPreviewableText(): bool
{
    return $this->processing_finalized_at !== null 
        && $this->finalized_source !== null
        && !empty($this->previewable_text);
}
```

#### 2.1.2 `getPreviewableTextAttribute()`: プレビュー用テキスト取得

**型:** `?string`  
**説明:** 最終化された抽出テキストを、表示に適した形式で返す。

**実装要点:**
```php
protected function previewableText(): Attribute
{
    return Attribute::make(
        get: function () {
            if (!$this->processing_finalized_at || !$this->finalized_source) {
                return null;
            }
            
            return match($this->finalized_source) {
                'vlm' => $this->vlm_markdown,
                'ocr', 'tika' => $this->getOcrTikaFormattedText(),
                default => null,
            };
        }
    );
}

private function getOcrTikaFormattedText(): ?string
{
    // content_attachedからテキスト取得（Eager Loading推奨）
    if (!$this->relationLoaded('ledger')) {
        return null; // N+1防止のため、Eager Loading必須
    }
    
    $text = data_get(
        $this->ledger->content_attached, 
        "{$this->column_id}.{$this->filename}.meta.content"
    );
    
    return $text ? "```\n{$text}\n```" : null;
}
```

**要点:**
- VLMの場合は`vlm_markdown`を直接返す（既にMarkdown形式）
- OCR/Tikaの場合は`content_attached`から取得し、コードブロックで囲む
- Eager Loadingされていない場合は`null`を返し、N+1問題を回避

#### 2.1.3 `getConfidenceBadgeInfo()`: 品質バッジ情報取得

**型:** `?array`  
**説明:** プレビューモーダルに表示する品質バッジ情報を生成する。

**戻り値構造:**
```php
[
    'label' => 'VLM抽出',
    'color' => 'success',
    'score' => '95.2%',
    'tooltip' => '高精度なVLM抽出結果です',
]
```

**実装要点:**
```php
public function getConfidenceBadgeInfo(): ?array
{
    if (!$this->processing_finalized_at || !$this->finalized_source) {
        return null;
    }
    
    return match($this->finalized_source) {
        'vlm' => $this->getVlmBadgeInfo(),
        'ocr' => [
            'label' => 'OCR抽出',
            'color' => 'warning',
            'score' => null,
            'tooltip' => 'OCRで抽出されたテキストです',
        ],
        'tika' => [
            'label' => 'Tika抽出',
            'color' => 'info',
            'score' => null,
            'tooltip' => 'Apache Tikaで抽出されたテキストです',
        ],
        default => null,
    };
}

private function getVlmBadgeInfo(): array
{
    $score = $this->vlm_confidence * 100;
    
    if ($score >= 70) {
        $color = 'success';
        $tooltip = '高精度なVLM抽出結果です';
    } elseif ($score >= 50) {
        $color = 'warning';
        $tooltip = '中精度のVLM抽出結果です';
    } else {
        $color = 'error';
        $tooltip = '低精度のVLM抽出結果です';
    }
    
    return [
        'label' => 'VLM抽出',
        'color' => $color,
        'score' => number_format($score, 1) . '%',
        'tooltip' => $tooltip,
    ];
}
```

**要点:**
- VLM: 信頼度スコアに応じた色分け（緑: ≥70%, 黄: ≥50%, 赤: <50%）
- OCR: 固定で警告色
- Tika: 固定で情報色

---

## 3. テスト計画

**テストの配置**: `tests/Feature/Models/AttachedFilePreviewTest.php` (新規作成)

### 3.1 テスト準備

```php
use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttachedFilePreviewTest extends TestCase
{
    use RefreshDatabase;
    
    private function createFinalizedAttachment(
        string $source, 
        ?float $vlmConfidence = null
    ): AttachedFile {
        $ledger = Ledger::factory()->create([
            'content' => [1 => ['hashed123' => 'test.pdf']],
            'content_attached' => [
                1 => [
                    'test.pdf' => [
                        'meta' => ['content' => 'OCR extracted text'],
                    ],
                ],
            ],
        ]);
        
        return AttachedFile::factory()
            ->for($ledger)
            ->create([
                'column_id' => 1,
                'filename' => 'test.pdf',
                'hashedbasename' => 'hashed123',
                'finalized_source' => $source,
                'processing_finalized_at' => now(),
                'vlm_markdown' => $source === 'vlm' ? '# VLM Result' : null,
                'vlm_confidence' => $vlmConfidence,
            ]);
    }
}
```

### 3.2 テストケース

#### TC1: VLM抽出テキストのプレビュー
```php
public function test_previewable_text_returns_vlm_markdown()
{
    $attachment = $this->createFinalizedAttachment('vlm', 0.95);
    
    $this->assertTrue($attachment->hasPreviewableText());
    $this->assertEquals('# VLM Result', $attachment->previewable_text);
}
```

#### TC2: OCR抽出テキストのプレビュー
```php
public function test_previewable_text_returns_ocr_text_with_code_block()
{
    $attachment = $this->createFinalizedAttachment('ocr');
    $attachment->load('ledger'); // Eager Loading
    
    $this->assertTrue($attachment->hasPreviewableText());
    $this->assertStringContainsString('```', $attachment->previewable_text);
    $this->assertStringContainsString('OCR extracted text', $attachment->previewable_text);
}
```

#### TC3: Eager Loadingなしの挙動
```php
public function test_previewable_text_returns_null_without_eager_loading()
{
    $attachment = $this->createFinalizedAttachment('ocr');
    // ledgerリレーションを読み込まない
    
    $this->assertNull($attachment->previewable_text);
}
```

#### TC4: 最終化前のファイル
```php
public function test_has_previewable_text_returns_false_before_finalization()
{
    $attachment = AttachedFile::factory()->create([
        'processing_finalized_at' => null,
    ]);
    
    $this->assertFalse($attachment->hasPreviewableText());
}
```

#### TC5: VLM高精度バッジ
```php
public function test_confidence_badge_info_for_high_quality_vlm()
{
    $attachment = $this->createFinalizedAttachment('vlm', 0.95);
    
    $badge = $attachment->getConfidenceBadgeInfo();
    
    $this->assertEquals('VLM抽出', $badge['label']);
    $this->assertEquals('success', $badge['color']);
    $this->assertEquals('95.0%', $badge['score']);
}
```

#### TC6: VLM低精度バッジ
```php
public function test_confidence_badge_info_for_low_quality_vlm()
{
    $attachment = $this->createFinalizedAttachment('vlm', 0.45);
    
    $badge = $attachment->getConfidenceBadgeInfo();
    
    $this->assertEquals('error', $badge['color']);
}
```

#### TC7: OCRバッジ
```php
public function test_confidence_badge_info_for_ocr()
{
    $attachment = $this->createFinalizedAttachment('ocr');
    
    $badge = $attachment->getConfidenceBadgeInfo();
    
    $this->assertEquals('OCR抽出', $badge['label']);
    $this->assertEquals('warning', $badge['color']);
    $this->assertNull($badge['score']);
}
```

---

## 4. 懸念事項と対策

| 懸念事項 | 詳細 | 対策 |
|:---|:---|:---|
| **N+1問題（限定的）** | OCR/Tikaテキスト取得時に`ledger`リレーションが必要 | `with('ledger')`を使用する箇所を明示的にドキュメント化。VLM採用の場合（最も一般的）は影響なし。 |
| **データ不整合** | `finalized_source`が設定されているのに対応するテキストが存在しない場合 | `data_get()`を使用し、`null`を安全に返す。テストで異常系をカバー。 |
| **`content_attached`のキー構造依存** | `{column_id}.{filename}.meta.content`のパス依存 | Phase5の最終化処理と整合しており、変更の可能性は低い。万が一変更があれば、この箇所のみ修正すればよい。 |
| **既存コードへの影響** | `ColumnHtmlService`等で既に`content_attached`を使用している箇所との整合性 | 既存コードは変更せず、新規メソッドのみを追加する。既存ロジックとは独立している。 |

---

## 5. 実装チェックリスト

- [ ] `hasPreviewableText()`メソッドを実装
- [ ] `getPreviewableTextAttribute()`アクセサを実装
- [ ] `getOcrTikaFormattedText()`プライベートメソッドを実装
- [ ] `getConfidenceBadgeInfo()`メソッドを実装
- [ ] `getVlmBadgeInfo()`プライベートメソッドを実装
- [ ] `tests/Feature/Models/AttachedFilePreviewTest.php`を作成
- [ ] 全テストケース（TC1-TC7）を実装
- [ ] テスト実行（`./vendor/bin/sail test`）
- [ ] コード整形（`./vendor/bin/sail pint`）

---

## 6. 次のステップ

WBS 1.0完了後、以下を実施:
1. **WBS 2.0:** Livewireコンポーネント実装（プレビューモーダル）
2. **WBS 3.0:** UI/UX実装（ファイルカード改修、モーダルデザイン）
3. **統合テスト:** エンドツーエンドでのプレビュー機能検証

---

**改訂履歴:**
- 2025-11-11: 初版作成（誤認を含む）
- 2025-11-11: データ構造の誤認を修正、実装方針を大幅に簡素化
