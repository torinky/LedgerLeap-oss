# 台帳テーブルUI モダナイゼーション計画

**作成日:** 2025年10月12日  
**最終更新:** 2025年10月12日  
**対象:** LedgerLeap開発チーム  
**関連ドキュメント:**
- [検索結果スコアリング実装計画](../architecture/database/2025-10-08_search-result-scoring-and-sorting-plan.md)
- [RecordsTable.php](../../../app/Livewire/Ledger/RecordsTable.php)
- [table-row.blade.php](../../../resources/views/components/ledger/table-row.blade.php)
- [table-header.blade.php](../../../resources/views/components/ledger/table-header.blade.php)

---

## 📋 概要

### 1. 目的

現在の台帳テーブルUIにおいて、以下の課題を解決し、モダンで効率的なデータテーブルUIを実現する：

1. **カラム幅の最適化:** 編集・詳細ボタン専用カラムが画面を圧迫している
2. **補助情報の整理:** スコア列とワークフローステータス列を控えめに配置
3. **業務データの可視性向上:** 本来の台帳データ列を最大化
4. **ソート・フィルタUIの統合:** カラムヘッダーから独立した統合ツールバーの導入

### 2. 背景

Phase 1でスコアリング機能を実装した結果、以下の新たなUI課題が顕在化：

- スコア列が2列目（ID列の隣）に配置され目立ちすぎる
- 編集・詳細ボタン用のカラムが縦並びで幅を占有（約80-100px）
- ワークフローステータスは適切に後方配置されているが統一感がない
- カラムヘッダーにソートボタンとフィルタ入力欄が混在し複雑化
- カラムを削除・移動するとソート・フィルタUIも消失する問題

### 3. 設計思想

**モダンなデータテーブルUIの原則:**

1. **データファースト:** 業務データ列を最優先で表示
2. **コンテキスト情報は控えめに:** スコア、ステータス、アクションは必要時のみ表示
3. **操作の集約:** ソート・フィルタ機能を統合ツールバーに集約
4. **プログレッシブディスクロージャー:** ホバー/タップで詳細情報・アクションを表示

**参考実装:** Gmail, Notion, Linear, Asana, Airtable

---

## 🎯 改善案の比較検討

### 案1: ホバーオーバーレイ方式（推奨 ★★★★★）

**概要:**  
行ホバー時に右側へアクションボタン、スコア、ステータスをオーバーレイ表示

```html
<tr class="group relative hover:bg-accent/10">
    <!-- 業務データ列のみ -->
    <td>データ</td>
    
    <!-- ホバー時に表示 -->
    <div class="absolute right-2 opacity-0 group-hover:opacity-100 
                bg-base-100 shadow-xl rounded-lg p-2 flex gap-2">
        <span class="badge">スコア: 85</span>
        <span class="badge">承認待</span>
        <a class="btn btn-xs"><i class="fas fa-pencil"></i></a>
        <a class="btn btn-xs"><i class="fas fa-table-list"></i></a>
    </div>
</tr>
```

**レイアウト変更:**
```
[変更前]
| 編集詳細 | ID | スコア | データ1 | データ2 | ... | 更新日時 | ステータス |

[変更後]
| ID | データ1 | データ2 | データ3 | ... | 更新日時 |
                                    ↑ ホバーで [スコア][ステータス][編集][詳細] 表示
```

**メリット:**
- ✅ カラム幅完全削減（アクション列・スコア列削除）
- ✅ 業務データ列を最大化
- ✅ モダンなUX（Gmail、Notion等で採用）
- ✅ 既存レイアウト変更最小
- ✅ 必要な時だけ表示される

**デメリット:**
- ⚠️ モバイル/タッチデバイスで工夫必要
- ⚠️ ホバーに気づかないユーザーも存在
- ⚠️ スコア・ステータスのソートUIを別途実装必要

**採用例:** Gmail, Notion, Linear, Asana

---

### 案2: 行末固定アクション列（最小幅）

**概要:**  
アクション列を行末に配置し、最小幅（80px）に固定

