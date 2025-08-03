# 台帳表示・入力UIの改善計画 - ステップ4 詳細

## 1. 概要

本ドキュメントは、`docs/work/2025-08-02_column-display-and-grouping-plan.md` に記載された「台帳表示・入力UIの改善計画」における「ステップ4: 入力フォーム/詳細表示画面の改修」の詳細設計を記述するものです。ユーザーの提案に基づき、詳細画面と入力フォームの改修を別々のステップとして分割し、それぞれの目的、設計、成果物、確認方法を明確にします。

## 2. 基本方針

「重要度（表示レベル）」の概念を、台帳の詳細画面および入力フォームに適用します。これにより、情報過多な画面を整理し、ユーザーがより直感的かつ効率的に情報を閲覧・入力できるようにします。

## 3. 実装計画

### **ステップ4.1: 詳細画面 (`Show`) の改修（表示レベル対応）**

*   **目的:**
    台帳の詳細画面において、表示レベル（概要、標準、詳細）に応じて表示されるカラムを動的に制御する機能を追加します。利用者は表示レベルを切り替えることで、情報の粒度を調整できるようになります。

*   **対象ファイル:**
    *   `app/Livewire/Ledger/Show.php` (Livewireコンポーネント)
    *   `resources/views/livewire/ledger/show.blade.php` (Bladeビュー)

*   **主なタスク:**
    1.  **Livewireコンポーネント (`Show.php`) の改修:**
        *   **プロパティの追加:**
            *   `public int $displayLevel = 1;`
                *   現在の表示レベルを保持します（デフォルト: 1 '概要'）。
            *   `public array $filteredColumns = [];`
                *   表示レベルでフィルタリングされた後のカラム定義を保持します。
        *   **クエリ文字列の利用:**
            *   `protected $queryString = ['displayLevel'];` を設定し、URLで表示レベルの状態を維持できるようにします。
        *   **メソッドの追加:**
            *   `public function setDisplayLevel(int $level): void`
                *   表示レベルを切り替えるためのメソッド。`$displayLevel` プロパティを更新し、カラムの再フィルタリングを実行します。
            *   `private function filterColumns(): void`
                *   `$ledgerRecord` のカラム定義を現在の `$displayLevel` に基づいてフィルタリングし、`$filteredColumns` プロパティを更新する内部メソッド。
        *   **ライフサイクルメソッドの改修:**
            *   `mount()`: コンポーネントの初期化時に `filterColumns()` を呼び出します。
            *   `updatedDisplayLevel()`: `$displayLevel` が更新された後に `filterColumns()` を自動的に呼び出します。

    2.  **Bladeビュー (`show.blade.php`) の改修:**
        *   **表示レベル切り替えUIの設置:**
            *   `<x-mary-button-group>` を使用して、「概要」「標準」「詳細」の3つのボタンを配置します。
            *   各ボタンに `wire:click="setDisplayLevel(レベル番号)"` を設定します。
            *   現在の `$displayLevel` に応じて、選択されているボタンのスタイルがアクティブになるようにします。
        *   **カラム表示ロジックの変更:**
            *   これまで直接 `ledgerRecord->define->column_define` をループしていた箇所を、新しく作成した `$filteredColumns` プロパティをループするように変更します。これにより、ビュー側での条件分岐 (`@if`) が不要になり、ロジックがコンポーネントに集約されます。

*   **理由と意図:**
    *   **操作性の向上:** 表示レベルを切り替えることで、ユーザーは自分の関心のある粒度で情報を閲覧でき、不要な情報を非表示にすることで、画面のノイズを減らし、集中力を高めます。
    *   **UIの一貫性:** `RecordsTable` と同様の表示レベル切り替え機能を提供することで、システム全体のUIに一貫性を持たせます。
    *   **段階的な実装:** まずは表示レベルの制御という単一の機能に絞って実装することで、問題を切り分けやすくし、確実な機能追加を目指します。

*   **成果物:**
    *   `app/Livewire/Ledger/Show.php` の修正。
    *   `resources/views/livewire/ledger/show.blade.php` の修正。

*   **確認方法:**
    1.  台帳の詳細画面に「概要」「標準」「詳細」の切り替えボタンが表示されることを確認します。
    2.  「標準」ボタンをクリックすると、表示レベルが `1` と `2` のカラムが表示されることを確認します。
    3.  「概要」ボタンをクリックすると、表示レベル `1` のカラムのみが表示されることを確認します。
    4.  URLに `?displayLevel=2` のようにクエリ文字列が反映され、ページをリロードしても選択した表示レベルが維持されることを確認します。
    5.  この段階では、カラムのグループ化（折りたたみパネル）は実装されていないことを確認します。

### **ステップ4.2: 入力フォーム (`CreateColumn`, `ModifyColumn`) の改修**

*   **目的:** 台帳の作成・編集画面において、カラムを「グループ名」でグループ化し、折りたたみ可能なパネルで表示する。また、表示レベルに応じて表示/非表示になるカラムを制御する。

*   **対象ファイル:**
    *   `app/Livewire/Ledger/CreateColumn.php` (Livewireコンポーネント)
    *   `resources/views/livewire/ledger/create-column.blade.php` (Bladeビュー)
    *   `app/Livewire/Ledger/ModifyColumn.php` (Livewireコンポーネント)
    *   `resources/views/livewire/ledger/modify-column.blade.php` (Bladeビュー)

