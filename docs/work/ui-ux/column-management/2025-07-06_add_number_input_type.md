# 台帳入力タイプ「number」の実装計画 (最終版)

## 1. 概要

台帳の項目として、ユーザーが数値を入力できる「数値型 (number)」を追加する。
この入力は、作業者がメーターの値を読み取るなどのユースケースを想定し、**テキスト入力欄**と**スライダー**を設ける。**Livewire** を用いて双方の値を連動させる実装を行う。

## 2. 要件

- **UIコンポーネント**:
    - テキスト入力欄 (`<input type="number">`)
    - 範囲スライダー (`<input type="range">`)
- **UIの挙動 (Livewire実装)**:
    - テキスト入力とスライダーの値を、Livewireコンポーネントのプロパティに `wire:model.live` でバインドする。
- **台帳定義で設定可能な項目**:
    - **下限値 (min)**: 必須。
    - **上限値 (max)**: 必須。
    - **ステップ (step)**: 必須。数値の刻み幅 (例: 0.1)。
    - **単位 (unit)**: 任意。入力欄の横に表示するテキスト (例: "℃")。

## 3. 検討事項

- **パフォーマンス**: Livewireでスライダーをリアルタイム同期させると、操作中に多数のサーバー通信が発生し、UIの応答が遅延する可能性がある。まずはこの方式で実装し、操作性に問題があれば Alpine.js への切り替えを検討する。

## 4. 関連ファイルと実装方針

台帳の作成・編集画面は、それぞれ `livewire:ledger.create-column` と `livewire:ledger.modify-column` コンポーネントによって実現されている。これらのコンポーネントを直接修正する。

### Step 1: `ColumnDefine` モデルの拡張 (完了)

- **ファイル:** `app/Models/ColumnDefine.php`
- **タスク:** `$min`, `$max`, `$step`, `$unit` プロパティと関連メソッドを追加済み。

### Step 2: 台帳定義編集UIの改修 (完了)

- **ファイル:**
    - `app/Livewire/LedgerDefine/ModifyColumn.php`
    - `resources/views/livewire/ledger-define/modify-column.blade.php`
    - `lang/ja/ledger.php`
    - `lang/ja/validation.php`
- **タスク:** `number` 型のカラムに `min`, `max`, `step`, `unit` を設定するUIとロジックを実装済み。`min`, `max`, `step` の関係性を考慮したバリデーションも強化済み。

### Step 3: 台帳入力UIの改修 (Livewire)

- **ファイル:**
    - `app/Livewire/Ledger/CreateColumn.php`
    - `resources/views/livewire/ledger/create-column.blade.php`
    - `app/Livewire/Ledger/ModifyColumn.php`
    - `resources/views/livewire/ledger/modify-column.blade.php`
    - `resources/views/components/ledger/form/number.blade.php` (新規作成)
- **タスク:**
    - **ビュー (`*.blade.php`)**: 
        - `number` 型のカラムの場合、`@if($column['type'] === 'number')` で分岐させる。
        - 内部に `<input type="number">` と `<input type="range">` を配置する。
        - 両方の入力要素を、`wire:model.live` を使って同じプロパティ（例: `$content.{$column['id']}`）にバインドする。
        - 各入力要素に、`ColumnDefine` から取得した `min`, `max`, `step` 属性を正しく設定する。
        - `unit` があれば、入力欄の横に表示する。
    - **コンポーネント (`*.php`)**: 
        - ビューに渡すデータ構造に変更はないため、基本的にはビューの修正が中心となる。
    - **`number.blade.php` (新規)**: `number` 型の入力UIをBladeコンポーネントとして実装済み。

### Step 4: バリデーションルールの追加 (完了)

- **ファイル:**
    - `app/Livewire/Ledger/CreateColumn.php`
    - `app/Livewire/Ledger/ModifyColumn.php`
- **タスク:** `number` 型のカラムに対して、`ColumnDefine` の設定値 (`min`, `max`, `step`) に基づいたバリデーションルール (`numeric`, `min`, `max`, `multiple_of`) を動的に追加済み。

### Step 5: テストコードの作成 (完了)

- **ファイル:** `tests/Feature/Livewire/LedgerColumnValidationTest.php`
- **タスク:** `number` 型のバリデーションを検証する Feature テストを追加済み。

## 5. 作業ステップ

1.  `ColumnDefine` モデルの修正。(完了)
2.  Livewireコンポーネント (`ModifyColumn`) のバックエンドとフロントエンドを修正。(完了)
3.  台帳入力用のLivewireコンポーネントとビューを修正し、テキストとスライダーの連携UIを実装。(完了)
4.  バリデーションルールを実装。(完了)
5.  Featureテストを実装。(完了)
6.  ブラウザでUIの操作性を評価し、今後の対応を判断する。(完了)
7.  **台帳レコード登録UIのスライダー両端に最小値と最大値を単位付きで表示する。**
8.  **詳細画面やリスト画面で登録された数値を単位付きで表示する。**