```html
<th class="w-20 sticky right-0 bg-base-100">
    <div class="flex gap-1">
        <a class="btn btn-xs btn-square"><i class="fas fa-pencil"></i></a>
        <a class="btn btn-xs btn-square"><i class="fas fa-table-list"></i></a>
    </div>
</th>
```

**メリット:**
- ✅ 常に表示で分かりやすい
- ✅ モバイルでも使いやすい
- ✅ `sticky right-0`で横スクロール時も固定可能
- ✅ 実装が容易

**デメリット:**
- ⚠️ 最小でも80-100px必要
- ⚠️ ボタンアイコンのみだと分かりにくい場合も
- ⚠️ スコア・ステータス列は依然として別カラムが必要

**採用例:** Jira, Trello, Airtable

---

### 案3: 行クリックで詳細、アイコンで編集

**概要:**  
行全体をクリッカブルにし、詳細画面へ遷移。編集のみアイコン表示

```html
<tr class="cursor-pointer hover:bg-accent/10" 
    onclick="window.open('詳細URL')">
    <td class="w-10" onclick="event.stopPropagation()">
        <a class="btn btn-xs"><i class="fas fa-pencil"></i></a>
    </td>
    <!-- 他の列 -->
</tr>
```

**メリット:**
- ✅ カラム幅最小（40-50px）
- ✅ 行全体がクリッカブルで効率的
- ✅ 編集は明示的アイコン

**デメリット:**
- ⚠️ 行クリックの挙動が直感的でない場合も
- ⚠️ テキスト選択がしにくい
- ⚠️ 詳細ボタンの存在意義が薄れる

**採用例:** GitHub Issues, Slack, Discord

---

### 案4: ドロップダウンメニュー（3点リーダー）

**概要:**  
3点リーダーメニューからアクション選択

```html
<td class="w-12">
    <div class="dropdown dropdown-end">
        <label class="btn btn-ghost btn-xs btn-circle">
            <i class="fas fa-ellipsis-vertical"></i>
        </label>
        <ul class="dropdown-content menu">
            <li><a><i class="fas fa-pencil"></i> 編集</a></li>
            <li><a><i class="fas fa-table-list"></i> 詳細</a></li>
        </ul>
    </div>
</td>
```

**メリット:**
- ✅ 最小幅（48px）
- ✅ アクション追加が容易
- ✅ 整理された印象

**デメリット:**
- ⚠️ 1クリック余分に必要
- ⚠️ よく使うアクションへのアクセスが遅い
- ⚠️ ユーザーの学習コスト

**採用例:** Google Drive, Dropbox, Basecamp

---

## 🎨 ソート・フィルタUI統合案

### 課題

カラムレス設計（案1採用時）では、スコア・ステータス列のソート・フィルタUIが消失する問題が発生。

### 解決策: 統合ツールバー方式（推奨）

検索バーの横に専用のソート・フィルタコントロールを配置

**現在の実装:**
```
┌─────────────────────────────────────────┐
│ [検索バー                            ]  │
│ [昇順/降順] [シノニム] [専門用語]       │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│ テーブル                                │
│ ├ ヘッダー（各カラムにソートボタン）    │
│ └ 各カラムヘッダーにフィルタ入力欄      │
└─────────────────────────────────────────┘
```

**改善後:**
```
┌───────────────────────────────────────────────────────────┐
│ [検索バー] [ソート▼] [↕] [🔍フィルタ(2)] [表示件数▼]   │ ← 統合ツールバー
│ ┌ アクティブ: [スコア≥50 ×] [ステータス:承認待 ×]      │ ← フィルタバッジ
└───────────────────────────────────────────────────────────┘
┌───────────────────────────────────────────────────────────┐
│ ▼ フィルタパネル（展開時）                                │
│   [スコア: 0 〜 100]  [ステータス: すべて▼]              │
│   [更新日: 2025-10-01〜]  [カラムA: フィルタ...]         │
│   [カラムB: フィルタ...]  [クリア]                       │
└───────────────────────────────────────────────────────────┘
┌───────────────────────────────────────────────────────────┐
│ テーブル（ヘッダーはシンプルにカラム名のみ）              │
│ | ID | カラムA | カラムB | カラムC | 更新日時 |          │
└───────────────────────────────────────────────────────────┘
```

