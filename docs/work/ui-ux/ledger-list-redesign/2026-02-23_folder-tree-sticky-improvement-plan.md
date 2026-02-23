# フォルダツリー固定表示・深い階層対応 改善提案 (2026-02-23)

**作成日:** 2026年2月23日  
**ステータス:** ✅ Sprint 1・2・3・4 実装完了（2026-02-23）  
**GitHub Issue:** [#73 台帳リスト画面: フォルダツリーのスクロール追従・深い階層対応](https://github.com/torinky/LedgerLeap/issues/73)  
**関連ドキュメント:**
- [台帳リスト画面 UIリニューアル計画](./2026-02-11_design-plan.md)
- [ペルソナ・ユースケース・シナリオ](../../../function/PersonaUseCaseScenario.md)
- [`app/Livewire/Folder/Tree.php`](../../../../app/Livewire/Folder/Tree.php)
- [`resources/views/components/folder/tree.blade.php`](../../../../resources/views/components/folder/tree.blade.php)
- [`resources/views/layouts/appWithDrawer.blade.php`](../../../../resources/views/layouts/appWithDrawer.blade.php)

---

## 1. 課題の整理

### 1.1. 現象

| 画面幅 | 現在の挙動 | 問題 |
| :--- | :--- | :--- |
| **狭い（モバイル/タブレット）** | DaisyUI Drawer でハンバーガーメニューから開閉 | なし（意図的な設計） |
| **広い（`xl` 以上）** | `xl:drawer-open` で左サイドバーとして常時表示 | **縦スクロールするとツリーがビューポート外に流れる** |

### 1.2. 影響するペルソナとシナリオ

**実務担当者（ペルソナ1.1）**  
- 日報・申し送りなどを確認しながらフォルダを行き来する。  
- レコード一覧を 50〜100 件スクロールした状態でフォルダを切り替えたいとき、ツリーが見えないためページ最上部に戻る手間が生じる。

**現場リーダー/作業班長（ペルソナ1.3）**  
- 医療・製造現場では組織階層が深く（例: 病院 → 診療科 → 病棟 → チーム）、ツリーが縦に長い。  
- 深い階層のフォルダを展開した状態でスクロールすると、選択中のノードが視野外になり現在地を見失う。

**管理者（ペルソナ1.2）**  
- 権限設定・監査のため複数フォルダを参照する操作が多い。  
- ツリーが 20〜30 ノード以上になると縦スクロールが必要となり、目的のフォルダを探しにくい。

---

## 2. 改善の方向性

ペルソナが求める体験から、以下の 3 軸で改善を検討する。

| 軸 | 概要 |
| :--- | :--- |
| **A. 固定（Sticky）表示** | スクロールしてもツリーが追従し、常に操作可能にする |
| **B. 深い階層への対応** | 多数ノード展開時でもツリー自体がスクロール可能にする |
| **C. 現在地の視認性** | 選択中フォルダが常に視野内に収まるようにする |

---

## 3. 具体的な改善提案

### 提案 1: サイドバー内スクロール + Sticky（最優先・低コスト）

**概要:**  
`drawer-side` 内のツリーコンテナに `sticky top-[ナビ高さ]` と独立した `overflow-y-auto` + `max-h` を付与する。

```
画面全体のスクロール: コンテンツエリアのみ流れる
サイドバー: ナビバーの高さ分だけ下にある固定領域として残る
└── ツリーコンテナ: ビューポートに収まる高さで、それ以上はツリー自体がスクロール
```

**実装イメージ:**

```html
{{-- appWithDrawer.blade.php の drawer-side --}}
<div class="drawer-side z-40 h-screen">
    <label for="app-drawer" class="drawer-overlay"></label>
    {{-- sticky + overflow-y-auto で独立スクロール領域を形成 --}}
    <ul class="menu sticky top-20 overflow-y-auto max-h-[calc(100vh-5rem)] w-72 ...">
        {{ $drawer ?? '' }}
    </ul>
</div>
```

**メリット:** レイアウト変更が最小限。Tailwind ユーティリティのみで対応可能。  
**注意点:** DaisyUI の `drawer` 実装（`h-screen`）と組み合わせる際、`drawer-side` コンテナ自体の高さ設定との兼ね合いを要確認。

---

### 提案 2: ツリーの仮想スクロール（中〜高コスト・大規模対応）

**概要:**  
ノード数が 100 を超えるような大規模テナントを想定し、DOM に描画するノード数を画面内のものだけに限定する仮想スクロールを導入する。

**実装候補:**
- **Alpine.js + カスタム実装:** ビューポート内のノードのみ `v-if` 相当で描画制御
- **`@tanstack/virtual`（JS ライブラリ）:** Livewire の Alpine.js 統合と組み合わせる

**メリット:** 深さ 10 以上・ノード数 200 以上でも DOM 負荷が一定。  
**デメリット:** 実装コスト大。`<ul>` のネスト構造（再帰的 Blade コンポーネント）との相性が悪く、段階的な展開（lazy expand）の仕組みを別途設計する必要がある。  
**判断基準:** 想定ノード数が 50 未満であれば提案 1 で十分。100 を超える運用が見込まれる場合に検討する。

---

### 提案 3: アコーディオン + 折りたたみ状態の永続化（中コスト・UX 向上）

**概要:**  
現在のツリーは全ノードを常時展開している。各フォルダノードをアコーディオン（クリックで開閉）にし、展開状態を `localStorage` に保存することで、再訪時に同じ状態で表示する。

**実装イメージ:**

```javascript
// Alpine.js で展開状態を管理
x-data="{
    expanded: JSON.parse(localStorage.getItem('folderTree') || '{}'),
    toggle(id) {
        this.expanded[id] = !this.expanded[id];
        localStorage.setItem('folderTree', JSON.stringify(this.expanded));
    }
}"
```

**メリット:**
- 使用頻度の低い深いノードを折りたたむことで初期表示が短くなる。
- ユーザーが自分の作業フォルダだけ展開した状態を維持できる。

**デメリット:** 現在の再帰的 Blade コンポーネント構造（`<x-folder.tree>`）に Alpine.js の状態管理を組み込む設計改修が必要。

---

### 提案 4: 選択中ノードの自動スクロール（低コスト・単体で効果大）

**概要:**  
フォルダ選択時に `Element.scrollIntoView({ behavior: 'smooth', block: 'nearest' })` を呼び出し、選択ノードが常に表示領域内に来るようにする。

**実装イメージ（Blade コンポーネント）:**

```html
{{-- 選択中フォルダのアンカー要素に Alpine.js で自動スクロール --}}
<a
    x-init="if ({{ $folder->id == $currentFolderId ? 'true' : 'false' }}) {
        $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))
    }"
    ...
>
```

**メリット:** 数行の変更で体験が大きく向上。提案 1 と併用すると最大効果。

---

## 4. 優先度と実装ロードマップ

| 優先度 | 提案 | 工数目安 | 効果 |
| :---: | :--- | :---: | :--- |
| 🔴 高 | **提案 1:** Sticky + 独立スクロール | 0.5 日 | スクロール時ツリー消失の解消 |
| 🔴 高 | **提案 4:** 選択ノード自動スクロール | 0.25 日 | 現在地視認性の向上 |
| 🟡 中 | **提案 3:** アコーディオン + 状態永続化 | 1〜2 日 | 深い階層・多ノード時のUX向上 |
| 🟢 低 | **提案 2:** 仮想スクロール | 3〜5 日 | 超大規模テナント対応（現状は不要） |

**推奨順序:** まず提案 1 + 4 を組み合わせて適用（合計約 0.75 日）。実際に深い階層のデータで運用してみた後、必要に応じて提案 3 を追加する。

---

## 5. 実装時の注意点

1. **Livewire Reactive との兼ね合い:**  
   `currentFolderId` は `#[Reactive]` プロパティのため、Livewire の再レンダリング後に Alpine.js の状態が初期化されることがある。`x-init` による自動スクロール（提案 4）は `$nextTick` を必ず介すこと。

2. **DaisyUI Drawer の `h-screen` と sticky:**  
   `drawer-side` は `h-screen` + `overflow-y-hidden` が効いている場合がある。`sticky` が効かない場合は親要素の `overflow` 設定を見直す。

3. **モバイル時はドロワーのまま:**  
   `xl:drawer-open` によりモバイルは現行のドロワー開閉方式を維持する。sticky 化はデスクトップ（`xl` 以上）でのみ動作するため、影響範囲はデスクトップに限定される。

4. **`eagerLoadDescendants` のコスト:**  
   現在 `Tree.php` の `mount()` では再帰的 Eager Load を行っている。階層が深い場合クエリ数が増加する可能性がある。必要に応じて `Folder::with('descendants.ledgerDefines')` への切り替えを検討すること（nestedset の `descendants` リレーション活用）。

---

## 6. 関連ファイル

| ファイル | 役割 |
| :--- | :--- |
| `resources/views/layouts/appWithDrawer.blade.php` | ドロワーレイアウト（sticky 適用箇所） |
| `resources/views/livewire/folder/tree.blade.php` | Livewire ツリーコンポーネントのラッパー |
| `resources/views/components/folder/tree.blade.php` | 再帰的ツリー Blade コンポーネント（アコーディオン・自動スクロール適用箇所） |
| `app/Livewire/Folder/Tree.php` | ツリーのデータ取得ロジック |
| `resources/views/livewire/ledger/index-manager.blade.php` | drawer スロットへのツリー埋め込み箇所 |

---

## 7. 実装結果・得られた知見（2026-02-23）

### 7.1 Sprint 1: Sticky + 独立スクロール — 実装結果

`sticky` アプローチは不採用。DaisyUI の `.drawer` 構造では親がスクロールコンテナでないため `sticky` が機能しない。**`position: fixed` を inline style で強制するのが唯一の確実な解決策**だった。

```html
{{-- 採用した実装（appWithDrawer.blade.php） --}}
<div class="drawer-side z-40 xl:w-64 2xl:w-72"
    style="position: fixed; top: 64px; height: calc(100vh - 64px); overflow-y: auto; overflow-x: hidden;">
```

> **学び:** Tailwind の `sticky top-XX` は先祖要素に `overflow: hidden/auto/scroll` があると無効になる。DaisyUI のコンポーネントはこれを内部で設定していることがあるため、確実に固定したい場合は `position: fixed` を使う。

### 7.2 Sprint 2: 選択ノード自動スクロール — 実装結果

提案通りに `x-init` + `$nextTick` + `scrollIntoView` で実装。Livewire の `#[Reactive]` プロパティ変化による再レンダリング後に実行されるため、`$nextTick` は**必須**（外すと DOM 更新前に実行されて無効になる）。

### 7.3 Sprint 3: アコーディオン + localStorage 永続化 — 実装結果と落とし穴

#### 落とし穴 1: `display: block !important` が `x-show` を壊す

daisyUI の `.menu` は `li > div`（`.btn` 以外）に `display: grid` を付与する（`:where()` セレクター、詳細度 0）。これを打ち消すために `tree.css` に `display: block !important` を追加したところ、**Alpine.js の `x-show` が効かなくなった**。

**原因:** Alpine.js の `x-show` は inline style で `display: none` を書き込む。`!important` の CSS は inline style に勝つため、折りたたみ不能になる。

**解決策:** `!important` を外す。daisyUI の `:where()` は詳細度 0 のため、通常のクラスセレクターで `display: block` を指定するだけで上書きできる。

```css
/* ✅ 正解: !important なし → x-show が正常動作する */
.menu .tree li > div.tree-collapse {
    display: block; /* daisyUI の display:grid（詳細度0）を上書き */
    ...
}
```

#### 落とし穴 2: Font Awesome の `<svg>` 置換と Alpine.js の `:style` バインド

折りたたみボタンのアイコン（`<i class="fas fa-chevron-right">`）に Alpine.js の `:style` で回転を適用しようとしたが、Font Awesome が `<i>` を `<svg>` に置換するため `:style` バインドが置換後に消える。

**解決策:** `<button>` 要素自体に `:style` を適用する。Font Awesome は `<button>` を置換しないため確実に動作する。

```html
<button
    x-on:click="toggleOpen()"
    :style="open ? 'transform: rotate(90deg); transition: transform 0.2s ease;' : 'transform: rotate(0deg); transition: transform 0.2s ease;'"
    ...>
    <i class="fas fa-chevron-right text-xs"></i>
</button>
```

#### 落とし穴 3: `<a>` の中に `<button>` をネストするアクセシビリティ違反

当初の設計では `<a>` 内に折りたたみ `<button>` を内包していたが、これは HTML 仕様違反（インタラクティブコンテンツの入れ子禁止）。`<button>` を `<a>` の兄弟として並べ、`flex` で横並びにすることで解消。

#### 行レイアウトの設計（最終形）

```html
<div class="tree-row flex items-center gap-1 overflow-visible">
    <a class="flex items-center gap-1 ... flex-1 overflow-hidden">
        {{-- アイコン（shrink-0） + タイトル（min-w-0 truncate flex-1） --}}
    </a>
    {{-- バッジ（shrink-0） --}}
    {{-- 折りたたみボタン（shrink-0） --}}
</div>
```

- `<a>` が `flex-1` を持つことで、バッジとボタンが自動的に右端に押し出される
- タイトルは `min-w-0 + truncate` で残り幅を使いきりつつはみ出しを省略

### 7.4 残スプリント

| Sprint | 内容 | 状態 |
|---|---|---|
| Sprint 4 | `eagerLoadDescendants()` → `descendants` リレーションへのクエリ最適化 | ✅ 完了 |
| Sprint 5 | 全体回帰テスト | ✅ 完了 |
| Sprint 6 | `DemoDeepHierarchySeeder` 深い階層デモデータ整備 | 🔲 未着手 |

### 7.5 Sprint 4: クエリ最適化 — 実装結果と知見（2026-02-23）

#### 変更内容

`app/Livewire/Folder/Tree.php` の `mount()` で行っていた再帰的 Eager Load を廃止し、kalnoy/nestedset の `descendants` リレーションによる一括取得に置き換えた。

**変更前（再帰的 Eager Load）:**
```php
$this->folders = Folder::whereIsRoot()
    ->with(['ledgerDefines', 'children' => fn($q) => $q->with('ledgerDefines')])
    ->get();
$this->eagerLoadDescendants($this->folders); // 再帰メソッド（階層が深いほどクエリ増加）
```

**変更後（descendants 一括取得）:**
```php
$this->folders = Folder::whereIsRoot()
    ->with(['ledgerDefines', 'descendants' => function ($query) {
        $query->with('ledgerDefines')->defaultOrder();
    }])
    ->get();

foreach ($this->folders as $root) {
    $allNodes = new NestedSetCollection(array_merge([$root], $root->descendants->all()));
    $allNodes->linkNodes(); // children リレーションをメモリ上でポピュレート
}
```

#### 重要な落とし穴: `descendants` と `children` は独立したリレーション

kalnoy/nestedset の `descendants` をEager Loadしても、`children` リレーションは**自動的にポピュレートされない**。ビューは再帰コンポーネントで `$folder->children` を使って描画するため、`descendants` のみでは動作しない。

**解決策:** `NestedSetCollection::linkNodes()` を使ってメモリ上で `children` をポピュレートする。

```php
use Kalnoy\Nestedset\Collection as NestedSetCollection;

// root + descendants を NestedSetCollection にまとめて linkNodes() を呼ぶ
$allNodes = new NestedSetCollection(array_merge([$root], $root->descendants->all()));
$allNodes->linkNodes(); // 各ノードの children リレーションがセットされる
```

`linkNodes()` は `parent_id` でグルーピングして `children` リレーションを各ノードに `setRelation()` するため、追加クエリなしで動作する。

#### クエリ数の改善

| 階層の深さ | 変更前（再帰 Eager Load） | 変更後（descendants） |
|:---:|:---:|:---:|
| 3段（浅い） | ~15 クエリ | **~10 クエリ** |
| 5段（Deep L2〜L5） | ~22 クエリ | **~10 クエリ（階層非依存）** |
| 8段以上（大規模） | 30+ クエリ | **~10 クエリ（変化なし）** |

テストで確認: `it_avoids_n_plus_one_queries_for_ledger_defines` のしきい値を **25→20 未満** に締め直した。

#### テスト追加

- `it_loads_folders_with_ledger_defines_eager_loaded`: `descendants` リレーションがロードされていることを確認するよう更新
- `it_loads_all_descendants_in_deep_hierarchy`（新規）: 5段階層（Deep L2〜L5）が `descendants` で正しく取得されることを確認
- `it_renders_selected_folder_and_ancestors_in_html`: `fa-chevron-down` → `fa-chevron-right` に修正（Sprint 3でアイコン変更済み）

