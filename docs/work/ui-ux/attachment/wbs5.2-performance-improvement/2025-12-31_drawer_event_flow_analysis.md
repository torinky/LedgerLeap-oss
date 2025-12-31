# FileInspectorドロワーのイベントフロー網羅的分析

**分析日:** 2025年12月31日  
**更新日:** 2025年12月31日（npm run build改善を反映）  
**目的:** パフォーマンス問題の根本原因を俯瞰的に特定

---

## 🎉 重要な発見（2025-12-31 更新）

### npm run dev → npm run build で劇的な改善！

**変更内容:**
```bash
# 修正前
sail npm run dev

# 修正後  
sail npm run build
```

**改善結果:**
- ✅ **フォーカス遅延: 完全に解消**（数秒 → 即座）
- ✅ **画像プレビュー: 解消**（143ms、ログ記録も成功）
- ✅ **UIのブロック: 解消**（Alpine.jsが高速動作）
- ❌ **キーワード検索: 依然として1500ms**（Livewireのレンダリングが原因）

**結論:** 
- フォーカス遅延は**Viteの開発サーバーのオーバーヘッド**が原因だった
- 画像プレビューも同様にJavaScriptの実行速度が問題だった
- **キーワード検索のみがLivewireのレンダリングコスト**が原因

詳細: [npm run build改善分析](./2025-12-31_npm_build_improvement_analysis.md)

---

## 🔍 重要な発見（初期分析）

### 発見1: ログ記録の構文エラー

**問題:** `@this.call()`は**Bladeディレクティブとして評価される**

```blade
<!-- ❌ 間違い：Bladeで処理されてしまう -->
@this.call('logPerformance', ...)

<!-- ✅ 正しい：JavaScriptとして評価される -->
$wire.logPerformance(...)
```

**影響:**
- 画像プレビューのログが記録されない
- 検索のログも`@this`から`$wire`に変更が必要

**修正:** 
- ✅ preview.blade.php: `@this.call()` → `$wire.logPerformance()`
- ✅ content.blade.php: `@this.call()` → `$wire.logPerformance()`

---

## 📊 ドロワーのイベントフロー全体図

### 1. ドロワー開閉のフロー

```
ユーザーアクション: ファイルクリック
  ↓
イベント発火: @open-file-inspector
  ↓
Alpine.js処理:
  - open = true
  - isLoading = true
  - measureDrawerOpen() ← パフォーマンス測定開始
  ↓
Livewire処理: $wire.openInspector(fileId)
  ↓
サーバー側処理: FileInspector.php
  - loadData()
  - DB クエリ (activities含む)
  - データ整形
  ↓
Livewireレンダリング: Blade全体を再構築 (1500-2000ms)
  ↓
Alpine.js: isLoading = false
  ↓
パフォーマンス測定完了: measureDrawerOpened()
  ↓
ユーザーに表示
```

**ボトルネック:** Livewireレンダリング (1500-2000ms)

### 2. 検索のイベントフロー

```
ユーザー入力: 検索ボックスに文字入力
  ↓
Alpine.js: $watch('$wire.searchKeyword') 発火
  - searching = true (スピナー表示)
  - searchStartTime = performance.now()
  ↓
デバウンス待機: 1000ms
  ↓
Livewireリクエスト: wire:model.live.debounce.1000ms
  ↓
サーバー側: updatedSearchKeyword()
  - clearPreviewCache()
  - logPerformance('search_keyword_update', 0ms)
  ↓
Livewireレンダリング: Blade全体を再構築 (1500ms)
  - hasKeywordHit()を再計算
  - プレビューテキストを再生成
  - ハイライト処理
  ↓
Alpine.js: 1500ms後にログ記録
  - $wire.logPerformance('search_render', 1500ms)
  ↓
ユーザーに表示
```

**ボトルネック:** Livewireレンダリング (1500ms)

**副作用:** レンダリング中はUI全体がブロック → フォーカス遅延

### 3. 画像プレビューのイベントフロー