**メリット:**
- ✅ すべてのソート・フィルタが一箇所に集約
- ✅ テーブルヘッダーがシンプルに（カラム名のみ）
- ✅ モバイルでも使いやすい
- ✅ アクティブフィルタが一目瞭然
- ✅ スコア・ステータス等の非カラム項目もソート・フィルタ可能

**デメリット:**
- ⚠️ カラムクリックでソートできない（慣れが必要）
- ⚠️ 実装工数が大きい

**採用例:** Notion, Airtable, Linear, Monday.com

---

## 🏆 推奨実装プラン

### 最終推奨: 案1（ホバーオーバーレイ）+ 統合ツールバー

**理由:**

1. **業務データの可視性を最大化:** カラム幅を完全削減
2. **モダンなUX:** 主要SaaSで採用されている実績
3. **拡張性:** 将来的なアクション追加も容易
4. **ソート・フィルタの統合:** 一箇所で全操作が完結

---

## 📐 実装計画

### Phase 1: 統合ツールバーの実装

**目的:** ソート・フィルタ機能を統合ツールバーに集約

#### Step 1.1: RecordsTable.php の拡張

**作業内容:**

1. **新規プロパティの追加:**
   ```php
   // スコアフィルタ
   public $filterScoreMin = 0;
   public $filterScoreMax = 100;
   
   // ステータスフィルタ
   public $filterStatus = '';
   
   // 日付フィルタ
   public $filterUpdatedAfter = '';
   ```

2. **ヘルパーメソッドの追加:**
   ```php
   public function toggleSortDirection()
   {
       $this->orderAsc = !$this->orderAsc;
   }
   
   public function clearFilters()
   {
       $this->filter = [];
       $this->filterScoreMin = 0;
       $this->filterScoreMax = 100;
       $this->filterStatus = '';
       $this->filterUpdatedAfter = '';
   }
   
   public function removeFilter($key)
   {
       unset($this->filter[$key]);
   }
   
   public function getSortLabel($orderBy)
   {
       return match($orderBy) {
           'composite_score' => __('ledger.scoring.composite_score'),
           'updated_at' => __('ledger.updated_at'),
           'id' => 'ID',
           'status' => __('ledger.workflow.status.label'),
           default => $this->getColumnName(str_replace('content->', '', $orderBy))
       };
   }
   ```

3. **render()メソッドでのフィルタ適用:**
   ```php
   $ledgerRecords = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
       ->searchContext($this->searchContext)
       ->contentsFilter($this->filter)
       ->when($this->filterScoreMin > 0, function ($query) {
           return $query->where('composite_score', '>=', $this->filterScoreMin);
       })
       ->when($this->filterScoreMax < 100, function ($query) {
           return $query->where('composite_score', '<=', $this->filterScoreMax);
       })
       ->when(!empty($this->filterStatus), function ($query) {
           return $query->where('status', $this->filterStatus);
       })
       ->when(!empty($this->filterUpdatedAfter), function ($query) {
           return $query->whereDate('updated_at', '>=', $this->filterUpdatedAfter);
       })
       // ... 既存のorderBy処理
   ```

**成功基準:**
- 新規プロパティ・メソッドが正常動作
- 既存テストがパス
- フィルタ条件が正しくクエリに適用される

---

#### Step 1.2: 統合ツールバーコンポーネントの作成

**作業内容:**

1. **新規Bladeコンポーネント作成:**
   - `resources/views/components/ledger/toolbar.blade.php`

