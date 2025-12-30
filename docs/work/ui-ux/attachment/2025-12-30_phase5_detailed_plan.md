# Phase 5 詳細計画: 未実装分岐と最適化

**作成日:** 2025年12月30日  
**最終更新:** 2025年12月30日  
**Phase 4完了時点:** WBS 4.6完了  
**Phase 5目標:** 未実装分岐の実装、パフォーマンス改善、検証完了  
**予定工数:** 14時間  
**親ドキュメント:** [添付ファイルUI改善計画](/docs/work/ui-ux/attachment/2025-12-13_attachment-ui-improvement-plan.md)

---

## 0. 全体進捗と Phase 5の位置づけ

### 0.1 全フェーズ概要（添付ファイルUI改善計画より）

| Phase | 内容 | 工数 | 状態 | 完了率 |
|-------|------|------|------|--------|
| Phase 1 | モックアップ・UX評価 | 7h | ✅ 完了 | 100% |
| Phase 2 | モデル拡張 | 7h | ✅ 完了 | 100% |
| Phase 3 | 基盤改修 | 18h | ✅ 完了 | 100% |
| **Phase 4** | **インスペクター実装** | **41h** | **✅ 完了** | **100%** |
| **Phase 5** | **最終調整・未実装分岐** | **14h** | **📋 本計画** | **0%** |
| **合計** | **全5フェーズ** | **87h** | **🔄 進行中** | **91%** |

### 0.2 Phase 4の主要成果

**Phase 4完了内容:**
- ✅ 4タブ完全実装（Content/Details/History/Permissions）
- ✅ VLM/OCR/Tika統合とソース切り替え
- ✅ モックデータ12種類実装
- ✅ 権限管理とアクション（再処理、VLM再処理）
- ✅ パフォーマンス測定機能実装
- ✅ アクセシビリティ検証準備
- ✅ 旧VLMモーダル削除
- ✅ 全21テスト成功

**Phase 4で判明した課題（Phase 4レビューサマリーより）:**
- ⚠️ **UI分岐**: 24パターンの処理状態組み合わせのうち12パターンのみ実装（50%）
- ⚠️ **パフォーマンス**: ドロワー開閉時間2033ms（目標300ms未達）
- ⚠️ **パフォーマンス**: クエリ数6-7回（目標5回未達）
- 📋 **検証**: アクセシビリティ実検証未実施（ガイドのみ作成済み）

---

## 1. Phase 4完了時の状況

### 1.1 完了項目

✅ **Phase 4.0〜4.5:** FileInspector完全実装（4タブ）
- Content/Details/History/Permissionsタブ
- VLM/OCR/Tika統合
- 権限管理とアクション

✅ **Phase 4.6.1〜4.6.4:** 統合と検証準備
- 旧VLMモーダル削除
- フッターボタン整理
- UI分岐検証チェックリスト作成

### 1.2 未実装項目（Phase 5対応）

**A. UI分岐の実装（5項目）**
- 未最終化ファイル表示
- 全処理失敗ケース
- Tika単独失敗
- 処理タイムアウト
- MIMEタイプ不明ファイル

**B. パフォーマンス改善（Phase 4.6.5測定結果に基づく）**
- ドロワー開閉時間の最適化（キャッシング）
- クエリ数の削減（activitiesの遅延ロード）

**C. アクセシビリティ実検証**
- Chrome Lighthouse / VoiceOverでの実検証
- レポート作成

---

## 2. 未実装分岐の詳細仕様

### 🔴 優先度: 高

#### 2.1 未最終化ファイル表示

**現状の問題:**
- `finalized_at`がnullのファイルは表示が不完全
- 処理中と未最終化の区別が不明確

**必要な実装:**

