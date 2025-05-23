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

## 定数

*   **`NEEDED_RELATIONS`**:
    *   一覧表示などで共通して必要となるリレーションの定義配列。`scopeWithNeededRelations` で利用される。
        *   `define:id,title,folder_id`
        *   `creator:id,name`
        *   `latestDiff.inspector:id,name`
        *   `latestDiff.approver:id,name`
