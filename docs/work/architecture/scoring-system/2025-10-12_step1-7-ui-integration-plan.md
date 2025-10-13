# Step 1.7: 基本的な表示順変更 - 詳細実装計画

**親ドキュメント:** [検索結果スコアリング・ソート機能 実装計画](./2025-10-08_search-result-scoring-and-sorting-plan.md)

**目標:** 台帳一覧でcomposite_scoreによるソートを追加し、デフォルトソート順を変更する

**予定工数:** 1日  
**優先度:** 高（Phase 1完了に必須）  
**作成日:** 2025-10-12

---

## 📋 実装タスク

### Task 1: RecordsTableコンポーネントの修正（3時間）

#### 1.1 デフォルトソート順の変更

**ファイル:** `app/Livewire/Ledger/RecordsTable.php`

**変更箇所:**
```php
// 現在（行34）
public $orderBy = 'id';
public $orderAsc = false;

// 変更後
public $orderBy = 'composite_score';  // デフォルトを複合スコアに変更
public $orderAsc = false;  // DESC（高スコアが上）
```

**影響範囲:**
- 既存の`id`ソートとの互換性維持
- URLパラメータ（Url属性なし）との整合性確認

#### 1.2 ソートクエリの修正

**現在の実装（行269-270）:**
```php
->orderBy('ledger_define_id', 'asc')
->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
```

**変更案:**
```php
->orderBy('ledger_define_id', 'asc')
->when($this->orderBy === 'composite_score', function ($query) {
    // MySQLでは NULLS LAST が使えないため、スコア0を最後に
    return $query->orderByRaw('composite_score = 0, composite_score ' . 
        ($this->orderAsc ? 'ASC' : 'DESC'));
}, function ($query) {
    return $query->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
});
```

**MySQLでのソート順:**
- `composite_score = 0, composite_score DESC`
  1. `composite_score = 0`がfalse（スコアあり）のレコードが先
  2. その中で`composite_score DESC`でソート
  3. 最後に`composite_score = 0`がtrue（スコア0）のレコード

#### 1.3 フォールバックの追加

**目的:** スコアが未計算の場合の挙動を制御

**実装:**
```php
public function mount()
{
    // 既存のmount処理
    // ...
    
    // composite_scoreカラムの存在確認
    if (!Schema::hasColumn('ledgers', 'composite_score')) {
        // マイグレーション未適用時のフォールバック
        $this->orderBy = 'id';
    }
}
```

### Task 2: テーブルヘッダーの修正（2時間）

#### 2.1 composite_scoreソートボタンの追加

**ファイル:** `resources/views/components/ledger/table-header.blade.php`

**追加箇所:** ID列とコンテンツ列の間（行26の後）

**実装コード:**
```blade
<th class="text-center px-4 py-2 tracking-wider bg-accent bg-opacity-30">
    <span class="text-sm font-bold">{{ __('ledger.scoring.composite_score') }}</span>
    <button class="btn btn-xs"
            wire:click.self="sort('composite_score')"
            wire:key="ledger_composite_score_sort_{{$ledgerDefine->id}}"
    >
        @if($orderBy == 'composite_score')
            @if($orderAsc)
                <i class="fas fa-sort-up"></i>
            @else
                <i class="fas fa-sort-down"></i>
            @endif
        @else
            <i class="fas fa-sort opacity-30"></i>
        @endif
    </button>
</th>
```

#### 2.2 翻訳キーの追加

**ファイル:** `lang/ja/ledger.php`

**追加内容:**
```php
'scoring' => [
    'composite_score' => '総合スコア',
    'activity_score' => '活動スコア',
    'freshness_score' => '新鮮度',
    'importance_score' => '重要度',
],
```

**ファイル:** `lang/en/ledger.php`（英語版）

```php
'scoring' => [
    'composite_score' => 'Score',
    'activity_score' => 'Activity',
    'freshness_score' => 'Freshness',
    'importance_score' => 'Importance',
],
```

### Task 3: テーブル行にスコア表示を追加（2時間）

#### 3.1 table-rowコンポーネントの修正

**ファイル:** `resources/views/components/ledger/table-row.blade.php`

**追加箇所:** ID列の後

**基本実装:**
```blade
{{-- 複合スコア表示 --}}
<td class="px-2 py-2 text-center">
    @if($ledgerRecord->composite_score > 0)
        <span class="badge badge-sm badge-primary">
            {{ number_format($ledgerRecord->composite_score, 1) }}
        </span>
    @else
        <span class="text-base-content/30 text-xs">-</span>
    @endif
</td>
```

