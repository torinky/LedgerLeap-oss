# パフォーマンス最適化ガイド

**最終更新:** 2026年1月3日  
**対象:** LedgerLeap開発者

## 1. 概要

LedgerLeapのパフォーマンス最適化に関する開発者向けガイドです。実装済みの最適化手法、測定方法、トラブルシューティングを記載します。

**記載範囲:**
- フロントエンド最適化（Vite、Alpine.js、Livewire）
- バックエンド最適化（Eloquent、キャッシュ、非同期処理）
- データベース最適化（Mroonga、インデックス）
- パフォーマンス測定とモニタリング

**記載しない内容:**
- 運用監視の設定 → `docs/operations/fileinspector-performance-monitoring.md`
- インフラ設定 → インフラドキュメント

---

## 2. フロントエンド最適化

### 2.1. Viteビルドの最適化

**重要:** 本番環境では必ず`npm run build`を使用してください。

#### 開発環境 vs 本番環境

| 環境 | コマンド | 特徴 | パフォーマンス |
|------|---------|------|--------------|
| 開発 | `npm run dev` | HMR（Hot Module Replacement）有効 | 遅い（UIブロックあり） |
| 本番 | `npm run build` | 最適化されたバンドル | 高速（Alpine.js即座に動作） |

#### 実測データ（添付ファイル機能、WBS 5.2測定）

| 項目 | npm run dev | npm run build | 改善率 |
|------|-------------|---------------|--------|
| フォーカス応答 | 数秒 | 即座 | 100% |
| 画像プレビュー | 遅い | 143ms | 劇的改善 |
| UIブロック | あり | なし | 100% |

**結論:** npm run devのHMRオーバーヘッドにより、Alpine.jsの初期化やイベントリスナーの登録が遅延します。本番環境では必ず`npm run build`を使用してください。

### 2.2. Alpine.jsの最適化

#### x-cloakの活用

**目的:** フラッシュコンテンツ（Alpine.js初期化前の未加工HTML）を防ぐ

```blade
<div x-data="{ open: false }" x-cloak>
    <div x-show="open">
        <!-- Alpine.js初期化後に表示 -->
    </div>
</div>
```

```css
/* app.css */
[x-cloak] { 
    display: none !important; 
}
```

#### x-show vs x-if

| ディレクティブ | 動作 | 使用場面 |
|--------------|------|---------|
| `x-show` | CSS display切替（DOM保持） | 頻繁に表示/非表示を切り替える要素 |
| `x-if` | DOM追加/削除 | めったに表示されない要素 |

**推奨:**
- モーダル、ドロワー → `x-show`（頻繁に開閉）
- 条件付き大量コンテンツ → `x-if`（DOMサイズ削減）

#### イベントリスナーの最適化

```javascript
// ❌ 悪い例：グローバルイベントの多用
window.addEventListener('click', handler);

// ✅ 良い例：適切なスコープ
Alpine.data('fileInspector', () => ({
    init() {
        this.$el.addEventListener('click', handler);
    }
}));
```

### 2.3. Livewireの最適化

#### wire:model.live.debounce

**目的:** サーバーリクエストの頻度を制御

```blade
<!-- ❌ 悪い例：毎キーストロークでリクエスト -->
<input wire:model.live="search" />

<!-- ✅ 良い例：500msの遅延 -->
<input wire:model.live.debounce.500ms="search" />

<!-- ✅ さらに良い例：1000msの遅延（検索など） -->
<input wire:model.live.debounce.1000ms="search" />
```

#### Lazy Loading

**目的:** 重いコンポーネントの初回レンダリングを高速化

```php
use Livewire\Attributes\Lazy;

#[Lazy]
class HeavyComponent extends Component
{
    public function placeholder()
    {
        return view('livewire.placeholders.skeleton');
    }
    
    public function render()
    {
        // 重い処理
        return view('livewire.heavy-component');
    }
}
```

#### Computed Properties

**目的:** 同じリクエスト内での計算結果をキャッシュ

```php
use Livewire\Attributes\Computed;

#[Computed]
public function expensiveData()
{
    return $this->performExpensiveCalculation();
}
```

```blade
<!-- ✅ キャッシュされる -->
{{ $this->expensiveData }}
{{ $this->expensiveData }} <!-- 再計算されない -->
```

#### wire:keyの活用

**目的:** 不要なDOM再利用を防ぐ

```blade
@foreach($items as $item)
    <div wire:key="item-{{ $item->id }}">
        {{ $item->name }}
    </div>
@endforeach
```

**重要:** `wire:key`がない場合、Livewireは要素を再利用しようとして予期しない動作を引き起こす可能性があります。

---

## 3. バックエンド最適化

### 3.1. Eloquent N+1問題の解決

#### Eager Loadingの基本

