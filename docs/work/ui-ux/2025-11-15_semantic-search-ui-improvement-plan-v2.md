# セマンティック検索UI改善計画書 v2.0

**日付:** 2025年11月15日  
**最終更新:** 2025年11月15日 21:00  
**ステータス:** レビュー完了・実装準備中  
**関連ドキュメント:**
- [RAG導入 Phase1 実装計画 - セマンティック検索追加](../rag-implementation/2025-10-17-phase1-hybrid-search-plan.md)
- [v1.0計画書](./2025-11-15_semantic-search-ui-improvement-plan.md)

---

## 📋 変更履歴

| バージョン | 日付 | 変更内容 |
|----------|------|---------|
| v1.0 | 2025-11-15 | 初版作成（調査結果反映） |
| v2.0 | 2025-11-15 | レビュー結果反映・追加要件対応 |

---

## 1. 目的と背景

### 1.1. 基本目的

現在の台帳一覧画面では、「セマンティック検索」が並び順の一種として実装されている。これはUI/UX的に以下の問題を抱えている：

- 「検索方法」と「並び順」という異なる概念が混在
- セマンティック検索選択時、他の並び順が利用できなくなる
- ユーザーの直感的な操作と乖離

### 1.2. 追加要件（v2.0で追加）

レビューおよびプロジェクト要件の精査の結果、以下の機能が必須となった：

1. **並び順の継続サポート**: セマンティック検索ON時も、`created_at`, `updated_at`, `composite_score`, `semantic_score`による並び替えが可能
2. **スコア表示の柔軟な切り替え**: 
   - `composite_score`選択時: 従来の総合スコア（⭐マーク）表示
   - `semantic_score`選択時: 意味検索スコア（🧠マーク）表示
3. **ユースケースへの対応**:
   - 「意味的に関連する台帳を、作成日時順で見たい」
   - 「セマンティック検索で絞り込んだ結果を、総合スコアで並べたい」

---

## 2. 現状の課題分析

### 2.1. UI/UX上の問題

- **概念の混在**: 「並び順」と「検索方法」が同じUIコンポーネントに配置
- **柔軟性の欠如**: セマンティック検索を選ぶと、並び順の選択肢が実質的に失われる
- **説明不足**: 検索モードの切り替えがユーザーに分かりにくい

### 2.2. 技術的な問題

#### 2.2.1. RagSearchServiceの制約

**場所**: `app/Services/RagSearchService.php` 行28-82

**問題点**:
```php
// 行73-80: スコア情報が破棄される
$sortedLedgers = collect($searchResults)->map(function ($result) use ($ledgers) {
    return $ledgers->get($result['ledger_id']);  // ← $result['max_score']が失われる
})->filter();
```

- `searchLedgers()`メソッドは`max_score`を保持しているが、`search()`メソッドでLedgerモデルに変換する際にスコアが破棄される
- 返される`LengthAwarePaginator`には`$ledger->semantic_score`属性が存在しない

#### 2.2.2. RecordsTableの実装課題

**場所**: `app/Livewire/Ledger/RecordsTable.php` 行319-365

**問題点**:
```php
if ($this->orderBy === 'semantic_score' && !empty($this->search)) {
    $ledgerRecords = app(\App\Services\RagSearchService::class)->search(...);
    // ↑ この時点で類似度順にソート済み・ページネート済み
    // 後から並び替えることができない
}
```

- セマンティック検索と通常検索が完全に分離された分岐
- `RagSearchService`が返す結果は既にページネート済みのため、後から全体をソートできない
- `orderBy`の値で検索方法とソート方法の両方を制御しており、責務が混在

### 2.3. 調査で判明した実装詳細

| 項目 | 実装箇所 | 現状 | 改善必要性 |
|------|---------|-----|-----------|
| UIコンポーネント | `resources/views/components/ledger/search.blade.php` 行29-32 | `<option value="semantic_score">`として並び順に混在 | ✅ 必須 |
| 検索ロジック | `RecordsTable::render()` 行319 | `orderBy`による分岐 | ✅ 必須 |
| スコア付与 | `RagSearchService::search()` 行73-80 | スコア情報破棄 | ✅ 必須 |
| スコア表示 | `resources/views/components/ledger/table-row.blade.php` 行87-91 | `composite_score`のみ | ✅ 必須 |
| 並び替えロジック | `RecordsTable::render()` 行343-353 | Eloquentクエリビルダー | ⚠️ 拡張必要 |