**A. モックデータ追加（2種類）**
```php
// MockAttachmentService.php に追加
// ID: 10013 - 未最終化（処理完了、最終化待ち）
[
    'id' => 10013,
    'filename' => '未最終化_処理完了.pdf',
    'status' => 'completed',
    'finalized_at' => null, // ← 未最終化
    'mock_vlm_status' => 'completed',
    'mock_ocr_status' => 'completed',
    'mock_tika_status' => 'completed',
    // ...
]

// ID: 10014 - 未最終化（一部処理完了）
[
    'id' => 10014,
    'filename' => '未最終化_一部完了.jpg',
    'status' => 'processing',
    'finalized_at' => null,
    'mock_vlm_status' => 'completed',
    'mock_ocr_status' => 'processing',
    // ...
]
```

**B. Detailsタブ UI追加**
```blade
<!-- tabs/details.blade.php の処理情報セクション -->
@if (!$file->finalized_at)
    <x-mary-alert icon="o-clock" class="alert-warning mb-4">
        <span class="font-semibold">{{ __('ledger.file_inspector.status.not_finalized') }}</span>
        <p class="text-sm mt-1">
            {{ __('ledger.file_inspector.status.not_finalized_desc') }}
        </p>
    </x-mary-alert>
@endif
```

**C. Historyタブ 説明追加**
```blade
<!-- tabs/history.blade.php のタイムライン -->
@if (!$file->finalized_at)
    <div class="timeline-item">
        <div class="timeline-badge bg-warning">
            <x-mary-icon name="o-clock" class="w-4 h-4" />
        </div>
        <div class="timeline-content">
            <h4>{{ __('ledger.file_inspector.history.waiting_finalization') }}</h4>
            <p class="text-sm text-base-content/70">
                {{ __('ledger.file_inspector.history.finalization_desc') }}
            </p>
        </div>
    </div>
@endif
```

**D. 翻訳キー追加**
```json
// lang/ja.json
{
    "ledger.file_inspector.status.not_finalized": "最終化前",
    "ledger.file_inspector.status.not_finalized_desc": "処理は完了していますが、まだ最終化されていません。処理結果は確認できますが、変更される可能性があります。",
    "ledger.file_inspector.history.waiting_finalization": "最終化待ち",
    "ledger.file_inspector.history.finalization_desc": "全処理が完了しました。最終化処理を待っています。"
}
```

**E. テスト追加**
```php
// tests/Feature/Livewire/AttachedFile/FileInspectorTest.php
public function test_it_shows_not_finalized_badge_for_unfinalized_files(): void
{
    $file = AttachedFile::factory()->create([
        'finalized_at' => null,
        'tika_processed_at' => now(),
        'ocr_processed_at' => now(),
    ]);

    Livewire::test(FileInspector::class)
        ->call('openInspector', $file->id)
        ->assertSee('最終化前')
        ->assertSee('最終化されていません');
}
```

**工数見積:** 2h

---

#### 2.2 全処理失敗ケース

**現状の問題:**
- 全ソースが失敗した場合のエラー表示が不明確
- ユーザーが次のアクションを理解できない

**必要な実装:**

**A. モックデータ追加**
```php
// ID: 10015 - 全処理失敗
[
    'id' => 10015,
    'filename' => '破損ファイル.pdf',
    'mime' => 'application/pdf',
    'status' => 'error',
    'mock_vlm_text' => null,
    'mock_ocr_text' => null,
    'mock_tika_text' => null,
    'mock_vlm_status' => 'error',
    'mock_ocr_status' => 'error',
    'mock_tika_status' => 'error',
    'mock_error_message' => 'ファイルが破損しているため、テキスト抽出ができませんでした。',
    // ...
]
```

**B. Contentタブ エラーUI強化**
```blade
<!-- tabs/content.blade.php -->
@if ($this->isAllProcessingFailed())
    <div class="alert alert-error">
        <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6" />
        <div>
            <h3 class="font-bold">{{ __('ledger.file_inspector.error.all_failed_title') }}</h3>
            <p class="text-sm">{{ __('ledger.file_inspector.error.all_failed_message') }}</p>
            @if ($file->error_message)
                <p class="text-xs mt-2 opacity-80">{{ $file->error_message }}</p>
            @endif
            <div class="mt-3 flex gap-2">
                <button class="btn btn-sm btn-outline" 
                        wire:click="retryProcessing">
                    {{ __('ledger.file_inspector.actions.retry_all') }}
                </button>
                <a href="mailto:support@example.com" class="btn btn-sm btn-ghost">
                    {{ __('ledger.file_inspector.actions.contact_support') }}
                </a>
            </div>
        </div>
    </div>
@endif
```

