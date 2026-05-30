# Livewire & UI/UX ベストプラクティス

LedgerLeap における Livewire コンポーネント設計と UI/UX 実装の標準ガイドラインです。

## 1. コンポーネント設計と状態管理

### Single Source of Truth (単一の真実)
複雑な親子関係（例：`IndexManager` と `RecordsTable`）では、状態管理を親コンポーネントに集約します。
- **親の責務**: 検索条件、フィルタ状態、選択済みID、ページネーションの状態を保持。
- **子の責務**: 渡された状態（`#[Reactive]` プロパティ）に基づいた描画と、ユーザー操作の親への伝達。

### 直接的な親子通信
Livewire 3 では、イベントのディスパッチ (`Livewire.dispatch`) よりも親への直接呼び出しを推奨します。
- **手法**: Blade 内で `$parent.methodName()` を使用するか、Alpine.js から `$wire.$parent.methodName()` を呼び出す。
- **利点**: 
  - イベントのバブリング待ちがなく、レスポンスが高速。
  - Livewire の `wire:loading.target` が親のメソッドを正確に捕捉できる。
  - 通信回数の削減（Dispatch + 受信側リクエスト の2回ではなく1回で完結）。

## 2. ローディング戦略 (3ティア + 1方式)

システム全体で一貫したフィードバックを提供するため、以下のティアに分けて実装します。

### Tier 0: グローバル・ナビゲーション
- **用途**: ページ遷移 (`wire:navigate`)。
- **表現**: 画面最上部のプログレスバー。

### Tier 1: フルコンテント・ロード (Heavy Actions)
- **用途**: フォルダ移動、大規模な検索、初期ロード。
- **表現**: 構造的スケルトン（メガスケルトン）。現在のコンテンツを `wire:loading.remove` で隠し、スケルトンを表示。
- **判断基準**: 情報の構造が大きく変わる場合。

### Tier 2: セクション・ロード (Light Actions)
- **用途**: ソート、フィルタ、ページネーション、アイテムのトグル表示。
- **表現**: 現在の表示を維持したまま、透過オーバーレイ (`opacity-50`) + 操作無効化 (`pointer-events-none`)。
- **判断基準**: リストの内容は変わるが、枠組みは維持される場合。

### Tier 3: マイクロ・インタラクション
- **用途**: ボタンクリック、インラインのステータス変更。
- **表現**: maryUI の `spinner` 属性。

## 3. パフォーマンスと視覚的安定性

### `wire:key` の固定化
動的な ID (特に `Hash::make()`) を `wire:key` に使用してはいけません。
- **理由**: レンダリングのたびにキーが変わると、Livewire はコンポーネントを完全に破棄して再生成します。これは入力中のフォーカス喪失、点滅、パフォーマンス低下の原因となります。
- **正しい方法**: `wire:key="ledger-records-stable"` や `wire:key="item-{{ $id }}"` のような、ライフサイクルを通じて不変なキーを使用します。

### Font Awesome 6 アイコンの安定化
プレースホルダー内のアイコンが「？」になったり点滅するのを防ぐ設定です。
- **CSS**: `Font Awesome 6 Free` をフォントファミリーの先頭に配置。
- **Style**: `font-weight: 900 !important` を適用。

### 通信の局所化 (`wire:target`)
`wire:loading` には必ず `wire:target` を指定します。
- ターゲットを絞ることで、サイドバーや他の関係ないコンポーネントが不要に反応（点滅）するのを防ぎます。
- 親コンポーネントで複数のアクションを監視する場合は、`$heavyTargets` や `$lightTargets` のようにターゲット文字列を整理して管理します。

### DaisyUI テーブルのスティッキーヘッダー (`table-pin-rows`)
DaisyUIの`table-pin-rows`クラスを使用してスティッキーヘッダーを実装する際の必須要件です。
- **必須**: テーブルを囲む親要素に**高さ制限**を設定する必要があります。
  - 例: `max-h-[70vh]`, `h-96`, `h-[500px]` など
- **理由**: 高さ制限がないと、テーブルは無限に伸びてしまい、スティッキー動作が発生しません。
- **実装例**:
  ```blade
  <div class="overflow-x-auto max-h-[70vh]">
      <table class="table table-pin-rows">
          <thead>...</thead>
          <tbody>...</tbody>
      </table>
  </div>
  ```
- **背景色**: DaisyUIが自動的にテーマの背景色を適用するため、カスタムCSSは不要です。