2. **ツールバーの基本構造実装:**
   ```blade
   <div x-data="{ showFilters: false }" 
        class="sticky top-20 z-40 bg-base-100/95 backdrop-blur-sm shadow-sm p-4 space-y-3">
       
       <!-- メインツールバー -->
       <div class="flex flex-wrap gap-2 items-center">
           <!-- 検索バー -->
           <div class="flex-1 min-w-64">
               <input wire:model.change="search" type="search"
                      class="input input-bordered w-full"
                      placeholder="🔍 {{ __('ledger.search_message') }}">
           </div>
           
           <!-- ソート選択 -->
           <select wire:model.live="orderBy" class="select select-bordered w-48">
               <option value="composite_score">{{ __('ledger.scoring.composite_score') }}</option>
               <option value="updated_at">{{ __('ledger.updated_at') }}</option>
               <option value="id">ID</option>
               @if($hasWorkflowEnabled)
                   <option value="status">{{ __('ledger.workflow.status.label') }}</option>
               @endif
           </select>
           
           <!-- 昇順/降順ボタン -->
           <button wire:click="toggleSortDirection" 
                   class="btn btn-square btn-outline tooltip"
                   data-tip="{{ $orderAsc ? __('ledger.ascending') : __('ledger.descending') }}">
               <i class="fas fa-sort-{{ $orderAsc ? 'up' : 'down' }}"></i>
           </button>
           
           <!-- フィルタトグルボタン -->
           <button @click="showFilters = !showFilters" 
                   class="btn btn-outline gap-2"
                   :class="{ 'btn-active': showFilters }">
               <i class="fas fa-filter"></i>
               {{ __('ledger.filter') }}
               <!-- フィルタ件数バッジ -->
               @if(count($filter) > 0 || $filterScoreMin > 0 || $filterScoreMax < 100 || !empty($filterStatus))
                   <span class="badge badge-primary badge-sm">
                       {{ count($filter) + ($filterScoreMin > 0 ? 1 : 0) + ($filterScoreMax < 100 ? 1 : 0) + (!empty($filterStatus) ? 1 : 0) }}
                   </span>
               @endif
           </button>
           
           <!-- 表示件数 -->
           <select wire:model.live="perPage" class="select select-bordered select-sm w-24">
               <option>10</option>
               <option>25</option>
               <option>50</option>
               <option>100</option>
           </select>
       </div>
       
       <!-- 展開可能なフィルタパネル -->
       <div x-show="showFilters" x-collapse class="card bg-base-200">
           <div class="card-body p-4">
               <h3 class="font-bold mb-3">{{ __('ledger.filters') }}</h3>
               
               <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                   <!-- スコアフィルタ -->
                   <div class="form-control">
                       <label class="label">
                           <span class="label-text">{{ __('ledger.scoring.composite_score') }}</span>
                       </label>
                       <div class="flex gap-2 items-center">
                           <input wire:model.live="filterScoreMin" 
                                  type="number" min="0" max="100"
                                  class="input input-sm input-bordered w-20"
                                  placeholder="0">
                           <span>〜</span>
                           <input wire:model.live="filterScoreMax" 
                                  type="number" min="0" max="100"
                                  class="input input-sm input-bordered w-20"
                                  placeholder="100">
                       </div>
                   </div>
                   
                   <!-- ステータスフィルタ -->
                   @if($hasWorkflowEnabled)
                   <div class="form-control">
                       <label class="label">
                           <span class="label-text">{{ __('ledger.workflow.status.label') }}</span>
                       </label>
                       <select wire:model.live="filterStatus" 
                               class="select select-sm select-bordered">
                           <option value="">{{ __('ledger.all') }}</option>
                           @foreach(\App\Enums\LedgerStatus::cases() as $status)
                               <option value="{{ $status->value }}">{{ $status->label() }}</option>
                           @endforeach
                       </select>
                   </div>
                   @endif
                   
                   <!-- 更新日フィルタ -->
                   <div class="form-control">
                       <label class="label">
                           <span class="label-text">{{ __('ledger.updated_after') }}</span>
                       </label>
                       <input wire:model.live="filterUpdatedAfter" 
                              type="date"
                              class="input input-sm input-bordered">
                   </div>
               </div>
               
               <div class="card-actions justify-end mt-4">
                   <button wire:click="clearFilters" class="btn btn-sm btn-ghost gap-2">
                       <i class="fas fa-times"></i>
                       {{ __('ledger.clear_filters') }}
                   </button>
               </div>
           </div>
       </div>
       
       <!-- アクティブフィルタバッジ -->
       @if(count($filter) > 0 || $filterScoreMin > 0 || $filterScoreMax < 100 || !empty($filterStatus) || !empty($filterUpdatedAfter))
           <div class="flex flex-wrap gap-2">
               <span class="text-xs text-base-content/70">{{ __('ledger.active_filters') }}:</span>
               
               @if($filterScoreMin > 0 || $filterScoreMax < 100)
                   <span class="badge badge-primary gap-1">
                       {{ __('ledger.scoring.composite_score') }}: {{ $filterScoreMin }}〜{{ $filterScoreMax }}
                       <button wire:click="$set('filterScoreMin', 0); $set('filterScoreMax', 100)" 
                               class="btn btn-xs btn-ghost btn-circle">×</button>
                   </span>
               @endif
               
               @if(!empty($filterStatus))
                   <span class="badge badge-primary gap-1">
                       {{ __('ledger.workflow.status.label') }}: {{ \App\Enums\LedgerStatus::from($filterStatus)->label() }}
                       <button wire:click="$set('filterStatus', '')" 
                               class="btn btn-xs btn-ghost btn-circle">×</button>
                   </span>
               @endif
               
               @if(!empty($filterUpdatedAfter))
                   <span class="badge badge-primary gap-1">
                       {{ __('ledger.updated_after') }}: {{ $filterUpdatedAfter }}
                       <button wire:click="$set('filterUpdatedAfter', '')" 
                               class="btn btn-xs btn-ghost btn-circle">×</button>
                   </span>
               @endif
           </div>
       @endif
   </div>
   ```