*   **主なタスク:**
    1.  コンポーネントに、現在の表示レベルを管理するプロパティを追加する。
    2.  ビューに表示レベル切り替えボタンを設置する。
    3.  コンポーネントの `render()` メソッド（または `mount()`）で、`column_define` を `group` 名でグループ化する処理を追加する。
    4.  ビューのループを、グループのループとカラムのループの二重構造に変更する。
    5.  外側のループで折りたたみパネル (`<x-mary-collapse>`) を使用する。
    6.  内側のループで、表示レベルに基づいたカラムの表示/非表示制御を行う。
    7.  必須項目が含まれるグループには、それが分かるようにインジケーターを表示する。

*   **詳細設計:**
    *   **`app/Livewire/Ledger/CreateColumn.php` および `app/Livewire/Ledger/ModifyColumn.php` の改修:**
        *   **プロパティの追加:**
            *   `public int $displayLevel = 1;`
                *   現在の表示レベルを保持するプロパティ。デフォルト値は `1` (概要) とします。
                *   このプロパティは、ユーザーの選択に応じて更新され、表示するカラムをフィルタリングするために使用されます。
            *   `protected $queryString = ['displayLevel'];`
                *   `displayLevel` プロパティをURLのクエリ文字列に含めることで、ページリロード時やURL共有時に表示状態を維持できるようにします。
        *   **メソッドの追加:**
            *   `public function setDisplayLevel(int $level): void`
                *   ユーザーがボタンをクリックした際に呼び出され、`$this->displayLevel` を更新します。
                *   バリデーションを追加し、`$level` が `1`, `2`, `3` のいずれかであることを保証します。
        *   **`mount()` メソッドの改修:**
            *   初期化時に、URLクエリ文字列から `displayLevel` を読み込みます。もしクエリ文字列に存在しない場合や不正な値の場合は、デフォルト値 `1` を設定します。
        *   **カラムのグループ化とフィルタリングロジックの追加:**
            *   `render()` メソッド内で、`$this->ledgerDefine->column_define` をグループ名でグループ化し、各グループ内のカラムを `display_level` に基づいてフィルタリングするロジックを追加します。
            *   グループ化されたカラムは、ビューに渡すための新しいプロパティ（例: `$groupedColumns`）に格納します。
            *   グループ名が `null` のカラムは「その他」グループとして扱います。
            *   各グループに必須項目が含まれているかどうかのフラグ（例: `hasRequired`）も計算し、ビューに渡します。

    *   **`resources/views/livewire/ledger/create-column.blade.php` および `resources/views/livewire/ledger/modify-column.blade.php` の改修:**
        *   **表示レベル切り替えボタンの設置:**
            *   フォームの上部（例: ワークフロー情報の下）に、MaryUIの `<x-mary-button-group>` を使用して、表示レベルを切り替えるボタンを配置します。
            *   各ボタンに `wire:click="setDisplayLevel(1)"`, `wire:click="setDisplayLevel(2)"`, `wire:click="setDisplayLevel(3)"` を設定します。
            *   現在の `$this->displayLevel` に応じて、アクティブなボタンのスタイルを変更します。
        *   **カラムのグループ化と折りたたみパネルの導入:**
            *   `$groupedColumns` をループし、各グループに対して `<x-mary-collapse>` を使用して折りたたみ可能なパネルを作成します。
            *   パネルのヘッダーにはグループ名を表示し、`hasRequired` フラグに基づいて必須項目インジケーター（例: `*`）を表示します。
            *   パネルのボディ内で、グループ内のカラムをループし、各カラムの入力フィールドをレンダリングします。
            *   各カラムのレンダリング時に、`@if($column['display_level'] <= $this->displayLevel)` のような条件分岐を追加し、表示レベルに基づいてカラムの表示/非表示を制御します。

*   **理由と意図:**
    *   **入力効率の向上:** フォームをグループ化し、折りたたみ可能にすることで、ユーザーは必要な入力項目に素早くアクセスでき、スクロール量を減らすことができます。必須項目インジケーターは入力漏れを防ぎます。
    *   **フォームの視認性向上:** 長大なフォームを整理し、不要な項目を非表示にすることで、ユーザーはフォーム全体の構造を把握しやすくなります。
    *   **UIの一貫性:** `RecordsTable` や `Show` と同様の表示レベル切り替え機能を提供することで、システム全体のUIに一貫性を持たせます。
    *   **再利用性:** `CreateColumn` と `ModifyColumn` で共通のロジックとビュー構造を使用することで、コードの重複を避け、メンテナンス性を向上させます。

*   **成果物:**
    *   `app/Livewire/Ledger/CreateColumn.php` の修正。
    *   `resources/views/livewire/ledger/create-column.blade.php` の修正。
    *   `app/Livewire/Ledger/ModifyColumn.php` の修正。
    *   `resources/views/livewire/ledger/modify-column.blade.php` の修正。

*   **確認方法:**
    1.  台帳の作成画面および編集画面にアクセスし、カラムがグループ化され、折りたたみ可能なパネルで表示されることを確認する。
    2.  表示レベル切り替えボタンが正しく機能し、選択に応じてカラムの表示/非表示が切り替わることを確認する。
    3.  URLのクエリ文字列に `displayLevel` が反映され、ページリロード後も表示状態が維持されることを確認する。
    4.  各グループのヘッダーに、必須項目が含まれる場合にインジケーターが表示されることを確認する。
