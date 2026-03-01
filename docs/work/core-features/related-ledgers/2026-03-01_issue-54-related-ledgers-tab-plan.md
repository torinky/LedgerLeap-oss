# Issue #54: 詳細画面に関連案件タブを追加

**作成日:** 2026年3月1日  
**更新日:** 2026年3月1日  
**ステータス:** 🚧 Sprint 1・2・3 完了 / Sprint 4 進行中  
**目的:** 台帳レコード詳細画面に「関連案件」タブを追加し、識別番号検索・意味検索の2軸で関連レコードを探索できるようにする  
**関連Issue:** https://github.com/torinky/LedgerLeap/issues/54

---

## 🎯 背景と要件

### 背景

現在の台帳詳細画面（`Show`）には「基本情報」「更新履歴」「活動履歴」「アクセスと権限」の4タブがある。  
しかし、あるレコードに関連する他のレコード（例：同じ設備番号を持つ保守記録・点検記録）を横断的に確認する手段が存在しない。  
ユーザーは都度リスト画面に戻り、手動で検索し直す必要があり、業務上のコンテキストスイッチが多発している。

### ペルソナシナリオからの洞察

[`docs/function/PersonaUseCaseScenario.md`](../../../../function/PersonaUseCaseScenario.md) の各ペルソナに対して以下のシナリオが想定される。

#### 実務担当者
- **UC2「過去の業務記録の検索と参照」の延長:** 特定の案件（レコード）を閲覧中に「この案件と似たケースを過去に扱ったか」をその場で確認したい
- 例: 設備点検記録を見ながら「同じ設備番号 EQ-042 に関する過去の保守依頼（保守台帳）・作業報告（報告台帳）を一覧したい」
- **重要**: 複数の台帳フォーマットにまたがって結果が出るため、**どの台帳のレコードか**をひと目で識別できる必要がある

#### 現場リーダー / 作業班長
- **UC2「チーム内の情報共有と確認」の延長:** 障害報告レコードを確認中に「類似の過去インシデント」を即座に参照し、対応方針の参考にしたい
- **重要**: 識別番号でヒットしたレコードと意味的に近いレコードは性質が異なる。**どちらの理由で表示されているかを区別**したい

#### 管理者
- **UC3「活動状況の監査」の延長:** 特定の台帳レコードを起点に、識別番号で紐づく全関連文書を一覧確認し監査の網羅性を担保したい
- **重要**: 「識別番号でヒット」と「意味検索でヒット」は監査上の根拠が異なる。**フィルタリングして表示件数を絞れる**必要がある

---

## 📐 機能要件（詳細版）

### 1. 新タブ「関連案件」の追加

- `show.blade.php` の `x-mary-tabs` に新タブを追加
- 既存タブ（details/history/activity/permissions）の後に配置
- タブラベル: `関連案件` + 件数バッジ（重複排除後合計、0件時は非表示）
- URL パラメータ `?tab=related` でディープリンク可能
- `#[Lazy]` で遅延ロード（タブ切替前に重い検索を実行しない）

### 2. 結果表示: 台帳定義ごとのグルーピング＋テーブル表示

既存の台帳リスト画面（`records-table.blade.php`）と**同じ視覚言語**を使う。

```
┌─ [台帳名ヘッダー] 設備保守台帳 ─────────────────────────┐
│ 🔖 識別番号  📋 №  設備番号   作業内容   日付              │  ← テーブルヘッダー
│ [識別] 詳細→  001  EQ-042    定期点検   2026-01-10        │  ← 行バッジ付き
│ [識別] 詳細→  003  EQ-042    緊急修理   2025-11-05        │
└───────────────────────────────────────────────────────────┘
┌─ [台帳名ヘッダー] 作業報告台帳 ─────────────────────────┐
│ 📋 №  報告者   内容              類似度                    │
│ [意味] 詳細→  田中   設備不具合の対応… ●●●○○            │
│ [両方] 詳細→  山田   EQ-042 交換作業…  ●●●●○            │  ← 両方ヒット
└───────────────────────────────────────────────────────────┘
```

