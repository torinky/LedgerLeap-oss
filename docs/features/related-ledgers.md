# 関連案件タブ機能

**バージョン:** 1.0.0  
**実装完了日:** 2026年3月3日  
**ステータス:** ✅ 全スプリント完了（Issue #54 + Issue #76）  
**関連Issue:** [#54](https://github.com/torinky/LedgerLeap/issues/54) / [#76](https://github.com/torinky/LedgerLeap/issues/76)

---

## 概要

台帳レコード詳細画面（`Show`）に「関連案件」タブを追加し、閲覧中のレコードに関連する他のレコードを **識別番号検索** と **意味検索（RAGベクトル検索）** の2軸で横断的に探索できる機能です。

例えば、設備点検記録を閲覧中に「同じ設備番号 `EQ-042` に関する保守依頼・作業報告」を一覧したり、
「このインシデント報告に似た過去の事例」をその場で参照したりすることができます。

---

## 主な機能

### 1. 識別番号検索（パターンA・B）

閲覧中のレコードが持つ識別番号を使って、テナント内の全台帳を横断検索します。

**パターンA（`auto_number` 型列）:**  
レコード自身の `auto_number` 型カラムの値（例: `EQ-042`、`WO-099`）を抽出し、
全台帳の `auto_number` 型カラムに対して `MATCH() AGAINST()` 全文検索を実行します。

**パターンB（テキスト列に記載された識別番号）:**  
テナント内の全 `auto_number` カラム定義から正規表現パターンを生成し、
レコードの `text` / `textarea` / `memo` 型カラムに対してパターンマッチングを実行します。
一致した識別番号値もパターンAと同様に横断検索の対象となります。

> **ツールチップによる区別:**  
> 識別番号バッジ（🔖）のツールチップに「識別番号: EQ-042（識別番号列）」または
> 「識別番号: EQ-042（テキスト記載）」と表示されるため、根拠の違いを確認できます。

### 2. 意味検索（RAGベクトル検索）

`RagSearchService::searchLedgers()` を利用し、閲覧中のレコードのコンテンツをクエリとして
コサイン類似度に基づく意味検索を実行します。

- RAGサービスが未起動の場合は意味検索トグルをグレーアウトし、識別番号検索のみ有効
- スコア（0〜100%）はアイコンバッジのツールチップに表示
- 結果はスコア降順にソート

### 3. 結果の統合表示

| 表示理由 | アイコン | 説明 |
|---|---|---|
| 識別番号のみ | 🔖（`text-warning`） | パターンA/Bの識別番号でヒット |
| 意味検索のみ | スコアバッジ（`badge-info`） | RAGベクトル検索でヒット |
| 両方 | 🔖 + スコアバッジ（`text-success`） | 識別番号・意味検索の両方でヒット |

結果は以下の順序でソートされます:
1. 意味検索スコアを持つレコード（`both` / `semantic`）をスコア降順
2. 識別番号のみのレコード（`identifier`）をスコアなし末尾

### 4. フィルタートグル

ツールバーに2つのトグルスイッチを配置し、表示する結果を絞り込めます。

```
[ 🔖 識別番号  <badge-warning>5</badge>  ●ON ]  [ 意味検索  <badge-info>8</badge>  ●ON ]
```

- 「両方」ヒットのレコードはどちらかのトグルがオンであれば表示
- フィルター切替は DB 再クエリなし（PHP 側でフィルタリング）

### 5. 台帳定義ごとのグルーピング

結果は `ledger_define_id` ごとにグループ化され、台帳定義名ヘッダーの下にテーブル表示されます。
行の表示は台帳リスト画面の `x-ledger.table-row` コンポーネントを再利用するため、
添付ファイル、ラベル表示、Markdownレンダリング、「もっと見る」など同等の視覚言語を使用します。

### 6. タブバッジ（件数表示）

「関連案件」タブラベルの横に重複排除後の総件数をバッジで表示します（0件時は非表示）。

### 7. 表示レベル同期

「基本情報」タブの表示レベル（基本/詳細/すべて）と `wire:model.live="$parent.displayLevel"` で
双方向に同期します。関連案件タブの表示レベルを変更すると他のタブにも反映されます。

### 8. ページネーション

全台帳横断の結果を1つの `LengthAwarePaginator`（20件/ページ）でページングします。
ページをまたいで台帳グループが分断される場合、次ページ先頭にも台帳ヘッダーを再表示します。

### 9. 権限フィルター

表示結果は `WritableFolderRepository::getReadableFolderIds()` によるアクセス制御を通過した
レコードのみ表示されます。

### 10. 遅延ロード（`#[Lazy]`）

タブ切替前に重い検索を実行しないよう `#[Lazy]` で遅延ロードされます。
ロード中は `x-element.skeleton-table` によるスケルトン UI を表示します。

---

## 関連ファイル

### 新規作成ファイル

| ファイル | 種別 | 役割 |
|---|---|---|
| `app/Livewire/Ledger/RelatedLedgers.php` | Livewire コンポーネント | 関連案件タブのバックエンドロジック（検索・マージ・フィルター・ページング） |
| `app/Services/AutoNumberPatternService.php` | サービス | `auto_number` カラムから正規表現パターンを生成・キャッシュ（`AutoLinkService` と共用） |
| `resources/views/livewire/ledger/related-ledgers.blade.php` | Blade | 関連案件タブのビュー |
| `resources/views/livewire/ledger/related-ledgers-placeholder.blade.php` | Blade | Lazy ロード中のスケルトン |
| `resources/views/components/ledger/related-reason-badge.blade.php` | Blade コンポーネント | 識別理由インジケーター（アイコン＋ツールチップ） |
| `tests/Feature/Livewire/Ledger/RelatedLedgersTest.php` | テスト | 関連案件タブのフィーチャーテスト（22件） |
| `tests/Feature/Services/AutoNumberPatternServiceTest.php` | テスト | AutoNumberPatternService のテスト（6件） |

### 変更ファイル

| ファイル | 変更内容 |
|---|---|
| `resources/views/livewire/ledger/show.blade.php` | 「関連案件」タブ追加・タブバッジ表示 |
| `app/Livewire/Ledger/Show.php` | `$relatedCount` プロパティ・`relatedCountUpdated` イベントリスナー追加 |
| `app/Services/AutoLinkService.php` | `AutoNumberPatternService` を DI で利用するよう変更（パターン生成ロジックを委譲） |
| `lang/ja/ledger.php` | `related.*` 翻訳キー群・`identifier_source_*` 翻訳キー追加 |

---

## アーキテクチャ概要

### コンポーネント構成

```
Show.php（親 Livewire）
└── [関連案件タブ] #[Lazy]
    └── RelatedLedgers.php（子 Livewire）
        ├── ツールバー（トグル・件数バッジ・表示レベル）
        ├── ページネーション（上部）
        ├── [台帳定義グループ A]
        │   ├── x-ledgerDefine.header
        │   └── x-ledger.table-header + x-ledger.table-row（relatedBadge スロット）
        ├── [台帳定義グループ B] ...
        └── ページネーション（下部）
```

### データフロー

```
mount(int $ledgerId)
  ↓ Ledger::with('define')->findOrFail($ledgerId)
  ↓ extractAutoNumberValues()
       Step A: auto_number 型列の値を収集
       Step B: AutoNumberPatternService::getPatterns() でパターン取得
               → テキスト系列にパターンマッチング → 追加の識別番号を収集
  ↓ WritableFolderRepository::getReadableFolderIds()

render()
  ├── searchByIdentifiers($identifierKeys)
  │     → Collection<array{ledger: Ledger, matched_keys: array}>
  ├── searchBySemantic($ledger)  ← RAG利用可能時のみ
  │     → Collection<array{ledger: Ledger, score: float}>
  ├── mergeResults()
  │     → reason 付与 + score 降順ソート + identifier 末尾
  ├── applyFilter()  ← showIdentifier / showSemantic トグル
  ├── buildPaginator()  ← LengthAwarePaginator, perPage=20
  └── groupByDefine()  ← ledger_define_id でグループ化
```

### `RelatedLedgerItem` 配列構造（内部 DTO）

```php
[
    'ledger'       => Ledger,            // Ledgerモデル（define ロード済み）
    'reason'       => 'identifier' | 'semantic' | 'both',
    'score'        => float|null,        // コサイン類似度 0.0〜1.0（identifier のみは null）
    'matched_keys' => [                  // ヒットした識別番号情報の配列
        [
            'value'         => 'EQ-042',
            'source'        => 'auto_number' | 'text_column',
            'source_column' => '設備番号',
        ],
    ],
]
```

### `matched_keys` の `source` フィールド

| 値 | 意味 |
|---|---|
| `auto_number` | パターンA: `auto_number` 型カラムの値で一致（監査根拠として厳密） |
| `text_column` | パターンB: テキスト系カラムに記載された識別番号で一致 |

---

## 技術的考慮事項

### Mroonga 制約

識別番号の全文検索には Mroonga の `MATCH() AGAINST()` を使用します。
複合インデックスは使用不可のため、複数カラムの検索は `OR` で結合します（1カラム1クエリ）。

### `#[Lazy]` とテナントコンテキスト

`#[Lazy]` コンポーネントは初回リクエスト時にルートパラメータが利用できない場合があるため、
`mount()` 内で明示的にテナントコンテキストを初期化します。
`render()` の `tenant()?->id` は null になり得るため、`$model->tenant_id` をフォールバックとして使用します。

### Livewire パブリックプロパティの制約

大量の Eloquent モデルを Livewire のパブリックプロパティに保持するとシリアライズエラーが発生するため、
検索結果は `render()` ごとに再クエリする設計としています。
フィルター切替は DB 再クエリなしで PHP 側でフィルタリングを行います。

### `AutoNumberPatternService` のキャッシュ

`getPatterns()` のキャッシュキーは `"auto_number_patterns:{$tenantId}"` の形式です。
テナント ID を含めることでマルチテナント環境でのキャッシュ混在を防止しています（2026-03-03 修正済み）。
同様に `AutoLinkService::getVirtualAutoNumberLinks()` のキャッシュキーも同形式です。

---

## エッジケース一覧

| ケース | 対応 |
|---|---|
| `auto_number` カラムが存在しない | 識別番号トグルをグレーアウト + 「識別番号カラムがありません」ツールチップ |
| RAGサービス未起動 | 意味検索トグルをグレーアウト + 「意味検索は現在利用できません」ツールチップ |
| 識別番号・意味検索ともに0件 | 「関連案件が見つかりませんでした」プレースホルダー表示 |
| フィルターで全件非表示 | 「フィルターを調整してください」メッセージ |
| 自身が検索結果に含まれる | `where('id', '!=', $this->ledgerId)` で除外 |
| ページをまたいでグループが分断 | 次ページ先頭に台帳ヘッダーを再表示 |
| 1レコードに複数の `auto_number` カラム | 全カラムの値を収集し OR 検索 |
| パターンAとBで同じ値が抽出された | 重複排除（`array_unique` 等）で1件にまとめる |

---

## テスト

テストファイルは `tests/Feature/Livewire/Ledger/RelatedLedgersTest.php`（22件）および
`tests/Feature/Services/AutoNumberPatternServiceTest.php`（6件）です。

> **重要:** 全文検索（Mroonga）を使うテストは `DatabaseMigrationsOnce` トレイトを使用します（`RefreshDatabase` は使用しないこと）。

主なテストケース:

- `it_extracts_auto_number_values` — `auto_number` 型列から識別番号を抽出できる
- `it_searches_by_identifier` — 識別番号横断検索が実行される
- `it_excludes_self_from_results` — 自身が結果に含まれない
- `it_searches_by_semantic` — RAGモックを使った意味検索が実行される
- `it_merges_identifier_and_semantic_results_with_correct_reason` — マージ結果の reason が正しく付与される
- `it_marks_both_when_ledger_appears_in_both_searches` — 両方ヒット時に `reason=both` になる
- `it_filters_by_show_identifier_toggle` / `it_filters_by_show_semantic_toggle` — トグルフィルターが機能する
- `it_paginates_merged_results` — ページネーションが機能する
- `it_retains_score_from_semantic_search` — スコアが保持される
- `it_retains_matched_keys_from_identifier_search` — matched_keys が保持される
- `it_sorts_by_score_descending_with_identifier_last` — ソート順が正しい
- `it_extracts_identifier_from_text_column` — テキスト列から識別番号が抽出される（パターンB）
- `it_marks_source_as_text_column_in_matched_keys` — `source='text_column'` が記録される

---

## 関連ドキュメント

- [AutoLink 機能](../function/AutoLink.md)
- [全文検索機能](../function/Search.md)
- [ペルソナ・ユースケースシナリオ](../function/PersonaUseCaseScenario.md)
- [AutoLink サービス実装](../services/AutoLinkService.md)
- [Issue #54 詳細設計・Sprint ログ](../work/core-features/related-ledgers/2026-03-01_issue-54-related-ledgers-tab-plan.md)
- [Issue #76 テキスト列識別番号検索 詳細設計・Sprint ログ](../work/core-features/related-ledgers/2026-03-02_issue-related-ledgers-text-identifier-search.md)

