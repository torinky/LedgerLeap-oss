# Issue #64: カラム単位での「変更なし」表示実装計画

## 概要
台帳の更新履歴タブにおいて、過去バージョンと比較した際に変更がないカラムに対して、「現行と同じ」というプレースホルダーを表示することで、ユーザーがどのカラムが変更されたかを直感的に理解できるようにする。

## 関連Issue
- GitHub Issue: https://github.com/torinky/LedgerLeap/issues/64

## 現状分析

### 現在の実装
- **グループ単位**での `identical_content` プレースホルダー表示
- 条件: `!$hasChangedColumns && !collect($group['columns'])->contains('is_omitted', true)`
- 台帳全体に変更がない場合のみ、グループ全体に対してプレースホルダーを表示

### 問題点
1. 一部のカラムに変更があると、変更のないカラムでも値が表示される
2. 変更があったカラムとないカラムの区別が視覚的に分かりにくい
3. ユーザーが変更箇所を探すのに時間がかかる

### 既存の翻訳キー (lang/ja/ledger.php)
```php
'diff' => [
    'added' => '追加',
    'deleted' => '削除済み',
    'modified' => '変更',
    'not_exist' => 'なし',
    'identical_content' => '内容一致',
    'identical_content_hint' => '比較対象と内容は完全に一致しています。',
    'no_changes' => '変更はありません',
    'omitted_items' => ':count項目の非表示項目があります',
    // ...
]
```

### テスト状況
#### 既存テスト
1. **LedgerDiffViewerTest.php** (Feature)
   - `it_renders_correctly_with_data_from_processor`: 基本的なレンダリング
   - `it_renders_omitted_indicator_when_columns_are_filtered_by_level`: 省略表示
   - `it_shows_diff_view_when_show_changes_is_true`: 差分表示の切り替え
   - `it_displays_attached_files_correctly_in_diff_viewer`: 添付ファイル表示

2. **LedgerDiffProcessorTest.php** (Unit)
   - `it_returns_unchanged_status_when_content_is_identical`: unchanged ステータスの検証
   - `it_identifies_modified_columns`: modified ステータスの検証
   - `it_identifies_added_columns`: added ステータスの検証
   - `it_identifies_deleted_columns`: deleted ステータスの検証

#### テストカバレッジの確認
- ✅ `status === 'unchanged'` の判定ロジックは既にテスト済み
- ✅ `status === 'modified'`, `'added'`, `'deleted'` も検証済み
- ❌ **カラム単位でのプレースホルダー表示**のUIテストは未実装

## 実装方針

### 1. 翻訳キーの統合方針
既存の翻訳キーを活用し、新規追加を最小限にする。

**追加する翻訳キー:**
```php
'diff' => [
    // ...existing keys...
    'same_as_current' => '現行と同じ',  // NEW: unchangedカラム用
]
```

**活用する既存キー:**
- `'not_exist'` → `status === 'added'` の場合
- `'deleted'` → `status === 'deleted'` の場合
- `'same_as_current'` → `status === 'unchanged'` の場合（新規）

### 2. 実装対象ファイル

#### A. Bladeビュー (UI層)
- `resources/views/livewire/ledger/ledger-diff-viewer.blade.php`

#### B. 翻訳ファイル
- `lang/ja/ledger.php` (メイン)
- 必要に応じて `lang/ja.json` も更新

#### C. テストファイル (追加)
- `tests/Feature/Livewire/Ledger/LedgerDiffViewerTest.php` に新規テスト追加

### 3. 既存機能の維持
以下の機能は変更せず、そのまま維持する:
- ✅ グループ全体に変更がない場合の大きなプレースホルダー (既存実装)
- ✅ `LedgerDiffProcessor` のロジック (変更不要)
- ✅ `LedgerContentProcessor` のロジック (変更不要)
- ✅ 既存の全テスト

## WBS: 作業ブロック分解

### Phase 1: 準備・調査 (完了)
- [x] **Task 1.1**: 現状実装の調査
  - 完了日: 2026-02-14
  - 成果物: Issue #64 作成、履歴調査コメント追加

- [x] **Task 1.2**: テスト状況の調査
  - 完了日: 2026-02-14
  - 成果物: このドキュメント

