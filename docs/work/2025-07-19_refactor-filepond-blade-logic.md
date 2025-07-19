# FilePond Bladeコンポーネントのリファクタリング計画 (最終版)

## 1. 目的

`resources/views/components/ledger/form/files.blade.php` に含まれるPHPロジックを、呼び出し元のLivewireコンポーネント (`CreateColumn`, `ModifyColumn`) に移譲する。これにより、Bladeコンポーネントの責務を表示に限定し、コードの保守性と再利用性を向上させる。

## 2. 現状の課題

-   **関心の混在:** Bladeビュー内に、データベースへの問い合わせ (`AttachedFile::find()`)、ファイルシステムのチェック (`Storage::disk('public')->exists()`)、MIMEタイプに基づく複雑な条件分岐（アイコンURLの決定など）といった、表示ロジックとは言えないPHPコードが直接記述されている。
-   **保守性の低下:** ロジックがビューに散在しているため、将来の仕様変更やデバッグが困難になっている。
-   **再利用性の欠如:** 同様のファイル表示ロジックを他の場所で再利用することができない。
-   **JavaScriptコードの表示:** `files.blade.php` 内のJavaScriptコードがそのままHTMLとして表示されてしまう問題。

## 3. アプローチ

**「単一の信頼できる情報源 (Single Source of Truth)」** の原則に基づき、FilePondに渡すためのデータ構造の生成ロジックを、すべてLivewireコンポーネント側に集約する。

-   **Livewireコンポーネントの責務:**
    -   既存の添付ファイル情報をデータベースから取得する。
    -   各ファイルについて、FilePondが必要とするすべての情報（`source`, `options`内の`name`, `size`, `type`, `metadata`内の`poster` URLなど）を事前に計算し、整形済みの配列を生成する。
    -   この整形済み配列を、コンポーネントの公開プロパティとして保持する。
-   **Bladeコンポーネントの責務:**
    -   Livewireコンポーネントから整形済みのファイル情報配列を受け取る。
    -   受け取った配列を `json_encode` し、FilePondの `files` オプションに渡すことだけに専念する。

## 4. 実装計画 (ステップ・バイ・ステップ)

### ステップ 1: Bladeコンポーネント (`files.blade.php`) の簡素化

-   **目的:** BladeコンポーネントからPHPロジックを完全に排除し、表示に専念させる。
-   **対象ファイル:**
    -   `resources/views/components/ledger/form/files.blade.php`
-   **タスク:**
    1.  `@props` ディレクティブに、Livewireコンポーネントから整形済みデータを受け取るための `initialFiles` (デフォルトは空配列 `[]`) を追加する。
    2.  `@php ... @endphp` ブロックを完全に削除する。
    3.  FilePondの `files` オプションを生成している `@foreach` ループと、その中のPHPロジックをすべて削除する。
    4.  FilePondの `setOptions` 内にある `files` プロパティを、以下のように簡潔な記述に書き換える。
        ```javascript
        files: {!! json_encode($initialFiles) !!},
        ```
    5.  `onremovefile` のJavaScriptコードを、`window.Livewire.find('{{ $this->id() }}').set(...)` の形式で、JavaScriptのテンプレートリテラルを適切に使用して記述する。

### ステップ 2: LivewireコンポーネントへのFilePond初期化ロジックの移譲

-   **目的:** `files.blade.php` 内のPHPロジックをLivewireコンポーネントに移設し、FilePond用のデータ配列を生成する。
-   **対象ファイル:**
    -   `app/Livewire/Ledger/CreateColumn.php`
    -   `app/Livewire/Ledger/ModifyColumn.php`
-   **タスク:**
    1.  FilePondに渡すファイル情報をカラム定義IDごとに保持するための公開プロパティ `public array $filePondInitialFiles = [];` を追加する。
    2.  `mount()` メソッド（または `ModifyColumn` の場合は `setInitialContent` のような適切な場所）で、既存の添付ファイル情報をロードする。
    3.  ロードした情報をもとに、`files.blade.php` に記述されていたロジック（`AttachedFile` の検索、`AttachedFilePathHelper` を使ったパス解決、MIMEタイプに応じたポスターURLの決定など）を実行する。
    4.  各ファイルについて、FilePondの `files` オプションが要求する形式の連想配列を生成し、`$this->filePondInitialFiles[$columnDefine->id]` に格納する。

### ステップ 3: 呼び出し元のビューの修正

-   **目的:** Livewireコンポーネントで準備したデータを、Bladeコンポーネントに正しく渡す。
-   **対象ファイル:**
    -   `resources/views/livewire/ledger/create-column.blade.php`
    -   `resources/views/livewire/ledger/modify-column.blade.php`
