# Issue #54: 詳細画面に関連案件タブを追加

**作成日:** 2026年3月1日  
**更新日:** 2026年3月2日  
**ステータス:** ✅ Sprint 1〜8 完了  
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
- **補足（複数 auto_number カラム）**: 1レコードに「設備番号（EQ-042）」「案件番号（WO-099）」の2つの識別番号列がある場合、両方の値で検索が走り、どちらかでヒットした全レコードが表示される。`matched_keys` にはどの番号値でヒットしたかが記録されツールチップに表示されるため、ユーザーは「なぜ表示されているか」を確認できる。

#### 現場リーダー / 作業班長
- **UC2「チーム内の情報共有と確認」の延長:** 障害報告レコードを確認中に「類似の過去インシデント」を即座に参照し、対応方針の参考にしたい
- **重要**: 識別番号でヒットしたレコードと意味的に近いレコードは性質が異なる。**どちらの理由で表示されているかを区別**したい
- **補足（他台帳の識別番号がテキスト欄に記載）**: 作業報告の「作業内容」欄に `EQ-042` と書かれているケースは、識別番号検索の「識別番号列の値が一致」という厳密な根拠には該当しない。このようなケースは意味検索が文脈的に拾うことを期待する設計とする（詳細は「識別番号検索範囲の設計判断」を参照）。

#### 管理者
- **UC3「活動状況の監査」の延長:** 特定の台帳レコードを起点に、識別番号で紐づく全関連文書を一覧確認し監査の網羅性を担保したい
- **重要**: 「識別番号でヒット」と「意味検索でヒット」は監査上の根拠が異なる。**フィルタリングして表示件数を絞れる**必要がある
- **補足（監査根拠の明確化）**: 識別番号トグルをオンにして表示されるレコードは「`auto_number` 型カラムの値が一致した」という厳密な関連性を意味する。テキスト欄に番号が書かれているだけのレコードは意味検索（semantic）として区別されるため、監査根拠として利用できる。

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

### 3. 識別理由インジケーター（行ごとの表示）— Sprint 6 で改訂

各レコード行の先頭に **識別理由アイコン** を表示する（Sprint 4 のバッジ表示から変更）。  
列幅を最小化するため、テキストラベルを廃止しアイコン＋ツールチップに変更する。

| アイコン | 条件 | 色 | ツールチップ |
|---|---|---|---|
| `🔖`（fa-bookmark） | 識別番号検索のみヒット | `text-warning` | `識別番号: [一致した番号]` |
| `🔍`（fa-magnifying-glass） | 意味検索のみヒット | `text-info` | `類似度: XX%` |
| `⭐`（fa-star） | 識別番号・意味検索 両方ヒット | `text-success` | `識別番号 & 意味検索` |

- テーブルの「関連理由」列をアイコン列（`w-8`）に縮小
- ツールチップは `data-tip` で表示（識別番号の場合は一致した番号値も表示）

### 4. トグルスイッチによるフィルタリング

タブ内のツールバーにトグルスイッチを配置する。

```
[ 🔖 識別番号 ●ON ]  [ 🔍 意味検索 ●ON ]   計 12件
```

- 各トグルをオフにすると該当理由のレコードが非表示になる
- 「両方」ヒットのレコードはどちらかのトグルがオンなら表示
- トグル状態は Livewire プロパティ（`$showIdentifier`, `$showSemantic`）で管理
- 状態変更はブラウザ側で再レンダリング（`wire:model.live`）

### 4-B. 表示レベル調節 UI — Sprint 6 で追加

「基本情報」タブの表示レベルセレクタと同期する形で、関連案件タブにも表示レベル調節 UI を追加する。

```
表示レベル: [ 基本 ][ 詳細 ][ すべて ]   ← x-mary-group (displayLevel)
```

- `$displayLevel` プロパティは `Show.php` から `displayLevelUpdated` イベント経由で受け取る
  （`RelatedLedgers` に `#[On('displayLevelUpdated')]` リスナーを追加）
- ツールバーに表示レベルセレクタを配置し、基本情報タブと常に同期
- 表示カラムフィルタは `display_level <= $displayLevel` で統一

