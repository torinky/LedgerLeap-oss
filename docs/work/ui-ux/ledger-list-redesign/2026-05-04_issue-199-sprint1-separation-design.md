# Issue #199 Sprint 1: 統計とグルーピングの分割単位・表示責務設計

## 日付
2026-05-04

## 目的
Issue #199「統計とグルーピングを子コンポーネントへ分割する」の Sprint 1「分割単位と表示責務を決める」を完了する。

---

## 1. 現状分析

### 1.1 `RecordsTable::render()` の処理構成

`RecordsTable::render()` (app/Livewire/Ledger/RecordsTable.php) は以下の処理を毎回実行している：

1. **検索コンテキスト初期化** (`initSearchContext`) — キーワード/タグ/フィルタの解決
2. **検索対象台帳定義IDの取得** — `LedgerDefine::searchTags()` で絞り込み
3. **台帳レコード取得** — 通常検索 or セマンティック検索（RAG）
4. **ページネーション** — `simplePaginate()` or `LengthAwarePaginator`
5. **台帳定義情報の取得・ロード** — `ledgerDefineRecords` + `folder.ancestors`
6. **パンくず構築** — `breadcrumbsPerLedgerDefine`
7. **添付ファイルのヒットマーク** — `content_attached` に `hit` フラグ付与
8. **表示列のフィルタリング** — `displayLevel` に応じた `column_define` フィルタ
9. **【対象】統計計算** — `scoreStatsByDefineId`（734-746行目）
10. **【対象】グルーピング** — `ledgerRecordsGroupByDefineIds`（749-770行目）
11. **ビュー返却** — `records-table.blade.php`

### 1.2 統計計算の詳細

```php
// RecordsTable::render() L.734-746
$scoreStatsByDefineId = $ledgerRecords->groupBy('ledger_define_id')->map(function ($records) {
    $scores = $records->pluck('composite_score')->filter(fn ($score) => $score > 0);
    return [
        'count' => $records->count(),
        'avg_score' => $scores->count() > 0 ? round($scores->avg(), 1) : 0,
        'max_score' => $scores->count() > 0 ? round($scores->max(), 1) : 0,
        'min_score' => $scores->count() > 0 ? round($scores->min(), 1) : 0,
        'has_scores' => $scores->count() > 0,
    ];
});
```

- **入力**: ページネーション後の `$ledgerRecords`（Collection or Paginator）
- **出力**: 台帳定義IDをキーとした統計情報配列
- **用途**: `ledgerDefine.header` コンポーネント内でスコア統計バッジとして表示（avg_score, max_score, count）
- **計算量**: O(n) — ページ内レコード数に比例

### 1.3 グルーピング計算の詳細

```php
// RecordsTable::render() L.749-770
$ledgerRecordsGroupByDefineIds = collect();
foreach ($ledgerRecords as $ledger) {
    $defineId = $ledger->ledger_define_id;
    if (! $ledgerRecordsGroupByDefineIds->has($defineId)) {
        $ledgerRecordsGroupByDefineIds->put($defineId, collect());
    }
    $ledgerRecordsGroupByDefineIds->get($defineId)->push($ledger);
}

// 検索時は平均スコアの降順でソート
if (! empty($this->search)) {
    $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortByDesc(function ($records, $defineId) use ($scoreStatsByDefineId) {
        return $scoreStatsByDefineId[$defineId]['avg_score'] ?? 0;
    });
} else {
    $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortKeys();
}
```

- **入力**: ページネーション後の `$ledgerRecords`
- **出力**: `ledger_define_id` をキーとした Collection of Collections
- **用途**: `records-table.blade.php` で `@foreach` ループし、台帳定義ごとのセクションを描画
- **依存**: 検索時のソート順序は `$scoreStatsByDefineId['avg_score']` に依存
- **計算量**: O(n log n) — グループ化 + ソート

### 1.4 ビューでの使用方法

`resources/views/livewire/ledger/records-table.blade.php`:

```blade
@foreach ($ledgerRecordsGroupByDefineIds as $ledgerDefineId => $ledgerDefineAndRecords)
    @php
        $ledgerDefine = $ledgerDefineRecordsKeyById[$ledgerDefineId] ?? null;
        // ...
    @endphp
    <div class="card ..." wire:key="ledger_record_{{ $ledgerDefineId }}">
        <div class="card-body pt-0 px-0">
            <x-ledgerDefine.header
                :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
                :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null"
                ... />
            <div class="overflow-x-auto max-h-[70vh]">
                <table ...>
                    <tbody>
                        @foreach ($ledgerDefineAndRecords as $ledgerRecordValues)
                            <x-ledger.table-row :ledgerRecord="$ledgerRecordValues" ... />
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach
```