## 4. Alpine.js と daisyUI の CSS 詳細度に関する注意点

### `x-show` と `!important` の競合

Alpine.js の `x-show` は要素を非表示にする際に **inline style** で `display: none` を書き込む。  
CSS に `display: block !important` が存在すると、**inline style よりも `!important` の CSS が優先**されるため `x-show` による折りたたみが機能しなくなる。

```css
/* ❌ 悪い例: x-show が効かなくなる */
.some-class {
    display: block !important;
}

/* ✅ 良い例: !important なし → x-show の inline style が優先される */
.some-class {
    display: block;
}
```

> **法則:** `!important` の優先順位は「CSSの!important > inline style > 通常CSS」ではなく、  
> 正確には「inline style の !important > CSS の !important > inline style > 通常CSS」。  
> Alpine.js の `x-show` は **!important なし** の inline style を使うため、CSS 側に `!important` があると負ける。

### daisyUI の `:where()` セレクターと上書き

daisyUI v5 の `.menu` は `:where()` 擬似クラスを多用しており、**詳細度が 0** になる。  
これにより、**通常のクラスセレクター**（詳細度 > 0）なら `!important` なしで簡単に上書きできる。

```css
/* daisyUI 内部（詳細度0）:where() 内で display: grid が付与される */
/* :where(li:not(.menu-title) > :not(ul,details,...)) { display: grid } */

/* ✅ 詳細度が 1 以上のセレクターで上書き可能（!important 不要） */
.menu .tree li > div.tree-collapse {
    display: block; /* daisyUI の display:grid を上書き */
}
```

### daisyUI の `li > div` への `display: grid` 強制付与

`.menu` 内の `li > div`（`.btn` 以外）に daisyUI が `display: grid` を自動付与する。  
Alpine.js の `x-show` で制御する `div` がこのセレクターにマッチする場合は必ずリセットすること。

```css
/* tree.css での実例 */
.menu .tree li > div.tree-collapse {
    display: block;               /* grid → block（!important なしで x-show が正常動作） */
    grid-template-rows: unset !important;
    grid-auto-columns: unset !important;
    grid-auto-flow: unset !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    background-color: transparent !important;
    width: 100% !important;
}
```

---

## 5. DaisyUI Drawer のサイドバー固定（Sticky/Fixed）

### 問題

`xl:drawer-open` 時、`drawer-side` の `ul.menu` はページスクロールに追従して画面外に流れる。  
DaisyUI が期待する `sticky` が効かない理由は、**親要素 `.drawer` がスクロールコンテナでない**ため。

### 解決策: `position: fixed` を inline style で強制

```html
{{-- appWithDrawer.blade.php --}}
<div class="drawer-side z-40 xl:w-64 2xl:w-72"
    style="position: fixed; top: 64px; height: calc(100vh - 64px); overflow-y: auto; overflow-x: hidden;">
    <label for="app-drawer" class="drawer-overlay w-full"></label>
    <ul class="menu overflow-y-auto overflow-x-hidden h-full xl:w-64 2xl:w-72 p-2">
        {{ $drawer ?? '' }}
    </ul>
</div>
```

- `top: 64px` — ナビバー高さ（`pt-20` = 80px ではなく実測で調整）
- `height: calc(100vh - 64px)` — ビューポート全体からナビバー分を引いた高さ
- `overflow-y: auto` — サイドバー内のコンテンツが独立してスクロール可能

> **注意:** Tailwind の `sticky` や `top-16` は、親要素に `overflow: hidden/auto` があると効かない。  
> その場合は迷わず `position: fixed` を使うこと。

### Alpine.js による選択ノード自動スクロール

サイドバーが独立スクロールになった後、フォルダ切り替え時に選択ノードが画面外になる場合がある。  
Livewire の再レンダリング後に確実に動作させるため `$nextTick` が**必須**。

```html
{{-- resources/views/components/folder/tree.blade.php --}}
@if ($folder->id == $currentFolderId)
    x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
@endif
```

---

## 6. 既存の実装例
- 参照先: `app/Livewire/Ledger/IndexManager.php`, `resources/views/livewire/ledger/index-manager.blade.php`
- ローディング統合計画: `docs/work/ui-ux/2026-01-25_loading_unification_plan.md`
- フォルダツリー実装: `resources/views/components/folder/tree.blade.php`, `resources/css/tree.css`
