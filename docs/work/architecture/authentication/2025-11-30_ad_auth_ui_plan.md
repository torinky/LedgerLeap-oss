# Active Directory 認証・UI実装詳細計画書

**作成日:** 2025-11-30
**更新日:** 2025-11-30 (要件追加: 組織同期日時、ログイン拒否条件、検索範囲分離、ログイン時の複数DN対応、手動管理フラグ)
**対象:** Active Directory連携機能 (Phase 3)
**関連:**
- [Active Directory 連携実装計画](2025-11-28_ad_integration_plan.md)
- [Active Directory 組織同期 詳細設計書](2025-11-29_ad_sync_revision_plan.md)
- [ペルソナ・ユースケース](../../../function/PersonaUseCaseScenario.md)

## 1. 目的

本ドキュメントは、Active Directory (AD) 連携プロジェクトの **Phase 3: 認証プロバイダの切り替えとUI実装** に関する詳細設計と実装計画を定義します。
ハイブリッド認証の実装により、ADユーザーとローカルユーザーの共存を実現し、管理画面およびユーザー画面においてAD連携状態を可視化することで、運用上の混乱を防ぎます。
また、ログイン画面を統合し、組織情報の整合性を厳格にチェックすることでセキュリティ統制を強化します。

## 2. 要件と対応方針

`docs/function/PersonaUseCaseScenario.md` および運用ヒアリングから抽出された要件に対し、以下の技術的アプローチを採用します。

| カテゴリ | 要件 (ペルソナ/シナリオ) | 技術的対応方針 |
| :--- | :--- | :--- |
| **認証** | ユーザーはADのID/パスワードでログインを試行する。ADにない場合はローカルDBで認証する。ログイン画面は一本化する。 | **ハイブリッド認証 & 画面統合:** Filament独自のログイン画面 (`/app/login`) を廃止し、Laravel標準のログイン画面 (`/login`) に一本化します。認証ロジック内で `ldap` -> `web` の順に認証を試行します。ログイン後は `redirect()->intended()` により適切なページへ遷移します。 |
| **自動登録** | ADユーザーが初めてログインした時、または定期同期時に自動的にLedgerLeapのアカウントを作成する。 | **Auto-Provisioning (Hybrid):**<br>1. **ログイン時:** 認証成功時にLdapRecordの同期機能で即時登録・更新。<br>2. **バッチ/手動:** `AdSync` コマンドで一括同期（未ログインユーザーも取り込み）。 |
| **検索範囲** | ユーザーを自動同期するためAD検索範囲は複数設定できるようにする。ログイン時の検索範囲は別で設定する。 | **設定分離 (Multi-Base DN):**<br>1. **同期用:** `config/ldap_sync.php` に `sync_search_base_dns` (配列) を定義。<br>2. **ログイン用:** `config/ldap.php` (または `auth`) に `login_search_base_dns` (配列) を定義し、認証時に順次検索します。 |
| **同期管理** | いつ同期されたか把握したい。OrganizationにもAD同期時期を追加する。 | **同期日時カラム:** `users` および `organizations` テーブルに `ad_last_synced_at` カラムを追加し、同期処理ごとに更新します。 |
| **統制** | ADの所属組織とDBの所属組織が違う場合はログインを拒否する。ただし、手動で明示的に別組織に所属させる場合もある。 | **組織整合性チェック & 手動オーバーライド:**<br>1. **原則:** ログイン時にAD組織とDB組織を比較し、不一致なら拒否。<br>2. **例外:** ユーザーに `ignore_ad_organization_sync` フラグがある場合はチェックをスキップし、手動設定を優先します。<br>3. **提示:** ログイン拒否時はエラーメッセージを表示。管理画面ではフラグ設定UIを提供します。 |
| **可視化** | 管理者は、ユーザーや組織がAD由来のものか手動作成かを見分ける必要がある。 | **バッジ表示:** `objectguid`/`org_id` の有無と `ad_last_synced_at` に基づき、Filamentのリソース画面およびMy Portalに「AD連携」ステータスを表示します。 |
| **DB変更** | マイグレーションは差分マイグレーション不要。 | **既存定義修正:** 新規マイグレーションファイルを作成せず、既存の `create_users_table` 等を修正するか、スキーマ定義を直接更新する方針をとります。 |

## 3. アーキテクチャ詳細

### 3.1 認証フロー (統合ログイン)

対象: 統一ログイン画面 (`/login`) - `App\Http\Requests\Auth\LoginRequest`

1.  **入力:** Email (または UserPrincipalName) / Password
2.  **試行1 (LDAP):**
    *   **ループ検索:** `login_search_base_dns` に設定されたBase DNリストをループ。
    *   各DN配下でユーザーを検索 (`LdapUser::query()->in($dn)->where('mail', $email)->first()`)。
    *   **認証:** ユーザーが見つかった場合、パスワード検証 (`$ldapUser->connection()->auth()->attempt(...)`)。
    *   **成功時処理:**
        *   **手動管理チェック:** ユーザーの `ignore_ad_organization_sync` が `true` か確認。
        *   **Trueの場合:**
            *   組織同期・チェックをスキップ。
            *   `users` テーブルの基本情報(名前・Email)のみ同期。
        *   **Falseの場合:**
            *   `users` テーブルへの同期 (Import) 実行。
            *   **組織チェック:**
                *   LDAPユーザーの `parent_dn` から組織階層を解決（キャッシュまたはDB検索）。
                *   LedgerLeap上の当該ユーザーの `primaryOrganization` と比較。
                *   不一致の場合: `ValidationException` ("組織情報の同期が不完全です。管理者に連絡してください。") をスローして中断。
        *   `ad_last_synced_at` を現在日時で更新。
        *   Laravel認証: `Auth::guard('ldap')->login($user)`。
        *   ループを抜けて完了。
    *   **失敗:** ループを継続。全DNで失敗したら次のステップへ。