3. **records-table.blade.phpへの統合:**
   - 既存の`<x-ledger.search/>`を`<x-ledger.toolbar/>`に置き換え

**成功基準:**
- ツールバーが正しく表示される
- ソート選択が動作する
- フィルタパネルの開閉が動作する
- アクティブフィルタバッジが表示される

---

#### Step 1.3: 多言語対応

**作業内容:**

1. **言語ファイルへの追加:**
   - `lang/ja/ledger.php`に以下を追加:
     ```php
     'filter' => 'フィルタ',
     'filters' => 'フィルタ設定',
     'all' => 'すべて',
     'updated_after' => '更新日以降',
     'active_filters' => '有効なフィルタ',
     'clear_filters' => 'フィルタをクリア',
     ```

**成功基準:**
- すべてのUI文言が多言語化されている

---

#### Step 1.4: テストの実装

**作業内容:**

1. **フィーチャーテストの作成:**
   - `tests/Feature/Livewire/Ledger/RecordsTableToolbarTest.php`

   ```php
   <?php
   
   namespace Tests\Feature\Livewire\Ledger;
   
   use Tests\TestCase;
   use Livewire\Livewire;
   use App\Models\Ledger;
   use App\Models\LedgerDefine;
   use App\Enums\LedgerStatus;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   
   class RecordsTableToolbarTest extends TestCase
   {
       use RefreshDatabase;
       
       public function test_score_filter_works()
       {
           $ledgerDefine = LedgerDefine::factory()->create();
           Ledger::factory()->create([
               'ledger_define_id' => $ledgerDefine->id,
               'composite_score' => 85
           ]);
           Ledger::factory()->create([
               'ledger_define_id' => $ledgerDefine->id,
               'composite_score' => 45
           ]);
           
           Livewire::test(RecordsTable::class)
               ->set('filterScoreMin', 50)
               ->assertSee('85')
               ->assertDontSee('45');
       }
       
       public function test_status_filter_works()
       {
           $ledgerDefine = LedgerDefine::factory()->create(['workflow_enabled' => true]);
           Ledger::factory()->create([
               'ledger_define_id' => $ledgerDefine->id,
               'status' => LedgerStatus::PENDING_APPROVAL
           ]);
           Ledger::factory()->create([
               'ledger_define_id' => $ledgerDefine->id,
               'status' => LedgerStatus::APPROVED
           ]);
           
           Livewire::test(RecordsTable::class)
               ->set('filterStatus', LedgerStatus::PENDING_APPROVAL->value)
               ->assertSee(LedgerStatus::PENDING_APPROVAL->label())
               ->assertDontSee(LedgerStatus::APPROVED->label());
       }
       
       public function test_clear_filters_works()
       {
           Livewire::test(RecordsTable::class)
               ->set('filterScoreMin', 50)
               ->set('filterStatus', 'pending')
               ->call('clearFilters')
               ->assertSet('filterScoreMin', 0)
               ->assertSet('filterScoreMax', 100)
               ->assertSet('filterStatus', '');
       }
       
       public function test_sort_toggle_works()
       {
           Livewire::test(RecordsTable::class)
               ->assertSet('orderAsc', false)
               ->call('toggleSortDirection')
               ->assertSet('orderAsc', true);
       }
   }
   ```

