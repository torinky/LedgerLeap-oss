# Ledgerモデル

## モデルの目的
システム内で管理される具体的な台帳のレコード（データ行）を表します。各レコードは特定の台帳定義 ([`LedgerDefine`](./LedgerDefine.md)) に基づいて作成され、ユーザーが入力した情報 (`content`) や添付ファイル情報 (`content_attached`) を保持します。ワークフローの状態管理も行います。

台帳レコードの取得や基本的な操作は [`LedgerService`](../services/LedgerService.md) が、ワークフローに関連する複雑な状態遷移やビジネスロジックは [`WorkflowService`](../services/WorkflowService.md) が主に担当します。
機能面での詳細は [台帳管理機能説明](/docs/function/Ledger.md) および [ワークフロー機能説明](/docs/function/WorkFlow.md) も参照してください。

## 関連テーブル
`ledgers` テーブル

## 主要な属性

*   **`$fillable`**:
    *   `content`: 台帳の主要なデータ (JSON形式でカラム値を格納)
    *   `content_attached`: 添付ファイルに関する情報 (JSON形式)
    *   `ledger_define_id`: 関連する台帳定義のID
    *   `creator_id`: 作成者のユーザーID
    *   `modifier_id`: 更新者のユーザーID
    *   `status`: ワークフローの状態 (`App\Enums\WorkflowStatus`)
    *   `latest_diff_id`: 最新の差分ID (`ledgers_diffs`テーブルのID)
    *   `version`: レコードのバージョン番号
*   **`$casts`**:
    *   `content`: `App\Casts\AsColumnArrayJson::class` (JSON文字列を配列として扱う)
    *   `content_attached`: `App\Casts\AsColumnArrayJson::class` (JSON文字列を配列として扱う)
    *   `status`: `App\Enums\WorkflowStatus::class`
*   **その他主要な属性**:
    *   `id`: 一意なID (Primary Key)

### AsColumnArrayJsonカスタムキャストについて

`AsColumnArrayJson`カスタムキャストは、Mroongaの全文検索に対応するための特殊な実装です。

**重要な機能：Mroonga対応の自動型変換**

Mroongaのベクターカラム処理には、数値キーのJSON配列内に整数値がある場合、その配列をさらにJSON配列としてエンコードしてしまう副作用があります：

```php
// 問題のあるケース（整数を含む配列）
["EXP-0001", "2025-10-11", "交通費", 1000, "説明", []]
// → Mroongaの処理により二重配列化
// → ["[\"EXP-00","01\",...]"]  // 分割されて破損
// → Eloquentでの取得時にJSON decodeエラーでnullに
```

この問題を回避するため、`AsColumnArrayJson::setContent()`メソッドで**整数・浮動小数点数を自動的に文字列に変換**しています：

```php
// app/Casts/AsColumnArrayJson.php
public function setContent(mixed $item): mixed
{
    // Mroongaのベクターカラム処理の副作用を回避
    if (is_int($item) || is_float($item)) {
        return (string) $item;
    }
    // ... 他の処理
}
```

**メリット：**
- シーダーやテストコードで整数を直接渡しても自動的に文字列に変換される
- 開発者がMroongaの副作用を意識する必要がない
- UIからのフォーム入力（自動的に文字列）との整合性が保たれる
- 一箇所で対策が完結し、メンテナンスが容易

詳細は`app/Casts/AsColumnArrayJson.php`のクラスコメントおよび[データベーススキーマ](/docs/database/schema.md)を参照してください。

## リレーションシップ

*   **`define()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: [`App\Models\LedgerDefine`](./LedgerDefine.md)
    *   説明: この台帳レコードが属する台帳定義。
*   **`ledgerDiff()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\LedgerDiff` (このモデルのドキュメントは作成されていませんが、`ledgers_diffs` テーブルに対応します。詳細は [`WorkflowService`](../services/WorkflowService.md) や[データベーススキーマ](/docs/database/schema.md)を参照)
    *   説明: この台帳レコードに関連する全ての変更履歴（スナップショット）。ワークフローの各ステップ（点検依頼、承認など）や編集時のデータ変更が記録されます。
*   **`creator()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: [`App\Models\User`](./User.md)
    *   説明: この台帳レコードを作成したユーザー。