```php
// ❌ N+1問題
$ledgers = Ledger::all();
foreach ($ledgers as $ledger) {
    echo $ledger->creator->name; // 各ループでクエリ発行
}

// ✅ Eager Loading
$ledgers = Ledger::with('creator')->get();
foreach ($ledgers as $ledger) {
    echo $ledger->creator->name; // クエリは1回のみ
}
```

#### 複雑なリレーションのEager Loading

```php
// 添付ファイルの例
AttachedFile::with([
    'ledger:id,content,content_attached,ledger_define_id',
    'ledger.define:id,folder_id,title',
    'ledger.define.folder:id,title,path',
    'creator:id,name',
    'modifier:id,name',
])->findOrFail($fileId);
```

**ポイント:**
- `:id,name`のように必要なカラムのみ選択
- リレーションを`.`でチェーン
- 外部キーは必ず含める（例: `ledger_define_id`）

### 3.2. キャッシュ戦略

#### アプリケーションキャッシュ

```php
use Illuminate\Support\Facades\Cache;

// 60分キャッシュ
$users = Cache::remember('users.all', 60, function () {
    return User::all();
});

// タグ付きキャッシュ
Cache::tags(['users'])->put('user.1', $user, 60);
Cache::tags(['users'])->flush(); // タグ単位でクリア
```

#### クエリ結果キャッシュ

```php
// ✅ キャッシュ付きクエリ（カスタムメソッド推奨）
public function getCachedStatistics()
{
    return Cache::remember('statistics', 3600, function () {
        return DB::table('ledgers')
            ->select(DB::raw('COUNT(*) as total, AVG(composite_score) as avg_score'))
            ->first();
    });
}
```

### 3.3. 非同期処理の活用

#### キューの適切な使用

```php
// ❌ 同期実行（ユーザー待機）
ProcessAttachedFile::dispatchSync($file);

// ✅ 非同期実行（バックグラウンド）
ProcessAttachedFile::dispatch($file);

// ✅ 遅延実行（2秒後）
ProcessAttachedFile::dispatch($file)->delay(now()->addSeconds(2));

// ✅ 専用キュー指定
ProcessVlmExtraction::dispatch($file)->onQueue('vlm-processing');
```

#### バッチ処理

```php
use Illuminate\Support\Facades\Bus;

// 複数ジョブを並列実行
Bus::batch([
    new ProcessFile($file1),
    new ProcessFile($file2),
    new ProcessFile($file3),
])->then(function (Batch $batch) {
    // 全て完了後の処理
})->dispatch();
```

---

## 4. データベース最適化

### 4.1. Mroonga全文検索の最適化

#### 単一カラムインデックスの使用

```sql
-- ✅ 正しい（単一インデックスをOR結合）
SELECT * FROM ledgers 
WHERE MATCH(content) AGAINST('キーワード')
   OR MATCH(content_attached) AGAINST('キーワード');

-- ❌ 動作しない（複合インデックス）
SELECT * FROM ledgers 
WHERE MATCH(content, content_attached) AGAINST('キーワード');
```

**重要:** Mroongaは複数のベクターカラムを対象とした複合インデックスが正しく動作しません。必ず単一インデックスをOR結合してください。

#### クエリビルダーでの実装

```php
// Ledgerモデルのscopeメソッド
public function scopeSearch($query, $keyword)
{
    return $query->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$keyword])
        ->orWhereRaw('MATCH(content_attached) AGAINST(? IN BOOLEAN MODE)', [$keyword]);
}
```

### 4.2. インデックスの設計

#### 複合インデックスの順序

```php
// マイグレーション
Schema::table('ledgers', function (Blueprint $table) {
    // ✅ 正しい順序（WHERE句で最も絞り込むカラムを先頭に）
    $table->index(['ledger_define_id', 'status', 'created_at']);
});
```

**原則:**
1. WHERE句で等価比較するカラムを先頭
2. 範囲検索するカラムを後方
3. ORDER BY句のカラムを最後

#### カバリングインデックス

```php
// ✅ カバリングインデックス（SELECT句の全カラムを含む）
$table->index(['ledger_define_id', 'status', 'id', 'title']);
```

**効果:** テーブルアクセス不要で、インデックスのみからデータ取得

### 4.3. スコアリングシステムの最適化

```php
// ledgersテーブル
Schema::table('ledgers', function (Blueprint $table) {
    $table->decimal('activity_score', 5, 2)->default(0);
    $table->decimal('composite_score', 5, 2)->default(0);
    
    // 高速ソート用インデックス
    $table->index('composite_score', 'idx_ledgers_composite_score');
});
```

**非同期計算:** スコアはバッチ処理で非同期に計算し、クエリ時はインデックスから高速取得

---

## 5. パフォーマンス測定

### 5.1. ログベースの測定

#### Bladeテンプレートでの測定

