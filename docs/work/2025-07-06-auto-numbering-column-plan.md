# 台帳カラム自動採番機能 実装計画

## 1. 目的

台帳レコード作成時に、特定のカラムに対してユニークな番号を自動で採番する機能を追加する。これにより、手動での番号管理の手間を省き、入力ミスや重複を防ぐ。

## 2. ユースケース

- **作業者:** 資料番号のような連番を管理したい。
- **番号構成:** `接頭辞` + `連番（ゼロ埋め）` + `版記号`。版記号は「NC」「A」「B」など自由な文字列。
- **システムの挙動:**
    - 台帳の新規レコード作成時、対象カラムに「直近の最大番号 + 1」を初期値として表示する。
    - ユーザーは表示された初期値を自由に編集できる。
- **重複禁止(`unique`)オプションの挙動:**
    - **採番時:**
        - `unique`が**無効**の場合: 連番は「接頭辞」と「版記号」の組み合わせごとに管理される。
        - `unique`が**有効**の場合: 連番は「接頭辞」のみで管理され、版記号は連番の採番に影響しない。
    - **バリデーション（重複チェック）時 (`unique`が有効の場合のみ):**
        - 新規登録時・編集時ともに、「接頭辞 + 連番」の組み合わせの重複をチェックする（版記号は無視）。
        - 編集時は、自分自身のレコードを除外してチェックを行う。

---

## 5. 実装ステップ

### ステップ 1: `NumberType`を`AutoNumberType`にリファクタリング

*   **目的:** 自動採番機能の専用タイプをシステムに定義する。
*   **作業内容:**
    1.  **ファイルリネーム:** `app/Models/ColumnTypes/NumberType.php` を `app/Models/ColumnTypes/AutoNumberType.php` にリネーム。
    2.  **クラス名変更:** `AutoNumberType.php` 内のクラス名を `AutoNumberType` に変更。
    3.  **メソッド修正 (`AutoNumberType.php`):**
        *   `getName()` を `'auto_number'` に変更。
        *   `getLabel()` を `__('ledger.form.auto_number')` に変更。
        *   `hasOptions()` を `true` に変更。
    4.  **ファクトリ更新 (`InputTypeFactory.php`):** `$typeMap` 内の `'number' => ...` を `'auto_number' => AutoNumberType::class` に置き換え。
    5.  **翻訳ファイル更新 (`lang/ja/ledger.php`):** `form` 配列に `'auto_number' => '自動採番'` を追加。
*   **動作確認:**
    *   台帳定義編集画面 (`/ledger-defines/modify-column/{id}`) を開き、カラムの「タイプ」ドロップダウンに「自動採番」が表示されることを確認。
    *   タイプとして「自動採番」を選択した際に、エラーが発生せず、画面が正常に表示されることを確認。
*   **成果物:** 自動採番タイプの基本的な定義。

---

### ステップ 2: 自動採番オプションUIの実装

*   **目的:** ユーザーが台帳定義編集画面で、自動採番の詳細なオプション（接頭辞、桁数、版記号）を設定できるようにする。
*   **作業内容:**
    1.  **ビュー修正 (`modify-column.blade.php`):**
        *   `@if($columns[$index]['useOptions'])` ブロックの内側に `@if($column['type'] === 'auto_number')` ブロックを追加。
        *   追加したブロック内に、以下の3つの入力フィールドを `<x-mary-input>` で作成する。
            *   **接頭辞:** `wire:model.live.debounce` で `columns.{{$index}}.options.prefix` にバインド。
            *   **桁数:** `wire:model.live.debounce` で `columns.{{$index}}.options.digits` にバインド (type="number")。
            *   **版記号:** `wire:model.live.debounce` で `columns.{{$index}}.options.revision` にバインド。
    2.  **コンポーネント確認 (`ModifyColumn.php`):**
        *   `save()` メソッドや `saveColumn()` メソッドが、`$columns` 配列をそのまま `column_define` にJSONとして保存する実装になっていることを再確認（特別な変更は不要）。
*   **動作確認:**
    *   台帳定義編集画面で、カラムタイプを「自動採番」に設定すると、「接頭辞」「桁数」「版記号」の入力欄が表示されることを確認。
    *   他のタイプ（テキスト等）を選択すると、これらの入力欄が非表示になることを確認。
    *   各欄に値を入力し「保存」ボタンを押した後、ページをリロードしても入力した値が保持されていることを確認。
    *   DBの `ledger_defines` テーブルの `column_define` カラムに、設定したオプションがJSON形式で正しく保存されていることを確認。
*   **成果物:** 自動採番オプションを設定・保存できるUI。

---

### ステップ 3: `NumberingService`の作成と採番ロジックの実装