3.  **試行2 (Local):** `Auth::guard('web')->attempt()`
    *   **成功:** ログインセッション確立 -> リダイレクト。
    *   **失敗:** 認証エラー (`auth.failed`) を返却。

### 3.2 データモデル拡張

*   **Users テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。
    *   `ignore_ad_organization_sync` (`boolean`, `default: false`): AD組織同期・チェックを無効化するフラグ。
*   **Organizations テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。

### 3.3 UI/UX 設計

*   **Filament:**
    *   `AdminPanelProvider` から `->login()` を削除し、独自ログインページを廃止。
    *   **UserResource:** `ignore_ad_organization_sync` のトグルスイッチを追加。「このユーザーはADの組織構造に従わず、手動で組織を管理する」旨のヘルプテキストを表示。
*   **My Portal:**
    *   AD連携ステータスと最終同期日時を表示。
    *   手動管理ユーザーの場合、「組織手動管理モード」である旨を表示（任意）。

## 4. 実装フェーズ (WBS)

### Phase 3.1: 認証ロジックのハイブリッド化と画面統合
*   [ ] **3.1.1 データベース定義変更:** `users` (カラム追加: `ad_last_synced_at`, `ignore_ad_organization_sync`), `organizations` (カラム追加: `ad_last_synced_at`) のスキーマ変更（既存マイグレーション修正または直接反映）。
*   [ ] **3.1.2 設定分離:**
    *   `config/ldap_sync.php` に `sync_search_base_dns` を追加。
    *   `config/ldap.php` (custom key) に `login_search_base_dns` を追加。
*   [ ] **3.1.3 ログインリクエスト改修:** `App\Http\Requests\Auth\LoginRequest::authenticate()` を修正。
    *   複数DNでの検索・認証試行ループ実装。
    *   **手動管理フラグ (`ignore_ad_organization_sync`) の判定ロジック**追加。
    *   LdapRecord同期処理の呼び出し。
    *   **組織整合性チェックロジック**の実装。
    *   成功時の `ad_last_synced_at` 更新。
*   [ ] **3.1.4 Filament設定変更:** `AdminPanelProvider` から `->login()` を削除。

### Phase 3.2: バッチ同期コマンドの強化
*   [ ] **3.2.1 複数DN対応:** `AdSync` コマンドおよび `AdSyncService` を修正し、`sync_search_base_dns` ループに対応。
*   [ ] **3.2.2 手動管理対応:** 同期処理時、`ignore_ad_organization_sync` が `true` のユーザーの組織変更をスキップするよう修正。
*   [ ] **3.2.3 同期日時更新:** User/Organizationの同期成功時に `ad_last_synced_at` を更新。

### Phase 3.3: UI改修
*   [ ] **3.3.1 UserResource:**
    *   `ignore_ad_organization_sync` トグル追加。
    *   バッジ表示、同期日時表示、フィールド制御。
*   [ ] **3.3.2 OrganizationResource:** バッジ表示、同期日時表示、フィールド制御。
*   [ ] **3.3.3 MyPortal:** AD連携ステータス表示。

## 5. 影響範囲と懸念事項

### 5.1 組織不一致によるログイン拒否 (Operational Risk)
*   **懸念:** 人事異動直後など、ADは更新されたがLedgerLeapの同期バッチが未実行の期間（タイムラグ）、ユーザーがログインできなくなる。
*   **対応:**
    *   エラーメッセージで「組織情報の同期が必要です」と明示。
    *   管理者が手動同期 (`AdSync`) を実行することで解消可能とする。

### 5.2 手動管理ユーザーのリスク (Security Risk)
*   **懸念:** `ignore_ad_organization_sync` を有効にしたユーザーは、AD側で部署移動や退職（アカウント無効化は認証で防げるが、組織権限は残る）があってもLedgerLeap側の権限が維持されてしまう。
*   **対応:**
    *   FilamentのUser一覧で「手動管理ユーザー」をフィルタリングできるようにし、定期的な棚卸しを推奨する。
    *   退職時（ADアカウント削除/無効化）は認証自体が通らなくなるため、システム利用は防げる。

### 5.3 複数DN検索とパフォーマンス (Performance Risk)
*   **懸念:** ログイン時の検索DN数に比例してLDAPレスポンス待ち時間が増加する。
*   **対応:**
    *   `login_search_base_dns` は必要最小限のDNに絞る。
    *   ループ処理において、ヒットした時点で即座に `break` する。

### 5.4 既存Filamentユーザーの動線 (UX)
*   **懸念:** `/app/login` をブックマークしているユーザーが 404 になる。
*   **対応:** `routes/web.php` に `/app/login` から `/login` へのリダイレクト定義を追加する。

## 6. 変更対象ファイル一覧

*   `database/migrations/xxxx_xx_xx_create_users_table.php` (修正)
*   `database/migrations/xxxx_xx_xx_create_organizations_table.php` (修正)
*   `config/ldap_sync.php`
*   `config/ldap.php`
*   `app/Http/Requests/Auth/LoginRequest.php`
*   `app/Providers/Filament/AdminPanelProvider.php`
*   `app/Console/Commands/AdSync.php`
*   `app/Services/AdSyncService.php`
*   `app/Filament/Resources/UserResource.php`
*   `app/Filament/Resources/OrganizationResource.php`
*   `resources/views/livewire/my-portal.blade.php`
*   `routes/web.php`