---

## 2. 分割単位の決定

### 2.1 評価基準

| 基準 | 重み | 説明 |
|------|------|------|
| 再描画の独立性 | 高 | 子コンポーネントが独立して再描画できるか |
| データの流れの適切さ | 高 | 親→子へのデータ渡しが自然か |
| Lazy load の可否 | 中 | `#[Lazy]` / `wire:init` / `wire:intersect.once` が使えるか |
| 影響範囲の小ささ | 高 | 既存コードへの変更が最小限か |
| パフォーマンス効果 | 高 | 実際に再描画負荷が減るか |

### 2.2 分割候補の比較

| 候補 | 説明 | 利点 | 欠点 | 評価 |
|------|------|------|------|------|
| **A. セクション単位 Livewire 化** | 各台帳定義セクションを `LedgerDefineSection` Livewire コンポーネントに | 最も細かい再描画制御が可能 | Livewire コンポーネント数が多く hydrate オーバーヘッド大 | ⚠️ |
| **B. 統計を Livewire 化** | 統計バッジ部分を `LedgerDefineStats` Livewire に | 統計のみ独立して再描画 | データ依存が強く Lazy 化しにくい | ⚠️ |
| **C. 計算のサービス化 + Blade コンポーネント化** | 統計・グルーピングを Service に、表示を Blade コンポーネントに | render() の肥大化解消、責務分離明確 | Livewire 単位の再描画制御は不可 | ✅ **推奨** |
| **D. `wire:init` 遅延** | 統計計算を `wire:init` で非同期化 | 初期描画が軽くなる | 統計表示のちらつき、複雑さ増大 | △ |

### 2.3 採用する分割設計（推奨: 候補 C を基盤とし、拡張で A/B を検討）

#### Layer 1: 計算責務のサービス化

新規: `App\Services\Ledger\RecordsGroupingService`

```php
class RecordsGroupingService
{
    /**
     * レコードコレクションを台帳定義ごとにグループ化し、統計情報を付与する
     *
     * @param Collection|Paginator $ledgerRecords ページネーション後のレコード
     * @param bool $isSearchActive 検索実行中かどうか（ソート順序の決定に使用）
     * @return array {
     *     groups: Collection<int, Collection<Ledger>>,
     *     stats: array<int, array{count:int,avg_score:float,max_score:float,min_score:float,has_scores:bool}>
     * }
     */
    public function groupAndComputeStats($ledgerRecords, bool $isSearchActive = false): array;
}
```

- **責務**: `groupBy('ledger_define_id')` + 統計計算 + ソート順序の適用
- **テスタビリティ**: 高（純粋関数、DB アクセスなし）
- **再利用性**: 高（他の一覧表示でも使用可能）

#### Layer 2: 表示責務の Blade コンポーネント化

新規: `resources/views/components/ledger/records-section.blade.php`

入力パラメータ：
- `$ledgerDefineId: int`
- `$records: Collection<Ledger>`
- `$ledgerDefine: LedgerDefine`
- `$breadcrumbs: array`
- `$scoreStats: array|null`
- `$filteredColumnDefines: array`
- `$search: string`
- `$currentTenantId: int|string|null`
- `$canManage`, `$canCreate`, `$canView`, `$canUpdate: bool`
- `$selectedFileId`, `$selectedLedgerId`, `$selectedColumnId: int|null`

- **責務**: 1つの台帳定義セクション（ヘッダーカード + テーブル）を描画
- **現在の `<x-ledgerDefine.header>` との関係**: `records-section` 内で `<x-ledgerDefine.header>` を呼び出し、さらにテーブルを描画
- **利点**: `records-table.blade.php` の `@foreach` ループが簡潔化され、各セクションの表示責務がカプセル化される

#### Layer 3: `RecordsTable::render()` の簡潔化

```php
public function render()
{
    // ...（検索・ページネーション・DB取得は継続）...
    
    // 計算責務をサービスに委譲
    $groupingResult = app(RecordsGroupingService::class)
        ->groupAndComputeStats($ledgerRecords, !empty($this->search));
    
    return view('livewire.ledger.records-table', [
        'ledgerRecords' => $ledgerRecords,
        'ledgerDefineRecordsKeyById' => $ledgerDefineRecords,
        'groupingResult' => $groupingResult,
        // ...その他の変数...
    ]);
}
```