### 5. タブバッジ（件数表示）— Sprint 6 で改訂

- タブラベル横に `badge` で件数表示（括弧書き数字から変更）
- 件数はフィルター前の総数（識別番号 + 意味検索の重複排除後）
- 0件時はバッジ非表示（タブ自体は常に表示）
- ツールバーの件数表示も括弧書き数字からバッジに統一:

```
[ 🔖 識別番号  <badge-warning>5</badge>  ●ON ]  [ 🔍 意味検索  <badge-info>8</badge>  ●ON ]
```

- 識別番号件数バッジ: `badge-warning`（トグルアイコン色と同期）
- 意味検索件数バッジ: `badge-info`（トグルアイコン色と同期）

### 6. ページネーション

- 全結果（台帳横断）をまとめてページングする（デフォルト 20件/ページ）
- 台帳グループのヘッダーは各グループの先頭行に表示
- ページをまたいで台帳グループが分断される場合、次ページ先頭に台帳ヘッダーを再表示
- 既存の `components.ledger.pagination-links` コンポーネントを流用

### 7. 意味検索スコア表示 — Sprint 6 で追加

- 意味検索でヒットしたレコード（`reason='semantic'` / `reason='both'`）にコサイン類似度スコアを表示
- スコアは `RagSearchService::searchLedgers()` が返す `max_score`（0.0〜1.0）をパーセント表示
- 表示箇所: ツールチップ（`data-tip="類似度: 85%"`）
- 表示順: 意味検索ヒットのレコードはスコア降順でソート、識別番号のみのレコードはその後に配置
- スコアの保持: `searchBySemantic()` の戻り値を `Collection<Ledger>` から
  `Collection<array{ledger: Ledger, score: float}>` に変更し、`mergeResults()` でスコアを保持

### 8. 識別番号強調表示 — Sprint 6 で追加

- 識別番号でヒットしたレコード（`reason='identifier'` / `reason='both'`）のセル値に含まれる
  一致した識別番号を `<mark>` タグや強調スタイルで視覚的に強調表示
- 一致した識別番号は `matched_keys` 配列に格納
- `searchByIdentifiers()` を改修: 各 `$key` に対してヒットした `ledger_id` と一致キーのマッピングを保持し、
  `mergeResults()` に渡す
- ツールチップ: `🔖` アイコンのツールチップに「識別番号: [一致した番号]」を表示

### 9. 権限フィルター

- 表示結果はログインユーザーが閲覧可能なレコードのみ
- `WritableFolderRepository::getReadableFolderIds()` による権限フィルターを通す

### 10. RAGサービス未起動時のグレースフルデグラデーション

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

### データ構造設計（Sprint 6 更新）

#### `RelatedLedgerItem`（内部 DTO）

```php
// app/Livewire/Ledger/RelatedLedgers.php 内で使用する配列構造
[
    'ledger'       => Ledger,            // Ledgerモデル（with define ロード済み）
    'reason'       => 'identifier' | 'semantic' | 'both',
    'score'        => float|null,        // 意味検索コサイン類似度（0.0〜1.0、identifierのみは null）
    'matched_keys' => string[],          // ヒットした識別番号の値（identifier の場合に格納）
]
```

**Sprint 6 変更点:**
- `score` が実際の `max_score` 値を保持するよう `searchBySemantic()` を改修
  - `searchLedgers()` の戻り値 `['ledger_id', 'max_score', ...]` の `max_score` を保持
  - 戻り値を `Collection<int, array{ledger: Ledger, score: float}>` に変更
- `matched_keys` が実際の一致識別番号値を保持するよう `searchByIdentifiers()` を改修
  - `$key → [ledger_id, ...]` のマッピングを保持し、`mergeResults()` に渡す

#### ソート戦略（Sprint 6 追加）

```
意味検索結果は score 降順 → 識別番号のみ結果 → スコアなし の順
```

```php
// mergeResults() 内でのソート
usort($merged, function ($a, $b) {
    // 'both' / 'semantic' は score で降順
    // 'identifier' のみは score=null → 末尾
    $scoreA = $a['score'] ?? -1;
    $scoreB = $b['score'] ?? -1;
    return $scoreB <=> $scoreA;
});
```