**実装方針:**
- `x-ledger.table-row` / `x-ledgerDefine.header` と同じコンポーネントを**再利用**
- `filteredColumnDefines` には `display_level = 1` のカラムのみ（= リスト画面のデフォルト表示と同じ）
- ページングは全結果をまとめた1つの `LengthAwarePaginator` で行う（台帳をまたいで1ページ N 件）

### 3. 識別理由バッジ（行ごとの表示）

各レコード行の先頭に **識別理由バッジ** を表示する。

| バッジ | 条件 | 色 |
|---|---|---|
| `🔖 識別番号` | 識別番号検索のみヒット | `badge-warning` |
| `🔍 意味検索` | 意味検索のみヒット | `badge-info` |
| `⭐ 両方` | 識別番号・意味検索 両方ヒット | `badge-success` |

### 4. トグルスイッチによるフィルタリング

タブ内のツールバーにトグルスイッチを配置する。

```
[ 🔖 識別番号 ●ON ]  [ 🔍 意味検索 ●ON ]   計 12件
```

- 各トグルをオフにすると該当理由のレコードが非表示になる
- 「両方」ヒットのレコードはどちらかのトグルがオンなら表示
- トグル状態は Livewire プロパティ（`$showIdentifier`, `$showSemantic`）で管理
- 状態変更はブラウザ側で再レンダリング（`wire:model.live`）

### 5. タブバッジ（件数表示）

- タブラベル横に `badge badge-neutral badge-sm` で件数表示
- 件数はフィルター前の総数（識別番号 + 意味検索の重複排除後）
- 0件時はバッジ非表示（タブ自体は常に表示）

### 6. ページネーション

- 全結果（台帳横断）をまとめてページングする（デフォルト 20件/ページ）
- 台帳グループのヘッダーは各グループの先頭行に表示
- ページをまたいで台帳グループが分断される場合、次ページ先頭に台帳ヘッダーを再表示
- 既存の `components.ledger.pagination-links` コンポーネントを流用

### 7. 権限フィルター

- 表示結果はログインユーザーが閲覧可能なレコードのみ
- `WritableFolderRepository::getReadableFolderIds()` による権限フィルターを通す

### 8. RAGサービス未起動時のグレースフルデグラデーション

- RAGサービスが利用不可の場合、意味検索トグルをグレーアウト + ツールチップ「意味検索は現在利用できません」
- 識別番号検索は影響を受けない

---

## 🏗 アーキテクチャ設計

### コンポーネント構成

```
Show.php（親）
└── [新タブ] related
    └── livewire:ledger.related-ledgers（新規 Livewire コンポーネント）
        ├── ツールバー（トグルスイッチ・件数表示）
        ├── ページネーション（上部・固定）
        ├── [台帳定義グループ A]
        │   ├── x-ledgerDefine.header（再利用、簡略版）
        │   └── table（x-ledger.table-row 再利用）
        ├── [台帳定義グループ B]
        │   └── ...
        └── ページネーション（下部）
```

### 新規ファイル

| ファイル | 種別 | 目的 |
|---|---|---|
| `app/Livewire/Ledger/RelatedLedgers.php` | Livewire | 関連案件タブのコントローラー（Sprint 1 で作成済み） |
| `resources/views/livewire/ledger/related-ledgers.blade.php` | Blade | 関連案件タブのビュー |
| `resources/views/livewire/ledger/related-ledgers-placeholder.blade.php` | Blade | Lazy ロード中のスケルトン |

### 変更ファイル

| ファイル | 変更内容 |
|---|---|
| `resources/views/livewire/ledger/show.blade.php` | 新タブの追加、バッジ表示 |
| `app/Livewire/Ledger/RelatedLedgers.php` | ページング・フィルター・グルーピングロジック追加 |
| `lang/ja/ledger.php` | `related.*` 翻訳キー追加 |

