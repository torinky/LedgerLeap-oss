# セマンティック検索UI改善計画書

**日付:** 2025年11月15日
**ステータス:** 計画中 (調査結果反映版)
**関連ドキュメント:**
- [RAG導入 Phase1 実装計画 - セマンティック検索追加](../rag-implementation/2025-10-17-phase1-hybrid-search-plan.md)

## 1. 目的と背景

現在の台帳一覧画面では、「セマンティック検索」が並び順の一種として実装されている。しかし、これはユーザーの直感的な操作と乖離しており、UI/UXの観点から改善が必要である。

本計画は、このUIを改善し、セマンティック検索を並び順から独立した「検索モード」として明確に切り替える機能を提供することを目的とする。

## 2. 現状の課題分析

- **UI/UX上の問題:**
    - 「並び順」と「検索方法」という異なる概念が混在しており、ユーザーが混乱しやすい。
    - セマンティック検索を選択すると、他の並び順（作成日時、更新日時）が利用できなくなる。
- **技術的な問題:**
    - `app/Livewire/Ledger/RecordsTable.php` の `render()` メソッド内で、`$this->orderBy === 'semantic_score'` という条件分岐が検索ロジックの根幹を切り替えており、柔軟性に欠ける。

## 3. 関連コードの調査結果

- **UI:** `resources/views/components/ledger/search.blade.php`
    - `<select>` 要素内に `<option value="semantic_score">` がハードコードされている。
- **バックエンド:** `app/Livewire/Ledger/RecordsTable.php`
    - 当初、検索ロジックは `App\Services\LedgerSearch` のような専用クラスにカプセル化されていると推測されたが、調査の結果、**該当クラスは存在せず、検索ロジックは `render()` メソッド内に直接実装されている**ことが判明した。
    - `public $orderBy` プロパティで並び順を管理している。
    - `render()` メソッド内で `$this->orderBy === 'semantic_score'` かつ検索語が存在する場合に、`app(\App\Services\RagSearchService::class)->search(...)` を直接呼び出している。
    - `RagSearchService->search()` は、内部でMroongaのベクトル検索を実行し、スコア（類似度）順にソート済みの `LengthAwarePaginator` インスタンスを返す。このため、**セマンティック検索実行時に追加で `orderBy` を適用することはできない**。

## 4. 変更計画

セマンティック検索を「並び順」から完全に分離し、独立した検索オプションとして扱う。

### 4.1. UIの変更 (`search.blade.php`)

1.  **並び順オプションの修正:**
    -   並び順の `<select>` から `<option value="semantic_score">` を完全に削除する。
2.  **トグルスイッチの追加:**
    -   セマンティック検索のON/OFFを切り替えるための `<x-mary-toggle>` コンポーネントを新設する。
    -   このスイッチは、後述するLivewireコンポーネントの新しいプロパティ (`useSemanticSearch`) に `wire:model.live` でバインドする。
    -   検索キーワードが空の場合は、セマンティック検索が無意味であるため、スイッチを `:disabled` 属性で無効化する。

**実装イメージ:**
```html
<!-- resources/views/components/ledger/search.blade.php -->

<!-- ... (既存の検索バーの横) ... -->
<div class="flex items-center space-x-2">
    <x-mary-toggle :label="__('ledger.semantic_search')" wire:model.live="useSemanticSearch" :disabled="!$search" />
</div>

<!-- ... (既存の並び順select) ... -->
```

### 4.2. バックエンドの変更 (`RecordsTable.php`)

1.  **プロパティの追加:**
    -   `public bool $useSemanticSearch = false;` を追加する。`#[Url(as: 'sem', history: true)]` 属性を付け、状態がURLに反映されるようにする。
2.  **検索ロジックの修正 (`render` メソッド):**
    -   検索の実行判定を `if ($this->orderBy === 'semantic_score' ...)` から `if ($this->useSemanticSearch && !empty($this->search))` に変更する。
    -   `else` ブロック（通常検索）内の `orderBy` 句から、`semantic_score` に関する分岐を削除し、ロジックを簡素化する。