-   **タスク:**
    1.  `<x-ledger.form.files ... />` コンポーネントを呼び出している箇所を修正する。
    2.  ステップ2で準備したプロパティを、ステップ1で追加した `initialFiles` 属性に渡すように変更する。
        ```blade
        <x-ledger.form.files
            ...
            :initialFiles="$filePondInitialFiles[$column['id']] ?? []"
            ...
        />
        ```

## 5. 期待される効果

-   **責務の分離:** ビューとロジックが明確に分離され、コードの見通しが大幅に向上する。
-   **保守性の向上:** ファイル表示に関するロジックがLivewireコンポーネントに集約されるため、修正や機能追加が容易になる。
-   **パフォーマンス:** 事前にサーバーサイドでデータを準備することで、ビューのレンダリング処理が簡素化され、可読性が高まる。
-   **テスト容易性:** ロジックがコンポーネントクラスに移動することで、ユニットテストが書きやすくなる。

## 6. 作業結果と差分

### `resources/views/components/ledger/form/files.blade.php`

-   **変更点:**
    -   `@props` に `initialFiles` 属性を追加。
    -   `@php ... @endphp` ブロックを削除。
    -   FilePondの `files` オプションの値を `$initialFiles` を `Illuminate\Support\Js::from()` でJSONエンコードしたものに置き換え。
    -   `onremovefile` のJavaScriptコードを、`window.Livewire.find('{{ $this->id() }}').set(`deletedContent.${columnId}.${position}`, filename);` の形式に修正。

### `app/Livewire/Ledger/CreateColumn.php`

-   **変更点:**
    -   `public array $filePondInitialFiles = [];` プロパティを追加。
    -   `prepareFilePondInitialFiles()` メソッドを追加。このメソッドは、`files.blade.php` から移譲されたロジックを実装し、`$this->filePondInitialFiles` を生成する。
    -   `mount()` メソッド内で `prepareFilePondInitialFiles()` を呼び出すように修正。
    -   `use App\Enums\AttachedFileStatus;` を追加。

### `app/Livewire/Ledger/ModifyColumn.php`

-   **変更点:**
    -   `public array $filePondInitialFiles = [];` プロパティを追加。
    -   `mount()` メソッド内で `prepareFilePondInitialFiles()` を呼び出すように修正。
    -   `prepareFilePondInitialFiles()` メソッドをオーバーライドし、`AttachedFile::find()` の代わりに `$this->ledgerRecord->attachedFiles` コレクションからファイルを取得するように効率化。
    -   `use App\Enums\AttachedFileStatus;` を追加。

### `resources/views/livewire/ledger/create-column.blade.php`

-   **変更点:**
    -   `<x-ledger.form.files ... />` コンポーネントの呼び出しに `:initialFiles="[]"` を追加。

### `resources/views/livewire/ledger/modify-column.blade.php`

-   **変更点:**
    -   `<x-ledger.form.files ... />` コンポーネントの呼び出しに `:initialFiles="$filePondInitialFiles[$columnDefine->id] ?? []"` を追加。

## 7. 今後の要注意事項

今回の作業で、LivewireコンポーネントとBladeテンプレート間の連携において、特に以下の点に注意が必要であることが再確認されました。

-   **Livewireプロパティのライフサイクル:** Livewireのパブリックプロパティは、リクエスト間で状態を維持しますが、その初期化や更新のタイミングを正確に理解しておく必要があります。特に、親クラスと子クラスで同じプロパティを扱う場合、意図しない上書きやデータ消失が発生しないよう注意が必要です。
-   **BladeとJavaScriptの連携:** Bladeの `@this` や `{{ $this->id() }}` のようなLivewire固有のディレクティブをJavaScript内で使用する場合、最終的に出力されるHTMLがJavaScriptの有効な構文として成立するように、エスケープや文字列結合の方法に細心の注意を払う必要があります。`Illuminate\Support\Js::from()` のようなヘルパーを積極的に活用し、JavaScriptオブジェクトとして安全に渡すことを心がけます。
-   **エラーメッセージの解読:** LivewireやLaravelのエラーメッセージは、時に直接的な原因を示さないことがあります。スタックトレースを注意深く読み解き、どのファイル、どの行で問題が発生しているかを特定し、関連するフレームワークのライフサイクルやPHPの言語仕様を考慮して原因を特定するスキルが重要です。
-   **段階的な確認:** 今回のように、複雑なリファクタリングを行う際は、小さなステップに分割し、各ステップで動作確認を行うことが、問題の早期発見と解決に繋がります。特に、Livewireのような状態を持つフレームワークでは、このアプローチが非常に有効です。

これらの教訓を活かし、今後の作業に臨みます。