### 既存資産の流用（更新）

| 既存資産 | 流用方法 |
|---|---|
| `x-ledger.table-row` | 識別理由バッジ用スロット追加またはプロパティ追加で再利用 |
| `x-ledgerDefine.header` | 台帳グループヘッダーとして再利用（権限ボタンは省略） |
| `components.ledger.pagination-links` | ページネーションそのまま流用 |
| `RagSearchService::searchLedgers()` | 意味検索に直接利用 |
| `InitializesTenantContext` trait | テナント初期化 |
| `x-element.loading-overlay` | ローディング表示 |
| `x-element.skeleton-table` | Lazy プレースホルダー |

### データ構造設計

#### `RelatedLedgerItem`（内部 DTO）

```php
// app/Livewire/Ledger/RelatedLedgers.php 内で使用する配列構造
[
    'ledger'      => Ledger,         // Ledgerモデル（with define ロード済み）
    'reason'      => 'identifier' | 'semantic' | 'both',
    'score'       => float|null,     // 意味検索スコア（identifier のみの場合は null）
    'matched_keys' => string[],      // ヒットした識別番号キー（identifier の場合）
]
```

#### ページング後のグルーピング

```php
// render() 内でページング後に台帳定義ごとにグルーピング
$pagedItems    = $paginator->items();   // 20件/ページ
$groupedByDefine = collect($pagedItems)
    ->groupBy(fn($item) => $item['ledger']->ledger_define_id);
```

### データフロー（更新）

```
[RelatedLedgers::mount(int $ledgerId)]
   ↓
   1. Ledger::with('define')->findOrFail($ledgerId)
   2. extractAutoNumberValues() → $identifierKeys[]
   3. getReadableFolderIds() → $readableFolderIds[]

[render()]
   ├── searchByIdentifiers($identifierKeys) → Collection<Ledger>
   │     各レコードに reason='identifier' を付与
   ├── searchBySemantic($ledger) → Collection<Ledger>（RAG利用可能な場合）
   │     各レコードに reason='semantic', score=float を付与
   ├── マージ・重複処理
   │     同一 ledger_id が両方に存在 → reason='both'
   ├── フィルタリング（$showIdentifier / $showSemantic トグル）
   ├── ページネーション（LengthAwarePaginator, perPage=20）
   └── グルーピング（ledger_define_id でグループ化）
        → $groupedResults: [defineId => [RelatedLedgerItem, ...]]
```

---

## ⚠️ 技術的考慮事項

### パフォーマンス

- タブは `#[Lazy]` で遅延ロード（初期表示コストゼロ）
- 意味検索は RAG ベクトル検索のため重い → スピナー表示
- `searchByIdentifiers`: `auto_number` カラムが複数ある場合は複数クエリを発行するが、結果はまとめて IN 句で Ledger を1回取得
- フィルター切替（トグル）: DB 再クエリなし。`$allResults`（全件配列）をコンポーネントプロパティに持ち、フィルターはレンダリング時に PHP 側で適用
  - **ただし**: Livewire のパブリックプロパティに大量の Eloquent モデルを持つと直列化でエラー → `$allResults` は `int[]`（ID配列）で持ち、render 時に再クエリする設計とする

### エッジケース

| ケース | 対応 |
|---|---|
| `auto_number` カラムが存在しない | 識別番号検索セクションを非表示、トグルをグレーアウト |
| RAGサービス未起動 | 意味検索トグルをグレーアウト、「利用不可」ツールチップ |
| 両方0件 | 「関連案件が見つかりませんでした」プレースホルダー表示 |
| 自身が検索結果に含まれる | `where('id', '!=', $this->ledgerId)` で除外 |
| ページをまたいで台帳グループが分断 | 次ページ先頭にも台帳ヘッダーを再表示 |
| 意味検索のみ有効（識別番号なし） | 識別番号トグルを非表示、意味検索のみ表示 |
| フィルターで全件非表示 | 「フィルターを調整してください」メッセージ |

