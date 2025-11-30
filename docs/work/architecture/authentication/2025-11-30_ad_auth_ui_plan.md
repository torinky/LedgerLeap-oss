# Active Directory 認証・UI実装詳細計画書

**作成日:** 2025-11-30
**更新日:** 2025-11-30 (要件追加: 組織同期日時、ログイン拒否条件、検索範囲分離)
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
| **認証** | ユーザーはADのID/パスワードでログインを試行する。ADにない場合はローカルDBで認証する。ログイン画面は一本化する。 | **ハイブリッド認証 & 画面統合:** Filament独自のログイン画面 (`/app/login`) を廃止し、Laravel標準のログイン画面 (`/login`) に一本化します。認証ロジック内で `ldap` -> `web` の順に認証を試行します。ログイン後はダイレクトリンクにより適切なページへ遷移します。 |
| **自動登録** | ADユーザーが初めてログインした時、または定期同期時に自動的にLedgerLeapのアカウントを作成する。 | **Auto-Provisioning (Hybrid):**<br>1. **ログイン時:** 認証成功時にLdapRecordの同期機能で即時登録・更新。<br>2. **バッチ/手動:** `AdSync` コマンドで一括同期（未ログインユーザーも取り込み）。 |
| **検索範囲** | ユーザーを自動同期するためAD検索範囲は複数設定できるようにする。ログイン時の検索範囲は別で設定する。 | **設定分離 (Multi-Base DN):**<br>1. **同期用:** `config/ldap_sync.php` に `sync_search_base_dns` (配列) を定義。<br>2. **ログイン用:** `config/ldap.php` または専用設定に `login_search_base_dns` (配列) を定義し、認証時に順次検索します。 |
| **同期管理** | いつ同期されたか把握したい。OrganizationにもAD同期時期を追加する。 | **同期日時カラム:** `users` および `organizations` テーブルに `ad_last_synced_at` カラムを追加し、同期処理ごとに更新します。 |
| **統制** | ADの所属組織とDBの所属組織が違う場合はログインを拒否する。 | **組織整合性チェック:** ログイン処理において、ADから取得したユーザーの所属組織(OU)と、DB上の現在の所属組織(`organizations`)を比較します。不一致の場合は同期ズレとみなし、ログインを拒否（例外スロー）します。 |
| **可視化** | 管理者は、ユーザーや組織がAD由来のものか手動作成かを見分ける必要がある。 | **バッジ表示:** `objectguid`/`org_id` の有無と `ad_last_synced_at` に基づき、Filamentのリソース画面およびMy Portalに「AD連携」ステータスを表示します。 |

## 3. アーキテクチャ詳細

### 3.1 認証フロー (統合ログイン)

対象: 統一ログイン画面 (`/login`)

1.  **入力:** Email (または UserPrincipalName) / Password
2.  **試行1 (LDAP):** `Auth::guard('ldap')->attempt()`
    *   **検索ロジック:** `login_search_base_dns` に設定された複数のDNに対して順次検索を実行。
    *   **成功:**
        *   AD認証成功。
        *   `users` テーブルへの同期 (Import) 実行。
        *   **組織チェック:** 同期されたユーザー情報から所属OUを判定し、DB上のPrimary Organizationと比較。不一致なら `ValidationException` ("所属組織情報が同期されていません。管理者に連絡してください。") をスローしてログアウト。
        *   `users` テーブルの `ad_last_synced_at` を更新。
        *   ログインセッション確立 -> `intended` または デフォルトページへリダイレクト。
    *   **失敗:** 次のステップへ。
3.  **試行2 (Local):** `Auth::guard('web')->attempt()`
    *   **成功:** ログインセッション確立 -> リダイレクト。
    *   **失敗:** 認証エラー (`auth.failed`) を返却。

### 3.2 データモデル拡張

*   **Users テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。
*   **Organizations テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。

### 3.3 UI/UX 設計

*   **Filament:**
    *   `AdminPanelProvider` から `->login()` を削除し、独自ログインページを廃止。
    *   未認証時のリダイレクトはLaravel標準 (`Authenticate` middleware) に委譲。
*   **My Portal:**
    *   AD連携ステータスと最終同期日時を表示。

## 4. 実装フェーズ (WBS)

### Phase 3.1: 認証ロジックのハイブリッド化と画面統合
*   [ ] **3.1.1 データベース拡張:** `users`, `organizations` テーブルへ `ad_last_synced_at` カラムを追加するマイグレーション作成・実行。
*   [ ] **3.1.2 設定分離:** `config/ldap_sync.php` 等に `sync_search_base_dns`, `login_search_base_dns` 設定を追加。
*   [ ] **3.1.3 ログインリクエスト改修:** `App\Http\Requests\Auth\LoginRequest::authenticate()` を修正。
    *   複数DNでの検索・認証試行ループ。
    *   **組織整合性チェックロジック**の実装。
    *   成功時の `ad_last_synced_at` 更新。
*   [ ] **3.1.4 Filament設定変更:** `AdminPanelProvider` から `->login()` を削除。

### Phase 3.2: バッチ同期コマンドの強化
*   [ ] **3.2.1 複数DN対応:** `AdSync` コマンドを修正し、`sync_search_base_dns` ループに対応。
*   [ ] **3.2.2 同期日時更新:** User/Organizationの同期成功時に `ad_last_synced_at` を更新。

### Phase 3.3: UI改修
*   [ ] **3.3.1 UserResource/OrganizationResource:** バッジ表示、同期日時表示、フィールド制御。
*   [ ] **3.3.2 MyPortal:** AD連携ステータス表示。

## 5. 影響範囲と懸念事項

### 5.1 組織不一致によるログイン拒否
*   **懸念:** 人事異動直後など、ADは更新されたがLedgerLeapの同期バッチが未実行の期間（タイムラグ）、ユーザーがログインできなくなる。
*   **対応:**
    *   エラーメッセージで「組織情報の同期が必要です」と明示し、管理者に手動同期を促す運用フローを整備する。
    *   緊急時は管理画面から手動同期 (`AdSync`) を実行することで解消可能とする。

### 5.2 複数DN検索とパフォーマンス
*   **懸念:** ログイン時の検索DN数に比例してLDAPレスポンス待ち時間が増加する。
*   **対応:** ログイン用の `login_search_base_dns` は必要最小限のDNに絞るよう設定コメントで推奨する。また、可能な限り上位DNを指定してフィルタで絞り込む運用を推奨する。

### 5.3 既存Filamentユーザーの動線
*   **懸念:** `/app/login` をブックマークしているユーザーが 404 になる可能性がある。
*   **対応:** 必要であれば `routes/web.php` で `/app/login` を `/login` へリダイレクトする定義を追加する。

## 6. 変更対象ファイル一覧

*   `database/migrations/xxxx_xx_xx_add_ad_last_synced_at_cols.php` (新規)
*   `config/ldap_sync.php` (設定追加)
*   `app/Http/Requests/Auth/LoginRequest.php`
*   `app/Providers/Filament/AdminPanelProvider.php`
*   `app/Console/Commands/AdSync.php`
*   `app/Filament/Resources/UserResource.php`
*   `app/Filament/Resources/OrganizationResource.php`
*   `resources/views/livewire/my-portal.blade.php`