**成功基準:**
- すべてのテストがパス
- 既存のRecordsTableテストも引き続きパス

---

### Phase 2: ホバーオーバーレイアクションの実装

**目的:** アクション列とスコア・ステータス列を削除し、ホバーオーバーレイで表示

#### Step 2.1: table-header.blade.php の簡素化

**作業内容:**

1. **ヘッダーからの削除:**
   - アクション列（編集・詳細ボタン列）を削除
   - スコア列を削除
   - ステータス列を削除
   - 各カラムのフィルタ入力欄を削除
   - ソートボタンを削除

2. **シンプルな構造への変更:**
   ```blade
   <tr>
       <th class="px-4 py-2 text-center bg-accent/30 w-12">ID</th>
       
       @foreach($filteredColumnDefines as $column)
           <th class="px-4 py-2 bg-accent/30">
               <span class="font-bold">{{ $column->name }}</span>
           </th>
       @endforeach
       
       <th class="px-4 py-2 text-center bg-accent/30 w-32">{{ __('ledger.updated_at') }}</th>
   </tr>
   ```

**成功基準:**
- テーブルヘッダーがシンプルになる
- カラム名のみが表示される

---

#### Step 2.2: table-row.blade.php のホバーオーバーレイ化

**作業内容:**

1. **行構造の変更:**
   ```blade
   <tr class="group relative hover:bg-accent/10 transition-colors">
       <!-- ID -->
       <td class="px-4 py-2 text-center border">
           {{ $ledgerRecord->id }}
       </td>
       
       <!-- 業務データ列 -->
       @foreach($filteredColumnDefines as $columnDefine)
           <td class="border px-4 py-2">
               <!-- 既存のデータ表示ロジック -->
           </td>
       @endforeach
       
       <!-- 更新日時 -->
       <td class="border px-4 py-2">
           <div class="text-sm">
               {{ $ledgerRecord->updated_at->format('Y-m-d H:i') }}
               <div class="text-xs text-base-content/50">
                   {{ $ledgerRecord->updated_at->diffForHumans() }}
               </div>
           </div>
       </td>
       
       <!-- ホバーオーバーレイ: スコア・ステータス・アクション -->
       <div class="absolute right-2 top-1/2 -translate-y-1/2 
                   opacity-0 group-hover:opacity-100 
                   transition-all duration-200 ease-in-out
                   flex items-center gap-2 
                   bg-base-100 shadow-xl rounded-lg px-3 py-2 z-10
                   border border-base-300">
           
           <!-- スコアバッジ -->
           @if($ledgerRecord->composite_score > 0)
               @php
                   $scoreClass = match(true) {
                       $ledgerRecord->composite_score >= 70 => 'badge-success',
                       $ledgerRecord->composite_score >= 40 => 'badge-primary',
                       $ledgerRecord->composite_score >= 20 => 'badge-info',
                       $ledgerRecord->composite_score > 0 => 'badge-ghost',
                       default => ''
                   };
               @endphp
               <span class="badge badge-sm {{ $scoreClass }} gap-1 tooltip" 
                     data-tip="{{ __('ledger.scoring.composite_score') }}">
                   <i class="fas fa-star text-xs"></i>
                   {{ number_format($ledgerRecord->composite_score, 0) }}
               </span>
           @endif
           
           <!-- ステータスバッジ -->
           @if($ledgerRecord->define->workflow_enabled && $ledgerRecord->status)
               <x-mary-badge :value="$ledgerRecord->status->label()" 
                             class="badge-xs {{ $ledgerRecord->status->colorClass() }}"/>
           @endif
           
           <div class="divider divider-horizontal m-0"></div>
           
           <!-- 編集ボタン -->
           @if($canUpdate && !$ledgerRecord->isLocked())
               <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id]) }}"
                  class="btn btn-xs btn-square btn-ghost tooltip"
                  data-tip="{{ __('ledger.edit') }}"
                  target="ledgerEdit_{{$ledgerRecord->define->id}}">
                   <i class="fas fa-pencil"></i>
               </a>
           @else
               <button class="btn btn-xs btn-square btn-ghost opacity-30 tooltip"
                       data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}"
                       disabled>
                   <i class="fas fa-pencil"></i>
               </button>
           @endif
           
           <!-- 詳細ボタン -->
           <a href="{{ route('ledger.show', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id, 'highlight' => $highlightKeyword]) }}"
              class="btn btn-xs btn-square btn-ghost tooltip"
              data-tip="{{ __('ledger.show_details') }}"
              target="ledgerShow_{{$ledgerRecord->define->id}}">
               <i class="fas fa-table-list"></i>
           </a>
       </div>
   </tr>
   ```