### Mroonga 制約（Critical）

- 識別番号の全文検索に Mroonga の複合インデックスは使用不可
- `MATCH() AGAINST()` は単一カラムに限定、複数カラムは `OR` で結合

---

## 📋 WBS：スプリント計画

---

### ✅ Sprint 1: バックエンド基盤（識別番号検索）— 完了

**完了日:** 2026-03-01 — テスト 8 passed / 1 skipped

- [x] **Task 1.1.1**: `auto_number` カラム値の抽出メソッドを `RelatedLedgers.php` に実装
- [x] **Task 1.2.1**: バックエンドロジック調査 → `SearchContext` + `scopeSearchContext` 直接利用に決定
- [x] **Task 1.2.2**: 識別番号横断検索メソッドの実装（複数キー OR、自身除外、権限フィルター）
- [x] **Task 1.3.1**: `RelatedLedgersTest.php` 新規作成（識別番号検索テスト 5 件）
- [x] **Task 1.3.2**: テスト実行・パス確認 — 8 passed ✅

---

### ✅ Sprint 2: バックエンド基盤（意味検索）— 完了

**完了日:** 2026-03-01 — テスト 11 passed / 1 skipped

- [x] **Task 2.1.1**: コンテンツ→クエリ文字列変換メソッド（`files` 除外、500文字上限）← Sprint 1 先行実装済み
- [x] **Task 2.2.1**: `RagSearchService::searchLedgers()` 呼び出しメソッド（例外キャッチ込み）← Sprint 1 先行実装済み
- [x] **Task 2.3.1**: 意味検索テスト 3 件追加（RAGモック・自身除外・空クエリ）
- [x] **Task 2.3.2**: テスト実行・パス確認 — 11 passed ✅
- [x] CI 考慮事項確認（RefreshDatabase、fakeQueue、$this->mock、feature ジョブ分類）

---

### ✅ Sprint 3: マージ・グルーピング・ページングバックエンド — 完了

