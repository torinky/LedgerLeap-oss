# Active Directory 連携実装記録

**作成日:** 2025-11-29
**対象:** Active Directory連携機能 (AD統合)

## 概要

`docs/work/architecture/authentication/2025-11-28_ad_integration_plan.md` に基づき、Active Directory (AD) 連携の実装を進めています。
本ドキュメントでは、Phase 1 (環境構築と基本設定) および Phase 2 (モデル・同期ロジック実装)、Phase 3 (認証プロバイダ切り替えとテスト) の実施結果を記録します。

## Phase 1: 環境構築と基本設定

*   **完了:** 2025-11-29
*   **内容:**
    *   LDAPモック (`rroemhild/test-openldap`) の導入。
    *   `directorytree/ldaprecord-laravel` のインストールと設定。
    *   `docker-compose.override.yml` へのサービス追加とポート設定 (10389ポート使用)。
    *   `bin/setup.sh` の改修 (キャッシュビルド対応)。
    *   LDAP接続テストの成功。

## Phase 2: モデル・同期ロジック実装

*   **完了:** 2025-11-29
*   **内容:**

### 1. LdapRecordモデルの作成
`App\Ldap\User`, `App\Ldap\OrganizationalUnit` を作成しました。
OpenLDAP互換のため、`LdapRecord\Models\OpenLDAP\User` を継承し、`$guidKey = 'entryuuid'` を設定しました。

### 2. データモデルの拡張 (マイグレーション)
`users` および `organizations` テーブルに `objectguid` カラム (string, nullable, unique) を追加しました。

### 3. 設定ファイル作成
`config/ldap_sync.php` を作成し、同期モード (`attribute`)、階層属性 (`ou`)、LDAPフィルタ等を定義しました。

### 4. 同期コマンド (`ad:sync`) の実装
`app/Console/Commands/AdSync.php` を実装しました。

*   **属性ベース階層生成:** ユーザーの `ou` 属性から `Organization` を動的に作成・階層化。
*   **ユーザー同期:** LDAPユーザーを `User` モデルに同期し、`objectguid` をキーに関連付け。
*   **所属管理:** ユーザーを適切な `Organization` に所属させ、Primaryフラグを設定。
*   **NestedSet修復:** 同期後に `Organization::fixTree()` を実行。
*   **Dry Run機能:** `--dry-run` オプションでDB変更なしに動作確認可能。

### 5. 同期ロジックの修正と安定化 (2025-11-29 追記)
テスト (`Tests\Feature\Console\AdSyncTest`) を通じて発見された問題を修正しました。

*   **ルートへの移動:** 組織がルートレベルへ移動（親がなくなる）した際に、`appendToNode` がスキップされ移動が反映されない問題を修正しました。`$currentOrg->makeRoot()` を明示的に呼び出すロジックを追加しました。
*   **大量削除保護のテスト対応:** テストシナリオにおいて意図的に大規模な組織変更を行う際、安全装置（削除閾値チェック）が働いてテストが失敗しないよう、テストコード内で閾値を一時的に緩和 (`100%`) する対応を行いました。

## Phase 3: 認証プロバイダの切り替えとテスト

*   **状況:** 完了
*   **内容:**

### 1. 認証ガードの設定
`config/auth.php` に `ldap` ガードとプロバイダを追加しました。

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'ldap' => ['driver' => 'session', 'provider' => 'ldap'], // driverはsession
],
'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'ldap' => ['driver' => 'ldap', 'model' => App\Ldap\User::class],
],
```

### 2. 認証テスト
Tinker を使用して、LDAPユーザー (`fry@planetexpress.com`) での認証に成功しました。

```php
Auth::guard('ldap')->attempt(['mail' => 'fry@planetexpress.com', 'password' => 'fry']); // true
```

### 3. 実接続テスト (`LdapRealConnectionTest`) の有効化 (2025-11-29 追記)
これまでスキップされていた `tests/Feature/LdapRealConnectionTest.php` を有効化し、LDAPコンテナとの実際の接続確認を自動テストに組み込みました。

*   **PHPUnit設定:** `phpunit.xml` の環境変数を `rroemhild/test-openldap` コンテナの仕様に合わせて修正しました。
    *   `LDAP_PORT`: `389` -> `10389`
    *   `LDAP_BASE_DN`: `dc=planetexpress,dc=com`
    *   `LDAP_USERNAME`: `cn=admin,dc=planetexpress,dc=com`
    *   `LDAP_PASSWORD`: `GoodNewsEveryone`
*   **Docker設定:** `docker-compose.override.yml` のヘルスチェックポートを `10389` に修正しました。
*   **テストコード修正:**
    *   テストスキップ (`markTestSkipped`) を失敗 (`fail`) に変更し、接続エラーを確実に検知するようにしました。
    *   PHP 8 Attribute (`#[Group]`) を導入し、PHPUnitのDeprecation Warningを解消しました。

## 次のステップ

*   ログイン画面でのガード切り替え、またはハイブリッド認証の実装 (UI/UX)。
*   定期同期ジョブ (`ad:sync`) のスケジューリング設定。
*   本番AD環境での検証。