**成功基準:**
- 行ホバー時にオーバーレイが表示される
- スコア・ステータス・アクションボタンが正しく表示される
- 編集・詳細リンクが機能する

---

#### Step 2.3: モバイル対応

**作業内容:**

1. **タッチデバイス用のトリガー追加:**
   ```blade
   <tr x-data="{ showActions: false }" 
       @click.away="showActions = false"
       class="group relative hover:bg-accent/10">
       
       <!-- データ列 -->
       
       <!-- モバイル用トリガー（画面幅が小さい時のみ表示） -->
       <td class="lg:hidden w-10 border" @click.stop="showActions = !showActions">
           <button class="btn btn-xs btn-ghost btn-circle">
               <i class="fas fa-ellipsis-vertical"></i>
           </button>
       </td>
       
       <!-- オーバーレイ（モバイルではx-show制御） -->
       <div class="... lg:opacity-0 lg:group-hover:opacity-100"
            x-show="showActions || false"
            @click.away="showActions = false">
           <!-- ボタン -->
       </div>
   </tr>
   ```

**成功基準:**
- デスクトップではホバーで表示
- モバイルでは3点リーダータップで表示
- 外側タップで閉じる

---

#### Step 2.4: テストの実装

**作業内容:**

1. **ビジュアルリグレッションテスト:**
   - 既存のRecordsTableテストが引き続きパス
   - 行のホバー状態が正しく動作

2. **アクセシビリティ確認:**
   - キーボードナビゲーションが機能
   - スクリーンリーダーで読み上げ可能

**成功基準:**
- すべての既存テストがパス
- 新機能が正しく動作

---

### Phase 3: パフォーマンス最適化（オプション）

**目的:** 大量データでのレンダリングパフォーマンス確保

#### Step 3.1: 遅延レンダリングの検討

**作業内容:**

1. **Intersection Observerの導入:**
   - 画面外の行は簡略表示
   - スクロール時に詳細レンダリング

2. **仮想スクロールの検討:**
   - 大量データ（1000件以上）の場合のみ適用

**成功基準:**
- 100件表示時のレンダリング時間が50ms以内
- スクロールがスムーズ

---