```
ドロワー開閉 → Livewireレンダリング
  ↓
preview.blade.php レンダリング
  ↓
Alpine.js: x-data init()
  - sessionStorage確認
  - キャッシュヒット? → imgLoaded = true
  - キャッシュなし? → loadStartTime = performance.now()
  ↓
<img>タグ読み込み開始
  ↓
ブラウザが画像取得 (ネットワーク)
  ↓
x-on:load="markLoaded()" 発火
  - imgLoaded = true
  - sessionStorage.setItem()
  - duration = performance.now() - loadStartTime
  - console.log()
  - $wire.logPerformance('image_preview_load', duration) ← 修正済み
  ↓
サーバー側: logPerformance()
  - ログに記録
  - JSON統計ファイルに記録
```

**問題点（修正済み）:** `@this.call()`が動作しない → `$wire.logPerformance()`に修正

### 4. タブ切り替えのイベントフロー

```
ユーザークリック: タブをクリック
  ↓
Alpine.js: $watch('$wire.selectedTab') 発火
  ↓
パフォーマンス測定開始: performance.now()
  ↓
Livewireリクエスト: selectedTab = 'details'
  ↓
サーバー側: 状態変更のみ（高速）
  ↓
Livewireレンダリング: 該当タブのみ再構築 (30ms)
  ↓
requestAnimationFrame: duration計算
  ↓
$wire.logPerformance('tab_switch', 30ms)
  ↓
ユーザーに表示
```

**なぜ速いのか:** タブ切り替えは差分レンダリング（全体を再構築しない）

---

## 🎯 根本原因の特定

### 共通の問題: Livewireの全体レンダリング

**遅い操作:**
1. ドロワー開閉: 2000ms ← **全体レンダリング**
2. 検索: 1500ms ← **全体レンダリング**

**速い操作:**
1. タブ切り替え: 30ms ← **差分レンダリング**

### なぜ全体レンダリングが発生するのか

#### ドロワー開閉

```php
// FileInspector.php
public function openInspector($id): void
{
    $this->loadData($id);  // データを全読み込み
    $this->open = true;     // Livewire全体を再レンダリング
}
```

**問題:**
- ドロワーを開くたびに`loadData()`が実行される
- Livewireが**Blade全体を再生成**する
- 4タブ × 多数のコンポーネント × Alpine.jsバインディング = 2000ms

#### 検索

```php
// FileInspector.php
public function updatedSearchKeyword(): void
{
    $this->clearPreviewCache();  // キャッシュクリア
    // Livewireが自動的に全体を再レンダリング
}
```

**問題:**
- `searchKeyword`プロパティが変更される
- Livewireが**自動的に全体を再レンダリング**
- 検索結果のハイライト処理もサーバー側で実行 = 1500ms

### なぜフォーカスが遅延するのか

**原因:** Livewireのレンダリング中、JavaScriptの実行がブロックされる

```
ユーザーがクリック → Livewireレンダリング開始(1500ms)
  ↓
レンダリング中: UI操作がブロック
  ↓
ユーザーが検索ボックスをクリック → 反応しない
  ↓
レンダリング完了(1500ms後) → ようやくフォーカスが当たる
```

**これが「数秒かかる」の正体**

---

## 💡 解決策の方向性

### Option 1: wire:ignoreで部分的にスキップ（推奨）

**現状:**
```blade
<div>
    <!-- 検索のたびに全体が再レンダリング -->
    <input wire:model.live.debounce.1000ms="searchKeyword" />
    <div>{{ $previewText }}</div>
</div>
```

**改善案:**
```blade
<div wire:ignore x-data="{
    previewText: @js($this->getPreviewText()),
    keyword: @entangle('searchKeyword'),
    get highlightedText() {
        // Alpine.jsでハイライト処理
        return this.highlightKeyword(this.previewText, this.keyword);
    }
}">
    <input x-model="keyword" type="text" />
    <div x-html="highlightedText"></div>
</div>
```

**効果:**
- Livewireのレンダリングをスキップ
- Alpine.jsのみで検索処理
- 1500ms → <50ms
- **UIがブロックされない** → フォーカス遅延解消

### Option 2: 段階的な表示

**現状:**
```blade
<div x-show="open" x-transition>
    <!-- 2000ms後にすべて表示 -->
    @include('all-content')
</div>
```

