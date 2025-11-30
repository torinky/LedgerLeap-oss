# Active Directory 認証・UI実装詳細計画書

**作成日:** 2025-11-30
**更新日:** 2025-11-30 (要件変更: 組織不一致時の自動修正、棚卸しUI簡素化、理由カラム追加、Persistent Toast)
**対象:** Active Directory連携機能 (Phase 3)
**関連:**
- [Active Directory 連携実装計画](2025-11-28_ad_integration_plan.md)
- [Active Directory 組織同期 詳細設計書](2025-11-29_ad_sync_revision_plan.md)
- [ペルソナ・ユースケース](../../../function/PersonaUseCaseScenario.md)

## 1. 目的

本ドキュメントは、Active Directory (AD) 連携プロジェクトの **Phase 3: 認証プロバイダの切り替えとUI実装** に関する詳細設計と実装計画を定義します。
ハイブリッド認証の実装により、ADユーザーとローカルユーザーの共存を実現し、管理画面およびユーザー画面においてAD連携状態を可視化することで、運用上の混乱を防ぎます。
また、ログイン画面を統合し、組織情報の整合性を自動維持しつつ、例外的な手動運用を厳格に管理する仕組みを導入します。

## 2. 要件と対応方針

`docs/function/PersonaUseCaseScenario.md` および運用ヒアリングから抽出された要件に対し、以下の技術的アプローチを採用します。

| カテゴリ | 要件 (ペルソナ/シナリオ) | 技術的対応方針 |
| :--- | :--- | :--- |
| **認証** | ユーザーはADのID/パスワードでログインを試行する。ADにない場合はローカルDBで認証する。ログイン画面は一本化する。 | **ハイブリッド認証 & 画面統合:** Filament独自のログイン画面 (`/app/login`) を廃止し、Laravel標準のログイン画面 (`/login`) に一本化します。認証ロジック内で `ldap` -> `web` の順に認証を試行します。ログイン後は `redirect()->intended()` により適切なページへ遷移します。 |
| **自動登録** | ADユーザーが初めてログインした時、または定期同期時に自動的にLedgerLeapのアカウントを作成する。 | **Auto-Provisioning (Hybrid):**<br>1. **ログイン時:** 認証成功時にLdapRecordの同期機能で即時登録・更新。<br>2. **バッチ/手動:** `AdSync` コマンドで一括同期（未ログインユーザーも取り込み）。 |
| **検索範囲** | ユーザーを自動同期するためAD検索範囲は複数設定できるようにする。ログイン時の検索範囲は別で設定する。 | **設定分離 (Multi-Base DN):**<br>1. **同期用:** `config/ldap_sync.php` に `sync_search_base_dns` (配列) を定義。<br>2. **ログイン用:** `config/ldap.php` (または `auth`) に `login_search_base_dns` (配列) を定義し、認証時に順次検索します。 |
| **同期管理** | いつ同期されたか把握したい。OrganizationにもAD同期時期を追加する。 | **同期日時カラム:** `users` および `organizations` テーブルに `ad_last_synced_at` カラムを追加し、同期処理ごとに更新します。 |
| **統制** | ADの所属組織とDBの所属組織が違う場合はログイン時に補正する。手動管理が必要な場合は棚卸しを行い、期間を定型で延長する。理由も記録する。 | **組織自動修正 & 定型棚卸し:**<br>1. **自動修正:** ログイン時にAD組織とDB組織を比較。不一致でもAD組織がDB内に存在すれば、ユーザーの所属を自動更新します。<br>2. **例外:** AD組織がDBにない（同期範囲外）場合等はエラー。ただし `ignore_ad_org_sync_until` が有効ならスキップ。<br>3. **棚卸し:** 管理画面の一括アクションで期間延長（設定ファイルの日数分）を実施。同時に `manual_sync_reason` を記録。 |
| **警告** | 期限切れの手動管理ユーザーがいる場合、警告トーストは消せないようにする。 | **Persistent Toast:** 管理画面において、期限切れユーザーが存在する限り、閉じることのできない警告トースト（またはバナー）を常時表示します。 |
| **DB変更** | 開発中のため、DBは気にせず全体マイグレーションしてよい。 | **全体マイグレーション (`migrate:fresh`):** 既存のマイグレーションファイルを修正し、DBを再構築する方針をとります。 |

## 3. アーキテクチャ詳細

### 3.1 認証フロー (統合ログイン)

対象: 統一ログイン画面 (`/login`) - `App\Http\Requests\Auth\LoginRequest`

1.  **入力:** Email (または UserPrincipalName) / Password
2.  **試行1 (LDAP):**
    *   **ループ検索:** `login_search_base_dns` をループしてユーザー検索。
    *   **認証:** パスワード検証。
    *   **成功時処理:**
        *   **手動管理チェック:** `ignore_ad_org_sync_until` が未来であれば、組織チェック・同期をスキップして完了。
        *   **通常処理:**
            *   `users` テーブル同期 (Import)。
            *   **組織解決:** LDAPユーザーの `parent_dn` から対応する `Organization` をDB検索。
            *   **自動修正ロジック:**
                *   **組織が見つかった場合:** 現在の `primaryOrganization` と比較。不一致なら `setPrimaryOrganization` で**自動更新**。
                *   **組織が見つからない場合:** (同期範囲外など)
                    *   `ValidationException` ("所属組織が同期範囲外です。管理者に連絡してください。") をスローして中断。
        *   `ad_last_synced_at` 更新。
        *   Laravel認証 (`ldap` guard)。
        *   完了。
    *   **失敗:** 次のステップへ。