## 📊 実装スケジュール

| Phase | Step | 作業内容 | 見積工数 | 担当 | 期限 |
|-------|------|----------|----------|------|------|
| 1 | 1.1 | RecordsTable.php 拡張 | 0.5日 | - | - |
| 1 | 1.2 | 統合ツールバー作成 | 1.0日 | - | - |
| 1 | 1.3 | 多言語対応 | 0.3日 | - | - |
| 1 | 1.4 | テスト実装 | 0.5日 | - | - |
| **Phase 1 合計** | | | **2.3日** | | |
| 2 | 2.1 | ヘッダー簡素化 | 0.3日 | - | - |
| 2 | 2.2 | ホバーオーバーレイ実装 | 1.0日 | - | - |
| 2 | 2.3 | モバイル対応 | 0.5日 | - | - |
| 2 | 2.4 | テスト実装 | 0.3日 | - | - |
| **Phase 2 合計** | | | **2.1日** | | |
| 3 | 3.1 | パフォーマンス最適化 | 1.0日 | - | - |
| **Phase 3 合計** | | | **1.0日** | | |
| **総計** | | | **5.4日** | | |

---

## 🎯 成功指標

### Phase 1 完了時点

- ✅ 統合ツールバーが実装され、すべてのソート・フィルタが動作
- ✅ スコア・ステータスのフィルタが機能
- ✅ アクティブフィルタが視覚的に確認可能
- ✅ 既存テストがすべてパス

### Phase 2 完了時点

- ✅ アクション列・スコア列・ステータス列が削除され、カラム幅が拡大
- ✅ ホバー時にスコア・ステータス・アクションが表示
- ✅ モバイルでもアクセス可能
- ✅ ユーザビリティテストで問題なし

### Phase 3 完了時点

- ✅ 大量データ（100件以上）でもスムーズな動作
- ✅ レンダリング時間が目標値以内

---

## 🔍 リスクと対策

### リスク1: ユーザーがホバーに気づかない

**対策:**
- 初回ログイン時にツールチップやガイドを表示
- 行ホバー時に軽微なアニメーション効果
- フィードバック収集とUI改善

### リスク2: モバイルでの操作性低下

**対策:**
- 3点リーダーボタンを常時表示（モバイルのみ）
- タップ領域を十分に確保
- タッチデバイスでのユーザビリティテスト

### リスク3: 既存機能との互換性

**対策:**
- 段階的実装（Phase 1完了後に評価）
- 既存テストの継続的実行
- ロールバック計画の準備

### リスク4: パフォーマンス低下

**対策:**
- パフォーマンステストの実施
- 必要に応じて遅延レンダリング導入
- ページネーション設定の見直し

---

## 📝 実施記録

### 2025-10-12: ドキュメント作成

- ✅ 台帳テーブルUI改善計画を作成
- ✅ 4つの改善案を比較検討
- ✅ 統合ツールバー方式を設計
- ✅ 実装計画を3フェーズに分割

### 次回作業予定

- Phase 1 Step 1.1の着手
- RecordsTable.phpへの新規プロパティ追加

---

## 🔗 関連リソース

### 参考実装

- **Gmail:** ホバーオーバーレイアクション
- **Notion:** 統合ツールバー、フィルタパネル
- **Linear:** モダンなデータテーブルUI
- **Airtable:** カラム管理、フィルタ機能

### 技術ドキュメント

- [Tailwind CSS - Group Hover](https://tailwindcss.com/docs/hover-focus-and-other-states#styling-based-on-parent-state)
- [Alpine.js - x-collapse](https://alpinejs.dev/plugins/collapse)
- [DaisyUI - Dropdown](https://daisyui.com/components/dropdown/)
- [DaisyUI - Badge](https://daisyui.com/components/badge/)

---

## 📞 問い合わせ

このドキュメントに関する質問や提案は、開発チームまでお願いします。

**最終更新:** 2025年10月12日  
**ステータス:** 📝 計画段階  
**次のマイルストーン:** Phase 1 着手
