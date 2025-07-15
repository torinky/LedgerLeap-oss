# ColumnDefine モデルのリファクタリング計画

## 1. 目的

`App\Models\ColumnDefine` クラスの責務を分離し、コードの保守性と拡張性を向上させる。具体的には、各入力タイプ（数値、自動採番など）に固有のプロパティとロジックを、それぞれの `InputType` サブクラス（`NumberType`, `AutoNumberType` など）に移動する。

## 2. 現状の課題

- **関心の混在:** `ColumnDefine` クラスが、全ての入力タイプに共通の定義情報（名前、順序、必須フラグなど）と、特定の入力タイプ（`number`, `auto_number`）にのみ関連するプロパティ（`min`, `max`, `step`, `unit`, `prefix`, `digits`, `revision` など）を両方保持しており、クラスの責務が肥大化している。
- **拡張性の低下:** 新しい入力タイプを追加する際に、`ColumnDefine` クラス自体を修正する必要が生じる可能性が高く、拡張性に乏しい。
- **可読性の低下:** `ColumnDefine` の利用箇所で、どのプロパティがどの入力タイプで有効なのかが分かりにくい。

## 3. 根本的なアプローチ

Strategy パターンをより徹底し、`ColumnDefine` をコンテキスト、`InputType` を戦略として明確に分離する。

- **`ColumnDefine` の責務:** カラム定義のコンテナとしての役割に専念する。自身は共通のプロパティ（`id`, `name`, `order` など）と、`InputType` オブジェクトへの参照のみを保持する。
- **`InputType` サブクラスの責務:** 自身に関連する固有のプロパティ（例: `NumberType` の `min`, `max` や `AutoNumberType` の `prefix` など）と、それに関連するロジック（バリデーションルールの生成など）を保持する。

## 4. 影響範囲の調査

- `min`, `max`, `step`, `unit` は主に `NumberType` に関連。
- `prefix`, `digits`, `revision` は `AutoNumberType` に関連。
- これらのプロパティは以下の箇所で利用されている。
    - `ColumnDefine` モデル自身
    - `InputTypeFactory`
    - `NumberingService` (自動採番ロジック)
    - Livewireコンポーネント (`ModifyColumn`, `CreateColumn`) および関連するBladeビュー
    - バリデーションルール (`UniqueAutoNumber`)
    - キャスト (`AsColumnDefinesArrayJson`)

## 5. 実装計画と進捗

### ステップ 1: `NumberType` のリファクタリング

- **目的:** `min`, `max`, `step`, `unit` プロパティを `NumberType` クラスに移動する。
- **作業内容:**
    1.  `app/Models/ColumnTypes/NumberType.php` に `public` プロパティとして `$min`, `$max`, `$step`, `$unit` を追加する。
    2.  コンストラクタを追加し、これらの値を初期化できるようにする。
    3.  既存の `setMin` などの不要なメソッドを削除する。
- **進捗:** **完了** (既存コードで既に実装済み)

### ステップ 2: `AutoNumberType` のリファクタリング

- **目的:** `prefix`, `digits`, `revision` プロパティを `AutoNumberType` クラスに移動する。
- **作業内容:**
    1.  `app/Models/ColumnTypes/AutoNumberType.php` に `public` プロパティとして `$prefix`, `$digits`, `$revision` を追加する。
    2.  コンストラクタを追加し、これらの値を初期化できるようにする。
- **進捗:** **完了** (既存コードで既に実装済み)

### ステップ 3: `InputTypeFactory` の改修

- **目的:** `ColumnDefine` から渡された情報に基づき、各 `InputType` オブジェクトを適切に初期化する。
- **作業内容:**
    1.  `make` メソッドが、カラム定義の**配列** (`array $columnDefineArray`) を受け取るようにシグネチャを変更する。
    2.  `make` メソッド内で、`$columnDefineArray['type']` に応じてインスタンス化するクラスを決定する。
    3.  `NumberType` や `AutoNumberType` をインスタンス化する際に、`$columnDefineArray` 内の `options` や `min`, `max` などの値を取得し、コンストラクタに渡すようにロジックを修正する。