3.  **試行2 (Local):** `Auth::guard('web')->attempt()`

### 3.2 データモデル拡張

*   **Users テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。
    *   `ignore_ad_org_sync_until` (`timestamp`, `nullable`): 手動管理期限。
    *   `manual_sync_reason` (`text`, `nullable`): 手動管理の理由・備考。
*   **Organizations テーブル:**
    *   `ad_last_synced_at` (`timestamp`, `nullable`): 最終AD同期日時。

### 3.3 設定ファイル (`config/ldap_sync.php`)

*   `manual_sync_extension_days`: 棚卸し時の延長日数 (Default: 90)。

### 3.4 UI/UX 設計

*   **Filament UserResource:**
    *   **一覧:**
        *   フィルタ: 「組織手動管理」(有効/期限切れ/なし)。
        *   カラム: `ignore_ad_org_sync_until`, `manual_sync_reason` を表示。
        *   **Bulk Action (棚卸し):**
            *   アクション名: "手動管理期間の更新 (棚卸し)"。
            *   モーダルフォーム: `manual_sync_reason` (Textarea, 必須/任意)。
            *   処理: 選択されたユーザーの期限を `now() + config('manual_sync_extension_days')` に更新し、理由を保存。
    *   **Persistent Alert:**
        *   `ListUsers` ページ (または Global Hook) で期限切れユーザーを検知。
        *   存在する場合、`duration('persistent')` (または `sticky`) な Notification を表示。内容は「組織手動管理の期限切れユーザーがX名います。直ちに対応してください」。

## 4. 実装フェーズ (WBS)

### Phase 3.1: 認証ロジックのハイブリッド化と画面統合
*   [ ] **3.1.1 データベース再構築:** `users` (カラム追加: `ad_last_synced_at`, `ignore_ad_org_sync_until`, `manual_sync_reason`), `organizations` (`ad_last_synced_at`) のスキーマ修正と `migrate:fresh`。
*   [ ] **3.1.2 設定追加:** `config/ldap_sync.php` に `sync_search_base_dns`, `manual_sync_extension_days` を追加。`config/ldap.php` に `login_search_base_dns` を追加。
*   [ ] **3.1.3 ログインリクエスト改修:** `App\Http\Requests\Auth\LoginRequest::authenticate()` を修正。
    *   複数DNループ。
    *   手動管理期限チェック。
    *   **組織自動修正ロジック** (DBに組織があれば更新)。
    *   組織不明時のエラーハンドリング。
*   [ ] **3.1.4 Filament設定変更:** `AdminPanelProvider` から `->login()` を削除。

### Phase 3.2: バッチ同期コマンドの強化
*   [ ] **3.2.1 複数DN対応:** `AdSync` コマンド改修。
*   [ ] **3.2.2 手動管理対応:** 同期処理時、期限内ユーザーの組織変更スキップ。
*   [ ] **3.2.3 同期日時更新:** 成功時更新。

### Phase 3.3: UI改修
*   [ ] **3.3.1 UserResource:**
    *   **Bulk Action実装:** 棚卸し（期間延長＆理由入力）。
    *   **Persistent Notification実装:** 期限切れ警告。
    *   バッジ・カラム表示・フィールド制御。
*   [ ] **3.3.2 OrganizationResource:** バッジ・同期日時表示。
*   [ ] **3.3.3 MyPortal:** AD連携ステータス表示。

## 5. 影響範囲と懸念事項

### 5.1 自動修正による意図しない配転 (Operational Risk)
*   **懸念:** AD側の設定ミス（誤ったOUへの移動など）が、ユーザーがログインした瞬間にLedgerLeapに反映されてしまう。
*   **対応:** 自動修正時は必ずログ (`activity_log`) を記録し、後から追跡可能にする。重要な変更であれば管理者へ通知する仕組みも検討（Phase 4以降）。

### 5.2 Persistent NotificationによるUI阻害 (UX Risk)
*   **懸念:** 警告トーストが消せないため、管理者が対応を完了するまで画面の一部が隠れ続け、操作の邪魔になる可能性がある。
*   **対応:** トーストの表示位置を邪魔になりにくい場所（右下など）にするか、Filamentの `DatabaseNotifications` ではなくページ上部のバナー (`Header Widget`) として実装することを検討する（今回は要件通りトーストで実装するが、運用フィードバックで変更余地あり）。

### 5.3 同期範囲外ユーザーのログイン不可
*   **懸念:** AD同期対象外のOUに移動したユーザーは、手動管理設定をしない限りログインできなくなる。
*   **対応:** 運用ルールとして、同期対象外OUへの移動時は事前に手動管理設定を行うか、ローカルユーザーへの切り替えを行うことを周知する。

## 6. 変更対象ファイル一覧

*   `database/migrations/0001_01_01_000000_create_users_table.php`
*   `database/migrations/2025_10_05_131532_create_organizations_table.php`
*   `config/ldap_sync.php`
*   `config/ldap.php`
*   `app/Http/Requests/Auth/LoginRequest.php`
*   `app/Providers/Filament/AdminPanelProvider.php`
*   `app/Console/Commands/AdSync.php`
*   `app/Services/AdSyncService.php`
*   `app/Filament/Resources/UserResource.php`
*   `app/Filament/Resources/UserResource/Pages/ListUsers.php`
*   `app/Filament/Resources/OrganizationResource.php`
*   `resources/views/livewire/my-portal.blade.php`
*   `routes/web.php`