#### ページング後のグルーピング

```php
// render() 内でページング後に台帳定義ごとにグルーピング
$pagedItems    = $paginator->items();   // 20件/ページ
$groupedByDefine = collect($pagedItems)
    ->groupBy(fn($item) => $item['ledger']->ledger_define_id);
```

### データフロー（Sprint 6 更新）

```
[RelatedLedgers::mount(int $ledgerId)]
   ↓
   1. Ledger::with('define')->findOrFail($ledgerId)
   2. extractAutoNumberValues() → $identifierKeys[]
   3. getReadableFolderIds() → $readableFolderIds[]

[render()]
   ├── searchByIdentifiers($identifierKeys)
   │     → Collection<array{ledger: Ledger, matched_keys: string[]}>
   │     （各 $key でヒットした ledger_id と key のマッピングを保持）
   ├── searchBySemantic($ledger)（RAG利用可能な場合）
   │     → Collection<array{ledger: Ledger, score: float}>
   │     （max_score を保持、スコア降順ソート済み）
   ├── mergeResults($identifiers, $semantics)
   │     → reason 付与 + score 保持 + matched_keys 保持 + スコア降順ソート
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
| **自レコードに複数の `auto_number` カラム** | 全カラムの値を `$identifierKeys[]` に収集し全てOR検索（現実装で対応済み） |
| **他の台帳の識別番号が自レコードのテキスト列に書かれている** | 下記「識別番号検索範囲の設計判断」を参照 |

### 識別番号検索範囲の設計判断

#### 問題の整理

`auto_number` カラムには以下の2つの利用パターンがある：

| パターン | 例 | 現在の挙動 |
|---|---|---|
| **A. 自台帳の識別番号列** | 点検記録の「設備番号」列（auto_number型）に `EQ-042` | `extractAutoNumberValues()` が取得 → **検索対象になる** ✅ |
| **B. 他台帳の識別番号がテキスト列に記載** | 点検記録の「作業内容」列（text型）に `「EQ-042 の修理」` と記述 | `extractAutoNumberValues()` は `auto_number` 型のみ見るため **検索対象にならない** ⚠️ |

AutoLink機能（`AutoLink.md` §2.1）では「他のカラムに記載された自動採番形式の値もリンク化する」とあり、  
関連案件検索との一貫性という観点では、パターンBも検索できた方がよいように見える。

#### ペルソナ視点での望ましい動作

**実務担当者（業務記録の参照）**  
作業報告の「作業内容」欄に `EQ-042` と書いた場合、その記録から `EQ-042` に関わる他記録を辿れることは自然な業務フロー。  
→ パターンBも拾えることが理想だが、テキスト列に「EQ-042」という**文字列が含まれる**だけでは意図的な識別番号紐付けか偶然の記述かが判断できない。

**現場リーダー（過去事例の参照）**  
「識別番号でヒット」は「同じ番号が記録されている＝明確な関連性あり」という根拠として使いたい。  
テキスト欄の偶然の言及も拾ってしまうと、トグルフィルターの「識別番号」の信頼性が下がる。

**管理者（監査）**  
監査根拠として「識別番号でヒット」を使う場合、「明確に識別番号として登録されている列の値が一致する」という厳密な基準を求める。

#### 設計判断: **フェーズ1はパターンAのみ（現実装）。パターンBは意味検索で補完する**

```
識別番号検索 = 自台帳定義の auto_number 型カラムの値でのみ検索（厳密な紐付け根拠）
意味検索     = テキスト内容全体のベクトル類似度（パターンBを含む文脈的な関連を補完）
```

**根拠:**
1. **信頼性の担保**: 識別番号トグルは「明確な識別番号の一致」のみを意味するため、監査根拠として利用できる
2. **意味検索による補完**: テキスト欄に他台帳の番号が書かれているケースは、意味検索（semantic）が文脈的に拾う可能性が高い
3. **実装の複雑度**: パターンBを拾うには AutoLink と同様に全テナントの `auto_number` カラムパターンを動的に生成する仕組みが必要で、Sprint 1〜4 で構築した基盤への影響が大きい
4. **ユーザー認識との整合**: AutoLink のリンクをクリックして詳細画面を開いた時に「関連案件タブ」が表示されるため、「識別番号でリンクされた記録」は意味検索を通じて見えることが多い

#### 将来の拡張余地（→ Issue #76 として独立管理）

パターンBを識別番号検索に含める場合の実装方針は [Issue #76](https://github.com/torinky/LedgerLeap/issues/76) および  
[`2026-03-02_issue-related-ledgers-text-identifier-search.md`](./2026-03-02_issue-related-ledgers-text-identifier-search.md) を参照。

概要:
- `AutoLinkService::getVirtualAutoNumberLinks()` と `generateAutoNumberPattern()` のロジックを `AutoNumberPatternService` に切り出す
- `extractAutoNumberValues()` に Step B を追加（全テキスト列へのパターンマッチング）
- `matched_keys` に `source` 情報（パターンAかBか・どのカラムから）を付与してツールチップに表示
- **Issue #54 の全スプリント完了後に着手する**

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

### ✅ Sprint 4: Blade ビュー実装（テーブル表示・識別理由バッジ・トグル）— 完了

**完了日:** 2026-03-01  
**目標:** `related-ledgers.blade.php` を実装し、既存の table-row / header コンポーネントと整合した UI でブラウザ表示を確認する  
**確認ポイント:** トグルで表示が切り替わり、識別理由バッジが表示され、台帳リスト画面と視覚的に統一されていること

#### Block 4.1: プレースホルダービュー

- [x] **Task 4.1.1**: `related-ledgers-placeholder.blade.php` を作成
  - `x-element.skeleton-table` 2 ブロック
  - Lazy ロード中に表示

#### Block 4.2: 識別理由バッジコンポーネント

- [x] **Task 4.2.1**: `resources/views/components/ledger/related-reason-badge.blade.php` を新規作成
  - props: `reason` (`identifier` / `semantic` / `both`)
  - `identifier` → `badge-warning` 🔖 識別番号
  - `semantic` → `badge-info` 🔍 意味検索
  - `both` → `badge-success` ⭐ 両方

#### Block 4.3: メインビュー

- [x] **Task 4.3.1**: `related-ledgers.blade.php` を作成
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

- [x] **Task 4.4.1**: `show.blade.php` に「関連案件」タブを追加
  - `#[Lazy]` 遅延ロード、`wire:key="related-ledgers-{{ $ledgerRecord->id }}"`
  - タブラベルバッジ: `@listen('relatedCountUpdated')` で `$relatedCount` を更新