---

## 3. 解決策の設計

### 3.1. アーキテクチャの変更方針

**基本戦略**: 検索方法（通常/セマンティック）と並び順を完全に独立させる

```
┌─────────────────────────────────────┐
│ 検索方法の選択                       │
│ ├─ useSemanticSearch (bool)         │
│ │   ON: RAGベクトル検索で候補を取得 │
│ │   OFF: 通常の全文検索              │
└─────────────────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 検索結果の取得                       │
│ ├─ セマンティック: 全件取得+スコア付与│
│ └─ 通常: クエリビルダーで絞り込み    │
└─────────────────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ 並び順の適用 (orderBy)               │
│ ├─ composite_score                  │
│ ├─ semantic_score (セマンティック時) │
│ ├─ created_at                       │
│ └─ updated_at                       │
└─────────────────────────────────────┘
            ↓
┌─────────────────────────────────────┐
│ ページネーション & 表示               │
└─────────────────────────────────────┘
```

### 3.2. 実装計画の詳細

#### Phase 1: RagSearchService の改修

**目的**: スコア情報をLedgerモデルの属性として付与

**ファイル**: `app/Services/RagSearchService.php`

**変更箇所**: `search()`メソッド（行73-80）

```php
// ★ 修正前
$sortedLedgers = collect($searchResults)->map(function ($result) use ($ledgers) {
    return $ledgers->get($result['ledger_id']);
})->filter();

// ★ 修正後
$scoreMap = collect($searchResults)->pluck('max_score', 'ledger_id');
$sortedLedgers = collect($searchResults)->map(function ($result) use ($ledgers, $scoreMap) {
    $ledger = $ledgers->get($result['ledger_id']);
    if ($ledger) {
        // セマンティックスコアを動的属性として付与
        $ledger->semantic_score = $result['max_score'];
        $ledger->best_chunk_text = $result['best_chunk_text'];
    }
    return $ledger;
})->filter();
```

**影響範囲**:
- ✅ 既存のAPI実装（`searchForApi()`メソッド）には影響なし
- ✅ ページネーション処理は変更不要
- ✅ 既存テスト（`RagSearchServiceTest.php`）に追加のアサーションが必要

#### Phase 2: RecordsTable の大幅改修

**目的**: 検索方法と並び順を完全に独立させる

**ファイル**: `app/Livewire/Ledger/RecordsTable.php`

**2.1. プロパティの追加**

```php
// 行98付近に追加
#[Url(as: 'sem', history: true)]
public bool $useSemanticSearch = false;
```

**2.2. render()メソッドの全面改修**（行319-365を置き換え）