**改善案:**
```blade
<div x-show="open" x-transition>
    <!-- 即座に骨格を表示 -->
    <div>{{ $file->filename }}</div>
    
    <!-- 重いコンテンツは遅延ロード -->
    <div wire:loading>Loading...</div>
    <div wire:loading.remove>
        @include('heavy-content')
    </div>
</div>
```

**効果:**
- 体感速度が向上
- ユーザーが待ち時間を理解できる

### Option 3: 遅延ロード（activitiesなど）

**現状:**
```php
public function loadData($id): void
{
    $this->file = AttachedFile::with(['activities'])->find($id);
}
```

**改善案:**
```php
#[Computed]
public function activities()
{
    if ($this->selectedTab !== 'history') {
        return collect();  // Historyタブ以外では読み込まない
    }
    return $this->file->activities;
}
```

**効果:**
- ドロワー開閉時のクエリ削減
- 2000ms → 1800ms程度（効果は限定的）

---

## 📋 推奨される実装順序

### Phase 1: 画像ログの修正確認（即実施）

**修正内容:**
- ✅ `@this.call()` → `$wire.logPerformance()`

**確認方法:**
```bash
# ログクリア
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json

# ブラウザで画像を開く
# ログ確認
./vendor/bin/sail logs -f | grep "image_preview"
```

**期待結果:**
- ブラウザコンソール: "Image preview loaded"
- Laravelログ: "image_preview_load"
- JSON: データが記録される

### Phase 2: wire:ignoreで検索を高速化（1時間）

**実装:**
1. content.blade.phpの検索部分を`wire:ignore`で囲む
2. Alpine.jsでハイライト処理を実装
3. `searchKeyword`のみLivewireと同期（`@entangle`）

**期待効果:**
- 検索: 1500ms → <50ms ✅
- フォーカス遅延: 解消 ✅
- UIブロック: なし ✅

### Phase 3: activitiesの遅延ロード（30分）

**実装:**
1. `loadData()`から`activities`を除外
2. `#[Computed] public function activities()`を実装
3. Historyタブ選択時のみ読み込み

**期待効果:**
- ドロワー開閉: 2000ms → 1800ms
- Historyタブ: +200ms（許容範囲）

---

## 🔬 測定項目の整理

### 現在測定できているもの

| メトリクス | 測定箇所 | 記録先 | 状態 |
|-----------|---------|--------|------|
| drawer_open | file-inspector.blade.php | ✅ ログ + JSON | 動作中 |
| tab_switch | file-inspector.blade.php | ✅ ログ + JSON | 動作中 |
| search_keyword_update | FileInspector.php | ✅ ログ + JSON | 動作中 |
| search_render | content.blade.php | ✅ ログ + JSON | 動作中（修正済み） |
| image_preview_load | preview.blade.php | ✅ ログ + JSON | **修正済み** |

### これから測定すべきもの

| メトリクス | 目的 | 実装難易度 |
|-----------|------|-----------|
| focus_delay | フォーカス遅延の実測 | 中 |
| render_blocking | レンダリング中のUI操作試行 | 高 |
| cache_hit_rate | sessionStorageの効果測定 | 低 |

---

## 📊 まとめ

### 俯瞰的な分析結果

**共通の根本原因: Livewireの全体レンダリング（1500-2000ms）**

**影響:**
1. ドロワー開閉が遅い
2. 検索が遅い
3. **UIがブロックされる** → フォーカス遅延

**既存の最適化:**
- キャッシング ✅ → 効果なし（全体レンダリングが問題）
- デバウンス ✅ → 5秒遅延は解消、1.5秒遅延が残る
- PHP 8.4修正 ✅ → 効果なし（警告は消えたが速度改善なし）

**解決策:**
- **wire:ignoreで検索をフロントエンド化** ← 最も効果的
- activitiesの遅延ロード ← 補助的
- 段階的な表示 ← UX改善

**次のステップ:**
1. ✅ 画像ログ修正の確認（`$wire.logPerformance`に変更済み）
2. ✅ npm run buildで劇的な改善を確認
3. ⏳ キーワード検索のwire:ignore実装（残る唯一の問題）

---

**分析完了日:** 2025年12月31日  
**更新日:** 2025年12月31日  
**結論:** npm run buildでフロントエンド問題が解決。残るはキーワード検索のみ。  
**次のアクション:** 検索のwire:ignore実装（1500ms → <50ms）