#### 3.2 スコアレンジによる色分け（推奨実装）

**目的:** スコアの大きさを視覚的に表現

**実装:**
```blade
@php
    $scoreClass = match(true) {
        $ledgerRecord->composite_score >= 70 => 'badge-success',  // 緑: 非常に重要
        $ledgerRecord->composite_score >= 40 => 'badge-primary',   // 青: 重要
        $ledgerRecord->composite_score >= 20 => 'badge-info',      // 水色: 注目
        $ledgerRecord->composite_score > 0 => 'badge-ghost',       // グレー: 通常
        default => ''
    };
@endphp
@if($ledgerRecord->composite_score > 0)
    <span class="badge badge-sm {{ $scoreClass }}">
        {{ number_format($ledgerRecord->composite_score, 1) }}
    </span>
@else
    <span class="text-base-content/30 text-xs">-</span>
@endif
```

**色分けの基準:**
- 70点以上: 承認待ち + 活発 + 新しい（最優先）
- 40-69点: 重要度または活動が高い
- 20-39点: ある程度の活動がある
- 1-19点: 最小限の活動
- 0点: 未計算またはほぼ活動なし

### Task 4: 既存機能との互換性確認（1時間）

#### 4.1 確認項目チェックリスト

**ソート機能:**
- [ ] ID列ソートが正常に動作する
- [ ] カラムソート（content->xxx）が正常に動作する
- [ ] updated_atソートが正常に動作する
- [ ] ledger_define_idソートが維持される
- [ ] composite_scoreソートで昇順・降順が切り替わる

**表示機能:**
- [ ] ページネーションが正常に動作する
- [ ] フィルター機能が正常に動作する
- [ ] 検索機能が正常に動作する
- [ ] レスポンシブデザインが崩れない

#### 4.2 パフォーマンス確認

**測定項目:**
- クエリ実行時間（目標: 50ms以内）
- インデックスの使用確認
- N+1問題の発生確認

**確認方法:**
```php
// app/Livewire/Ledger/RecordsTable.php内で一時的に追加
use Illuminate\Support\Facades\DB;

DB::enableQueryLog();
$ledgerRecords = Ledger::whereIn(...)
    ->orderByRaw('composite_score = 0, composite_score DESC')
    ->get();
$queries = DB::getQueryLog();
Log::info('Query Time: ' . $queries[0]['time'] . 'ms');
```

**インデックス確認:**
```sql
EXPLAIN SELECT * FROM ledgers 
WHERE ledger_define_id IN (1,2,3) 
ORDER BY composite_score = 0, composite_score DESC;
```
- `idx_composite_score`インデックスが使用されることを確認

### Task 5: テストの作成（2時間）

#### 5.1 既存テストの更新

**ファイル:** `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`

**追加テストケース:**

```php
#[Test]
public function it_sorts_ledgers_by_composite_score_descending()
{
    // 異なるスコアの台帳を作成
    $ledgerHigh = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 80.5,
        'content' => ['text_column' => 'High Score'],
    ]);
    
    $ledgerMedium = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 45.2,
        'content' => ['text_column' => 'Medium Score'],
    ]);
    
    $ledgerLow = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 12.3,
        'content' => ['text_column' => 'Low Score'],
    ]);
    
    $ledgerZero = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 0,
        'content' => ['text_column' => 'Zero Score'],
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->set('orderBy', 'composite_score')
        ->set('orderAsc', false)
        ->assertSeeInOrder([
            'High Score',
            'Medium Score',
            'Low Score',
            // Zero Scoreは最後に表示される
        ]);
}

#[Test]
public function it_defaults_to_composite_score_sort()
{
    $ledger1 = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 50,
    ]);
    
    $ledger2 = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 30,
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->assertSet('orderBy', 'composite_score')
        ->assertSet('orderAsc', false);
}

#[Test]
public function it_can_toggle_composite_score_sort_direction()
{
    Ledger::factory()->count(3)->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => rand(10, 90),
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->call('sort', 'composite_score')
        ->assertSet('orderBy', 'composite_score')
        ->assertSet('orderAsc', true)  // トグルされる
        ->call('sort', 'composite_score')
        ->assertSet('orderAsc', false); // 再度トグル
}

#[Test]
public function it_handles_zero_composite_scores_correctly()
{
    // スコア0の台帳が最後に表示されることを確認
    $ledgerWithScore = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 10,
        'content' => ['text_column' => 'With Score'],
    ]);
    
    $ledgerZero = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 0,
        'content' => ['text_column' => 'Zero Score'],
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->set('orderBy', 'composite_score')
        ->set('orderAsc', false)
        ->assertSeeInOrder([
            'With Score',
            'Zero Score',
        ]);
}
```