```php
public function render(SearchContext $searchContext)
{
    $this->initSearchContext();
    
    // ... (既存の準備処理: 台帳定義の取得など) ...
    
    if ($this->useSemanticSearch && !empty($this->search)) {
        // ============================================
        // ★ セマンティック検索モード
        // ============================================
        
        // Step 1: RAGで検索（スコア情報付きで全件取得）
        $ragResults = app(\App\Services\RagSearchService::class)->searchLedgers(
            query: $this->search,
            limit: 1000, // 十分な件数を取得（後でソート・ページネーション）
            filters: array_merge($this->filter, [
                'user' => auth()->user(),
                'ledger_define_ids' => $searchTargetLedgerDefineIds,
            ])
        );
        
        if (empty($ragResults)) {
            $this->totalRecords = 0;
            return view('livewire.ledger.records-table', [/* 空の結果 */]);
        }
        
        // Step 2: Ledgerモデルを取得
        $ledgerIds = array_column($ragResults, 'ledger_id');
        $scoreMap = collect($ragResults)->pluck('max_score', 'ledger_id');
        
        $ledgersCollection = Ledger::whereIn('id', $ledgerIds)
            ->whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
            ->when(!empty($this->filterStatus), function ($query) {
                return $query->where('status', $this->filterStatus);
            })
            ->with(['define', 'creator', 'modifier'])
            ->get();
        
        // Step 3: スコアを動的属性として付与
        $ledgersCollection->each(function ($ledger) use ($scoreMap) {
            $ledger->semantic_score = $scoreMap[$ledger->id] ?? 0;
        });
        
        // Step 4: 並び順を適用
        $sortedLedgers = $this->applySorting($ledgersCollection, $this->orderBy, $this->orderAsc);
        
        // Step 5: ページネーション
        $this->totalRecords = $sortedLedgers->count();
        $currentPage = Paginator::resolveCurrentPage();
        $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();
        
        $ledgerRecords = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $this->totalRecords,
            $this->perPage,
            $currentPage
        );
        
        // 台帳定義情報を取得
        $ledgerDefineRecords = LedgerDefine::whereIn('id', $ledgerRecords->pluck('ledger_define_id')->unique()->toArray())
            ->with('folder')
            ->get()
            ->keyBy('id');
        
    } else {
        // ============================================
        // ★ 通常検索モード（既存ロジック）
        // ============================================
        $ledgerRecordsQuery = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
            ->searchContext($this->searchContext)
            ->contentsFilter($this->filter)
            ->when(!empty($this->filterStatus), function ($query) {
                return $query->where('status', $this->filterStatus);
            })
            ->orderBy('ledger_define_id', 'asc')
            ->when($this->orderBy === 'composite_score', function ($query) {
                return $query->orderByRaw('composite_score = 0, composite_score '.
                    ($this->orderAsc ? 'ASC' : 'DESC'));
            }, function ($query) {
                return $query->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
            });
        
        $ledgerDefineRecords = LedgerDefine::whereIn('id', 
            (clone $ledgerRecordsQuery)->get()->unique('ledger_define_id')->pluck('ledger_define_id')->toArray()
        )->with('folder')->get()->keyBy('id');
        
        $this->totalRecords = $ledgerRecordsQuery->count();
        $ledgerRecords = $ledgerRecordsQuery->simplePaginate($this->perPage);
    }
    
    // ... (以降の共通処理: 添付ファイル取得、スコア統計など) ...
}

/**
 * コレクションに対してソートを適用する新規メソッド
 */
private function applySorting($collection, string $orderBy, bool $orderAsc)
{
    return match($orderBy) {
        'semantic_score' => $orderAsc 
            ? $collection->sortBy('semantic_score')
            : $collection->sortByDesc('semantic_score'),
        'composite_score' => $orderAsc
            ? $collection->sortBy(fn($ledger) => $ledger->composite_score ?: 0)
            : $collection->sortByDesc(fn($ledger) => $ledger->composite_score ?: 0),
        'created_at' => $orderAsc
            ? $collection->sortBy('created_at')
            : $collection->sortByDesc('created_at'),
        'updated_at' => $orderAsc
            ? $collection->sortBy('updated_at')
            : $collection->sortByDesc('updated_at'),
        default => $collection // フォールバック
    };
}
```

**2.3. ライフサイクルフックの追加**

```php
// 検索語がクリアされたらセマンティック検索をOFF
public function updatedSearch($value)
{
    if (empty($value) && $this->useSemanticSearch) {
        $this->useSemanticSearch = false;
    }
    $this->initSearchContext();
}

// セマンティック検索ON時、semantic_score以外が選択されていたら自動切替
public function updatedUseSemanticSearch($value)
{
    if ($value && empty($this->search)) {
        // 検索語がないのにONにしようとした → OFF に戻す
        $this->useSemanticSearch = false;
        return;
    }
    
    // セマンティック検索ON時、デフォルトでsemantic_score順にする（オプション）
    if ($value && $this->orderBy === 'composite_score') {
        $this->orderBy = 'semantic_score';
        $this->orderByLabel = $this->getStandardSortLabel('semantic_score');
    }
}
```

**2.4. getStandardSortLabel()の更新**