*   **目的:** 次に採番されるべき番号を生成するビジネスロジックを、再利用可能なサービスクラスとして実装する。
*   **作業内容:**
    1.  **サービスクラス作成:** `app/Services/NumberingService.php` を新規作成。
    2.  **`getNextNumber()` メソッド実装:**
        *   `public function getNextNumber(object $columnDefine, int $ledgerDefineId): string` のシグネチャでメソッドを定義。
        *   `$columnDefine->options` から接頭辞、桁数、版記号を取得。未設定の場合のデフォルト値（例: 接頭辞='', 桁数=3, 版記号='')も考慮する。
        *   `$columnDefine->unique` の値で処理を分岐。
        *   `Ledger::where('ledger_define_id', $ledgerDefineId)->pluck('content')` で対象台帳の全レコードの`content`を取得。
        *   取得した`content`コレクションをループし、PHP側で正規表現 (`preg_match`) を使って各レコードの番号を解析し、最大値を探す。
            *   **`unique`=true時:** `/(^{$prefix})(\d+)(.*)/` のような正規表現で、接頭辞に続く数値部分のみを比較対象とする。
            *   **`unique`=false時:** `/(^{$prefix})(\d+)({$revision}$)/` のような正規表現で、接頭辞と版記号が一致するレコードの数値部分のみを比較対象とする。
        *   見つかった最大値に1を加え、`str_pad()` でゼロ埋めし、最終的な番号文字列を組み立てて返す。
        *   対象レコードが1件も無い場合は、連番の初期値として `1` を使用する。
*   **動作確認:**
    *   `php artisan tinker` を使用して `NumberingService` を手動で呼び出し、テストケースを実行する。
        *   レコードが存在しない場合に、正しい初期番号が返されることを確認。
        *   `unique`がtrue/falseの各パターンで、既存レコードの最大値+1の番号が正しく計算・フォーマットされることを確認。
        *   接頭辞や版記号が異なるレコードが、採番ロジックに影響を与えない（または正しく影響を与える）ことを確認。
*   **成果物:** 独立し、テスト可能な採番ロジック。

---

### ステップ 4: レコード作成画面への採番ロジック組み込み

*   **目的:** 台帳の新規作成画面を開いた際に、自動採番カラムにシステムが計算した初期値が自動的に入力されるようにする。
*   **作業内容:**
    1.  **`CreateColumn.php` 修正:**
        *   `use App\Services\NumberingService;` を追加。
        *   `boot()` メソッドで `NumberingService` をインジェクト (`$this->numberingService = $workflowService;` の下に追記)。
        *   `initColumns()` メソッド内の `foreach` ループを修正。
        *   `if ($column->type === 'auto_number')` の条件分岐を追加。
        *   分岐内で `$defaultValue = $this->numberingService->getNextNumber($column, $this->ledgerDefineId);` を呼び出し、結果をセットする。
*   **動作確認:**
    *   自動採番が設定された台帳定義の新規レコード作成画面 (`/ledgers/create/{ledgerDefineId}`) を開く。
    *   対象のカラムに、事前のデータに基づいた正しい次の番号（例: `DOC-001-A`）が初期表示されていることを確認。
    *   ユーザーがその初期値を手動で変更できることを確認。
*   **成果物:** ユーザーが意識することなく、自動採番の初期値が提示されるUI。

---

### ステップ 5: 重複チェック用カスタムバリデーションの実装

*   **目的:** `unique`オプションが有効な場合に、ユーザーが手動入力した値が「接頭辞+連番」で重複しないことを保証する。
*   **作業内容:**
    1.  **ルールクラス作成:** `php artisan make:rule UniqueAutoNumber` を実行し、`app/Rules/UniqueAutoNumber.php` を作成。
    2.  **コンストラクタ実装:** `__construct(int $ledgerDefineId, object $columnDefine, ?int $ignoreLedgerId = null)` を定義し、プロパティに保持。
    3.  **`validate()` メソッド実装:**
        *   `$this->columnDefine->options` から接頭辞を取得。
        *   `preg_match` を使い、引数で渡された `$value` から「接頭辞」と「連番」部分を抽出。もしパターンに一致しなければ`fail()`を呼ぶ。
        *   DBを検索 (`Ledger::query()->where(...)`) し、`content` カラムが「抽出した接頭辞 + 抽出した連番」で始まるレコードを検索。
        *   `$ignoreLedgerId` があれば、そのIDを持つレコードを検索対象から除外 (`where('id', '!=', $this->ignoreLedgerId)`)。
        *   該当レコードが見つかった場合は、`fail('その番号は既に使用されています。')` を呼び出す。
    4.  **`CreateColumn.php` と `app/Livewire/Ledger/ModifyColumn.php` の `rules()` メソッド修正:**
        *   `foreach` ループ内で `if ($column->type === 'auto_number' && $column->unique)` の条件分岐を追加。
        *   既存の `new UniqueColumnValue(...)` の代わりに `new UniqueAutoNumber($this->ledgerDefineId, $column, $this->ledgerId)` をルール配列に追加する。
*   **動作確認:**
    *   `unique`が有効な自動採番カラムを持つ台帳で、以下の操作を行う。
        *   **新規作成時:** 既存の番号（例: `DOC-001-A`）と同じ「接頭辞+連番」（例: `DOC-001-B`）を入力して保存しようとすると、バリデーションエラーが表示されることを確認。
        *   **編集時:** 他のレコードが使用している番号に変更しようとすると、バリデーションエラーが表示されることを確認。
        *   **編集時:** 自分自身の番号（版記号だけ変更するなど）で保存した場合は、バリデーションをパスすることを確認。
*   **成果物:** `unique`オプションの挙動を完全に満たす、堅牢な重複チェック機能。
