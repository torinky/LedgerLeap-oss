# Step 1.7 追加実装: 台帳定義ヘッダーにスコア統計表示

**実装日:** 2025-10-12  
**目的:** 横串検索時に台帳定義ごとのグループでスコア統計を表示

---

## 実装の背景

### 問題
- 台帳レコード（各行）にはスコアが表示されるが、台帳定義のカードヘッダーにはスコア情報がない
- 横串検索では台帳定義ごとにグループ化されるため、グループ全体のスコアが見えないと優先順位判断が困難

### UI上の制約
- 台帳レコードは必ず台帳定義でグループ化して表示される
- APIではレコード単位のソートが有益だが、UIでは台帳定義単位の情報が重要

---

## 実装内容

### 1. スコア統計の計算（RecordsTable.php）

**ファイル:** `app/Livewire/Ledger/RecordsTable.php`

**追加箇所:** render()メソッド内（行345-356付近）

```php
// 台帳定義ごとのスコア統計を計算
$scoreStatsByDefineId = $ledgerRecords->groupBy('ledger_define_id')->map(function ($records) {
    $scores = $records->pluck('composite_score')->filter(fn($score) => $score > 0);
    
    return [
        'count' => $records->count(),
        'avg_score' => $scores->count() > 0 ? round($scores->avg(), 1) : 0,
        'max_score' => $scores->count() > 0 ? round($scores->max(), 1) : 0,
        'min_score' => $scores->count() > 0 ? round($scores->min(), 1) : 0,
        'has_scores' => $scores->count() > 0,
    ];
});
```

**ビューへの追加:**
```php
return view('livewire.ledger.records-table', [
    // ... 既存のデータ
    'scoreStatsByDefineId' => $scoreStatsByDefineId, // 追加
]);
```

### 2. ヘッダーコンポーネントへの渡し（records-table.blade.php）

**ファイル:** `resources/views/livewire/ledger/records-table.blade.php`

**変更箇所:** x-ledgerDefine.header の呼び出し

```blade
<x-ledgerDefine.header
    :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
    {{-- ... 既存のプロパティ ... --}}
    :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null"
    :currentTenantId="$currentTenantId"
/>
```

### 3. ヘッダーでのスコア表示（header.blade.php）

**ファイル:** `resources/views/components/ledgerDefine/header.blade.php`

**追加内容:**

```blade
<h3 class="text-2xl font-medium leading-tight text-primary space-x-3 my-2 mr-4">
    <span><i class="fa-solid fa-book-open mr-2"></i>{{$ledgerDefine->title}}</span>
    @if($scoreStats && $scoreStats['has_scores'])
        <span class="text-sm font-normal text-base-content/70 ml-4">
            @php
                $avgScoreClass = match(true) {
                    $scoreStats['avg_score'] >= 70 => 'badge-success',
                    $scoreStats['avg_score'] >= 40 => 'badge-primary',
                    $scoreStats['avg_score'] >= 20 => 'badge-info',
                    $scoreStats['avg_score'] > 0 => 'badge-ghost',
                    default => 'badge-ghost'
                };
            @endphp
            <span class="badge {{ $avgScoreClass }} badge-sm gap-1">
                <i class="fas fa-chart-line text-xs"></i>
                {{ __('ledger.scoring.avg_score') }}: {{ $scoreStats['avg_score'] }}
            </span>
            <span class="badge badge-ghost badge-sm gap-1 ml-1">
                <i class="fas fa-arrow-up text-xs"></i>
                {{ __('ledger.scoring.max') }}: {{ $scoreStats['max_score'] }}
            </span>
            <span class="text-xs text-base-content/50">
                ({{ $scoreStats['count'] }}{{ __('ledger.records') }})
            </span>
        </span>
    @endif
</h3>
```

### 4. 翻訳キーの追加

**ファイル:** `lang/ja/ledger.php`

```php
'scoring' => [
    'composite_score' => '総合スコア',
    'activity_score' => '活動スコア',
    'freshness_score' => '新鮮度',
    'importance_score' => '重要度',
    'avg_score' => '平均',      // 追加
    'max' => '最高',             // 追加
    'min' => '最低',             // 追加
],
```

---

## 表示例

### UI上の表示

```
┌─────────────────────────────────────────────────────────────┐
│ 📖 営業日報  [平均: 34.5]  [最高: 37.2]  (15件)            │
│ [作成] [CSV] [設定]                                         │
├─────────────────────────────────────────────────────────────┤
│ テーブル                                                     │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ID │ 総合スコア │ カラム1 │ カラム2 │ ... │ 更新日時 │ │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 14 │  37.2    │ データ  │ データ  │ ... │ 2025-10-12│ │ │
│ │ 13 │  35.8    │ データ  │ データ  │ ... │ 2025-10-11│ │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 📖 案件管理  [平均: 28.3]  [最高: 32.1]  (8件)             │
│ [作成] [CSV] [設定]                                         │
├─────────────────────────────────────────────────────────────┤
│ テーブル                                                     │
│ ...                                                          │
└─────────────────────────────────────────────────────────────┘
```

### バッジの色分け（平均スコアに基づく）

- **70点以上**: 🟢 緑 (`badge-success`)
- **40-69点**: 🔵 青 (`badge-primary`)
- **20-39点**: 🔵 水色 (`badge-info`)
- **1-19点**: ⚪ グレー (`badge-ghost`)

---

## スコア統計の内容