```php
private function getStandardSortLabel(string $columnName): string
{
    return match ($columnName) {
        'composite_score' => __('ledger.scoring.score'),
        'semantic_score' => __('ledger.semantic_score_sort'),
        'created_at' => __('ledger.created_at'),
        'updated_at' => __('ledger.updated_at'),
        default => '',
    };
}
```

#### Phase 3: UIの改修

**ファイル**: `resources/views/components/ledger/search.blade.php`

**3.1. セマンティック検索トグルの追加**（行29の前に挿入）

```blade
{{-- セマンティック検索トグル --}}
<div class="form-control">
    <label class="label cursor-pointer gap-2">
        <span class="label-text flex items-center gap-1">
            <i class="fas fa-brain"></i>
            {{ __('ledger.semantic_search') }}
        </span>
        <input wire:model.live="useSemanticSearch" 
               type="checkbox" 
               class="toggle toggle-secondary"
               {{ empty($search) ? 'disabled' : '' }} />
    </label>
    @if(empty($search))
        <span class="label-text-alt text-base-content/50">
            {{ __('ledger.semantic_search_requires_query') }}
        </span>
    @elseif($useSemanticSearch)
        <span class="label-text-alt text-info flex items-center gap-1">
            <i class="fas fa-check-circle"></i>
            {{ __('ledger.semantic_search_active') }}
        </span>
    @endif
</div>
```

**3.2. ソート順セレクトの修正**（行22-33を置き換え）

```blade
<div class="form-control">
    <label class="label">
        <span class="label-text">{{ __('ledger.sort_by') }}</span>
    </label>
    <select wire:model.change="orderBy" class="select select-bordered select-sm">
        @if ($orderByLabel !== '')
            <option value="{{ $orderBy }}" selected>{{ $orderByLabel }}</option>
        @endif
        <option value="composite_score">{{ __('ledger.scoring.score') }}</option>
        <option value="created_at">{{ __('ledger.created_at') }}</option>
        <option value="updated_at">{{ __('ledger.updated_at') }}</option>
        {{-- ★ セマンティック検索ON時のみ表示 --}}
        @if($useSemanticSearch)
            <option value="semantic_score">{{ __('ledger.semantic_score_sort') }}</option>
        @endif
    </select>
</div>
```

#### Phase 4: スコア表示の拡張

**ファイル**: `resources/views/components/ledger/table-row.blade.php`

**変更箇所**: 行77-92（スコアバッジ表示部分）

```blade
@php
    // 表示するスコアとその種類を決定
    $displayScore = null;
    $scoreType = null;
    $scoreClass = '';
    
    if (isset($ledgerRecord->semantic_score) && $ledgerRecord->semantic_score > 0) {
        // セマンティックスコアが存在する場合（セマンティック検索モード）
        $displayScore = $ledgerRecord->semantic_score;
        $scoreType = 'semantic';
        // 類似度スコア (0.0-1.0) に基づく色分け
        $scoreClass = match(true) {
            $displayScore >= 0.8 => 'badge-success',
            $displayScore >= 0.6 => 'badge-primary',
            $displayScore >= 0.4 => 'badge-info',
            $displayScore > 0 => 'badge-ghost',
            default => ''
        };
    } elseif ($ledgerRecord->composite_score > 0) {
        // 通常の総合スコア
        $displayScore = $ledgerRecord->composite_score;
        $scoreType = 'composite';
        $scoreClass = match(true) {
            $displayScore >= 70 => 'badge-success',
            $displayScore >= 40 => 'badge-primary',
            $displayScore >= 20 => 'badge-info',
            $displayScore > 0 => 'badge-ghost',
            default => ''
        };
    }
@endphp

@if($displayScore !== null)
    <span class="badge badge-xl {{ $scoreClass }} flex items-center gap-1 tooltip"
          data-tip="{{ $scoreType === 'semantic' ? __('ledger.semantic_score_tooltip') : __('ledger.composite_score_tooltip') }}">
        @if($scoreType === 'semantic')
            <i class="fas fa-brain"></i> {{-- セマンティックスコアアイコン --}}
            {{ number_format($displayScore * 100, 1) }}% {{-- 0.0-1.0 を 0-100% に変換 --}}
        @else
            <i class="fas fa-star"></i> {{-- 総合スコアアイコン --}}
            {{ number_format($displayScore, 1) }}
        @endif
    </span>
@endif

{{-- ワークフローステータス（既存のまま）--}}
@if($ledgerRecord->define->workflow_enabled && $ledgerRecord->status)
    <span class="badge badge-lg {{ $ledgerRecord->status->colorClass() }} flex items-center gap-1">
        <i class="{{ $ledgerRecord->status->icon() }}"></i>
        {{ $ledgerRecord->status->label() }}
    </span>
@endif
```