```blade
<div x-data="{
    logPerformance(action, duration) {
        $wire.logPerformance(action, duration);
    }
}" 
@drawer-opened.window="
    const start = performance.now();
    setTimeout(() => {
        logPerformance('drawer_open', performance.now() - start);
    }, 10);
">
```

#### Livewireコンポーネントでの測定

```php
public function logPerformance($action, $duration)
{
    Log::info("Performance: {$action}", [
        'duration_ms' => round($duration, 2),
        'user_id' => auth()->id(),
        'timestamp' => now()->toIso8601String(),
    ]);
}
```

### 5.2. Laravel Debugbarの活用

```bash
# 開発環境にインストール
composer require barryvdh/laravel-debugbar --dev
```

**主な機能:**
- クエリ実行時間の可視化
- N+1問題の検出
- メモリ使用量の監視
- ビューレンダリング時間の測定

### 5.3. Laravel Telescopeの活用

```bash
# インストール
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**主な機能:**
- リクエスト全体のパフォーマンス分析
- ジョブ実行時間の監視
- キャッシュヒット率の確認
- 例外のトラッキング

---

## 6. 実装済みの最適化事例

### 6.1. 添付ファイル機能（Phase 1-5）

#### VLM/OCR並列処理

**実装:** VLMとOCRを並列実行し、処理時間を30-40%短縮

```php
// ProcessAttachedFile.php
ProcessVlmExtraction::dispatch($file)->onQueue('vlm-processing');
OcrAndOptimizeFile::dispatch($file)->delay(2)->onQueue('ocr');
```

**効果:**
- VLM: 8-25秒
- OCR: 15-120秒
- 並列実行により、全体時間は長い方（OCR）に依存

#### ユーザー待機時間の最小化

**戦略:** Tika処理完了後（約5秒）にユーザーを画面に復帰させ、VLM/OCRはバックグラウンド実行

**効果:** ユーザー体験の大幅改善（数分待機 → 5秒で操作可能）

### 6.2. ColumnHtmlServiceのリファクタリング（Phase 3）

**変更:** PHP内でのHTML文字列結合 → Bladeコンポーネント化

**効果:**
- コード量: 280行 → 20行（93%削減）
- 保守性: 大幅向上
- パフォーマンス: ほぼ同等（Bladeキャッシュが効く）

### 6.3. スコアリングシステム（別Phase）

**実装:** 台帳の重要度を複合スコアで算出し、非同期バッチ処理

**効果:**
- リアルタイム計算不要
- インデックスによる高速ソート
- ユーザークエリへの影響ゼロ

---

## 7. よくあるパフォーマンス問題と対策

### 7.1. Livewireの全体レンダリング遅延

**症状:** 入力フィールドの変更で1500ms以上かかる

**原因:** Livewireが大きなBladeテンプレートを全体再レンダリング

**対策:**
1. `wire:model.live.debounce.1000ms`でリクエスト頻度を削減
2. コンポーネントを分割し、レンダリング範囲を縮小
3. Lazy Loadingで重い部分を遅延読み込み

**未解決の場合:** 今後のLivewire 3.5+の`wire:stream`機能に期待

### 7.2. N+1問題

**検出方法:** Laravel Debugbarで重複クエリを確認

**対策:**
```php
// ✅ with()で事前ロード
$ledgers = Ledger::with(['creator', 'modifier', 'define'])->get();

// ✅ 必要なカラムのみ選択
$ledgers = Ledger::with(['creator:id,name'])->get();
```

### 7.3. 全文検索の遅延

**症状:** 検索クエリに数秒かかる

**原因:**
1. Mroongaインデックスが作成されていない
2. 複合インデックスを使用している（動作しない）
3. `RefreshDatabase`を使用している（テスト環境）

**対策:**
1. 単一インデックスをOR結合
2. テストでは`DatabaseMigrations`を使用
3. `sleep(1)`でインデックス更新を待機（テスト環境）

---

## 8. 関連ドキュメント

### アーキテクチャ
- **[非同期処理](../architecture/QueueProcessing.md)** - キューワーカーとジョブ設計
- **[ファイル処理フロー](../architecture/file-processing-flow.md)** - VLM/OCR/Tika並列処理

### 開発ガイド
- **[VLM/OCR開発者ガイド](./vlm-ocr.md)** - VLM/OCR最適化のヒント
- **[テストのベストプラクティス](./Testing-Best-Practices.md)** - Mroongaテストの注意点

### 運用ガイド
- **[FileInspectorパフォーマンス監視](../operations/fileinspector-performance-monitoring.md)** - 運用時の測定設定

### データベース
- **[データベーススキーマ](../database/schema.md)** - Mroongaインデックスの制約

---

**最終更新:** 2026年1月3日  
**主な最適化実装:** Phase 1-5（添付ファイル機能統合）、WBS 5.2（パフォーマンス改善）