- [x] **Task 1.3**: 実装計画の策定
  - 完了日: 2026-02-14
  - 成果物: このドキュメント

### Phase 2: 実装
#### Block 2.1: 翻訳キー追加
- [ ] **Task 2.1.1**: `lang/ja/ledger.php` に `same_as_current` キー追加
  - 予想工数: 5分
  - 依存: なし
  - 検証: キーが存在し、翻訳が正しく表示されること

#### Block 2.2: Bladeビュー更新
- [ ] **Task 2.2.1**: `ledger-diff-viewer.blade.php` の「旧データ」カラム表示ロジック修正
  - 予想工数: 30分
  - 依存: Task 2.1.1
  - ��更内容:
    - `$column['status'] === 'unchanged'` の場合にプレースホルダーを表示
    - それ以外は従来通り `$column['old_value_html']` を表示
    - グループ全体の `$showIdenticalPlaceholder` ロジックは維持
  - 検証: 手動テストで表示確認

- [ ] **Task 2.2.2**: スタイリング調整
  - 予想工数: 15分
  - 依存: Task 2.2.1
  - 内容: プレースホルダーの背景色、アイコン、テキストサイズの調整
  - 検証: ライト/ダークモード両方で視認性確認

### Phase 3: テスト実装
#### Block 3.1: Feature テスト追加
- [ ] **Task 3.1.1**: `it_displays_placeholder_for_unchanged_columns_in_diff_view` テスト作成
  - 予想工数: 45分
  - 依存: Task 2.2.1
  - テスト内容:
    - 一部カラムが `modified`、一部が `unchanged` のケース
    - `unchanged` カラムに `same_as_current` が表示されること
    - `modified` カラムには値が表示されること
  - 成果物: `LedgerDiffViewerTest.php` に新規テストメソッド追加

- [ ] **Task 3.1.2**: `it_shows_group_placeholder_when_all_columns_unchanged` テスト作成
  - 予想工数: 30分
  - 依存: Task 3.1.1
  - テスト内容:
    - 全カラムが `unchanged` の場合
    - 既存のグループ単位プレースホルダーが表示されること（後方互換性確認）
  - 成果物: 同上

#### Block 3.2: 既存テストの実行
- [ ] **Task 3.2.1**: 既存の全テスト実行
  - 予想工数: 10分
  - 依存: Task 3.1.2
  - 対象:
    - `LedgerDiffViewerTest.php` 全テスト
    - `LedgerDiffProcessorTest.php` 全テスト
    - `ShowTest.php` 関連テスト
  - 検証: 全てパスすることを確認

### Phase 4: コードレビュー・調整
- [ ] **Task 4.1**: Pint 実行
  - 予想工数: 5分
  - コマンド: `./vendor/bin/sail pint`

- [ ] **Task 4.2**: エラーチェック
  - 予想工数: 10分
  - 使用ツール: `laravel-boost` MCP (`last-error`, `browser-logs`)

- [ ] **Task 4.3**: 最終動作確認
  - 予想工数: 20分
  - 確認項目:
    - 履歴タブで過去バージョンと比較
    - 変更あり/なしのカラムが正しく表示される
    - グループ単位のプレースホルダーも正常動作

### Phase 5: ドキュメント・報告
- [ ] **Task 5.1**: 実装完了報告をIssue #64に追記
  - 予想工数: 15分
  - 内容:
    - 実装内容のサマリー
    - 追加したテスト
    - スクリーンショット (変更前/後)

- [ ] **Task 5.2**: このドキュメントの「実装結果」セクション追記
  - 予想工数: 10分

- [ ] **Task 5.3**: コミット・PR作成
  - 予想工数: 10分
  - コミットメッセージ: `feat(ledger): カラム単位での「変更なし」プレースホルダー表示を実装 #64`

## 総予想工数
- Phase 1: 完了
- Phase 2: 50分
- Phase 3: 85分
- Phase 4: 35分
- Phase 5: 35分
- **合計: 約3時間25分** (準備時間除く)

## 技術的考慮事項

### 1. 後方互換性
- 既存のグループ単位プレースホルダー表示は維持
- `$hasChangedColumns === false` の場合は従来通りの動作

