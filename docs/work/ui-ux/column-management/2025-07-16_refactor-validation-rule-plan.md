# バリデーションルール生成ロジックのリファクタリング計画

## 1. 目的

現在 `App\Livewire\Ledger\CreateColumn` クラスに集中している、台帳データ入力時のバリデーションルール生成ロジックをリファクタリングする。各カラムの型（`InputType`）に応じたバリデーションルールを、それぞれの `InputType` サブクラス（`NumberType`, `TextType` など）に責務として移譲することで、コードの保守性、拡張性、再利用性を向上させる。

## 2. 現状の課題

- **責務の集中:** `CreateColumn` の `rules()` メソッドが、すべてのカラムタイプのバリデーションルールを知っている必要があり、肥大化している。
- **拡張性の低下:** 新しいカラムタイプを追加するたびに、`CreateColumn` クラスを修正する必要がある。
- **再利用性の欠如:** 同様のバリデーションロジックを別の場所（APIなど）で再利用するのが困難。

## 3. アプローチ

**ハイブリッドアプローチ**を採用する。

- **`InputType` サブクラスの責務:**
    - `NumberType` の `min` `max` `step` や、`AutoNumberType` の文字長制限など、**自身の型に固有のプロパティに基づいたバリデーションルール**を生成する責務を持つ。
- **利用側コンポーネント (`CreateColumn`) の責務:**
    - `required` や `unique` といった、**カラム定義の共通設定に依存し、かつアプリケーションのコンテキスト（`ledgerId`など）を必要とするバリデーションルール**を付与する責務を持つ。

このアプローチにより、`InputType` の独立性を保ちつつ、責務を適切に分離する。

## 4. 実装計画と進捗

### ステップ 1: `InputType` インターフェースの変更

- **目的:** 型固有のバリデーションルールを取得するための共通メソッドを定義する。
- **作業内容:**
    1. `app/Models/ColumnTypes/InputType.php` の `InputType` インターフェースに、新しいメソッド `getValidationRules(): array` を追加する。
- **進捗:** **完了**

### ステップ 2: 各 `InputType` サブクラスへの `getValidationRules` メソッドの実装

- **目的:** 各クラスが、自身の型に特化したバリデーションルールを返すようにする。
- **作業内容:**
    1. **`NumberType.php`:** `numeric`, `min`, `max`, `multiple_of` のルールを返すように実装した。
    2. **`AutoNumberType.php`:** `string`, `max:(文字長)` のルールを返すように実装した。
    3. **`TextType.php`, `TextareaType.php`:** `string` のルールを返すように実装した。
    4. **`DateType.php`:** `date_format:Y-m-d` のルールを返すように実装した。
    5. **`CheckboxType.php`, `SelectType.php`:** 基本的な型制約（`array` または `string`）を返すように実装した。
    6. **`FilesType.php`, `PhoneNumberType.php`:** それぞれに対応するルールを返すように実装した。
- **進捗:** **完了**

### ステップ 3: `CreateColumn` コンポーネントの `rules` メソッドをリファクタリング

- **目的:** `InputType` から取得したルールと共通ルールを組み合わせて、最終的なバリデーションルールを構築するようにロジックを修正する。
- **作業内容:**
    1. `app/Livewire/Ledger/CreateColumn.php` の `rules()` メソッドを修正した。
    2. ループ内で、まず `$column->getInputType()->getValidationRules()` を呼び出し、ベースとなるルールを取得した。
    3. その後、`$column->required` や `$column->unique` のフラグをチェックし、`required` ルールや `UniqueColumnValue` / `UniqueAutoNumber` ルールを配列に追加するようにした。
- **進捗:** **完了**

## 5. 追加で発生した問題と対応 (完了済み)

### 5.1. 自動採番ルールの見直し

- **問題:** 自動採番のバリデーションルールが最大文字数 (`max`) であったため、枝番などの付与に柔軟に対応できなかった。
- **対応:** `AutoNumberType.php` の `getValidationRules` を修正し、最大文字数制限を撤廃。代わりに、**最小文字数 (`min`)** を「接頭辞の文字数 + 番号の桁数」とするように変更した。

### 5.2. 台帳定義画面での数値設定が保存されない

- **問題:** 台帳定義の編集画面で、数値型 (`number`) の `min`, `max`, `step`, `unit` を入力しても保存されなかった。
- **原因:** `modify-column.blade.php` の `wire:model` で指定されていたパスが、リファクタリング後のデータ構造 (`columns.{{$index}}.options.min` など) と一致していなかった。
- **対応:** `resources/views/livewire/ledger-define/modify-column.blade.php` を修正し、`wire:model` のパスを `columns.{{$index}}.options.min` のように正しいパスに変更した。

### 5.3. 台帳詳細画面で数値の単位が表示されない

- **問題:** 台帳の登録・詳細表示画面で、数値型の項目の横に単位が表示されなかった。
- **原因:** 表示ロジックを担う `App\Services\Ledger\ColumnHtmlService` が、リファクタリング後のデータ構造に対応しておらず、`unit` プロパティを `ColumnDefine` オブジェクトから直接取得しようとしていた。
- **対応:** `app/Services/Ledger/ColumnHtmlService.php` の `show` メソッドを修正し、`$columnDefineData->getInputType()->unit` を介して単位を取得するように変更した。

## 6. 最終的な状態

- バリデーションルールの生成ロジックは、各 `InputType` サブクラスと `CreateColumn` コンポーネントに適切に責務が分離された。
- 自動採番のバリデーションルールが、より柔軟な最小文字数制限に変更された。
- 台帳定義画面および台帳詳細画面におけるデータ表示の不整合が解消された。

**全ての計画と追加の問題対応は完了しました。**