---

## 3. 表示責務の境界

### 3.1 分離後の責務マトリクス

| コンポーネント/クラス | DB アクセス | ビジネスロジック | プレゼンテーション | 備考 |
|----------------------|------------|----------------|------------------|------|
| `IndexManager` | ❌ | URL 状態管理 | ページシェル、ナビ | 親コンポーネント |
| `RecordsTable` | ✅ | 検索・ページネーション | 一覧フレーム | DB アクセス責務 |
| `RecordsGroupingService` | ❌ | ✅ 統計・グルーピング | ❌ | 純粋な計算サービス |
| `records-section.blade` | ❌ | ❌ | ✅ セクション描画 | Blade コンポーネント |
| `ledgerDefine.header` | ❌ | ❌ | ✅ ヘッダー描画 | 既存 Blade コンポーネント |
| `ledger.table-row` | ❌ | ❌ | ✅ 行描画 | 既存 Blade コンポーネント |

### 3.2 データフロー（分離後）

```
IndexManager.render()
  └─ index-manager.blade.php
       └─ livewire:ledger.records-table (Reactive props)
            └─ RecordsTable.render()
                 ├─ DB Query: Ledger 検索・ページネーション
                 ├─ DB Query: LedgerDefine + Folder 取得
                 ├─ Service: RecordsGroupingService::groupAndComputeStats()
                 │            ├─ groupBy(ledger_define_id)
                 │            ├─ compute scoreStats
                 │            └─ sort (avg_score desc or keys)
                 └─ records-table.blade.php
                      └─ @foreach groupingResult['groups']
                           └─ <x-ledger.records-section
                                :records="$records"
                                :scoreStats="$groupingResult['stats'][$defineId]"
                                ... />
                                ├─ <x-ledgerDefine.header :scoreStats="..." />
                                └─ <table>
                                     └─ @foreach $records
                                          └─ <x-ledger.table-row />
```

---

## 4. Lazy Load 適用可否検討

### 4.1 `#[Lazy]`

- **可否**: ⚠️ 技術的には可能だが非推奨
- **理由**:
  - 統計は `$ledgerRecords`（ページ内レコード）に完全に依存
  - `#[Lazy]` を使うには placeholder で「スコア統計なし」の状態を表示する必要がある
  - placeholder → 実表示への切り替えでちらつきが発生
  - データ量が少ない（ページ内 100 件程度）ため、 hydrate のオーバーヘッドが計算削減を上回る可能性
- **適用対象**: 将来的に「統計詳細（分布グラフ等）」が追加された場合に検討

### 4.2 `wire:init`

- **可否**: ✅ 可能
- **適用方法**:
  - `<x-ledger.records-section wire:init="loadStats">` のような形で、統計計算を mount 後の非同期メソッドに委譲
  - ただし、これは Livewire コンポーネント化（候補 A/B）が前提
- **効果**: 初期描画時に統計計算をスキップできる
- **リスク**: 統計バッジの表示遅延が UX 上の問題になる可能性（検索結果画面では avg_score は重要情報）
- **結論**: Sprint 2 では採用せず、Sprint 3 の検証フェーズで A/B テストを推奨

### 4.3 `wire:intersect.once`

- **可否**: ✅ 最も有望
- **適用方法**:
  - ページ内に複数の台帳定義セクションがある場合、画面外のセクションの統計計算をスクロール時まで遅延
  - ただし、これは「グルーピング自体」を遅延させることになり、テーブル行の表示も遅延するため実用上の制約が大きい
  - 代替案: 各セクションの「詳細統計（max/min 等）」のみを `wire:intersect.once` で遅延
- **結論**: Sprint 2 では採用せず、Sprint 3 で画面外セクションの添付ファイル取得等との組み合わせを検討

### 4.4 Lazy Load 総合判断

| 手法 | Sprint 2 | Sprint 3 | 備考 |
|------|----------|----------|------|
| `#[Lazy]` | ❌ 見送り | ⚠️ 検討 | データ依存が強く効果薄 |
| `wire:init` | ❌ 見送り | ✅ 検証 | 統計のみ非同期化の A/B テスト |
| `wire:intersect.once` | ❌ 見送り | ✅ 検証 | 画面外セクションの遅延ロード |
| **計算のサービス化** | ✅ **採用** | — | render() 軽量化の第一歩 |
| **Blade コンポーネント化** | ✅ **採用** | — | 表示責務の分離 |

---

