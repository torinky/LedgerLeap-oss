# 台帳デフォルトソート機能の設計

**作成日:** 2025年12月9日  
**更新日:** 2025年12月9日  
**目的:** 台帳定義にデフォルトの並び順（複数カラム対応）を設定し、一覧表示時に適用する機能の設計。  
**関連:** なし

---

## 🎯 背景と要件

### 背景
現在、台帳一覧画面は `created_at` や `composite_score` 等の固定カラムでのソートのみ対応している。
ユーザーからは、台帳の内容（`contents` 内のデータ、例：主番・副番）に基づいてデフォルトの並び順を指定したいという要望がある。
特に、「主番」「副番」のように複数の列を組み合わせてソートするニーズが確認された。

### 要件
1.  **デフォルトソート設定（複数列対応）**
    *   台帳定義ごとに、どの列をどの順番でソートに使うかを設定できること。
    *   例：「主番」を第1優先、「副番」を第2優先とする。
2.  **UI（設定画面）**
    *   カラム設定画面で、各列に対して「ソート順位」を指定できること（1, 2, 3...）。
    *   単純なON/OFFではなく、優先順位を入力する形式とする。
3.  **UI（一覧画面）**
    *   台帳定義が特定されている場合（単一の台帳を表示中）、設定されたデフォルトソートを初期表示時に適用する。
    *   ユーザーが一時的に別のソート（例：更新日時順）を選択した場合でも、ワンクリックで「デフォルト順」に戻せるリセットボタンを提供する。
4.  **データ型への配慮**
    *   数値カラムは数値として、日付カラムは日付として正しくソートされること（文字列ソートによる `1, 10, 2` のような並びを防ぐ）。

---

## 🏗 データモデル設計

### ColumnDefine の変更

`app/Models/ColumnDefine.php`

既存の `sortBy` (bool) プロパティを削除し、`sort_index` プロパティを追加する。
**注意:** 本システムは開発中であり、後方互換性は考慮しない（既存の `sortBy` データは破棄・置換される）。

| プロパティ名 | 型 | 説明 |
| :--- | :--- | :--- |
| `sort_index` | `int \| null` | ソートの優先順位。1が最優先。nullの場合はソートに使用しない。 |

※ ソート方向（昇順/降順）は定義には持たせず、デフォルトは常に **ASC (昇順)** とする。

---

## 💻 UI設計

### 1. 台帳定義・列設定画面 (`ModifyColumn`)

*   **変更箇所:** カラム編集アコーディオン内。
*   **変更内容:**
    *   「この列で並び替え」チェックボックスを削除。
    *   **「デフォルトソート順位」** (Default Sort Order) という数値入力フィールドを追加。
    *   プレースホルダー: "例: 1 (最優先)"
    *   バリデーション: 正の整数または空。重複は許容（実装を単純にするため。重複時はID順などで解決）。

### 2. 台帳一覧画面 (`RecordsTable`)

*   **ソートリセットボタン:**
    *   ソート状態がデフォルト以外（`orderBy !== 'default'`）の場合に表示。
    *   アイコンボタン（リフレッシュアイコンなど）またはテキストリンク「デフォルト順に戻す」。
    *   クリック時の動作: `sort('default')` を呼び出す。

---

## ⚙ ロジック設計

### 1. デフォルトソート情報の取得

`LedgerDefine` からソート設定を取得するロジック。

```php
// 疑似コード
$sortColumns = collect($ledgerDefine->column_define)
    ->whereNotNull('sort_index')
    ->sortBy('sort_index');
```

### 2. クエリ構築 (`RecordsTable`)

`orderBy` が `'default'` の場合のクエリ構築。

```php
// 疑似コード
if ($this->orderBy === 'default') {
    foreach ($sortColumns as $column) {
        $jsonPath = "contents->'$.\"{$column->id}"'" ; 
        
        // 型に応じたキャスト
        $expression = match ($column->type) {
            'number', 'auto_number' => "CAST($jsonPath AS DECIMAL(20, 6))",
            'date', 'YMD' => "CAST($jsonPath AS DATE)",
            default => $jsonPath,
        };
        
        $query->orderByRaw("$expression ASC");
    }
}
```

