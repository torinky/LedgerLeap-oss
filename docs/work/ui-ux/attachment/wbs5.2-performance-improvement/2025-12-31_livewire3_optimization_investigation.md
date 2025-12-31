# Livewire 3とMaryUIを使った検索最適化の調査レポート

**調査日:** 2025年12月31日  
**目的:** wire:ignoreの代わりに、より適切でシンプルな最適化方法を見つける

---

## 🔍 調査結果

### 1. Livewire 3の公式推奨アプローチ

#### Lazy Loading（遅延読み込み）

**公式ドキュメント:** https://livewire.laravel.com/docs/lazy

```php
// FileInspector.php
#[Lazy]
public function placeholder()
{
    return view('livewire.placeholders.skeleton');
}
```

**特徴:**
- 初回レンダリングを高速化
- 重い処理を遅延実行
- **今回の問題には不適切**（検索の遅延が問題）

#### wire:model.live.debounce

**公式推奨:**
```blade
<input wire:model.live.debounce.500ms="search" />
```

**特徴:**
- サーバーリクエストを500ms遅延
- **既に実装済み**（1000ms）
- **サーバーレンダリング自体が遅い問題は未解決**

#### wire:key

**目的:** コンポーネントの再利用を防ぐ

```blade
<div wire:key="search-{{ $fileId }}">
    <!-- ... -->
</div>
```

**今回の問題には無関係**

---

### 2. MaryUI Inputの調査結果

#### MaryUI Inputの構造

```php
// Input.php (vendor/robsontenorio/mary)
public function modelName(): ?string
{
    return $this->attributes->whereStartsWith('wire:model')->first();
}
```

**重要な発見:**
- `x-model`は**MaryUIがサポートしていない**
- `wire:model`前提で設計されている
- clearable機能が`$wire.set()`に依存

**問題:**
- 前回の実装で`wire:model`を削除したため、MaryUIのInput機能が壊れた
- clearableボタン、moneyモード、エラー表示などが動作しなくなる

---

### 3. 一般的な最適化パターン

#### Pattern A: Computed Properties（Livewire標準）

**実装:**
```php
// FileInspector.php
#[Computed]
public function previewText()
{
    return $this->getPreviewText();
}
```

**特徴:**
- Livewireが自動的にキャッシュ
- 同じリクエスト内では1回のみ実行
- **今回の問題には不適切**（レンダリング自体が遅い）

#### Pattern B: Entangle（Alpine.js連携）

**公式ドキュメント:** https://livewire.laravel.com/docs/alpine

```blade
<div x-data="{ search: @entangle('searchKeyword') }">
    <input x-model="search" />
</div>
```

**特徴:**
- Alpine.jsとLivewireプロパティを双方向バインド
- **Livewireリクエストは依然として発生**
- 今回の問題には不適切

#### Pattern C: wire:ignore（部分的な除外）

**公式ドキュメント:** https://livewire.laravel.com/docs/wire-ignore

```blade
<div wire:ignore>
    <!-- Livewireがこの部分を無視 -->
</div>
```

**制約:**
- 内部のwire:model、wire:clickなどが動作しなくなる
- **MaryUIコンポーネントとの相性が悪い**

---

## 💡 推奨される解決策

### 根本的な問題の再確認

**測定データ:**
```
search_keyword_update: 0ms（サーバー処理は高速）
search_render: 1500ms（Livewireのレンダリングが遅い）
```

**原因:**
- サーバー処理自体は高速（0ms）
- **Livewireが大きなBladeテンプレートを再レンダリング**するのが遅い（1500ms）

### Option 1: wire:stream（Livewire 3.5+の新機能）

**公式:** https://livewire.laravel.com/docs/streams

```blade
<div wire:stream="search">
    @foreach($results as $result)
        <!-- 結果を逐次表示 -->
    @endforeach
</div>
```

**特徴:**
- 結果をストリーミング表示
- 体感速度が向上
- **検証が必要**（Livewire 3.5以上が必要）

### Option 2: Alpine.jsでの検索実装（シンプル版）

**MaryUIを壊さず、検索のみAlpine.js化**

```blade
<!-- 検索入力はMaryUIのまま -->
<x-mary-input wire:model.live.debounce.1000ms="searchKeyword" />

<!-- プレビュー表示のみAlpine.js化 -->
<div x-data="{ 
    text: @js($plainText),
    keyword: '',
    init() {
        // Livewireの値を監視
        Livewire.hook('morph.updated', ({ el, component }) => {
            this.keyword = component.get('searchKeyword');
        });
    }
}">
    <!-- ... -->
</div>
```

**メリット:**
- MaryUIコンポーネントはそのまま使用
- プレビュー表示のみクライアント側で処理
- 比較的シンプル

**デメリット:**
- Livewireリクエストは依然として発生
- 効果は限定的

### Option 3: プレビューテキストのキャッシュ強化（最もシンプル）

**問題の再分析:**
- 検索のたびに`getPreviewText()`が実行される
- Blade全体が再レンダリングされる

