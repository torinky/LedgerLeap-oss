# Active Directory 連携実装記録

**作成日:** 2025-11-29
**対象:** Active Directory連携機能 (AD統合)

## 概要

`docs/work/architecture/authentication/2025-11-28_ad_integration_plan.md` に基づき、Active Directory (AD) 連携の実装を進めています。
本ドキュメントでは、Phase 1 (環境構築と基本設定) の実施結果を記録します。

## Phase 1: 環境構築と基本設定

### 1. 開発環境の整備 (LDAPモック)

#### 1.1. `directorytree/ldaprecord-laravel` のインストール
Composer パッケージ `directorytree/ldaprecord-laravel` をインストールしました。

```bash
composer require directorytree/ldaprecord-laravel
```

#### 1.2. 設定ファイルのパブリッシュ
`config/ldap.php` をパブリッシュしました。

```bash
php artisan vendor:publish --provider="LdapRecord\Laravel\LdapServiceProvider"
```

#### 1.3. Docker Compose 構成の変更
開発環境 (`Sail`) に LDAP モックサーバーを追加するため、`docker-compose.override.yml` を作成・編集しました。
テスト用イメージとして `rroemhild/test-openldap` を採用しました。

**`docker-compose.override.yml`:**
```yaml
services:
  openldap:
    image: rroemhild/test-openldap
    ports:
      - '${FORWARD_LDAP_PORT:-389}:10389' # コンテナ内は10389ポート
    networks:
      - sail
```

#### 1.4. 環境構築スクリプト (`bin/setup.sh`) の改修
Docker Compose のリファクタリング計画に従い、`bin/setup.sh` を修正しました。

*   **キャッシュビルドのデフォルト化:** `--no-cache` オプションを削除し、デフォルトでキャッシュを利用するように変更しました。
*   **キャッシュなしビルドオプションの追加:** `-n` オプションを追加し、キャッシュを使わずに再構築できるようにしました。
*   **`docker-compose.override.yml` の明示的読み込み:** 開発環境モード時に `docker-compose.override.yml` を `COMPOSE_FILE` 環境変数に明示的に追加するロジックを追加しました。

#### 1.5. `.env` への接続情報設定
`.env` ファイルに LDAP 接続情報を追加しました。`rroemhild/test-openldap` のデフォルト設定に合わせています。

```env
# LDAP Configuration for rroemhild/test-openldap
LDAP_LOGGING=true
LDAP_CONNECTION=default
LDAP_HOST=openldap
LDAP_USERNAME="cn=admin,dc=planetexpress,dc=com"
LDAP_PASSWORD="GoodNewsEveryone"
LDAP_PORT=10389
LDAP_BASE_DN="dc=planetexpress,dc=com"
LDAP_TIMEOUT=5
LDAP_SSL=false
LDAP_TLS=false
LDAP_SASL=false
```

### 2. 接続テスト

`php artisan ldap:test` コマンドを実行し、LDAPサーバーへの接続成功を確認しました。

```bash
Testing LDAP connection [default]...
+------------+------------+----------------------------------+-------------------------+---------------+
| Connection | Successful | Username                         | Message                 | Response Time |
+------------+------------+----------------------------------+-------------------------+---------------+
| default    | ✔ Yes      | cn=admin,dc=planetexpress,dc=com | Successfully connected. | 438.12ms      |
+------------+------------+----------------------------------+-------------------------+---------------+
```

## 今後の予定

Phase 2: モデル・同期ロジック実装 に移行します。

*   LdapRecordモデル (`App\Ldap\User`, `App\Ldap\OrganizationalUnit`) の作成
*   属性マッピングの定義
*   同期ロジック (OU同期、ユーザー同期) の実装
