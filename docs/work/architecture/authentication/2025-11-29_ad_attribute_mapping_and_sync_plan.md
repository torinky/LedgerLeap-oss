# Active Directory 属性マッピングおよび同期詳細計画

**作成日:** 2025-11-29
**更新日:** 2025-11-29 (階層生成ロジック見直し、ネストセット安全性向上、任意深さ対応)
**対象:** Active Directory連携機能 (AD統合)
**関連:** `2025-11-28_ad_integration_plan.md`

## 1. 概要

本ドキュメントは、LedgerLeapとActive Directory (AD) 間のデータ同期における、属性マッピングと同期アルゴリズムの詳細計画を定義します。
OU構造に依存しない柔軟な組織階層の生成（任意の深さ対応）と、`kalnoy/nestedset` の整合性維持を最優先事項とします。

## 2. データモデルの拡張 (スキーマ変更)

ADオブジェクトを一意に識別し、変更（改名や移動）に追従するため、不変の識別子である `objectGuid` を保存するカラムを追加します。

### 2.1. Users テーブル
| カラム名 | 型 | 説明 | 備考 |
| :--- | :--- | :--- | :--- |
| `objectguid` | `string` (または `binary`) | ADの `objectGuid` (Hex string推奨) | **新規追加 (Unique, Nullable)** |
| `name` | `string` | ADの `cn` または `displayName` | 既存 |
| `email` | `string` | ADの `mail` | 既存 |

### 2.2. Organizations テーブル
| カラム名 | 型 | 説明 | 備考 |
| :--- | :--- | :--- | :--- |
| `objectguid` | `string` (または `binary`) | ADの `objectGuid` (または属性ハッシュ) | **新規追加 (Unique, Nullable)** |
| `name` | `string` | 組織名 | 既存 |
| `parent_id` | `integer` | 親組織ID | 既存 (NestedSet) |

---

## 3. 属性マッピング定義

### 3.1. ユーザー (User)

| LedgerLeap (DB) | Active Directory (LDAP) | 同期タイミング | 備考 |
| :--- | :--- | :--- | :--- |
| `objectguid` | `objectguid` | 初回作成時 | 同期キー (Immutable) |
| `name` | `cn` (Common Name) | ログイン時 / バッチ | 表示名として使用 |
| `email` | `mail` | ログイン時 / バッチ | メールアドレス (必須) |

### 3.2. 組織 (Organization) - 属性ベース生成の場合

OU構造ではなく、ユーザーの属性値（会社名、部署名など）から組織階層を動的に生成する場合、`objectguid` は使用せず（null）、組織名と親IDの一意性で管理します。

---

## 4. 同期アルゴリズム

組織階層の生成方法は、構成ファイルにより以下の2パターンから選択可能にします。

### パターンA: 属性ベース階層 (推奨)

ADのOU構造を無視し、ユーザー属性値の組み合わせから論理的な階層を動的に生成します。
設定配列の長さに応じて、**任意の深さの階層構造**に対応します。

*   **設定:** `AD_SYNC_HIERARCHY_ATTRIBUTES=['company', 'department', 'section', 'team']` (例: 4階層)
*   **アルゴリズム:**
    1.  ユーザー同期時に、設定された属性順に値を取得します（値が空の属性はスキップするか、階層をそこで止めるかは設定で制御）。
        *   例: `['A社', '営業部', '第1課', 'チームα']`
    2.  取得した値のリストを用いて、ルートから順に再帰的に Organization を探索・作成します。
        *   **Loop 1 (Level 1):** `A社` を検索。なければルートとして作成 (`saveAsRoot`)。
        *   **Loop 2 (Level 2):** 直前の親(`A社`)の子として `営業部` を検索。なければ作成し、`A社` に追加 (`appendToNode`)。
        *   ...
        *   **Loop N (Level N):** 直前の親(`第1課`)の子として `チームα` を検索。なければ作成し、`第1課` に追加。
    3.  最終的に特定された最下層の Organization (`チームα`) に、ユーザーを所属させます。

### パターンB: OUベース階層 (レガシー)
ADのOU構造 (`organizationalUnit`) をそのまま `Organization` ツリーとしてコピーします。
*   **ロジック:** 以前の計画通り、DNの親子関係を解析して `parent_id` を設定。

---

## 5. ネストセット (NestedSet) の整合性確保

`kalnoy/nestedset` は `parent_id` の変更時に `_lft`, `_rgt` インデックスを自動計算しますが、同期処理での大量更新時の安全性を確保するため、以下の実装を行います。

### 5.1. ノード追加・移動の実装
直接 `parent_id` を更新するのではなく、ライブラリが提供するメソッドを使用します。

*   **新規作成/移動時:**
    ```php
    // 安全な移動・追加
    $organization->appendToNode($parentOrganization)->save();
    ```
    ※ `$parentOrganization` が `null` の場合は `$organization->saveAsRoot()` を使用。

### 5.2. ツリー修復 (FixTree)
同期ジョブの完了時、またはエラー発生時には、必ずツリー構造の再計算を実行し、整合性を保証します。

```php
// 同期処理の finally ブロックで実行
Organization::fixTree();
```

*   **参考:** [lazychaser/laravel-nestedset Documentation](https://github.com/lazychaser/laravel-nestedset#fixing-tree-corruption)

---

## 6. 設定項目 (Configuration)

`config/ldap_sync.php` に定義する項目案。

| 項目名 | デフォルト値 | 説明 |
| :--- | :--- | :--- |
| `AD_SYNC_MODE` | `attribute` | `attribute` (属性ベース) または `ou` (OU構造ベース) |
| `AD_SYNC_HIERARCHY_ATTRIBUTES` | `['department']` | 階層化に使用する属性の配列（順序重要）。任意の深さに対応。 |
| `AD_SYNC_USER_FILTER` | `(objectClass=user)` | 同期対象ユーザーのLDAPフィルタ。 |

---

## 7. 懸念事項と処置

### 7.1. 属性値の揺らぎ
*   **問題:** AD入力ミスにより `Sales Dept` と `Sales  Dept` (スペース2つ) が別の組織として作成される。
*   **処置:** 同期時に `trim()` を行い、大文字小文字を区別しない検索を行う。必要に応じて「名称寄せ」のマッピング辞書機能を将来的に検討。

### 7.2. 部署名変更時の履歴
*   **問題:** AD上で部署名が変更された場合、属性ベース同期では別組織として新規作成され、旧組織が孤立する。
*   **処置:**
    *   旧組織に所属するユーザーがいなくなった時点で、旧組織を論理削除 (`SoftDelete`) するクリーンアップ処理をバッチに含める。

---

## 8. 関連ドキュメント

*   [ペルソナ、ユースケース、シナリオ](../../../function/PersonaUseCaseScenario.md)
*   [権限システム設計](../../../architecture/permission-system.md)
*   [User モデル](../../../models/User.md)
*   [Organization モデル](../../../models/Organization.md)
*   [kalnoy/nestedset 公式ドキュメント](https://github.com/lazychaser/laravel-nestedset)