#### 5.2 スコア表示のテスト

```php
#[Test]
public function it_displays_composite_score_badge()
{
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 75.8,
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->assertSee('75.8')
        ->assertSee('badge');  // バッジクラスが存在
}

#[Test]
public function it_hides_score_for_zero_composite_score()
{
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $this->ledgerDefine->id,
        'composite_score' => 0,
    ]);

    Livewire::test(RecordsTable::class)
        ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
        ->assertSee('-');  // ハイフンが表示される
}
```

---

## 🔍 検証ポイント

### 機能確認

**必須項目:**
- [ ] デフォルトでcomposite_score DESCでソートされる
- [ ] ソートボタンで昇順・降順を切り替えられる
- [ ] スコア0の台帳が最後に表示される
- [ ] スコア表示が適切にフォーマットされる（小数点1桁）
- [ ] 既存のソート機能（id, updated_at, content->xxx）が正常に動作する

**推奨項目:**
- [ ] スコアバッジの色分けが適切
- [ ] レスポンシブデザインが崩れない
- [ ] ダークモードで適切に表示される

### パフォーマンス確認

**目標値:**
- クエリ実行時間: 50ms以内
- ページロード時間: 増加なし
- メモリ使用量: 増加なし

**確認項目:**
- [ ] composite_scoreインデックスが使用される
- [ ] `ORDER BY composite_score = 0, composite_score DESC`が最適化される
- [ ] ページネーションが正常に動作する
- [ ] 100件以上の台帳でもパフォーマンス問題なし

### UI/UX確認

**視認性:**
- [ ] スコアバッジが視認しやすい
- [ ] ソートボタンが直感的
- [ ] ソート方向のアイコンが分かりやすい

**操作性:**
- [ ] クリック領域が十分
- [ ] ボタンのホバー効果が適切
- [ ] モバイルでもタップしやすい

---

## 🎯 完了条件

### 必須条件（Phase 1完了に必要）

1. ✅ デフォルトソートがcomposite_scoreに変更されている
2. ✅ テーブルヘッダーにcomposite_scoreソートボタンが追加されている
3. ✅ テーブル行にスコア表示が追加されている
4. ✅ 翻訳キー（日本語・英語）が追加されている
5. ✅ 既存のテストが全てパスする
6. ✅ 新規テスト（5ケース）が作成され、パスする
7. ✅ パフォーマンスが目標値以内（50ms）
8. ✅ 既存機能との互換性が確認されている

### 推奨条件（Phase 2で実装可能）

- [ ] スコアレンジによる色分け実装
- [ ] スコア詳細（breakdown）のツールチップ表示
- [ ] E2Eテスト作成
- [ ] スコア履歴の表示

---

## 📝 実装順序

### フェーズ1: 基本機能（4時間）

**午前（2時間）:**
1. RecordsTableコンポーネントの修正
   - デフォルトソート変更
   - ソートクエリ修正
   - フォールバック追加

2. 動作確認
   - ブラウザで表示確認
   - デバッグログでクエリ確認

**午後1（2時間）:**
3. テーブルヘッダーの修正
   - ソートボタン追加
   - 翻訳キー追加

4. テーブル行にスコア表示追加
   - 基本実装
   - 簡単なスタイリング

### フェーズ2: テスト・検証（3時間）

**午後2（2時間）:**
5. テストの作成
   - 既存テストの更新
   - 新規テストケース追加（5ケース）
   - 全テスト実行

**午後3（1時間）:**
6. 最終確認
   - パフォーマンス測定
   - 既存機能との互換性確認
   - UI/UX確認

### フェーズ3: 仕上げ（1時間）

**最終**
7. ドキュメント更新
   - 実装内容の記録
   - スクリーンショット追加
   - 親ドキュメントへの進捗反映

---

## ⚠️ 注意事項・リスク

### 技術的制約