---

## ⚠️ 影響範囲と対応

### 1. シーダー・ファクトリへの影響
`ColumnDefine` のコンストラクタシグネチャ変更（`bool $sortBy` → `?int $sortIndex`）に伴い、以下のファイルを修正する必要がある。これらは `new ColumnDefine(...)` を直接呼び出している。

*   `database/seeders/DemoMinimalSeeder.php`
*   `database/seeders/DemoPhase1ExtensionSeeder.php`
*   `database/seeders/AutoLinkCrossReferenceSeeder.php`
*   `database/factories/LedgerDefineFactory.php`
*   `database/factories/AutoNumberLedgerDefineFactory.php`

**対応方針:**
*   引数の `true` (ソート有効) は `1` (優先順位1) に置換。
*   引数の `false` (ソート無効) は `null` に置換。

### 2. テストコードへの影響
`tests` ディレクトリ内の多くのユニットテスト・機能テストで `ColumnDefine` がインスタンス化されている。

**対応方針:**
*   `grep` で抽出した全箇所の引数を修正する。
*   特に `tests/Unit/Models/ColumnDefineTest.php` は大幅に書き換え、`sort_index` の動作検証を追加する。

### 3. 既存データへの影響
*   既存の `ledger_defines` テーブル内の `column_define` JSONデータに含まれる `sortBy` キーは、モデル読み込み時に無視されるか、エラーの原因となりうる。
*   **対応方針:** 開発環境のため、マイグレーションのリセット (`migrate:refresh --seed`) を推奨とする。既存データの変換スクリプトは作成しない。

---

## ✅ 実装計画 (WBS)

### Phase 1: データモデルとコアクラスの変更
*   **1.1** `app/Models/ColumnDefine.php`: `sortBy` プロパティ削除、`sort_index` 追加。`constructByArgs`, `constructByObject`, `toArray` メソッドの修正。
*   **1.2** `app/Models/LedgerDefine.php`: 必要であればキャスト処理の確認（変更不要の見込み）。

### Phase 2: 既存コード（シーダー・テスト）の修正
*   **2.1** `database/seeders/*.php`: シーダー内の `new ColumnDefine` 呼び出し引数を修正。
*   **2.2** `database/factories/*.php`: ファクトリ内の呼び出し引数を修正。
*   **2.3** `tests/**/*.php`: テストコード内の呼び出し引数を修正。

### Phase 3: UI実装（設定画面）
*   **3.1** `app/Livewire/LedgerDefine/ModifyColumn.php`: `columns` 配列の構造変更、バリデーションルール修正（`sortBy` → `sort_index`）。
*   **3.2** `resources/views/livewire/ledger-define/modify-column.blade.php`: チェックボックスを削除し、数値入力欄（`<x-mary-input type="number" ... />`）を配置。

### Phase 4: ロジック実装（一覧画面）
*   **4.1** `app/Livewire/Ledger/RecordsTable.php`: `mount` メソッドでデフォルトソート設定を読み込む処理を追加。
*   **4.2** `app/Livewire/Ledger/RecordsTable.php`: `render` メソッド（またはクエリ構築メソッド）に、複合ソート適用ロジック（`JSON_EXTRACT`, `CAST` 使用）を実装。
*   **4.3** `resources/views/livewire/ledger/records-table.blade.php`: ソートリセットボタン（「デフォルト順」）を追加。

### Phase 5: テストと検証
*   **5.1** `tests/Unit/Models/ColumnDefineTest.php`: `sort_index` プロパティの単体テスト実行。
*   **5.2** **[新規]** `tests/Feature/Livewire/Ledger/DefaultSortTest.php`:
    *   複数の `sort_index` を設定した台帳定義を作成。
    *   データを作成し、一覧画面で意図した順序（例：主番ASC, 副番ASC）で表示されるか検証。
    *   数値カラムと文字列カラムのソート挙動の違いを検証。
*   **5.3** 手動検証: ブラウザで設定画面と一覧画面の動作を確認。

---

**作成者:** AI Assistant  
**ステータス:** 設計確定・実装待ち