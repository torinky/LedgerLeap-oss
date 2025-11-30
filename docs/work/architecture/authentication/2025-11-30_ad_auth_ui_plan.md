# Active Directory 認証・UI実装詳細計画書

**作成日:** 2025-11-30
**更新日:** 2025-11-30 (要件追加: 組織同期日時、ログイン拒否条件、検索範囲分離、ログイン時の複数DN対応、手動管理期限設定)
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
| **統制** | ADの所属組織とDBの所属組織が違う場合はログインを拒否する。ただし、手動管理が必要な場合は期限付きで許可し、定期的な棚卸しを行う。 | **組織整合性チェック & 期限付き例外:**<br>1. **原則:** ログイン時にAD組織とDB組織を比較し、不一致なら拒否。<br>2. **例外:** ユーザーに `ignore_ad_org_sync_until` (期限) が設定されており、かつ現在日時より未来である場合はチェックをスキップします。<br>3. **提示:** 管理画面で期限切れユーザーをフィルタリングし、Toast通知等で管理者に処置を促します。 |
| **可視化** | 管理者は、ユーザーや組織がAD由来のものか手動作成かを見分ける必要がある。 | **バッジ表示:** `objectguid`/`org_id` の有無と `ad_last_synced_at` に基づき、Filamentのリソース画面およびMy Portalに「AD連携」ステータスを表示します。 |
| **DB変更** | 開発中のため、DBは気にせず全体マイグレーションしてよい。 | **全体マイグレーション (`migrate:fresh`):** 既存のマイグレーションファイルを修正し、DBを再構築する方針をとります。 |

## 3. アーキテクチャ詳細

### 3.1 認証フロー (統合ログイン)

対象: 統一ログイン画面 (`/login`) - `App\Http\Requests\Auth\LoginRequest`

1.  **入力:** Email (または UserPrincipalName) / Password
2.  **試行1 (LDAP):**
    *   **ループ検索:** `login_search_base_dns` に設定されたBase DNリストをループ。
    *   各DN配下でユーザーを検索 (`LdapUser::query()->in($dn)->where('mail', $email)->first()`)。
    *   **認証:** ユーザーが見つかった場合、パスワード検証 (`$ldapUser->connection()->auth()->attempt(...)`)。
    *   **成功時処理:**
        *   **手動管理チェック:** ユーザーの `ignore_ad_org_sync_until` が **現在日時より未来** か確認。
        *   **有効期間内の場合:**
            *   組織同期・チェックをスキップ。
            *   `users` テーブルの基本情報(名前・Email)のみ同期。
        *   **期限切れ または 未設定の場合:**
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
    *   `ignore_ad_org_sync_until` (`timestamp`, `nullable`): AD組織同期・チェックを無効化する期限。Nullまたは過去の場合は無効（同期強制）。
*   **Organizations テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。

### 3.3 UI/UX 設計

*   **Filament:**
    *   `AdminPanelProvider` から `->login()` を削除し、独自ログインページを廃止。
    *   **UserResource:**
        *   **フォーム:** `ignore_ad_org_sync_until` の DatePicker (または DateTimePicker) を追加。「この日まで組織チェックをスキップ」する旨を説明。
        *   **一覧フィルタ:** 「組織手動管理」フィルタを追加。
            *   *有効 (Active):* 期限内
            *   *期限切れ (Expired):* 期限設定あり、かつ過去
            *   *なし:* 設定なし
        *   **アラート:** 管理者がUser一覧を表示した際、期限切れユーザーが存在すればToastで「組織手動管理の期限切れユーザーがX名います。確認してください」と警告を表示。
*   **My Portal:**
    *   AD連携ステータスと最終同期日時を表示。
    *   手動管理ユーザーの場合、「組織構成は手動管理されています（期限: YYYY/MM/DD）」と表示。

## 4. 実装フェーズ (WBS)

