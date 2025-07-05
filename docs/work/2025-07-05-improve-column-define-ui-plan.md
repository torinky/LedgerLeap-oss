# 台帳定義カラム編集UIの挙動改善計画

## 1. 目的

`LedgerDefine`（台帳定義）のカラム編集画面において、ユーザー体験を向上させ、サーバーへのリクエスト数を削減する。
現在は項目を一つ変更するたびにDBへの保存が実行されるが、これをユーザーが明示的に「保存」ボタンを押したタイミングで一括保存する方式に変更する。

## 2. 現状の問題点

- **過剰なサーバー通信:** カラム名の一文字入力やチェックボックスのON/OFFなど、すべての変更操作が即座にサーバーへのリクエストをトリガーし、DBを更新している。
- **ユーザー体験の悪化:** ネットワーク遅延がある環境では、入力のたびに待ち時間が発生し、スムーズな操作が妨げられる。
- **実装の複雑性:** 即時保存と並び替え順序の維持を両立させるため、状態管理ロジックが複雑になっている。

## 3. 根本的なアプローチ

**「単一の信頼できる情報源 (Single Source of Truth)」** の原則に基づき、UIの状態管理をLivewireコンポーネント内に集約する。

- **状態の保持:** カラムのすべての情報（並び順、名前、型、オプションなど）をコンポーネント内の単一のpublicプロパティ（例: `$columns` 配列）で管理する。
- **操作の分離:** UI上の操作（入力、並び替え、追加、削除）は、まずこの`$columns`配列を更新するだけにとどめる。DBへの永続化は行わない。
- **一括保存:** ユーザーが「保存」ボタンをクリックした際に、初めて`$columns`配列の内容を`LedgerDefine`モデルに反映し、DBに保存する。

## 4. 実装計画と結果 (ステップ・バイ・ステップ)

### ステップ1: Livewireコンポーネントのプロパティ再設計 (完了)

`ModifyColumn.php`コンポーネントのプロパティを、単一の配列で状態を管理するように変更した。

**【変更前】**
```php
public $columnType = [];
public $columnName = [];
public $columnRequired = [];
// ... 他のプロパティも同様
```

**【変更後】**
```php
// カラム定義の状態をすべて保持する単一の配列
public array $columns = [];
```

`mount()`メソッドで、`$ledgerDefineRecord->column_define`から`$this->columns`を初期化するロジックを実装した。これにより、既存のカラム定義が正しくLivewireコンポーネントの `$columns` プロパティにロードされる。

### ステップ2: UI (Blade) のデータバインディング変更 (完了)

`modify-column.blade.php`のデータバインディングを、新しい`$columns`プロパティに追従させた。

- `wire:model.live`や`wire:model.blur`を`wire:model.defer`に変更する計画だったが、カラムタイプ変更時の動的なオプションフォーム表示のため、`type`プロパティには`wire:model.live`を維持した。これにより、リクエストはフォーム送信時まで遅延されるが、`type`変更時は即座にLivewireが反応する。
- `foreach`ループ内で、`$column['id']`を`wire:key`として使用し、`$columns`配列の各要素にバインドした。
- オプション入力フォームの表示条件を、`@if((new \App\Models\ColumnDefine((object)$column))->useOptions)` から `$column['useOptions']` に変更し、Livewireコンポーネントで管理される `useOptions` プロパティを直接参照するように修正した。

**【変更前】**
```html
<input wire:model.blur="columnName.{{$columnDefine->id}}" ...>
<select wire:model.live="columnType.{{$columnDefine->id}}" ...>
@if((new \App\Models\ColumnDefine((object)$column))->useOptions)
    <!-- オプションフォーム -->
@endif
```

**【変更後】**
```html
@foreach($columns as $index => $column)
    <input wire:model.defer="columns.{{$index}}.name" ...>
    <select wire:model.live="columns.{{$index}}.type" ...>
    @if($column['useOptions'])
        <!-- オプションフォーム -->
    @endif
@endforeach
```

### ステップ3: 各種アクションメソッドの修正 (完了)

コンポーネント内のメソッドを、DBを直接操作するのではなく、`$this->columns`配列を操作するように変更した。`store()`メソッドの呼び出しはすべて削除した。

- **`updateColumnOrder($orderedItems)`**:
    - `@wotz/livewire-sortablejs` から渡される `$orderedItems` (各要素が `['value' => ID, 'order' => 新しい順序]` の形式) を基に `$this->columns` 配列を再構築し、各カラムの `order` プロパティを更新するように修正した。
    - `usort` を使用して、更新された `order` プロパティに基づいて `$this->columns` をソートするようにした。
- **`addColumn()`**:
    - 新しいカラムのデフォルト値を持つ配列を`$this->columns`に追加するように修正した。これにより、「Property type not supported」エラーを解消した。
    - `ColumnDefine` オブジェクトではなく、Livewireが扱いやすいシンプルな連想配列として初期化するように変更した。
- **`removeColumn($index)`**:
    - `$this->columns`配列から指定されたインデックスの要素を削除するロジックは変更なし。
- **`saveColumn($index)`**:
    - 各カラムの編集フォームに個別の「保存」ボタンを追加したことに伴い、`saveColumn($index)` メソッドを新規追加した。
    - このメソッドは、指定されたインデックスのカラムデータに対してバリデーションを実行し、そのカラムの変更を `ledgerDefineRecord` に反映して保存する。
- **`updatedColumns($value, $key)`**:
    - カラムタイプ変更時にオプション入力フォームを動的に表示するため、Livewireの命名規則に合わせた `updatedColumns` マジックメソッドを追加した。
    - `$key` パラメータから変更されたプロパティが `type` であることを確認し、`InputTypeFactory::make($value)->hasOptions()` を使用して `useOptions` プロパティを更新する。
    - Livewireに `$this->columns` 配列が変更されたことを明示的に通知し、ビューの再レンダリングを強制するため、メソッドの最後に `$this->columns = $this->columns;` を追加した。
- **`CheckboxType.php` の `hasOptions()`**:
    - `app/Models/ColumnTypes/CheckboxType.php` の `hasOptions()` メソッドを誤って `true` を返していたため、`false` を返すように修正した。

### ステップ4: 一括保存機能の実装 (完了)

- **UIの変更:** Bladeファイルの末尾に、フォーム全体を送信するための「保存」ボタンは元々存在していたため、変更なし。
- **`save()`メソッドの修正:**
    - `save()`メソッドは、`$this->columns`の内容を`$this->ledgerDefineRecord->column_define`にセットし、バリデーションを実行後、`$this->ledgerDefineRecord->save()`を実行してDBに保存する。個別のカラムのバリデーションは `saveColumn()` に移譲された。
    - 成功のToastメッセージを表示する。

### ステップ5: (任意) 変更破棄の警告 (未完了)

- この機能は**未実施**。

## 5. 期待される効果

- **UIの応答性向上:** 入力や並び替えがクライアントサイドで完結するため、遅延なくスムーズに操作できる。
- **サーバー負荷の軽減:** DBへの書き込みが一度に集約されるため、サーバーリクエストとDB負荷が大幅に削減される。
- **コードの簡素化:** 状態管理が一元化されることで、コンポーネントのロジックがよりシンプルで理解しやすくなる。

## 6. リスクと考慮事項

- **`save()`メソッドのロジック:** `$columns`配列から`column_define`への変換ロジックを正確に実装する必要がある。
- **キーの管理:** Livewireのループ内で要素を正しく識別するために、`wire:key`の管理が重要になる。`$loop->index`ではなく、各カラムの一意なID（`$column['id']`）をキーとして使用し続ける方が堅牢である。