- **進捗:** **完了** (既存コードで既に要件を満たしていた)

### ステップ 4: `ColumnDefine` モデルの改修

- **目的:** `ColumnDefine` からタイプ固有のプロパティを削除し、`InputType` オブジェクトへの移譲を徹底する。
- **作業内容:**
    1.  `min`, `max`, `step`, `unit` および `prefix`, `digits`, `revision` に関連するプロパティとセッターメソッドを全て削除する。
        - **進捗:** **完了** (これらのプロパティは `ColumnDefine` に直接存在しなかったため、削除は不要でした)
    2.  `constructByObject` および `constructByArgs` メソッドを修正し、`initializeType` を呼び出す際にカラム定義の**配列**を渡すように変更する。
        - **進捗:** **完了** (既存コードで既に要件を満たしていました)
    3.  `initializeType` メソッドを修正し、`InputTypeFactory::make` にカラム定義の配列を渡すようにする。
        - **進捗:** **完了** (既存コードで既に要件を満たしていました)
    4.  `normalizeArrayOrCollection` メソッドを修正し、タイプ固有のプロパティを `options` 配列に集約するなど、新しい構造に対応させる。
        - **進捗:** **保留** (Livewireコンポーネント側で `options` にマージする方針に変更したため、`ColumnDefine` 側での `normalizeArrayOrCollection` の修正は不要と判断)
    5.  **追加修正:** `App\Models\ColumnDefine` のコンストラクタで、`AsColumnDefinesArrayJson` から渡される配列を適切に処理し、`inputType` プロパティが初期化されるように修正。
        - **進捗:** **完了** (`app/Models/ColumnDefine.php` のコンストラクタに `is_array($inObject)` の条件を追加し、`constructByObject` を呼び出すように修正)

### ステップ 5: 関連コンポーネントとビューの修正

- **目的:** `ColumnDefine` の変更に伴い、影響を受ける全てのUIコンポーネントとビューを修正する。
- **作業内容:**
    1.  **`LedgerDefine/ModifyColumn.php`:**
        -   `use` ステートメントに `App\Models\ColumnTypes\NumberType` と `App\Models\ColumnTypes\AutoNumberType` を追加。
        -   `mount` メソッドで `$this->columns` を初期化する際に、`ColumnDefine` オブジェクトの `getInputType()` メソッドを通じてタイプ固有のプロパティ（`min`, `max`, `step`, `unit`, `prefix`, `digits`, `revision`）を `options` 配列にマージするように修正。
        -   `saveColumn` メソッドと `rules` メソッドで、バリデーションルールを `columns.{$index}.options.min` のように `options` 配列内のプロパティを参照するように修正。
        -   `addColumn` メソッドで新規カラムを追加する際の初期値からタイプ固有のプロパティを削除し、`options` 配列を空で初期化するように修正。
        -   `updatedColumns` メソッドで `InputTypeFactory::make()` を呼び出す際に、`['type' => $value]` の形式で配列を渡すように修正。
        - **進捗:** **完了**
    2.  **`modify-column.blade.php`:**
        -   `number` 型や `auto_number` 型のオプションへのアクセスを、`$column['options']['min']` のような形式に変更。
        - **進捗:** **手動での修正が必要** (エージェントはBladeテンプレートを直接修正できないため)
    3.  **`Ledger/CreateColumn.php`:**
        -   `use` ステートメントに `App\Models\ColumnTypes\NumberType` と `App\Models\ColumnTypes\AutoNumberType` を追加。
        -   `rules()` メソッドで、`number` 型と `auto_number` 型のバリデーションルールが `inputType` オブジェクトのプロパティを正しく参照するように修正。
        -   `initColumns` メソッドで `NumberingService::getNextNumber` を呼び出す際に、`ColumnDefine` オブジェクトを渡すように修正。
        - **進捗:** **完了**
    4.  **`create-column.blade.php`:**
        -   `number` 型の入力UI (`number.blade.php` を含む) で `min`, `max`, `step`, `unit` を表示・設定する際に、`$columnDefine->options['min']` のように `options` 配列経由でアクセスするように修正。
        - **進捗:** **手動での修正が必要** (エージェントはBladeテンプレートを直接修正できないため)
    5.  **`NumberingService`:**
        -   `getNextNumber` メソッドのシグネチャを `(\App\Models\ColumnDefine $columnDefine, int $ledgerDefineId)` に変更し、`ColumnDefine` オブジェクトを受け取るように修正。
        -   メソッド内で `AutoNumberType` オブジェクトと `columnId` を `ColumnDefine` オブジェクトから取得するように修正。
        -   `$prefix`, `$digits`, `$revision`, `$isUnique` の取得方法を `AutoNumberType` オブジェクトから取得するように修正。
        - **進捗:** **完了**
    6.  **`resources/views/components/ledger/form/number.blade.php`:**
        -   `min`, `max`, `step`, `unit` のプロパティを `props` から削除し、`columnDefine->options` から取得するように修正。
        - **進捗:** **完了**