## 5. 実装計画（Sprint 2 用）

### 5.1 新規ファイル

1. `app/Services/Ledger/RecordsGroupingService.php`
   - `groupAndComputeStats()` メソッド
   - 単体テスト: `tests/Unit/Services/Ledger/RecordsGroupingServiceTest.php`

2. `resources/views/components/ledger/records-section.blade.php`
   - セクション単位の表示カプセル化
   - `<x-ledgerDefine.header>` とテーブルの統合

### 5.2 変更ファイル

1. `app/Livewire/Ledger/RecordsTable.php`
   - `render()` から統計・グルーピング計算を削除
   - `RecordsGroupingService` の呼び出しを追加
   - パフォーマンスログの項目調整（`score_stats_ms`, `grouping_ms` を Service 内で計測）

2. `resources/views/livewire/ledger/records-table.blade.php`
   - `@foreach` ループを `<x-ledger.records-section>` の呼び出しに置き換え
   - パラメータの整理（`$ledgerRecordsGroupByDefineIds` → `$groupingResult`）

3. `resources/views/components/ledgerDefine/header.blade.php`
   - 変更なし（`$scoreStats` の受け取り方は維持）

### 5.3 非変更ファイル（影響なし）

- `app/Livewire/Ledger/IndexManager.php` — 親コンポーネント、Reactive props の構成に変更なし
- `resources/views/livewire/ledger/index-manager.blade.php` — 親ビュー、子コンポーネントの呼び出しに変更なし
- `app/Livewire/Folder/Tree.php` — フォルダツリー、独立した責務

### 5.4 テスト計画

1. **単体テスト** (`RecordsGroupingServiceTest`):
   - 空コレクションの処理
   - 単一定義IDのグルーピング
   - 複数定義IDのグルーピング
   - 統計計算の正確性（avg, max, min, count, has_scores）
   - 検索時のソート順序（avg_score desc）
   - 非検索時のソート順序（keys asc）

2. **Feature テスト**（回帰）:
   - `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`
   - `tests/Feature/Livewire/Ledger/RecordsTableActionsTest.php`
   - `tests/Feature/Livewire/Ledger/RecordsTableLedgerDefineSortTest.php`
   - `tests/Feature/Livewire/Ledger/RecordsTableCompositeScoreSortTest.php`
   - Issue #194 Sprint 2 と同じテストセットで PASS を確認

3. **表示検証**:
   - スコア統計バッジの表示確認（avg_score, max_score, count）
   - 検索時のセクション順序（avg_score 降順）
   - 非検索時のセクション順序（定義ID昇順）

---

## 6. リスクと対策

| リスク | 影響 | 対策 |
|--------|------|------|
| グルーピングソート順序の依存漏れ | 検索時のセクション順序が崩れる | Service 内で `isSearchActive` フラグを明確にし、テストで網羅 |
| `ledgerDefine.header` との整合性 | 統計バッジが表示されなくなる | `$scoreStats` の配列構造を維持、変更は `records-section` 側のみに制限 |
| パフォーマンスログの欠損 | 計測値が記録されなくなる | Service 内でも計測し、親に返却する設計を検討（または呼び出し側で計測） |
| Blade コンポーネントの props 増大 | メンテナンス性低下 | `$ledgerDefine` 等の関連オブジェクトをまとめた DTO を検討（過度に複雑化する場合は見送り） |

---

## 7. 結論

Sprint 1 の設計結果：

- **分割単位**: 「統計・グルーピング計算」を `RecordsGroupingService` に、「セクション表示」を `<x-ledger.records-section>` Blade コンポーネントに分離
- **表示責務**: `RecordsTable` は DB アクセスと検索に集中、Service は純粋計算、Blade コンポーネントは表示に集中
- **Lazy Load**: Sprint 2 では計算のサービス化・コンポーネント化を優先。`#[Lazy]` / `wire:init` / `wire:intersect.once` は Sprint 3 で効果検証後に検討
- **次のステップ**: Sprint 2 で `RecordsGroupingService` と `<x-ledger.records-section>` を実装し、`RecordsTable::render()` を簡潔化する

---

## 参照

- Issue #194（Epic）
- Issue #199（本 Issue）
- `app/Livewire/Ledger/RecordsTable.php`
- `resources/views/livewire/ledger/records-table.blade.php`
- `resources/views/components/ledgerDefine/header.blade.php`
- `docs/work/ui-ux/ledger-list-redesign/2026-05-03_issue-192-folder-switch-delay-retrospective.md`