**完了日:** 2026-03-01 — テスト 17 passed / 1 skipped  
**エビデンス:** [e2a5cb22](https://github.com/torinky/LedgerLeap/commit/e2a5cb22)

#### Block 3.1: 結果マージと識別理由付与

- [x] **Task 3.1.1**: `mergeResults()` を実装
  - 識別番号のみヒット → `reason = 'identifier'`
  - 意味検索のみヒット → `reason = 'semantic'`
  - 両方ヒット → `reason = 'both'`

- [x] **Task 3.1.2**: `applyFilter()` を実装
  - `showIdentifier` / `showSemantic` トグルに基づきフィルタリング
  - `reason = 'both'` はどちらかのトグルがオンなら表示

#### Block 3.2: ページングとグルーピング

- [x] **Task 3.2.1**: `buildPaginator()` を実装
  - `perPage = 20`、`pageName = 'related_page'`（他ページネーターとの衝突回避）
  - `LengthAwarePaginator` でラップ

- [x] **Task 3.2.2**: `groupByDefine()` を実装
  - `ledger_define_id` でグループ化

#### Block 3.3: テスト実装

- [x] **Task 3.3.1**: テスト 6 件追加
  - `it_merges_identifier_and_semantic_results_with_correct_reason` ✅
  - `it_marks_both_when_ledger_appears_in_both_searches` ✅
  - `it_filters_by_show_identifier_toggle` ✅
  - `it_filters_by_show_semantic_toggle` ✅
  - `it_paginates_merged_results` ✅
  - `it_groups_results_by_ledger_define` ✅

- [x] **Task 3.3.2**: テスト実行・パス確認 — 17 passed / 1 skipped ✅

**✅ Sprint 3 完了条件**
- マージ・フィルター・ページング・グルーピングテストが全てパスする ✅
- Pint 実行済み ✅

---

### 🏁 Sprint 4: Blade ビュー実装（テーブル表示・識別理由バッジ・トグル）

**目標:** `related-ledgers.blade.php` を実装し、既存の table-row / header コンポーネントと整合した UI でブラウザ表示を確認する  
**確認ポイント:** トグルで表示が切り替わり、識別理由バッジが表示され、台帳リスト画面と視覚的に統一されていること

#### Block 4.1: プレースホルダービュー

- [ ] **Task 4.1.1**: `related-ledgers-placeholder.blade.php` を作成
  - `x-element.skeleton-table` 2 ブロック
  - Lazy ロード中に表示

#### Block 4.2: 識別理由バッジコンポーネント

- [ ] **Task 4.2.1**: `resources/views/components/ledger/related-reason-badge.blade.php` を新規作成
  - props: `reason` (`identifier` / `semantic` / `both`)
  - `identifier` → `badge-warning` 🔖 識別番号
  - `semantic` → `badge-info` 🔍 意味検索
  - `both` → `badge-success` ⭐ 両方

#### Block 4.3: メインビュー

- [ ] **Task 4.3.1**: `related-ledgers.blade.php` を作成
  - **ツールバーセクション**: トグルスイッチ2個 + 件数表示
    - `wire:model.live="showIdentifier"` / `wire:model.live="showSemantic"`
    - RAGサービス利用不可時は意味検索トグルを `disabled` + ツールチップ
    - `auto_number` カラムなし時は識別番号トグルを `disabled` + ツールチップ
  - **ページネーション（上部固定）**: `pagination-links` を流用
  - **結果セクション**: `$groupedResults` をループ
    - 台帳グループごとに簡略版ヘッダー（台帳名 + フォルダパンくず）
    - テーブル: `x-ledger.table-header` + 識別理由バッジ列 + `x-ledger.table-row`
  - **ゼロ件プレースホルダー**: 3ケース
    - 全体0件: 「関連案件が見つかりませんでした」
    - フィルターで0件: 「フィルターを調整してください」
    - RAG利用不可かつ識別番号なし: 「この台帳では関連案件検索を利用できません」
  - **ページネーション（下部）**

#### Block 4.4: `show.blade.php` へのタブ追加

- [ ] **Task 4.4.1**: `show.blade.php` に「関連案件」タブを追加
  - `#[Lazy]` 遅延ロード、`wire:key="related-ledgers-{{ $ledgerRecord->id }}"`
  - タブラベルバッジ: `@listen('relatedCountUpdated')` で `$relatedCount` を更新

- [ ] **Task 4.4.2**: `lang/ja/ledger.php` に翻訳キーを追加
  ```php
  'tab' => [/* ... */ 'related' => '関連案件'],
  'related' => [
      'toolbar_identifier'     => '識別番号',
      'toolbar_semantic'       => '意味検索',
      'count_total'            => ':count 件',
      'reason_identifier'      => '識別番号',
      'reason_semantic'        => '意味検索',
      'reason_both'            => '両方',
      'empty_no_results'       => '関連案件が見つかりませんでした',
      'empty_filter'           => 'フィルターを調整してください',
      'empty_unavailable'      => 'この台帳では関連案件検索を利用できません',
      'rag_unavailable_tooltip'=> '意味検索は現在利用できません',
      'identifier_unavailable_tooltip' => '識別番号カラムがありません',
  ],
  ```

#### Block 4.5: ブラウザ動作確認

- [ ] **Task 4.5.1**: auto_number カラムを持つ台帳のレコード詳細画面で確認
  - 識別番号バッジ・意味検索バッジ・両方バッジが正しく表示される
  - トグルで表示が切り替わる
  - ページングが機能する（複数ページある場合）
  - 台帳ヘッダーが台帳定義ごとに表示される
- [ ] **Task 4.5.2**: `laravel-boost` MCP でエラーログ確認

**✅ Sprint 4 完了条件**
- ブラウザでトグル・バッジ・ページング・グルーピングが全て動作する ✅
- 台帳リスト画面と視覚的に統一されている ✅
- `laravel-boost` エラーなし ✅

---

### 🏁 Sprint 5: タブバッジ・テスト整備・全体仕上げ

**目標:** タブバッジ（件数）の親への通知、Livewire テストの整備、全テストパスで完成  
**確認ポイント:** タブバッジが更新され、全テストがパスすること

#### Block 5.1: タブバッジ実装

- [ ] **Task 5.1.1**: `RelatedLedgers.php` の `render()` 末尾で `$this->dispatch('relatedCountUpdated', count: $this->totalCount)` を送信
- [ ] **Task 5.1.2**: `Show.php` に `$relatedCount = 0` プロパティと `#[On('relatedCountUpdated')]` リスナーを追加
- [ ] **Task 5.1.3**: `show.blade.php` のタブラベルに `{{ $relatedCount > 0 ? $relatedCount : '' }}` バッジを追加

#### Block 5.2: Feature テスト整備

- [ ] **Task 5.2.1**: `RelatedLedgersTest.php` に Livewire レンダリングテストを追加（Sprint 3 の skipped テストを有効化）
  - `it_can_be_instantiated_as_livewire_component` → skipped 解除
  - `it_renders_with_toggle_toolbar`
  - `it_shows_badge_count_after_render`

- [ ] **Task 5.2.2**: 全テスト実行・リグレッション確認
  - コマンド: `./vendor/bin/sail test`
  - 既存テストへの影響がないこと

#### Block 5.3: コード品質

- [ ] **Task 5.3.1**: Pint 実行
- [ ] **Task 5.3.2**: `laravel-boost` エラーチェック（`last-error` / `browser-logs`）

**✅ Sprint 5 完了条件**
- タブバッジが件数を正しく表示する ✅
- `./vendor/bin/sail test` 全テストパス ✅
- Pint 実行済み・エラーなし ✅

---

## 📊 総予想工数（更新）

| スプリント | 内容 | 予想工数 |
|---|---|---|
| Sprint 1 | 識別番号検索バックエンド + テスト | ✅ 完了 |
| Sprint 2 | 意味検索バックエンド + テスト + CI確認 | ✅ 完了 |
| Sprint 3 | マージ・識別理由・ページング・グルーピングバックエンド | 2〜3時間 |
| Sprint 4 | Blade ビュー（テーブル・バッジ・トグル・show統合） | 3〜4時間 |
| Sprint 5 | タブバッジ・テスト整備・全体仕上げ | 1〜2時間 |
| **合計** | | **6〜9時間**（残り） |

---

## 🔗 関連ドキュメント

- [ペルソナ・ユースケースシナリオ](../../../../function/PersonaUseCaseScenario.md)
- [検索機能概要](../../../../function/Search.md)
- [AutoLink機能概要](../../../../function/AutoLink.md)
- [Auto-number クロスリファレンス改善計画](../auto-link/2025-10-13_auto-number-cross-reference-link-improvement.md)
- [台帳複製機能設計](../2025-12-11_ledger_duplicate_feature_design.md)
- [GitHub Issue #54](https://github.com/torinky/LedgerLeap/issues/54)

---

## 実装結果（Sprint 完了後に記入）

### Sprint 1 完了日時
2026-03-01 — テスト 8 passed / 1 skipped (Lazy placeholder は Sprint 4 で対応)

### Sprint 2 完了日時
2026-03-01 — テスト 11 passed / 1 skipped (Livewire コンポーネントテストは Sprint 5 で有効化)

### Sprint 3 完了日時
2026-03-01 — テスト 17 passed / 1 skipped (Livewire コンポーネントテストは Sprint 5 で有効化)

### Sprint 4 完了日時
_未完了_

### Sprint 5 完了日時
_未完了_