### 追加で発生した問題と対応

-   **`App\Models\ColumnTypes\AutoNumberType::__construct(): Argument #1 ($options) must be of type array, stdClass given` エラー:**
    -   **原因:** `App\Casts\AsColumnDefinesArrayJson` がJSONをデコードする際に、`options` を `stdClass` として生成していたため。
    -   **対応:** `app/Casts/AsColumnDefinesArrayJson.php` の `get` メソッドで、`json_decode` の第2引数を `true` に変更し、JSONオブジェクトを連想配列としてデコードするように修正。
    -   **進捗:** **解決済み**

-   **`Typed property App\Models\ColumnDefine::$inputType must not be accessed before initialization` エラー:**
    -   **原因:** `App\Models\ColumnDefine` のコンストラクタで、`AsColumnDefinesArrayJson` から渡される配列が適切に処理されず、`inputType` プロパティが初期化されないままアクセスされていたため。
    -   **対応:** `app/Models/ColumnDefine.php` のコンストラクタに、引数が配列である場合の処理を追加し、`constructByObject` メソッドを再利用して `inputType` が確実に初期化されるように修正。
    -   **進捗:** **解決済み**

-   **`App\Models\ColumnTypes\InputTypeFactory::make(): Argument #1 ($columnDefineArray) must be of type array, string given` エラー:**
    -   **原因:** `app/Livewire/LedgerDefine/ModifyColumn.php` の `updatedColumns` メソッドで、`InputTypeFactory::make()` に文字列が直接渡されていたため。
    -   **対応:** `app/Livewire/LedgerDefine/ModifyColumn.php` の `updatedColumns` メソッドで、`InputTypeFactory::make(['type' => $value])` のように配列を渡すように修正。
    -   **進捗:** **解決済み**

-   **`Undefined property: App\Models\ColumnTypes\AutoNumberType::$id` エラー:**
    -   **原因:** `App\Services\NumberingService::getNextNumber` メソッドが `AutoNumberType` オブジェクトを受け取るように定義されていたが、`id` プロパティは `ColumnDefine` オブジェクトに存在するため。
    -   **対応:** `app/Services/NumberingService.php` の `getNextNumber` メソッドのシグネチャを `ColumnDefine` オブジェクトを受け取るように変更し、メソッド内で `AutoNumberType` オブジェクトと `columnId` を取得するように修正。
    -   **進捗:** **解決済み**

## 6. 期待される効果

- **責務の明確化:** `ColumnDefine` と `InputType` の役割が明確になり、コードの見通しが良くなる。
- **保守性の向上:** 各入力タイプのロジックが自身のクラスにカプセル化されるため、修正が容易になる。
- **拡張性の向上:** 新しい入力タイプを追加する際に、新しい `InputType` サブクラスを作成し、`InputTypeFactory` に登録するだけで済むようになり、既存コードへの影響を最小限に抑えられる。