3.  **関連メソッドのクリーンアップ:**
    -   `getStandardSortLabel()` メソッドから `'semantic_score' => ...` の case を削除する。
    -   `updatedOrderBy()` メソッド内の `semantic_score` に関連する特殊処理を削除する。

**実装イメージ:**
```php
// app/Livewire/Ledger/RecordsTable.php

#[Url(as: 'sem', history: true)]
public bool $useSemanticSearch = false;

// ...

private function getStandardSortLabel(string $columnName): string
{
    return match ($columnName) {
        'composite_score' => __('ledger.scoring.score'),
        'created_at' => __('ledger.created_at'),
        'updated_at' => __('ledger.updated_at'),
        // 'semantic_score' の case を削除
        default => '',
    };
}

// ...

public function render(SearchContext $searchContext)
{
    // ... (準備処理) ...

    // 表示対象の台帳に紐づく仕訳データを取得
    if ($this->useSemanticSearch && ! empty($this->search)) { // ★ 条件を変更
        $ledgerRecords = app(\App\Services\RagSearchService::class)->search(
            query: $this->search,
            user: auth()->user(),
            ledgerDefineIds: $searchTargetLedgerDefineIds,
            filters: $this->filter,
            perPage: $this->perPage
        );
        // ... (後続処理はほぼ変更なし)
    } else {
        $ledgerRecordsQuery = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
            // ... (searchContext, contentsFilterなど) ...
            ->when($this->orderBy === 'composite_score', function ($query) {
                return $query->orderByRaw('composite_score = 0, composite_score '.
                    ($this->orderAsc ? 'ASC' : 'DESC'));
            }, function ($query) {
                // ★ 'semantic_score' の分岐を削除し、シンプルにする
                return $query->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
            });

        // ... (ページネーションなど)
    }

    // ... (ビューにデータを渡す) ...
}
```

## 5. 実装ステップ (WBS)

1.  **Livewireコンポーネント改修 (2h):**
    -   `RecordsTable.php` に `$useSemanticSearch` プロパティを追加する。
    -   `render()` メソッドのロジックを上記計画通りに修正する。
    -   `getStandardSortLabel()` 等の関連メソッドから `semantic_score` の記述を削除する。
2.  **UI改修 (1h):**
    -   `search.blade.php` を修正し、`<option>` の削除とトグルスイッチの追加を行う。
    -   必要であれば翻訳キー `ledger.semantic_search` を確認・追加する（既存のものを流用できるはず）。
3.  **テスト (2h):**
    -   セマンティック検索ON/OFFでの動作を確認するFeatureテストを作成・実行する。
    -   並び替えが通常検索モードで正しく機能することを確認する。
4.  **ドキュメント更新 (0.5h):**
    -   本計画書を「完了」ステータスに更新し、実装結果を追記する。

**合計見積工数: 5.5時間**

## 6. テスト計画

-   **手動テスト:**
    -   キーワードを入力しないとトグルスイッチが `disabled` になることを確認。
    -   スイッチをONにして検索し、意味的に関連するがキーワードを含まないレコードがヒットすることを確認。
    -   **スイッチONの時、UI上のソート順を変更しても、結果の順序（関連度順）が変わらないことを確認する。** (UI上はソートドロップダウンを無効化するのがより親切かもしれないが、まずはロジックの正しさを確認)
    -   スイッチをOFFにして検索し、従来のキーワード検索として動作し、かつ作成日順などのソートが正しく機能することを確認。
-   **自動テスト:**
    -   `RecordsTable` のFeatureテストを拡張。
    -   `->set('useSemanticSearch', true)` を設定した場合に `RagSearchService` が呼び出されることをモックを使って検証する。
    -   `->set('useSemanticSearch', false)` を設定した場合に `RagSearchService` が呼び出されないことを検証する。

## 7. 期待される効果

-   **UX向上:** ユーザーが「検索方法」と「並び順」を独立して直感的に操作できるようになる。
-   **保守性向上:** UIとバックエンドのロジックがより整理され、将来の変更が容易になる。