#### Phase 5: 翻訳キーの追加

**ファイル**: `lang/ja/ledger.php`

**追加箇所**: 行835付近（'semantic_search'の後）

```php
'semantic_search' => '意味検索',
'semantic_search_requires_query' => '意味検索を使用するには検索語を入力してください',
'semantic_search_active' => '意味検索モードで検索中',
'semantic_score_sort' => '意味検索スコア順',
'semantic_score_tooltip' => '検索語との意味的な関連度（AI判定）',
'composite_score_tooltip' => '総合スコア（活動度・新鮮度・ステータス）',
```

---

## 4. テスト計画

### 4.1. 単体テスト

#### 4.1.1. RagSearchService のテスト

**ファイル**: `tests/Feature/RagSearchServiceTest.php`

**追加テストケース**:

```php
#[Test]
public function search_attaches_semantic_scores_to_ledgers()
{
    // セットアップ
    $user = User::factory()->create();
    $ledger = Ledger::factory()->create();
    
    // モック設定
    $this->mock(EmbeddingService::class)
        ->shouldReceive('embed')
        ->andReturn(array_fill(0, 1024, 0.1));
    
    // 実行
    $results = $this->service->search(
        query: 'test query',
        user: $user,
        perPage: 10
    );
    
    // 検証
    $firstLedger = $results->first();
    $this->assertNotNull($firstLedger);
    $this->assertObjectHasProperty('semantic_score', $firstLedger);
    $this->assertIsFloat($firstLedger->semantic_score);
    $this->assertGreaterThanOrEqual(0, $firstLedger->semantic_score);
    $this->assertLessThanOrEqual(1, $firstLedger->semantic_score);
}
```

#### 4.1.2. RecordsTable のテスト

**ファイル**: `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`

**追加テストケース**:

```php
#[Test]
public function useSemanticSearch_is_disabled_when_search_is_empty()
{
    Livewire::test(RecordsTable::class)
        ->set('search', '')
        ->assertSet('useSemanticSearch', false)
        ->call('set', 'useSemanticSearch', true)
        ->assertSet('useSemanticSearch', false); // 自動的にOFFになる
}

#[Test]
public function useSemanticSearch_is_automatically_disabled_when_search_cleared()
{
    Livewire::test(RecordsTable::class)
        ->set('search', 'test query')
        ->set('useSemanticSearch', true)
        ->assertSet('useSemanticSearch', true)
        ->set('search', '') // 検索語をクリア
        ->assertSet('useSemanticSearch', false); // 自動的にOFF
}

#[Test]
public function semantic_search_mode_supports_different_sort_orders()
{
    $this->mock(\App\Services\RagSearchService::class)
        ->shouldReceive('searchLedgers')
        ->andReturn([
            ['ledger_id' => 1, 'max_score' => 0.9],
            ['ledger_id' => 2, 'max_score' => 0.8],
        ]);
    
    $ledger1 = Ledger::factory()->create(['id' => 1, 'created_at' => now()]);
    $ledger2 = Ledger::factory()->create(['id' => 2, 'created_at' => now()->subDay()]);
    
    // created_at 降順でソート
    Livewire::test(RecordsTable::class)
        ->set('search', 'test')
        ->set('useSemanticSearch', true)
        ->set('orderBy', 'created_at')
        ->set('orderAsc', false)
        ->assertOk();
    
    // TODO: 結果の順序を検証（ledger1が先に来るべき）
}

#[Test]
public function semantic_score_sort_option_appears_only_when_semantic_search_enabled()
{
    Livewire::test(RecordsTable::class)
        ->set('search', 'test')
        ->set('useSemanticSearch', false)
        ->assertDontSee(__('ledger.semantic_score_sort'))
        ->set('useSemanticSearch', true)
        ->assertSee(__('ledger.semantic_score_sort'));
}

#[Test]
public function semantic_search_results_display_semantic_scores()
{
    $this->mock(\App\Services\RagSearchService::class)
        ->shouldReceive('searchLedgers')
        ->andReturn([
            ['ledger_id' => 1, 'max_score' => 0.95],
        ]);
    
    $ledger = Ledger::factory()->create(['id' => 1]);
    
    Livewire::test(RecordsTable::class)
        ->set('search', 'test')
        ->set('useSemanticSearch', true)
        ->assertSee('95.0%') // 0.95 * 100
        ->assertSee('fa-brain'); // セマンティックスコアアイコン
}
```