- [x] **Task 4.4.2**: `lang/ja/ledger.php` に翻訳キーを追加
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

- [x] **Task 4.5.1**: auto_number カラムを持つ台帳のレコード詳細画面で確認
  - 識別番号バッジ・意味検索バッジ・両方バッジが正しく表示される
  - トグルで表示が切り替わる
  - ページングが機能する（複数ページある場合）
  - 台帳ヘッダーが台帳定義ごとに表示される
- [x] **Task 4.5.2**: `laravel-boost` MCP でエラーログ確認（`#[Lazy]` テナントコンテキスト欠落バグを修正: [1633cc30](https://github.com/torinky/LedgerLeap/commit/1633cc30)）

**✅ Sprint 4 完了条件 — 達成**
- ブラウザでトグル・バッジ・ページング・グルーピングが全て動作する ✅
- 台帳リスト画面と視覚的に統一されている ✅
- `laravel-boost` エラーなし ✅

---

### ✅ Sprint 5: タブバッジ・表示レベル同期・テスト整備・全体仕上げ — 完了

**完了日:** 2026-03-02 — テスト 22 passed  
**エビデンス:** [688dacd2](https://github.com/torinky/LedgerLeap/commit/688dacd2)

#### Block 5.1: タブバッジ実装

- [x] **Task 5.1.1**: `RelatedLedgers.php` の `render()` 末尾で `$this->dispatch('relatedCountUpdated', count: $this->totalCount)` を送信
- [x] **Task 5.1.2**: `Show.php` に `$relatedCount = 0` プロパティと `#[On('relatedCountUpdated')]` リスナーを追加
- [x] **Task 5.1.3**: `show.blade.php` のタブラベルに `badge` でバッジ表示（`$relatedCount > 0` の場合のみ）

#### Block 5.2: 表示レベル同期

- [x] **Task 5.2.1**: `RelatedLedgers.php` に `public int $displayLevel = 1` プロパティを追加
- [x] **Task 5.2.2**: `#[On('displayLevelUpdated')]` リスナーを追加し、`Show.php` からの `displayLevel` 変更を受け取る
- [x] **Task 5.2.3**: `render()` の表示カラムフィルタを `<= $this->displayLevel` に変更（現状は `<= 1` 固定）

#### Block 5.3: Feature テスト整備

- [x] **Task 5.3.1**: `RelatedLedgersTest.php` に Livewire レンダリングテストを追加（Sprint 3 の skipped テストを有効化）
- [x] **Task 5.3.2**: 全テスト実行・リグレッション確認

#### Block 5.4: コード品質

- [x] **Task 5.4.1**: Pint 実行
- [x] **Task 5.4.2**: `laravel-boost` エラーチェック

**✅ Sprint 5 完了条件 — 達成**
- タブバッジが件数を正しく表示する ✅
- `displayLevel` が基本情報タブと同期する ✅
- 全テストパス ✅
- Pint 実行済み・エラーなし ✅

---

### ✅ Sprint 6: UI品質改善（UIテスト指摘対応）— 完了

**完了日:** 2026-03-02 — テスト 22 passed  
**エビデンス:** [688dacd2](https://github.com/torinky/LedgerLeap/commit/688dacd2)

**背景:** Sprint 4 のブラウザ確認で以下の課題が発覚  
1. 関連理由列が幅を取りすぎ → アイコン＋ツールチップへ変更  
2. 表示レベルを基本情報タブと同期する調整 UI が未実装  
3. 意味検索ヒットのスコアが未表示（スコア順ソートも未実装）  
4. 識別番号でヒットした番号値が強調表示されていない  
5. 件数が括弧書き数字 → バッジ形式に変更  
6. 件数のアイコン・色がトグルと不一致  

#### Block 6.1: バックエンド改修

- [x] **Task 6.1.1**: `searchBySemantic()` の戻り値に score を保持・スコア降順で返す
- [x] **Task 6.1.2**: `searchByIdentifiers()` の戻り値に matched_keys を保持
- [x] **Task 6.1.3**: `mergeResults()` の引数・ソート処理を変更（score降順→identifier末尾）
- [x] **Task 6.1.4**: `displayLevel` プロパティ・`#[On('displayLevelUpdated')]` リスナーを追加

#### Block 6.2: バックエンドテスト（新規 4 件）

- [x] `it_retains_score_from_semantic_search`
- [x] `it_retains_matched_keys_from_identifier_search`
- [x] `it_sorts_by_score_descending_with_identifier_last`
- [x] `it_marks_both_score_when_ledger_appears_in_both`

#### Block 6.3: ビュー改修（識別理由インジケーター）

- [x] **Task 6.3.1**: `related-reason-badge.blade.php` をアイコン＋ツールチップ形式に改修（fa-brain + スコア）
- [x] **Task 6.3.2**: テーブルヘッダーの関連理由列を `w-8` に縮小
- [x] **Task 6.3.3**: 各行で `matched_keys` と `score` を `related-reason-badge` に渡す

#### Block 6.4〜6.6: ビュー・翻訳改修

- [x] 件数をバッジ形式に変更（badge-warning / badge-info）
- [x] 表示レベルセレクタ（x-mary-group）追加
- [x] `lang/ja/ledger.php` に `score_tooltip` / `identifier_tooltip` / `both_tooltip` / `display_level_label` 追加

**✅ Sprint 6 完了条件 — 達成**
- 識別理由列がアイコン（`w-8`）に縮小・ツールチップで詳細表示 ✅
- 意味検索スコアが台帳リスト画面と同じ badge+fa-brain+XX% 形式で表示 ✅
- スコア降順→identifier末尾のソート ✅
- 件数がバッジ形式・アイコン色と一致 ✅
- 表示レベルが基本情報タブと同期 ✅
- テスト 22 passed ✅

---

### ✅ Sprint 7: 台帳リスト画面と同等の行表示（UIテスト指摘 第2弾）— 完了

**目標:** 関連案件タブの各レコード行表示を台帳リスト画面と完全に揃える  
**完了日:** 2026-03-02 — テスト 22 passed  
**エビデンス:** [688dacd2](https://github.com/torinky/LedgerLeap/commit/688dacd2)  
**背景:** Sprint 6 完了後のUIレビューで以下の課題が発覚  
1. 添付ファイルが表示されない（`x-ledger.table-row` の添付ファイルロジック未使用）  
2. ラベル表示・マークダウン HTML 化などカラムの標準レンダリングがない  
3. 「もっと見る」（expandable-content）UI がない  
4. 先頭の識別理由列を廃止し、台帳リスト画面と同じ `updated_at` セルへのオーバーレイに変更  
5. タブのバッジ件数表示が括弧書き形式のまま（バッジ形式に変更が必要）  

**方針:** 独自テーブル実装を廃止し、`x-ledger.table-row` コンポーネントを**直接流用**する。  
識別理由バッジは `table-row` 内の `updated_at` セルオーバーレイ領域に  
`$relatedBadge` スロットとして差し込む形で `table-row.blade.php` を最小限拡張する。

#### Block 7.1: `x-ledger.table-row` コンポーネント拡張

- [x] **Task 7.1.1**: `table-row.blade.php` に `$relatedBadge` スロット prop を追加

#### Block 7.2: `related-ledgers.blade.php` リファクタリング

- [x] **Task 7.2.1**: 独自テーブル（`<table>` + 独自 `<thead>` + 独自 `<tbody>`）を廃止
- [x] **Task 7.2.2**: `x-ledger.table-header` + `x-ledger.table-row` の組み合わせに置き換え
- [x] **Task 7.2.3**: `x-ledger.table-header` を使ってヘッダーを統一（識別理由列ヘッダーは不要）

#### Block 7.3: `show.blade.php` タブバッジ更新

- [x] **Task 7.3.1**: タブラベルの括弧書き件数をバッジ形式に変更

#### Block 7.4: テスト整備

- [x] **Task 7.4.1**: `table-row` の `$relatedBadge` スロット追加に関するリグレッションテスト確認
- [x] **Task 7.4.2**: `./vendor/bin/sail test` 全テストパス確認 — 22 passed

#### Block 7.5: コード品質・動作確認

- [x] **Task 7.5.1**: Pint 実行
- [x] **Task 7.5.2**: ブラウザ動作確認（添付ファイル・標準レンダリング・もっと見る・識別理由バッジ・タブバッジ）
- [x] **Task 7.5.3**: `laravel-boost` エラーチェック

**✅ Sprint 7 完了条件 — 達成**
- 関連案件タブの行表示が台帳リスト画面と同等になる ✅
- 添付ファイルが表示される ✅
- ラベル・マークダウン・もっと見る が機能する ✅
- 識別理由バッジが `updated_at` オーバーレイに統合される ✅
- タブバッジが件数をバッジ形式で表示する ✅
- 全テストパス ✅

---

### ✅ Sprint 8: UI 細部修正・displayLevel 連動・ローディング改善 — 完了

**目標:** Sprint 7 完了後のUIレビュー指摘事項を解消する  
**完了日:** 2026-03-02 — テスト 22 passed  
**エビデンス:** [fix(related-ledgers): UI・UX の複数改善](https://github.com/torinky/LedgerLeap/commit/688dacd2)

#### 対応内容

- [x] 識別理由バッジ簡素化：semantic のみの場合はアイコン不要（大スコアバッジで自明）、identifier/both は `fa-bookmark` のみ表示
- [x] スコア小バッジ（`badge-sm`）廃止（大バッジに統一）
- [x] `table-zebra` 復元（ホバー遅延の原因でなかったため）
- [x] ホバー遅延解消（`sail npm run build` で Tailwind JIT を再コンパイル）
- [x] 表示レベルを `wire:model.live="$parent.displayLevel"` で `Show` と直接同期
  - `RelatedLedgers::updatedDisplayLevel()` の複雑なフラグ管理を廃止
  - 関連案件タブの表示レベル変更が基本情報・更新履歴タブに連動
- [x] 表示レベル変更時のローディングを `show.blade.php` 側の `displayLevel` ターゲットで制御
- [x] `placeholder` を `w-full min-h-[400px]` に変更してウィンドウ幅にフィット
- [x] `Show::mount()` で RAG 含む `relatedCount` を先行計算（初期ロード時のタブバッジ表示）

**✅ Sprint 8 完了条件 — 達成**
- 識別理由バッジが簡潔（semantic はスコアバッジのみ、identifier は `fa-bookmark` のみ） ✅
- 表示レベルが全タブで双方向に同期する ✅
- 表示レベル切替時にローディング・スケルトンが表示される ✅
- スケルトンがウィンドウ幅にフィットする ✅
- 全テストパス ✅

---

- [ ] **Task 6.1.1**: `searchBySemantic()` の戻り値を `Collection<int, array{ledger: Ledger, score: float}>` に変更
  - `ragResults` の `max_score` を各要素に付与
  - スコア降順でソート済みの状態で返す

- [ ] **Task 6.1.2**: `searchByIdentifiers()` の戻り値を `Collection<int, array{ledger: Ledger, matched_keys: string[]}>` に変更
  - `$key → ledger_id[]` のマッピングを保持し、各 Ledger に一致した識別番号値を付与

- [ ] **Task 6.1.3**: `mergeResults()` の引数・内部処理を変更
  - 引数: `Collection<array{ledger, matched_keys}>` + `Collection<array{ledger, score}>`
  - score を保持、matched_keys を保持
  - スコアのある要素（semantic/both）を score 降順、identifier のみを末尾に配置

- [ ] **Task 6.1.4**: `RelatedLedgers.php` プロパティに `public int $displayLevel = 1` を追加（Sprint 5 未完の場合）

#### Block 6.2: バックエンドテスト

- [ ] **Task 6.2.1**: `RelatedLedgersTest.php` に以下のテストを追加
  - `it_retains_score_from_semantic_search` — `searchBySemantic()` が score を保持することを確認
  - `it_retains_matched_keys_from_identifier_search` — `searchByIdentifiers()` が matched_keys を保持することを確認
  - `it_sorts_by_score_descending_with_identifier_last` — `mergeResults()` のソート順を確認
  - `it_marks_both_score_when_ledger_appears_in_both` — both 時にスコアが引き継がれることを確認

- [ ] **Task 6.2.2**: テスト実行・パス確認

#### Block 6.3: ビュー改修（識別理由インジケーター）

- [ ] **Task 6.3.1**: `related-reason-badge.blade.php` を **アイコン＋ツールチップ形式** に改修
  - テキストラベルを廃止し、アイコンのみ（`w-5 h-5`）
  - `data-tip` に識別番号値またはスコアを表示
  - Props 拡張: `reason`, `matched_keys` (array), `score` (float|null)
  
  ```blade
  @props(['reason' => 'identifier', 'matchedKeys' => [], 'score' => null])
  {{-- identifier: 🔖 tooltip="識別番号: EQ-042" --}}
  {{-- semantic:   🔍 tooltip="類似度: 85%" --}}
  {{-- both:       ⭐ tooltip="識別番号: EQ-042 / 類似度: 85%" --}}
  ```

- [ ] **Task 6.3.2**: `related-ledgers.blade.php` のテーブルヘッダー列幅を変更
  - `w-24` → `w-8`（アイコン1つ分）
  - ヘッダーラベル廃止（`—` またはアイコン）

- [ ] **Task 6.3.3**: `related-ledgers.blade.php` の各行で `matched_keys` と `score` を `related-reason-badge` に渡す

#### Block 6.4: ビュー改修（件数バッジ）

- [ ] **Task 6.4.1**: ツールバーの件数表示を括弧書き数字からバッジに変更
  - 識別番号件数: `<span class="badge badge-warning badge-sm">{{ $identifierCount }}</span>`
  - 意味検索件数: `<span class="badge badge-info badge-sm">{{ $semanticCount }}</span>`
  - 合計件数ラベルも `badge-neutral` のバッジ形式に変更
  - 件数が 0 の場合はバッジを非表示（ゼロ表示しない）

#### Block 6.5: ビュー改修（表示レベル調節 UI）

- [ ] **Task 6.5.1**: ツールバーに表示レベルセレクタを追加
  - `x-mary-group` コンポーネントで `wire:model.live="displayLevel"` をバインド
  - オプション: `基本(1)` / `詳細(2)` / `すべて(3)`
  - `show.blade.php` の基本情報タブと同じ `displayLevelOptions` 配列を使用
  - `#[On('displayLevelUpdated')]` リスナーで `Show.php` のセレクタ変更を受け取り自動同期

- [ ] **Task 6.5.2**: `render()` の表示カラムフィルタを `<= $this->displayLevel` に変更

#### Block 6.6: 翻訳キー追加

- [ ] **Task 6.6.1**: `lang/ja/ledger.php` の `related` セクションに追加
  ```php
  'score_tooltip'    => '類似度: :score%',
  'identifier_tooltip' => '識別番号: :keys',
  'both_tooltip'     => '識別番号: :keys / 類似度: :score%',
  'display_level_label' => '表示レベル',
  ```

#### Block 6.7: ブラウザ動作確認

- [ ] **Task 6.7.1**: 識別番号ヒットのアイコンにツールチップで番号値が表示されること
- [ ] **Task 6.7.2**: 意味検索ヒットの行にスコアがツールチップで表示されること
- [ ] **Task 6.7.3**: 結果がスコア降順→識別番号のみ の順で表示されること
- [ ] **Task 6.7.4**: ツールバーの件数がバッジ形式で表示されること（アイコン・色が一致）
- [ ] **Task 6.7.5**: 表示レベルを変更すると関連案件タブのカラムが変わること
- [ ] **Task 6.7.6**: 基本情報タブの表示レベル変更と連動すること
- [ ] **Task 6.7.7**: `laravel-boost` エラーチェック

**✅ Sprint 6 完了条件**
- 識別理由列がアイコン（`w-8`）に縮小され、ツールチップで詳細表示 ✅
- 意味検索ヒットのレコードにスコア（ツールチップ）が表示される ✅
- 結果がスコア降順でソートされる ✅
- 件数がバッジ形式で表示され、アイコン・色がトグルと一致する ✅
- 表示レベルが基本情報タブと同期する ✅
- 全テストパス（新規テスト 4 件追加） ✅
- Pint 実行済み・エラーなし ✅

---

## 📊 総予想工数（更新）

| スプリント | 内容 | 予想工数 |
|---|---|---|
| Sprint 1 | 識別番号検索バックエンド + テスト | ✅ 完了 |
| Sprint 2 | 意味検索バックエンド + テスト + CI確認 | ✅ 完了 |
| Sprint 3 | マージ・識別理由・ページング・グルーピングバックエンド | ✅ 完了 |
| Sprint 4 | Blade ビュー（テーブル・バッジ・トグル・show統合） | ✅ 完了 |
| Sprint 5 | タブバッジ・displayLevel同期・テスト整備・全体仕上げ | ✅ 完了 |
| Sprint 6 | UI品質改善（アイコン化・スコア表示・バッジ件数・表示レベル調節） | ✅ 完了 |
| Sprint 7 | 台帳リスト画面と同等の行表示（添付ファイル・標準レンダリング・タブバッジ） | ✅ 完了 |
| Sprint 8 | UI細部修正・displayLevel 双方向同期・ローディング改善 | ✅ 完了 |
| **合計** | | **全スプリント完了** |

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
2026-03-01 — ブラウザ動作確認済み・#[Lazy] テナントコンテキストバグ修正済み

### Sprint 5 完了日時
2026-03-02 — テスト 22 passed (Sprint 6 と同一コミットで完了)

### Sprint 6 完了日時
2026-03-02 — テスト 22 passed / エビデンス: [688dacd2](https://github.com/torinky/LedgerLeap/commit/688dacd2)

### Sprint 7 完了日時
2026-03-02 — テスト 22 passed / エビデンス: [688dacd2](https://github.com/torinky/LedgerLeap/commit/688dacd2)

### Sprint 8 完了日時
2026-03-02 — テスト 22 passed / Pint 実行済み
