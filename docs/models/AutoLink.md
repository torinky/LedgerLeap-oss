# AutoLink モデル

## 1. 責務

`AutoLink` モデルは、システム内で利用される自動リンクの定義情報を表現します。正規表現パターン、変換先のURLテンプレート、優先順位、適用範囲などの属性を保持し、`AutoLinkService` によって利用されます。

## 2. データベーススキーマ

### `auto_links` テーブル

自動リンク定義の本体を格納します。

| カラム名 | 型 | 説明 |
| :--- | :--- | :--- |
| `id` | `bigint`, `unsigned`, `PK` | 主キー |
| `label` | `string` | 管理画面で表示するための分かりやすい名前 |
| `pattern` | `string` | リンク対象を検出するための正規表現パターン |
| `url_template` | `string` | 変換先のURLテンプレート |
| `description` | `text`, `nullable` | この定義に関する詳細な説明 |
| `priority` | `integer` | 適用優先順位（小さいほど優先） |
| `is_enabled` | `boolean` | この定義が有効かどうかのフラグ |
| `open_in_new_tab` | `boolean` | リンクを新しいタブで開くかどうかのフラグ |
| `creator_id` | `bigint`, `unsigned`, `nullable` | 作成者のID |
| `modifier_id` | `bigint`, `unsigned`, `nullable` | 最終更新者のID |
| `created_at` | `timestamp` | 作成日時 |
| `updated_at` | `timestamp` | 更新日時 |

### `auto_link_scopes` テーブル

自動リンク定義の適用範囲を管理するための中間テーブルです。ポリモーフィックリレーションを利用して、`Folder` などの様々なモデルに適用範囲を設定できます。

| カラム名 | 型 | 説明 |
| :--- | :--- | :--- |
| `auto_link_id` | `bigint`, `unsigned` | `auto_links.id` への外部キー |
| `scopeable_id` | `bigint`, `unsigned` | 適用対象リソースのID (例: `folders.id`) |
| `scopeable_type` | `string` | 適用対象リソースのモデルクラス名 (例: `App\Models\Folder`) |

## 3. リレーションシップ

### `folders()`

- **種類:** `morphedByMany(Folder::class, 'scopeable')`
- **説明:** この自動リンク定義が適用される `Folder` モデルとの多対多（ポリモーフィック）リレーションを定義します。

### `creator()`, `modifier()`

- **種類:** `belongsTo(User::class)`
- **説明:** 作成者および最終更新者の `User` モデルへのリレーションを定義します。

## 4. 特殊な実装

### `AutoLinkScope` Pivotモデル

- `auto_link_scopes` 中間テーブルの操作には、`AutoLinkScope` Pivotモデルが使用されます。
- **`$touches = ['autoLink']`**: この設定により、適用範囲（フォルダ）が変更された際に、親である `AutoLink` モデルの `updated_at` タイムスタンプも自動的に更新されます。これにより `AutoLinkObserver` が変更を検知し、関連するキャッシュを正確にクリアできます。