### 4.2. 統合テスト

#### 4.2.1. E2Eシナリオテスト

**テストシナリオ**:

1. **基本フロー**:
   - 検索語を入力 → セマンティック検索トグルON → 結果が表示される
   - セマンティックスコアバッジ（🧠アイコン）が表示される
   
2. **並び順の切り替え**:
   - セマンティック検索ON + `created_at`順 → 作成日時順に並ぶ
   - セマンティック検索ON + `semantic_score`順 → 意味スコア順に並ぶ
   
3. **エッジケース**:
   - 検索語なしでトグルをONにしようとする → 無効化される
   - セマンティック検索中に検索語をクリア → 自動的にOFF

### 4.3. パフォーマンステスト

**検証項目**:

- セマンティック検索モードで1000件の候補から並び替え・ページネーション → 2秒以内
- 通常検索モードとの速度比較 → 許容範囲（2倍以内）

---

## 5. 実装WBS

| ID | タスク | 見積工数 | 優先度 | 備考 |
|----|-------|---------|-------|------|
| **Phase 1** | **RagSearchService改修** | **2.5h** | 🔴 高 | |
| 1.1 | `search()`メソッドのスコア付与実装 | 1.0h | 🔴 | 行73-80修正 |
| 1.2 | 既存テストの修正とアサーション追加 | 1.0h | 🔴 | |
| 1.3 | 動作確認（Tinker/手動テスト） | 0.5h | 🔴 | |
| **Phase 2** | **RecordsTable改修** | **5.0h** | 🔴 高 | |
| 2.1 | プロパティ追加（`useSemanticSearch`） | 0.5h | 🔴 | |
| 2.2 | `render()`メソッドの全面書き換え | 2.0h | 🔴 | 中核ロジック |
| 2.3 | `applySorting()`メソッドの新規作成 | 1.0h | 🔴 | |
| 2.4 | ライフサイクルフック追加 | 0.5h | 🔴 | |
| 2.5 | `getStandardSortLabel()`更新 | 0.5h | 🟡 | |
| 2.6 | 既存ロジックのクリーンアップ | 0.5h | 🟡 | |
| **Phase 3** | **UI改修** | **2.0h** | 🔴 高 | |
| 3.1 | トグルスイッチの追加 | 0.5h | 🔴 | `search.blade.php` |
| 3.2 | ソート順セレクトの修正 | 0.5h | 🔴 | |
| 3.3 | スタイル調整（レイアウト確認） | 0.5h | 🟡 | |
| 3.4 | レスポンシブ対応確認 | 0.5h | 🟡 | |
| **Phase 4** | **スコア表示拡張** | **2.0h** | 🔴 高 | |
| 4.1 | `table-row.blade.php`のロジック修正 | 1.0h | 🔴 | 行77-92 |
| 4.2 | アイコン・色分けの調整 | 0.5h | 🟡 | |
| 4.3 | ツールチップの追加 | 0.5h | 🟡 | |
| **Phase 5** | **翻訳追加** | **1.0h** | 🟡 中 | |
| 5.1 | 日本語翻訳キーの追加 | 0.5h | 🟡 | `lang/ja/ledger.php` |
| 5.2 | 英語翻訳の確認（必要に応じて） | 0.5h | 🟢 | |
| **Phase 6** | **テスト実装** | **4.0h** | 🔴 高 | |
| 6.1 | RagSearchServiceのテスト追加 | 1.0h | 🔴 | |
| 6.2 | RecordsTableのテスト追加 | 2.0h | 🔴 | 5つの新規テスト |
| 6.3 | 統合テスト・E2Eシナリオ確認 | 1.0h | 🟡 | |
| **Phase 7** | **ドキュメント更新** | **1.0h** | 🟡 中 | |
| 7.1 | 本計画書を「完了」に更新 | 0.5h | 🟡 | |
| 7.2 | `.github/copilot-instructions.md`更新 | 0.5h | 🟡 | |