**C. FileInspector.php メソッド追加**
```php
public function isAllProcessingFailed(): bool
{
    if (!$this->file) {
        return false;
    }

    $vlmFailed = $this->getSourceStatus('vlm') === 'error';
    $ocrFailed = $this->getSourceStatus('ocr') === 'error';
    $tikaFailed = $this->getSourceStatus('tika') === 'error';

    return $vlmFailed && $ocrFailed && $tikaFailed;
}
```

**D. 翻訳キー追加**
```json
{
    "ledger.file_inspector.error.all_failed_title": "テキスト抽出に失敗しました",
    "ledger.file_inspector.error.all_failed_message": "このファイルからテキストを抽出できませんでした。ファイルが破損しているか、対応していない形式の可能性があります。",
    "ledger.file_inspector.actions.retry_all": "再処理",
    "ledger.file_inspector.actions.contact_support": "サポートに連絡"
}
```

**E. テスト追加**
```php
public function test_it_shows_all_failed_error_message(): void
{
    $file = AttachedFile::factory()->create([
        'vlm_processed_at' => now(),
        'ocr_processed_at' => now(),
        'tika_processed_at' => now(),
        'vlm_error' => 'VLM error',
        'ocr_error' => 'OCR error',
        'tika_error' => 'Tika error',
    ]);

    Livewire::test(FileInspector::class)
        ->call('openInspector', $file->id)
        ->assertSee('テキスト抽出に失敗しました')
        ->assertSee('サポートに連絡');
}
```

**工数見積:** 2h

---

### 🟡 優先度: 中

#### 2.3 処理タイムアウト表示

**現状の問題:**
- タイムアウトとエラーの区別がない
- 大容量ファイルの対処方法が不明確

**必要な実装:**

**A. モックデータ追加**
```php
// ID: 10016 - タイムアウト
[
    'id' => 10016,
    'filename' => '大容量カタログ.pdf',
    'status' => 'error',
    'mock_ocr_status' => 'timeout',
    'mock_error_message' => 'OCR処理がタイムアウトしました（制限時間: 5分）',
    'size' => 1024 * 1024 * 50, // 50MB
]
```

**B. Historyタブ タイムアウト表示**
```blade
@if ($step['status'] === 'timeout')
    <x-mary-badge value="タイムアウト" class="badge-warning" />
    <p class="text-sm text-base-content/70 mt-1">
        {{ __('ledger.file_inspector.error.timeout_suggestion') }}
    </p>
@endif
```

**C. 翻訳キー追加**
```json
{
    "ledger.file_inspector.error.timeout_suggestion": "処理時間が制限を超えました。ファイルサイズが大きすぎる可能性があります。ファイルを分割するか、解像度を下げてください。"
}
```

**工数見積:** 1.5h

---

#### 2.4 Tika単独失敗

**現状の問題:**
- Tika失敗時の表示が一般的なエラーと同じ

**必要な実装:**

**A. Contentタブ メッセージ改善**
```blade
@if ($tikaFailed && !$vlmFailed && !$ocrFailed)
    <x-mary-alert icon="o-information-circle" class="alert-info">
        {{ __('ledger.file_inspector.info.tika_only_failed') }}
    </x-mary-alert>
@endif
```

**B. 翻訳キー追加**
```json
{
    "ledger.file_inspector.info.tika_only_failed": "基本的なテキスト抽出に失敗しましたが、VLM/OCR処理により代替のテキストが利用可能です。"
}
```

**工数見積:** 0.5h

---

### 🟢 優先度: 低

#### 2.5 MIMEタイプ不明ファイル

**現状の問題:**
- Phase 3で40種類以上のMIMEタイプを定義済み
- 未定義の場合のフォールバック表示が必要