1. **MySQLの制約**
   - `NULLS LAST`構文が使えない
   - `ORDER BY composite_score = 0, composite_score DESC`で代替
   - パフォーマンスへの影響は軽微（インデックスが有効）

2. **Livewireの制約**
   - `$orderBy`は既存プロパティを使用
   - 状態管理は単純明快
   - 新規プロパティ追加は不要

3. **パフォーマンス**
   - composite_scoreにインデックスが存在（Step 1.1で作成済み）
   - `ORDER BY`句が複雑だが、インデックスが効く
   - クエリプランを事前に確認

### 互換性維持

1. **既存ソート機能**
   - `id`, `updated_at`, `content->xxx`のソートは維持
   - `ledger_define_id`による第1ソートは維持
   - ソートロジックを`when`句で分岐

2. **URL状態**
   - `orderBy`はURL永続化されていない（`#[Url]`属性なし）
   - ページリロード時は常にデフォルトに戻る
   - ユーザーの期待通りの動作

3. **テスト**
   - 既存テストが全てパスすることを確認
   - 新規テストは最小限に抑える
   - テストデータのセットアップは既存パターンを踏襲

### 運用上の注意

1. **スコア未計算時の挙動**
   - 新規台帳はスコア0
   - 最初のバッチ実行まで最後に表示される
   - ユーザーには許容範囲内

2. **バッチ処理のスケジュール**
   - 日次バッチ（午前3時）で更新
   - リアルタイム性は不要（Phase 1の方針）
   - 必要に応じてPhase 2で改善

---

## 🚀 期待される効果

### ユーザー体験の改善

1. **情報発見の効率化**
   - 重要な台帳が自動的に上位表示される
   - 最近活動のある台帳を素早く見つけられる
   - 承認待ち等の緊急タスクを見逃さない

2. **優先順位の明確化**
   - スコアバッジで重要度が一目で分かる
   - 色分けにより緊急度を直感的に理解
   - 業務効率が向上

3. **学習コストゼロ**
   - デフォルトで最適なソート順
   - 従来のソート機能も使用可能
   - 既存ユーザーの操作感を維持

### システム改善

1. **Phase 1の完了**
   - Step 1.7の完了により、Phase 1が完成
   - MVPとして機能開始可能
   - ユーザーフィードバックの収集開始

2. **拡張性の確保**
   - Phase 2以降の機能追加が容易
   - スコアロジックの改善が可能
   - UI/UXの段階的改善が可能

---

## 📚 関連ドキュメント

**作業ドキュメント:**
- **親ドキュメント:** [検索結果スコアリング・ソート機能 実装計画](./2025-10-08_search-result-scoring-and-sorting-plan.md)
- **Phase 1全体:** 第5版（簡素化版）
- **実装済みステップ:**
  - Step 1.1: データベース基盤整備
  - Step 1.2: 設定ファイル作成
  - Step 1.3: 活動スコア計算サービス
  - Step 1.4: 重要度スコア計算サービス
  - Step 1.5: 複合スコア計算サービス
  - Step 1.6: バッチ処理コマンド
- **関連実装レポート:**
  - [Step 1.7 実装完了レポート](./2025-10-12_step1-7-implementation-complete.md)
  - [台帳定義ヘッダーにスコア統計表示](./2025-10-12_step1-7-header-score-display.md)
  - [検索時の台帳定義スコア順ソート](./2025-10-12_step1-7-ledger-define-sort.md)

**公式ドキュメント:**
- [スコアリングシステム（機能）](../../../features/scoring-system.md) - ユーザー向け説明
- [スコアリングシステム（開発者ガイド）](../../../development/scoring-system.md) - 開発者向け詳細

---

## 📅 スケジュール

| 時間帯 | タスク | 所要時間 |
|--------|--------|----------|
| 09:00-11:00 | RecordsTable修正・動作確認 | 2時間 |
| 11:00-13:00 | テーブルヘッダー・行修正 | 2時間 |
| 14:00-16:00 | テスト作成・実行 | 2時間 |
| 16:00-17:00 | 最終確認・ドキュメント更新 | 1時間 |
| **合計** | | **7時間** |

**バッファ:** 1時間（予期しない問題への対応）

---

**作成日:** 2025年10月12日  
**作成者:** GitHub Copilot CLI  
**ステータス:** 詳細計画完成・実装待ち  
**次のアクション:** Task 1から順次実装開始