**合計見積工数: 17.5時間**（v1.0の5.5時間から大幅増加）

---

## 6. リスクと対策

### 6.1. 技術的リスク

| リスク | 発生確率 | 影響度 | 対策 |
|-------|---------|-------|------|
| 大量データ取得によるメモリ不足 | 🟡 中 | 🔴 高 | `searchLedgers()`の`limit`を調整可能に |
| ソート処理のパフォーマンス劣化 | 🟡 中 | 🟡 中 | ベンチマークテストで計測、必要に応じてキャッシュ導入 |
| 既存テストの大量失敗 | 🔴 高 | 🟡 中 | Phase 2.6で既存ロジッククリーンアップ時に対応 |
| `semantic_score`属性の永続化誤認 | 🟢 低 | 🟡 中 | ドキュメントに「動的属性」と明記 |

### 6.2. UI/UXリスク

| リスク | 発生確率 | 影響度 | 対策 |
|-------|---------|-------|------|
| ユーザーがトグルの意味を理解しない | 🟡 中 | 🟡 中 | ツールチップ・ヒントテキストの充実 |
| スコア表示の混乱（総合 vs 意味） | 🟡 中 | 🟡 中 | アイコンと色で明確に区別 |
| レスポンシブ対応の崩れ | 🟢 低 | 🟡 中 | Phase 3.4で確認 |

---

## 7. 期待される効果

### 7.1. 定量的効果

- **柔軟性の向上**: 検索方法×並び順 = 8通りの組み合わせが可能に（従来は4通り）
- **コード品質**: 関心の分離により、`render()`メソッドの複雑度が低下
- **保守性**: 新しい並び順の追加が`applySorting()`メソッドへの追加のみで完結

### 7.2. 定性的効果

- **UX向上**: 「意味的に関連する台帳を日付順で見たい」といった直感的なニーズに対応
- **理解しやすさ**: 検索方法（トグル）と並び順（セレクト）が視覚的に分離
- **拡張性**: 将来的に「ハイブリッド検索」（セマンティック+全文検索）への拡張が容易

---

## 8. 付録

### 8.1. 用語集

| 用語 | 説明 |
|-----|------|
| セマンティック検索 | ベクトル埋め込みを用いた意味的類似度検索 |
| composite_score | 活動度・新鮮度・ワークフローステータスから算出される総合スコア |
| semantic_score | RAG検索によるコサイン類似度スコア（0.0-1.0） |
| useSemanticSearch | セマンティック検索モードのON/OFFを管理するLivewireプロパティ |

### 8.2. 参考実装パターン

**Strategy Pattern適用の検討**:

将来的に検索戦略を切り替える場合、以下のような抽象化が可能：

```php
interface SearchStrategyInterface
{
    public function search(array $params): Collection;
}

class SemanticSearchStrategy implements SearchStrategyInterface { /* ... */ }
class FullTextSearchStrategy implements SearchStrategyInterface { /* ... */ }

// RecordsTable.php
$strategy = $this->useSemanticSearch 
    ? new SemanticSearchStrategy() 
    : new FullTextSearchStrategy();
$results = $strategy->search([/* ... */]);
```

ただし、**現時点では過剰設計となるため、v2.0では採用しない**。将来的な検索方法の追加（例: ハイブリッド検索）が確定した際に検討する。

---

## 9. 実装開始の承認

- [ ] プロジェクトオーナー承認
- [ ] 技術レビュー完了
- [ ] 見積工数の承認
- [ ] リスク対策の承認

**承認者**: _________________  
**承認日**: _________________

---

**END OF DOCUMENT**