**必要な実装:**

**A. MimeTypeHelper.php フォールバック追加**
```php
public static function getIconClass(string $mimeType): string
{
    return self::MIME_TYPE_MAP[$mimeType]['icon'] 
        ?? 'fa-file'; // フォールバック
}

public static function getCategoryColor(string $mimeType): string
{
    return self::MIME_TYPE_MAP[$mimeType]['color'] 
        ?? 'text-base-content/50'; // フォールバック
}
```

**工数見積:** 0.5h

---

## 3. Phase 5実装順序（推奨）

### Week 1: 優先度高の実装
1. **Day 1-2:** 未最終化ファイル表示（2h）
   - モックデータ追加
   - UI実装（Detailsタブ、Historyタブ）
   - テスト追加
2. **Day 3-4:** 全処理失敗ケース（2h）
   - モックデータ追加
   - エラーUI強化
   - テスト追加

### Week 2: 優先度中・低の実装
3. **Day 1:** 処理タイムアウト表示（1.5h）
4. **Day 2:** Tika単独失敗（0.5h）
5. **Day 3:** MIMEタイプ不明（0.5h）

### Week 3: 統合と検証
6. **Day 1-2:** 全分岐の統合テスト
7. **Day 3:** パフォーマンス最適化
8. **Day 4-5:** アクセシビリティ対応

**合計工数見積:** 6.5h（実装） + 2.5h（統合・最適化） = **9h**

---

## 4. テスト計画

### 4.1 新規テストケース

**FileInspectorTest.php に追加:**
```php
// 未最終化ファイル（2ケース）
test_it_shows_not_finalized_badge_for_unfinalized_files()
test_it_shows_finalization_waiting_in_history_tab()

// 全処理失敗（3ケース）
test_it_shows_all_failed_error_message()
test_it_shows_retry_button_for_failed_files()
test_it_shows_support_contact_link()

// タイムアウト（2ケース）
test_it_shows_timeout_badge_in_history()
test_it_shows_timeout_suggestion()

// Tika単独失敗（1ケース）
test_it_shows_tika_only_failed_info()

// MIMEタイプ不明（1ケース）
test_it_shows_fallback_icon_for_unknown_mime()
```

**合計:** 10テストケース（約30アサーション）

### 4.2 既存テストの更新

**影響を受ける既存テスト:**
- `test_it_opens_inspector_and_loads_mock_data()` - モックデータ14種類に更新
- 統合テストでの分岐パターン検証

---

## 5. ドキュメント更新

### 5.1 更新が必要なドキュメント

1. **UI分岐検証チェックリスト**
   - セクション4.2「未実装分岐」→「実装済み分岐」に移動
   - 新規モックファイル（10013〜10016）を追加

2. **機能仕様書**
   - `/docs/function/Attachment.md`
   - 全分岐パターンの説明を追加

3. **開発ガイド**
   - Copilot-instructions.md
   - 新規実装パターンを追加

---

## 6. リスク管理

### 6.1 技術的リスク

| リスク | 影響度 | 対策 |
|-------|--------|------|
| モックデータと実データの不整合 | 中 | 実ファイルでの検証を追加 |
| UI分岐の複雑化 | 中 | コンポーネント分割を検討 |
| パフォーマンス劣化 | 低 | キャッシング機構実装 |

### 6.2 スケジュールリスク

- **工数超過:** 9h見積もりに20%バッファ（11h）
- **テスト不足:** CI/CDで自動検証

---

## 7. Phase 4から持ち越しタスク（改善・検証）

### 7.1 パフォーマンス改善（優先度: 高）

**現状（Phase 4.6.5実測定結果）:**
- ✅ タブ切り替え: 平均33ms（目標100ms達成）
- ⚠️ クエリ数: 6-7回（目標5回、わずかに超過）
- ❌ ドロワー開閉: 2033ms（目標300ms未達、約6.8倍超過）

**改善タスク:**

#### A. キャッシング実装（工数: 2h、効果: 大）