*   **`modifier()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: [`App\Models\User`](./User.md)
    *   説明: この台帳レコードを最後に更新したユーザー。
*   **`attachedFiles()`**:
    *   タイプ: `HasMany`
    *   相手モデル: `App\Models\AttachedFile` (このモデルのドキュメントは作成されていませんが、`attached_files` テーブルに対応します。詳細は[データベーススキーマ](/docs/database/schema.md)を参照)
    *   説明: この台帳レコードに添付されているファイルのリスト。
*   **`latestDiff()`**:
    *   タイプ: `BelongsTo`
    *   相手モデル: `App\Models\LedgerDiff`
    *   説明: この台帳レコードの最新の差分情報。[`WorkflowService`](../services/WorkflowService.md) によって管理されます。
*   **`folder()`**:
    *   タイプ: (リレーションではないが便宜上記載)
    *   相手モデル: [`App\Models\Folder`](./Folder.md)
    *   説明: この台帳が属する台帳定義 ([`LedgerDefine`](./LedgerDefine.md)) を通じて、関連するフォルダを取得します。

## 関連するEnum

*   **`App\Enums\WorkflowStatus`**:
    *   説明: 台帳レコードのワークフローにおける状態（例: `DRAFT`, `INSPECTION`, `APPROVAL`, `APPROVED`, `REJECTED`）を定義します。

## 主要なスコープやメソッド

*   **`scopeSearch(EloquentBuilder $query, string $freeWord)`**:
    *   説明: `content` および `content_attached` カラムに対して、指定されたフリーワードで全文検索を行います (Mroonga)。この検索ロジックは [`LedgerService`](../services/LedgerService.md) から利用されることがあります。
*   **`scopeSearchContext(EloquentBuilder $query, SearchContext $searchContext)`**:
    *   説明: `SearchContext` オブジェクト（キーワードと類義語を含む）に基づいて全文検索を行います。
*   **`scopeContentsFilter(EloquentBuilder $query, array $filter)` (static)**:
    *   説明: 指定されたフィルタ条件（カラムごとの検索文字列）に基づいて `content` および `content_attached` カラムをフィルタリングします (Mroonga)。
*   **`delete()`**:
    *   説明: 台帳レコードを削除する際に、関連する `AttachedFile` および `LedgerDiff` レコードも一緒に削除します。
*   **`getActivitylogOptions(): LogOptions`**:
    *   説明: `spatie/laravel-activitylog` の設定。ログに記録する属性やログ名を定義します。変更があった属性のみを記録し、`latest_diff_id` のみの変更はログに記録しません。
*   **`isLocked(): bool`**:
    *   説明: 台帳レコードが承認済み (`APPROVED`) で編集がロックされているかどうかを判定します。この状態は主に [`WorkflowService`](../services/WorkflowService.md) によって制御されます。
*   **`scopeWithNeededRelations(EloquentBuilder $query): EloquentBuilder`**:
    *   説明: 事前定義されたリレーション (`define`, `creator`, `latestDiff.inspector`, `latestDiff.approver`) をイーガーロードします。定数 `NEEDED_RELATIONS` で定義。

## その他

*   `LogsActivity` トレイトを利用して、モデルの変更履歴を記録します。
*   `booted()` メソッドのコメントアウト部分 (`saving` イベントでの `normalizeContent()`) は、過去に存在したか検討された正規化処理の可能性があります。
*   `AsColumnArrayJson` カスタムキャストは、JSON形式で保存されているカラムデータをPHPの配列として透過的に扱えるようにするものです。

### contentとcontent_attachedの正規化とデータ構造

**重要**: Ledgerの`content`および`content_attached`は、保存前に`normalizeByColumnDefine()`によって正規化され、`AsColumnArrayJson`キャストによって特殊な変換が行われます。

#### データフローの詳細

1. **Livewireコンポーネントでの管理**:
   - カラムIDをキーとした連想配列で管理: `[1 => 'value', 3 => 'value']`

2. **保存前の正規化** (`normalizeByColumnDefine()`):
   - カラムIDの欠番を空文字で埋める
   - maxId（最大カラムID）までのすべてのインデックスを作成
   - 例: `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`

3. **DB保存時の変換** (`AsColumnArrayJson::set()`):
   - `array_values()`で連番配列に変換
   - JSON文字列として保存: `["", "value", "", "value"]`

4. **DB読み取り時の復元** (`AsColumnArrayJson::get()`):
   - JSON文字列を連番配列として復元
   - 結果: `[0 => '', 1 => 'value', 2 => '', 3 => 'value']`
   - **カラムIDが配列インデックスと一致する**

#### 実装上の注意点

```php
// app/Livewire/Ledger/CreateColumn.php
protected function processFilesForSave(): void
{
    // 保存前に必ず正規化を実行
    $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
    $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);
}
```

**この正規化により**:
- DBから読み取ったcontentは、カラムIDを配列インデックスとして直接アクセス可能
- `$ledger->content[$columnId]`で値を取得できる
- ModifyColumnコンポーネントで既存値を正しく読み取れる

#### テストでの注意点

テストでLedgerを直接作成する場合、`normalizeByColumnDefine()`が呼ばれないため、手動で正規化された形式でデータを作成する必要があります：

```php
// 正しいテストデータの作成例
$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'content' => [
        0 => '',           // カラムID=0（空でも含める）
        1 => 'テスト値',   // カラムID=1
    ],
]);
```

詳細は [Testing-Best-Practices.md](../development/Testing-Best-Practices.md#-ledgerモデルのcontentデータ構造とテスト) を参照してください。

## 定数

*   **`NEEDED_RELATIONS`**:
    *   一覧表示などで共通して必要となるリレーションの定義配列。`scopeWithNeededRelations` で利用される。
        *   `define:id,title,folder_id`
        *   `creator:id,name`
        *   `latestDiff.inspector:id,name`
        *   `latestDiff.approver:id,name`