### Phase 3.1: 認証ロジックのハイブリッド化と画面統合
*   [ ] **3.1.1 データベース再構築:**
    *   `database/migrations/xxxx_create_users_table.php`: `ad_last_synced_at`, `ignore_ad_org_sync_until` を追加。
    *   `database/migrations/xxxx_create_organizations_table.php`: `ad_last_synced_at` を追加。
    *   `php artisan migrate:fresh --seed` 実行。
*   [ ] **3.1.2 設定分離:**
    *   `config/ldap_sync.php` に `sync_search_base_dns` を追加。
    *   `config/ldap.php` に `login_search_base_dns` を追加。
*   [ ] **3.1.3 ログインリクエスト改修:** `App\Http\Requests\Auth\LoginRequest::authenticate()` を修正。
    *   複数DNでの検索・認証試行ループ実装。
    *   **期限付き手動管理 (`ignore_ad_org_sync_until`) の判定ロジック**追加。
    *   **組織整合性チェックロジック**の実装。
    *   成功時の `ad_last_synced_at` 更新。
*   [ ] **3.1.4 Filament設定変更:** `AdminPanelProvider` から `->login()` を削除。

### Phase 3.2: バッチ同期コマンドの強化
*   [ ] **3.2.1 複数DN対応:** `AdSync` コマンドおよび `AdSyncService` を修正し、`sync_search_base_dns` ループに対応。
*   [ ] **3.2.2 手動管理対応:** 同期処理時、`ignore_ad_org_sync_until` が有効なユーザーの組織変更をスキップするよう修正。
*   [ ] **3.2.3 同期日時更新:** User/Organizationの同期成功時に `ad_last_synced_at` を更新。

### Phase 3.3: UI改修
*   [ ] **3.3.1 UserResource:**
    *   `ignore_ad_org_sync_until` DatePicker追加。
    *   **期限切れ検知・Toast通知ロジック** (`ListUsers` ページコンポーネント等で実装)。
    *   フィルタ実装。
    *   バッジ表示、同期日時表示、フィールド制御。
*   [ ] **3.3.2 OrganizationResource:** バッジ表示、同期日時表示、フィールド制御。
*   [ ] **3.3.3 MyPortal:** AD連携ステータス表示。

## 5. 影響範囲と懸念事項

### 5.1 期限切れによる突然のログイン不可 (Operational Risk)
*   **懸念:** `ignore_ad_org_sync_until` で設定した日付を過ぎた瞬間、組織不一致チェックが有効になり、ユーザーがログインできなくなる可能性がある。
*   **対応:**
    *   Filament管理画面でのToast通知により、管理者に期限切れ前の更新（棚卸し）を強く促す。
    *   My Portalでもユーザー自身に「組織手動管理の期限が迫っています」と表示することを検討（Phase 3.3）。

### 5.2 全体マイグレーションによるデータ損失
*   **懸念:** `migrate:fresh` を行うため、開発中のテストデータは全てリセットされる。
*   **対応:** 開発チーム内（ユーザー含む）でデータリセットの合意済みである前提で進める。Seederが最新のスキーマに対応しているか確認が必要。

### 5.3 複数DN検索とパフォーマンス (Performance Risk)
*   **懸念:** ログイン時の検索DN数に比例してLDAPレスポンス待ち時間が増加する。
*   **対応:** `login_search_base_dns` は必要最小限のDNに絞る運用とする。

## 6. 変更対象ファイル一覧

*   `database/migrations/0001_01_01_000000_create_users_table.php` (想定)
*   `database/migrations/2025_10_05_131532_create_organizations_table.php` (想定)
*   `config/ldap_sync.php`
*   `config/ldap.php`
*   `app/Http/Requests/Auth/LoginRequest.php`
*   `app/Providers/Filament/AdminPanelProvider.php`
*   `app/Console/Commands/AdSync.php`
*   `app/Services/AdSyncService.php`
*   `app/Filament/Resources/UserResource.php`
*   `app/Filament/Resources/UserResource/Pages/ListUsers.php` (新規/拡張: Toast通知用)
*   `app/Filament/Resources/OrganizationResource.php`
*   `resources/views/livewire/my-portal.blade.php`
*   `routes/web.php`