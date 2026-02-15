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

## 4. 既存の実装例
- 参照先: `app/Livewire/Ledger/IndexManager.php`, `resources/views/livewire/ledger/index-manager.blade.php`
- ローディング統合計画: `docs/work/ui-ux/2026-01-25_loading_unification_plan.md`