各台帳定義について以下の統計を計算：

```php
[
    'count' => 15,           // この台帳定義のレコード数
    'avg_score' => 34.5,     // 平均スコア（小数点1桁）
    'max_score' => 37.2,     // 最高スコア
    'min_score' => 21.2,     // 最低スコア（表示には未使用）
    'has_scores' => true,    // スコアが存在するか
]
```

**注意:**
- スコアが0のレコードは統計計算から除外
- `has_scores`がfalseの場合はスコア表示を非表示

---

## ユースケース

### 1. 横串検索時の優先順位判断

**シナリオ:** 全台帳から「重要」というキーワードで検索

**表示例:**
```
📖 営業日報    [平均: 45.2] [最高: 52.3] (23件)  ← 高スコア → 優先確認
📖 会議議事録  [平均: 38.7] [最高: 44.1] (15件)
📖 案件管理    [平均: 28.3] [最高: 32.1] (8件)   ← 低スコア → 後回し
```

**メリット:**
- どの台帳定義のグループを優先的に確認すべきか一目で判断可能
- 各グループ内のレコード数も把握できる

### 2. フォルダ内の台帳比較

**シナリオ:** 特定プロジェクトフォルダ内の複数台帳を閲覧

```
📁 プロジェクトX
  📖 進捗報告    [平均: 42.1] [最高: 48.5] (12件) ← 活発
  📖 課題管理    [平均: 38.9] [最高: 45.2] (25件)
  📖 資料保管    [平均: 15.3] [最高: 22.1] (50件) ← 低活動
```

**メリット:**
- プロジェクト内で活発な台帳と停滞している台帳が明確
- 注目すべき台帳を素早く特定

### 3. タグ検索結果の整理

**シナリオ:** #重要案件 タグでの検索結果

```
📖 営業日報    [平均: 52.3] [最高: 67.8] (5件)  ← 緊急度高
📖 案件管理    [平均: 48.2] [最高: 55.1] (8件)
📖 顧客対応    [平均: 35.7] [最高: 42.3] (12件)
```

---

## 技術的詳細

### パフォーマンス考慮

1. **既存コレクションの再利用**
   - `$ledgerRecords`から直接計算
   - 追加のDBクエリは不要

2. **メモリ効率**
   - `pluck()`で必要な値のみ抽出
   - `filter()`でスコア0を除外

3. **計算量**
   - O(n): レコード数に比例
   - グループ化は既存の`groupBy`を活用

### エッジケース対応

1. **スコア未計算の場合**
   ```php
   'has_scores' => $scores->count() > 0
   ```
   - スコアがない場合は表示を非表示

2. **空のグループ**
   ```php
   :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null"
   ```
   - nullセーフに対応

3. **0除算回避**
   ```php
   $scores->count() > 0 ? round($scores->avg(), 1) : 0
   ```

---

## テスト結果

### 既存テスト（全てパス）

```
✓ it sorts by composite score desc by default (9.24s)
✓ it shows zero score records last (1.52s)
✓ it can toggle sort order (2.07s)
✓ it displays score badges with correct styling (1.65s)
✓ it maintains compatibility with other sort columns (2.19s)
✓ it shows list on multiple matches (9.58s)
✓ it shows list on zero matches (1.35s)
✓ it forces list view on unique match with mode list (1.70s)
✓ it highlights keywords in list view (2.04s)
✓ it displays auto links in list view (1.91s)

Tests: 10 passed (29 assertions)
```

**確認事項:**
- 既存機能への影響なし
- スコア統計の追加によるエラーなし
- レスポンス時間の増加なし（既存コレクション活用のため）

---

## ユーザーへの確認手順

1. **ブラウザのハードリフレッシュ**
   - Mac: `Cmd + Shift + R`
   - Windows/Linux: `Ctrl + Shift + R`

2. **台帳一覧を表示**
   - フォルダまたは台帳定義を選択
   - 複数の台帳定義を表示

3. **期待される表示**
   - 各台帳定義のタイトルの横にスコア統計が表示される
   - 平均スコアがバッジで色分けされている
   - 最高スコアとレコード数も表示されている

---

## 今後の拡張案（Phase 2以降）

### 統計情報の充実
- 最低スコアの表示
- スコア分布のヒストグラム（ツールチップ）
- トレンド（前回との比較）

### インタラクティブ機能
- スコア統計クリックで該当グループにフォーカス
- スコアソートボタン（台帳定義をスコア順に並べ替え）

### カスタマイズ
- 表示する統計項目の選択（設定画面）
- スコア計算式のカスタマイズ

---

## 📝 関連ドキュメント

**作業ドキュメント:**
- [ハイブリッド型情報価値評価システム 実装計画](./2025-10-08_search-result-scoring-and-sorting-plan.md) - 親ドキュメント
- [Step 1.7 実装完了レポート](./2025-10-12_step1-7-implementation-complete.md) - 基本実装
- [検索時の台帳定義スコア順ソート](./2025-10-12_step1-7-ledger-define-sort.md) - 関連実装

**公式ドキュメント:**
- [スコアリングシステム（機能）](../../../features/scoring-system.md) - ユーザー向け説明
- [スコアリングシステム（開発者ガイド）](../../../development/scoring-system.md) - 開発者向け詳細

---

**実装日:** 2025年10月12日  
**所要時間:** 約30分  
**ステータス:** ✅ 完了  
**テスト:** ✅ 全テストパス（10件）