### 2. パフォーマンス
- 変更なし: `$column['status']` は既に計算済み
- 追加のロジックは条件分岐のみ

### 3. アクセシビリティ
- アイコンには適切な `aria-label` または代替テキスト
- 色だけでなくアイコン+テキストで情報を伝達

### 4. レスポンシブデザイン
- プレースホルダーはテーブルセル内に収まるよう調整
- モバイル表示でも視認性を確保

## リスク管理

### 高リスク
なし (既存ロジックを変更しないため)

### 中リスク
- **リスク**: テストケースの漏れ
  - **対策**: 既存テスト全実行で後方互換性確認
  
### 低リスク
- **リスク**: UI/UXの視認性
  - **対策**: ライト/ダークモード両方で確認

## 実装結果 (Phase 5で記入)

### 実装日時
2026-02-15

### 実装内容

#### 変更ファイル
1. **lang/ja/ledger.php**
   - 翻訳キー `'same_as_current' => '現行と同じ'` を追加

2. **resources/views/livewire/ledger/ledger-diff-viewer.blade.php**
   - カラム単位での `unchanged` ステータス判定を追加
   - `@elseif ($column['status'] === 'unchanged')` ブロックを実装
   - プレースホルダー表示: チェックアイコン + テキスト
   - 既存のグループ単位プレースホルダーは完全に維持

3. **tests/Feature/Livewire/Ledger/LedgerDiffViewerTest.php**
   - `it_displays_placeholder_for_unchanged_columns_in_diff_view`: 混在ケースのテスト
   - `it_shows_group_placeholder_when_all_columns_unchanged`: 後方互換性テスト

#### 実装ロジック
```blade
@elseif ($column['status'] === 'unchanged')
    {{-- カラム単位で変更がない場合のプレースホルダー --}}
    <td class="align-middle py-3 px-4 bg-base-200/10 text-center">
        <div class="flex items-center justify-center gap-2 text-base-content/40 text-xs">
            <x-mary-icon name="o-check-circle" class="w-4 h-4" />
            <span>{{ __('ledger.diff.same_as_current') }}</span>
        </div>
    </td>
```

### テスト結果

#### 新規テスト (2つ追加)
```
✓ it displays placeholder for unchanged columns in diff view (1.90s)
✓ it shows group placeholder when all columns unchanged (1.90s)
```

#### 既存テスト (全てパス)
- **LedgerDiffViewerTest**: 10テスト, 44 assertions ✅
- **LedgerDiffProcessorTest**: 9テスト, 36 assertions ✅
- **全Ledger Livewireテスト**: 115テスト, 392 assertions ✅

#### コード品質
- ✅ Pint実行: 自動フォーマット完了
- ✅ エラーチェック: 重大なエラーなし (既存の警告のみ)
- ✅ 後方互換性: 全既存テストがパス

### 実装時間
- Phase 2 (実装): 約15分
- Phase 3 (テスト): 約20分
- Phase 4 (レビュー): 約10分
- Phase 5 (報告): 約10分
- **合計: 約55分** (予想3時間25分に対して大幅短縮)

### 視覚的な変更

#### Before (変更前)
全カラムの値が表示され、変更箇所が分かりにくい状態

#### After (変更後)
- ✅ 変更があるカラム: 新旧両方の値を表示
- ✅ 変更がないカラム: "✓ 現行と同じ" プレースホルダーを表示
- ✅ 全カラム変更なし: 既存のグループ単位プレースホルダーを表示（後方互換性）

### 技術的な特徴
1. **後方互換性**: グループ単位プレースホルダーは完全に維持
2. **翻訳統合**: 既存キー活用、新規追加は1つのみ
3. **パフォーマンス**: 追加のクエリなし、条件分岐のみ
4. **アクセシビリティ**: アイコン + テキストで情報伝達
5. **レスポンシブ**: テーブルセル内に収まるコンパクト設計

### 備考
- 実装は予想よりスムーズに完了
- テストも全てパス、リグレッションなし
- Pintによるコード整形も自動完了

---

## 参考リンク
- [GitHub Issue #64](https://github.com/torinky/LedgerLeap/issues/64)
- [Copilot Instructions](/.github/copilot-instructions.md)
- [Testing Best Practices](/docs/development/Testing-Best-Practices.md)