**目的:** ドロワー開閉時間を2秒→1秒以下に短縮

**実装:**
```php
// FileInspector.php
public function openInspector($id)
{
    $cacheKey = "file_inspector:{$id}:{$this->searchKeyword}";
    $this->file = Cache::remember($cacheKey, 3600, function () use ($id) {
        return AttachedFile::with([
            'ledger:id,content,content_attached,ledger_define_id',
            'ledger.define:id,folder_id,title,workflow_enabled',
            'ledger.define.folder:id,title,tenant_id,parent_id',
            'creator:id,name',
            'modifier:id,name',
        ])->findOrFail($id);
    });
}
```

**期待効果:**
- 初回: 2秒（変わらず）
- 2回目以降: 0.5-1秒（500-1500ms短縮）

#### B. activitiesの遅延ロード（工数: 1h、効果: 中）

**目的:** クエリ数を6-7回→5回に削減、ドロワー開閉時間を100-200ms短縮

**実装:**
```php
// FileInspector.php
#[Computed]
public function activities()
{
    if ($this->selectedTab !== 'history') {
        return collect();
    }
    return $this->file->activities()->with('causer:id,name')->get();
}
```

**期待効果:**
- クエリ数: 6-7回 → 5回（目標達成）
- ドロワー開閉時間: 2033ms → 1800-1900ms

#### C. プリロード機能（工数: 1.5h、効果: 大、体感速度向上）

**目的:** ユーザーがクリックする前にデータをロード

**実装:**
```html
<!-- show.blade.php -->
<div @mouseenter="$wire.preloadFile({{ $file->id }})"
     @click="$dispatch('open-file-inspector', { id: {{ $file->id }} })">
    <!-- ファイルアイテム -->
</div>
```

**期待効果:**
- クリック時は既にキャッシュ済み
- 体感速度がほぼ即座（目標達成の体感）

**合計工数:** 4.5h

### 7.2 パフォーマンス実測定（優先度: 低）

**現状:**
- ✅ パフォーマンス測定機能実装完了（Phase 4.6.5）
- ✅ 初回実測定完了
- 🔄 改善後の再測定は未実施

**実測定の内容:**
1. **ドロワー開閉時間**: 目標300ms以内（改善後）
2. **タブ切り替え時間**: 目標100ms以内（既に達成）
3. **クエリ数**: 目標5回以内（改善後）

**実施方法:**
- 測定ガイドに従って実ブラウザで測定
- `docs/work/ui-ux/attachment/2025-12-30_phase4-6-measurement-guide.md` 参照

**工数:** 0.5h（実測定 + レポート更新）

**優先度:** 低（機能実装に影響なし、運用後でも実施可能）

### 7.3 アクセシビリティ実検証（優先度: 中）

**現状:**
- ✅ アクセシビリティ検証レポート作成済み（Phase 4.6.6）
- ✅ Safari Web Inspector auditファイル確認済み（`storage/logs/デモ監査.audit`）
- ✅ 検証項目チェックリスト準備完了
- 🔄 実検証は未実施

**検証内容:**

#### A. Chrome Lighthouse（総合スコア）
- 目標: 90点以上
- 自動検証によるWCAG 2.1 AA準拠確認

#### B. Chrome DevTools Accessibility
- ARIA属性の正確性確認
- アクセシビリティツリーの構造確認

#### C. コントラスト比測定
- 全要素が4.5:1以上を達成しているか
- Chrome DevTools Color Pickerで測定

#### D. キーボード操作テスト
- マウスを使わず全機能が操作可能か
- フォーカストラップの動作確認

#### E. Safari Web Inspector監査
- auditファイル（`storage/logs/デモ監査.audit`）を使用
- `getElementsByComputedRole`: role属性の検証
- `getComputedProperties`: ARIA属性の完全性確認
- `hasEventListeners`: イベントリスナーの適切性確認

#### F. VoiceOver検証
- スクリーンリーダーでの読み上げ確認
- 視覚障害者が操作可能か