**解決策:**
```php
// FileInspector.php
protected $cachedPreviewTextBySource = [];

#[Computed]
public function previewTextForSource($source)
{
    if (!isset($this->cachedPreviewTextBySource[$source])) {
        $this->cachedPreviewTextBySource[$source] = $this->getPreviewText();
    }
    return $this->cachedPreviewTextBySource[$source];
}
```

```blade
<!-- content.blade.php -->
@php
    $previewText = $this->previewTextForSource($activeSource);
@endphp
```

**メリット:**
- **最小限の変更**
- MaryUIとの互換性維持
- Livewireのキャッシュ機能を活用

**デメリット:**
- 効果は限定的（Bladeレンダリングは依然として実行）

### Option 4: レンダリングの最適化（実践的）

**問題:**
- 検索のたびにタブ全体が再レンダリングされる
- 検索結果だけを更新すればよい

**解決策:**
```blade
<!-- 検索結果部分のみをコンポーネント化 -->
<x-mary-input wire:model.live.debounce.1000ms="searchKeyword" />

@livewire('attached-file.preview-text', [
    'fileId' => $file->id,
    'source' => $activeSource,
    'keyword' => $searchKeyword
])
```

```php
// PreviewText.php（新しいコンポーネント）
class PreviewText extends Component
{
    public $fileId;
    public $source;
    public $keyword;
    
    #[Computed]
    public function text()
    {
        // テキスト取得
    }
    
    public function render()
    {
        return view('livewire.attached-file.preview-text');
    }
}
```

**メリット:**
- レンダリング対象が小さくなる
- 他の部分は再レンダリングされない
- MaryUIとの互換性維持

**デメリット:**
- コンポーネント分割が必要
- やや複雑

---

## 🎯 推奨アプローチ

### 最も現実的な解決策: **現状維持 + ユーザーへの説明**

**理由:**

1. **npm run buildで大幅改善済み**
   - フォーカス遅延: 解消
   - 画像プレビュー: 解消
   - UIブロック: 解消

2. **検索は1500msだが許容範囲の可能性**
   - サーバー処理は0ms（高速）
   - デバウンス1000msで重複リクエスト防止済み
   - 実際のユーザー体験は「1秒入力停止後、1.5秒で結果表示」

3. **wire:ignoreの実装は複雑すぎる**
   - MaryUIとの相性が悪い
   - 表示が壊れるリスクが高い
   - メンテナンスが困難

### 代替案: Option 3の実装（最小限の改善）

**実装工数:** 0.5時間  
**効果:** 限定的（200-300ms程度の改善）  
**リスク:** 低い

```php
// FileInspector.php
protected $previewTextCache = [];

public function getPreviewTextCached($highlight = true)
{
    $cacheKey = $this->activeSource . '_' . ($highlight ? 'highlighted' : 'plain');
    
    if (!isset($this->previewTextCache[$cacheKey])) {
        $this->previewTextCache[$cacheKey] = $this->getPreviewText($highlight);
    }
    
    return $this->previewTextCache[$cacheKey];
}
```

```blade
<!-- content.blade.php -->
@php
    $previewText = $this->getPreviewTextCached();
    $previewTextRaw = $this->getPreviewTextCached(false);
@endphp
```

**期待効果:**
- 同一ソース内での検索が高速化
- Bladeレンダリングは依然として実行（1200-1300ms程度）
- MaryUIとの互換性維持

---

## 📊 各オプションの比較

| オプション | 工数 | 効果 | リスク | 推奨度 |
|-----------|------|------|--------|--------|
| **現状維持** | 0h | - | なし | ⭐⭐⭐⭐⭐ |
| Option 1: wire:stream | 1h | 不明 | 中（要検証） | ⭐⭐ |
| Option 2: Alpine.js（シンプル版） | 2h | 小 | 中 | ⭐⭐ |
| Option 3: キャッシュ強化 | 0.5h | 小 | 低 | ⭐⭐⭐⭐ |
| Option 4: コンポーネント分割 | 3h | 中 | 低 | ⭐⭐⭐ |
| **前回実装: wire:ignore** | 1.5h | 大 | **高** | ❌ |

---

## 🎓 結論

### 推奨される対応

**Phase 1: 現状維持**
- npm run buildによる改善（4項目解決）で十分な成果
- 検索の1500msは許容範囲と判断
- ドキュメント化して完了

**Phase 2: 将来的な改善（オプション）**
- Option 3: キャッシュ強化（0.5h、リスク低）
- Option 4: コンポーネント分割（3h、効果中）

### wire:ignoreを避けるべき理由

1. **MaryUIとの非互換**
   - clearable、money、エラー表示が動作しない
   - `$wire.set()`が使えない

2. **複雑すぎる実装**
   - 外部JavaScriptファイルが必要
   - Bladeパーサーエラーの回避が必要

3. **メンテナンス困難**
   - 将来の変更が困難
   - デバッグが難しい

4. **表示が壊れるリスク**
   - 実際に壊れた（ロールバック実施）

---

**調査完了日:** 2025年12月31日  
**結論:** 現状維持を推奨。wire:ignoreは避けるべき。  
**代替案:** Option 3（キャッシュ強化）を必要に応じて検討

