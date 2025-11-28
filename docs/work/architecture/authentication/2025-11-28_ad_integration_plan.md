# Active Directory 連携実装計画

**作成日:** 2025-11-28
**ステータス:** 計画中
**対象:** 認証・認可システム、組織構造同期

## 1. 目的と背景

現在のLedgerLeapはローカルのユーザー管理を行っていますが、企業導入において既存のActive Directory (AD) との統合が必須要件となります。
本計画では、`directorytree/ldaprecord-laravel` ライブラリを使用し、以下の機能を実現します。

1.  **AD認証:** 既存のAD認証情報を使用したログイン。
2.  **組織同期:** ADのOrganizational Unit (OU) 構造を `Organization` モデルに同期。
3.  **ユーザー同期:** ADユーザーを `User` モデルに同期し、所属する `Organization` と紐付け。
4.  **権限連携:** `Organization` に割り当てられたロールを通じて、ユーザーに権限を付与。

**※注意:** ADの構造からLedgerLeapの「フォルダ」を自動生成することは行いません。フォルダはLedgerLeap内で管理されます。

---

## 2. 技術選定

*   **ライブラリ:** [LdapRecord-Laravel](https://ldaprecord.com/docs/laravel/v3/)
    *   選定理由: Laravelとの親和性が高く、Eloquentライクな操作が可能。ドキュメントが充実しており、多くの導入実績がある。
*   **プロトコル:** LDAP / LDAPS

---

## 3. アーキテクチャ設計

### 3.1 データマッピング

| AD オブジェクト | LedgerLeap モデル | 備考 |
| :--- | :--- | :--- |
| **User** | `User` | `sAMAccountName` または `userPrincipalName` を識別子とする。 |
| **OrganizationalUnit (OU)** | `Organization` | `NodeTrait` (`kalnoy/nestedset`) を利用して階層構造を維持する。 |
| **Group** | `Role` (検討中) | ADセキュリティグループをSpatieの `Role` として取り込むか、または手動で `Organization` に既存ロールを割り当てる運用とする。（本計画では手動割り当て、または別途マッピング定義を想定） |

### 3.2 認証・同期フロー

1.  **ログイン時:**
    *   ユーザーがユーザーID/パスワードを入力。
    *   LdapRecordがADに対して認証を試行。
    *   認証成功時、該当ユーザーがDBに存在しなければ作成、存在すれば情報を更新（同期）してログインセッションを発行。

2.  **バッチ同期 (定期実行):**
    *   **Step 1: 組織同期**
        *   ADの指定されたBase DN配下のOUを全取得。
        *   `Organization` テーブルに階層構造 (`parent_id`) を保って同期。
    *   **Step 2: ユーザー同期**
        *   ADユーザーを取得し、`User` テーブルを更新。
        *   ユーザーの `distinguishedName` (DN) を解析し、所属するOUに対応する `Organization` を特定。
        *   `user_organizations` テーブルを更新し、所属関係を同期。

### 3.3 権限モデル（Organizationベース）

LedgerLeapの `UserService` は、ユーザーが所属する `Organization` に割り当てられたロール・権限を継承する仕組みを持っています。

*   **構造:** `User` ∈ `Organization` -> has `Role` -> has `Permission`
*   **運用:**
    *   AD連携により、ユーザーは自動的に適切な `Organization` (部署) に配置されます。
    *   管理者は、LedgerLeap上で各 `Organization` に対して適切な `Role` (例: "経理部員", "承認者") を割り当てます。
    *   ユーザーは、所属する `Organization` に付与されたロールの権限を自動的に継承します。

---

## 4. 実装フェーズ (WBS)

### Phase 1: 環境構築と基本設定
*   [ ] `directorytree/ldaprecord-laravel` のインストール。
*   [ ] `config/ldap.php`, `config/auth.php` の設定。
*   [ ] `.env` へのAD接続情報の追加。
*   [ ] AD接続テスト (Tinker等を使用)。

### Phase 2: モデル・同期ロジック実装
*   [ ] **LdapRecordモデルの作成:**
    *   `App\Ldap\User`, `App\Ldap\OrganizationalUnit` クラスの作成。
*   [ ] **Import/Syncロジックの実装:**
    *   OU を `Organization` に変換するJob/Serviceの実装。
        *   ※既存の `Organization` とのID整合性（GUID等の保存）を考慮する。
    *   User を `User` に変換し、`Organization` に紐付けるハンドラの実装。

### Phase 3: 認証プロバイダの切り替えとテスト
*   [ ] 認証ガードを `ldap` (またはDatabaseとのハイブリッド) に切り替え。
*   [ ] ログインテスト実施。
*   [ ] 権限継承（Organization経由）の動作確認。

---

## 5. 考慮事項・制約

*   **フォルダ管理:** フォルダ（`folders` テーブル）はAD同期の対象外です。台帳の保存先となるフォルダは、LedgerLeap内で別途作成・管理する必要があります。
*   **ロールの割り当て:** ADグループからロールへの自動マッピングを行うかは、要件に応じてPhase 2で詳細化します。基本方針は「Organizationへのロール割り当て」を主とします。
*   **削除の扱い:** ADから削除されたユーザー・組織の扱い（論理削除 vs 物理削除）については、プロジェクトの標準規約（`SoftDeletes`）に従います。