**実施方法:**
- 検証レポートに従って実ブラウザで検証
- `docs/work/ui-ux/attachment/2025-12-30_phase4-6-6_accessibility_report.md` 参照

**工数:** 1.5h（実検証 + レポート更新）

**優先度:** 中（WCAG 2.1 AA準拠のため、Phase 5で実施推奨）

**期待される成果:**
- Lighthouseスコア: 90点以上
- 重大な問題: ゼロ
- 警告: Phase 5で対応検討
- コントラスト比: 全要素が4.5:1以上

---

## 8. Phase 5完了条件

### 8.1 機能要件
- [ ] 未実装分岐5種類の実装完了
- [ ] モックファイル14種類で全分岐動作確認
- [ ] 新規テスト10ケース全て成功

### 8.2 品質要件
- [ ] コードカバレッジ: 90%以上
- [ ] Laravel Pint: 違反ゼロ
- [ ] アクセシビリティ: WCAG 2.1 AA準拠（Phase 7.2の実検証含む）

### 8.3 ドキュメント要件
- [ ] 全ドキュメント更新完了
- [ ] Phase 5完了レポート作成

---

## 9. Phase 5完了後の状態

### 9.1 達成される品質

- ✅ FileInspector完全実装（全UI分岐対応、17/17パターン）
- ✅ パフォーマンス最適化（ドロワー開閉1秒以下、クエリ数5回）
- ✅ アクセシビリティWCAG 2.1 AA準拠（Lighthouse 90点以上）
- ✅ 31テスト全て成功（既存21+新規10）
- ✅ 本番環境デプロイ準備完了

### 9.2 残存課題（Phase 6以降）

**機能拡張（優先度: 低）:**
1. 大量ファイル（100件以上）のパフォーマンス検証
2. 仮想スクロール・ページネーション検討
3. 多言語対応（i18n）

**UI改善提案（UI検証チェックリストより）:**
1. 検索機能の強化（正規表現サポート、検索履歴）
2. タイムラインのフィルタリング（エラーのみ、完了のみ表示）
3. 信頼度閾値の調整UI（管理画面で変更可能に、現状0.7固定）

**パフォーマンス最適化（オプション）:**
1. キャッシュ無効化タイミングの実装（ファイル更新時に自動クリア）
2. activitiesの遅延ロード時のローディング表示追加

---

## 10. 参考資料

### 10.1 関連ドキュメント
- [Phase 4.6 実装ガイド](/docs/work/ui-ux/attachment/2025-12-30_phase4-6_implementation_guide.md)
- [UI分岐検証チェックリスト](/docs/work/ui-ux/attachment/2025-12-30_phase4-6-4_ui_verification_checklist.md)
- [FileInspector データ構造設計書](/docs/work/ui-ux/attachment/2025-12-15_file-inspector-data-structure.md)
- [パフォーマンス実測定ガイド](/docs/work/ui-ux/attachment/2025-12-30_phase4-6-measurement-guide.md)
- [パフォーマンス測定レポート](/docs/work/ui-ux/attachment/2025-12-30_phase4-6-5_performance_report.md)
- [アクセシビリティ検証レポート](/docs/work/ui-ux/attachment/2025-12-30_phase4-6-6_accessibility_report.md)

### 10.2 実装パターン参考
- 既存の処理中表示（ID: 10003）
- 既存のエラー表示（ID: 10007）
- 既存の信頼度バッジ（ID: 10001, 10011）

---

## 11. Phase 5開始前確認事項

1. [ ] Phase 4.6が完全に完了していること
2. [ ] 全テスト（Phase 4まで）が成功していること
3. [ ] 本ドキュメントをレビュー済み
4. [ ] 実装担当者がアサイン済み
5. [ ] UI検証チェックリストの未実装分岐を確認済み

---

**作成者:** Phase 4実装チーム  
**最終更新:** 2025年12月30日  
**Phase 5予定開始:** 2025年1月（Phase 4完了後）  
**Phase 5予定完了:** 2025年1月中旬（2週間、14h+バッファ）

