# マルチテナント実装課題の解決戦略

**日付:** 2025年9月7日
**作成者:** Gemini
**ステータス:** 合意済み

## 1. 概要
本文書は、マルチテナント機能の実装検証フェーズで確認された複数の課題について、その依存関係を整理し、合理的かつ効率的な解決順序を定義することを目的とする。

## 2. 確認済みの課題一覧
現在、以下の課題が確認されている。

1.  **テナントコンテキストにおけるルーティングとURL生成の問題:**
    テナントのコンテキストで、ルートの解決やURLの生成が正しく行われない問題が複数確認されている。根本原因は、テナントIDの引き渡し漏れなど、テナントコンテキストの考慮不足と推測される。
    *   **事象A (404エラー):** ロールにフォルダ権限を付与したユーザーで、個別の台帳の表示・編集・新規作成ページにアクセスすると`404 Not Found`エラーが発生する。
    *   **事象B (URL生成エラー):** 台帳一覧などで `AutoLinkService` が動作する際、ルート `ledger.lookup` のURL生成に必要な `tenant` パラメータが欠落し、`UrlGenerationException` が発生する。
2.  **ダッシュボードUIの問題:**
    テナントのコンテキストで表示されるダッシュボードウィジェット (`DashboardLinksWidget`) において、本来表示されるべき「台帳定義」画面へのリンクが表示されなくなっている。
    *   **補足:** この問題は、より根本的な「中央管理画面とテナント画面のコンテキストが不明確」というUI/UX上の課題に起因することが判明した。当初、テナントのコンテキストで表示されるダッシュボードウィジェット (`DashboardLinksWidget`) から中央管理画面へのリンクが表示されないという事象が確認されたが、これは意図された動作であると判断された。最終的な解決策として、共通ヘッダーに「設定」メニューを設け、コンテキストに応じた適切なナビゲーションを提供する方針が採択され、実装が完了した。
        *   **詳細:**
            *   `DashboardLinksWidget` は、テナントのコンテキストで表示される場合、中央管理パネルのリンク（例: 「台帳定義」）を表示しないように設計されている。
            *   中央管理パネルのナビゲーションに「設定」メニューが追加され、このメニューから中央管理パネルのダッシュボード（URL: `/app`）にアクセスできるようになった。
            *   中央管理パネルのダッシュボードでは `DashboardLinksWidget` が表示され、URLクエリパラメータ `tenant` を使用してテナントを切り替えることで、ウィジェットの内容（テナント固有の設定リンクなど）も動的に切り替わるように実装された。
            *   **実装上の課題と解決:**
                *   `AdminPanelProvider.php` におけるセッションへのテナントID (`filament_from_tenant_id`) の保存方法に誤りがあったため、`session()->put()` を使用するように修正した。
                *   `App\Filament\Pages\Dashboard.php` の `getWidgets()` メソッドで `DashboardLinksWidget` を呼び出す際に、`from_tenant` プロパティを渡す必要がないため、その部分を削除した。
    *   **関連資料:** [共通ヘッダー「設定」メニュー実装計画書](./2025-09-08_centralized-settings-menu-implementation-plan.md) <span style="color: green;">完了</span>
3.  **ナビゲーションUIの問題:**
    トップメニューに設置したテナント・フォルダ選択機能がツリー形式で表示されるため、UIの幅に収まらず視認性と操作性が低い。これを多階層のドロップダウンメニュー形式に改修する必要がある。
    *   **関連資料:** [テナント・フォルダスイッチャーUI改善計画書](./2025-09-14_tenant-folder-switcher-ui-improvement-plan.md)
4.  **ユーザー管理の仕様・運用上の課題:**
    ユーザーの権限（ロール）変更と、そのユーザーが所属するテナントの情報 (`tenant_user`テーブル) が自動で同期されない。これにより、管理者が手動でユーザーの所属テナントをメンテナンスする必要があり、運用の手間とデータ不整合のリスクが生じている。(詳細は [ユーザー・テナント所属自動同期機能の再検討](./2025-09-07_user-tenant-sync-reconsideration.md) を参照)

5.  **フォルダ新規作成・編集時のテナントID未設定問題:**
    フォルダの新規作成および編集時において、`Folder` モデルに `Stancl\Tenancy\Database\Concerns\BelongsToTenant` トレイトが適用されているにもかかわらず、`tenant_id` が自動的に設定されない、または正しく更新されない問題。この問題は、Filament管理画面からの操作だけでなく、スクラッチで実装された編集ビューからの操作においても発生する可能性がある。これにより、作成・更新されたフォルダが特定のテナントに紐づかず、データ分離の整合性が損なわれる可能性がある。原因として、各ビューからのデータ保存時にテナントコンテキストが適切にモデルに渡されていないことが推測される。

6.  **AutoLink機能のマルチテナント対応不足（システムグローバルな改修）:**
    `AutoLink` 機能（自動リンク生成）がテナントを考慮した動作になっていない。`app/Services/AutoLinkService.php` 内で `AutoLink` モデルをクエリする際に、現在のテナントコンテキストが適用されていないため、他のテナントの `AutoLink` が意図せず適用されたり、現在のテナントの `AutoLink` が正しく機能しなかったりする可能性がある。この機能はシステムグローバルな性質を持つため、単に `tenant_id` でスコープするだけでなく、どのテナントの `AutoLink` をどのコンテキストで適用すべきか、というシステム全体での設計見直しが必要である。`AutoLink` モデル自体に `BelongsToTenant` トレイトの適用、またはサービス層での明示的なテナントスコープの適用、およびその適用ロジックの再検討が求められる。

## 3. 課題間の依存関係の分析
各課題を個別に解決しようとすると、手戻りが発生するリスクがある。特に、以下の依存関係が重要である。

*   **課題4 → 課題3:** 「ユーザーがアクセス可能なテナント」の定義（課題4の仕様）は、ナビゲーションメニュー（課題3）で表示するべき項目を決定する上での大前提となる。
*   **課題4 → 課題1:** 「ユーザーがどのテナントに所属し、どのリソースにアクセスできるか」という認可ロジックの根幹（課題4の仕様）が未確定のままでは、ルーティングや権限の問題（課題1）の表面的な修正しかできず、根本解決に至らない可能性がある。

## 4. 最終的な解決順序
上記の依存関係を考慮し、以下の順序で課題解決に取り組むことに合意した。

1.  **課題4 (ユーザー管理の仕様確定):** <span style="color: green;">完了</span>
    まず、アプリケーションの根幹となる「ユーザーがどのテナントに、どういう条件で所属するのか」という仕様を完全に確定させる。
2.  **課題1 (ルーティング/URL生成の修正):** <span style="color: green;">完了</span>
    次に、確定した仕様に基づいて、アプリケーションの基本的な動作を妨げている致命的なエラーを修正する。
3.  **課題2 (ダッシュボードUIの修正):** <span style="color: green;">完了</span>
    続いて、比較的影響範囲が限定されているUIの不具合を修正する。
4.  **課題3 (ナビゲーションUIの改善):**
    最後に、確定した仕様に基づいて、データ構造に依存するナビゲーションUIの改善を行う。

この「**仕様確定 → 重大なバグ修正 → UI修正・改善**」という流れにより、手戻りを最小限に抑え、効率的に問題解決を進める。

## 5. 仕様確定の議論（課題4）

課題解決の前提となる「ユーザー管理の仕様」を確定させるため、議論を行った。

### 5.1. アーキテクチャの検討

当初、`tenant_user` テーブルは維持しつつ、その内容を権限変更に応じて自動で同期するアプローチ（詳細は[ユーザー・テナント所属自動同期機能の再検討](./2025-09-07_user-tenant-sync-reconsideration.md)を参照）も検討されましたが、その後の議論で`tenant_user`テーブル自体を利用しない、より抜本的なアプローチを検討することになりました。

具体的には、以下の2つのアプローチが比較検討されました。

*   **A案: `role_tenant` 新設案:**
    *   Web検索結果に基づくアプローチ。
    *   「ロール」と「テナント」を直接紐付ける `role_tenant` 中間テーブルを新設する。
    *   **メリット:** テナントへのアクセス権と、フォルダへのアクセス権の責務が明確に分離され、堅牢な設計になる。
    *   **デメリット:** DBスキーマの変更が必要になり、改修規模が大きくなる。

*   **B案: `role_folder_permissions` 流用案:**
    *   ユーザーからの提案。
    *   既存の「ロールとフォルダの紐付け」情報を信頼できる唯一の情報源とし、あるロールが特定テナントのフォルダに1つでも権限を持てば、そのテナントにアクセス可能と見なす。
    *   **メリット:** DBスキーマの変更が不要で、既存の権限設定UIを流用できる。
    *   **デメリット:** 「空のテナント」への権限付与ができない、意図しない権限剥奪が起こりうる、などの運用上のリスクや、パフォーマンスへの懸念が指摘された。

### 5.2. 設計思想の確認

議論の過程で、本アプリケーションにおける以下の重要な設計思想が確認された。

> **テナントは単なるデータの入れ物（スコープ）であり、アクセス制御の単位ではない。アクセス制御は、あくまで中身の「フォルダ」や「台帳の個別カラム」レベルで行うべきである。**

この思想に基づき、既存のポリシー（`FolderPolicy`, `LedgerPolicy`等）を確認したところ、認可ロジックはテナントへの所属有無ではなく、ユーザーが対象フォルダへの権限を持つか否かで判断されており、この思想が一貫して実装されていることが確認できた。

### 5.3. 最終的なアーキテクチャ方針

上記の設計思想に基づき、当初懸念されたB案のデメリットは問題にならない、あるいは別の方法で解決可能であると判断。最終的に、**B案（`role_folder_permissions` 流用案）** を採用することに合意した。

*   **方針1: 動的テナントリスト:** `role_folder_permissions` を辿り、ユーザーがアクセス可能なテナントリストを動的に生成する。パフォーマンス対策として結果はキャッシュする。
*   **方針2: 新規テナントの扱い:** テナント作成時に「ルートフォルダ」を自動生成し、指定された初期ロールにそのフォルダへの権限を付与することで、「空のテナント」問題に対応する。
*   **方針3: データ表示制御:** テナントへのアクセス自体は制限せず、台帳などを表示する際に、権限のないカラムやボタンは隠蔽（マスキング）する。（これは既存実装の踏襲）

## 6. 詳細な実行計画

上記の方針に基づき、以下のステップで実装を進める。

### ステップ1: 現状のポリシー実装の確認
*   **目的:** 既存の認可ロジックが、合意した設計思想と完全に一致しているかを再確認する。
*   **作業:** `LedgerPolicy`, `FolderPolicy` 等の関連ポリシーのコードレビュー。
*   **確認事項:** 認可の判断基準が、`tenant_user` のような所属情報ではなく、`role_folder_permissions` に基づくフォルダ単位の権限になっていること。
*   **状況:** <span style="color: green;">完了</span>。設計思想との一致を確認済み。

### ステップ2: `TenantAccessService` の実装
*   **目的:** ユーザーがアクセス可能なテナントリストを動的に生成する、再利用可能なサービスを実装する。
*   **作業:**
    1.  `app/Services/TenantAccessService.php` を作成する。
    2.  `getAccessibleTenants(User $user)` メソッドを実装する。このメソッドは、ユーザーのロールから `role_folder_permissions` -> `folders` -> `tenants` を辿り、重複のないテナントリストを返す。
    3.  上記の結果を、ユーザーIDをキーとしてキャッシュするロジック（`Cache::remember`）を実装する。
*   **確認事項:** サービスが正しいテナントリストを返すことを確認するためのユニットテストを作成する。
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Services/TenantAccessService.php` に、ユーザーのロールから `role_folder_permissions` を辿ってアクセス可能なテナントリストを動的に生成し、キャッシュするロジックを実装した。
    *   ユニットテスト `tests/Unit/Services/TenantAccessServiceTest.php` を作成し、サービスが期待通りに動作することを確認済み。

### ステップ3: キャッシュ無効化 `Observer` の実装
*   **目的:** 権限情報が変更された際に、`TenantAccessService` のキャッシュを自動的にクリアし、情報の鮮度を保つ。
*   **作業:**
    1.  `UserObserver` を作成または修正し、ユーザーのロールが変更された際に、該当ユーザーのキャッシュをクリアする処理を追加する。
    2.  `RoleFolderPermissionObserver` を作成し、権限が変更された際に、影響を受ける全ユーザーのキャッシュをクリアする処理を追加する。
    3.  `FolderObserver` を作成または修正し、フォルダが削除されたり、テナントIDが変更されたりした場合に、関連するキャッシュをクリアする処理を追加する。
*   **確認事項:** 関連モデルの変更時に、キャッシュが正しくクリアされることを確認するテストを作成する。
*   **状況:** <span style="color: green;">完了</span>

#### ステップ3.1: `UserObserver` の実装
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Observers/UserObserver.php` に、ユーザーの更新・削除時にキャッシュをクリアするロジックを実装した。
    *   ユニットテスト `tests/Unit/Observers/UserObserverTest.php` を作成し、Observerが期待通りに動作することを確認済み。
*   **想定と異なった点と解決策:**
    *   **`EventServiceProvider` の登録ミス:** `UserObserver` の `use` 文の追加漏れや、`$observers` プロパティへの登録方法の誤りにより、`ParseError` や `InvalidArgumentException` が多発した。`write_file` によるファイル全体の上書きで確実に修正した。

#### ステップ3.2: `RoleFolderPermissionObserver` の実装
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Observers/RoleFolderPermissionObserver.php` に、権限の作成・更新・削除時にキャッシュをクリアするロジックを実装した。
    *   ユニットテスト `tests/Unit/Observers/RoleFolderPermissionObserverTest.php` を作成し、Observerが期待通りに動作することを確認済み。
*   **想定と異なった点と解決策:**
    *   **`make:observer` コマンドのエラー:** `AppServiceProvider` での事前登録が原因で、Observerファイル作成時にエラーが発生した。一時的に登録を解除してファイルを作成し、その後登録を戻すことで解決した。
    *   **`modifier_id` 欠落エラー:** テストコードで `role_folder_permissions` に直接 `insert` する際に `modifier_id` が欠落していた。テストコードを修正し、`modifier_id` を含めることで解決した。
    *   **`ValueError` (Enum):** `permission` カラムにEnumの不正な値（整数 `1`）を渡した。テストコードを修正し、`FolderPermissionType::READ` を使うことで解決した。
    *   **`ParseError` (テストファイル):** テストコード修正時の `replace` 失敗による構文エラー。`write_file` によるファイル全体の上書きで確実に修正した。
    *   **`InvalidCountException` (Observer起動せず):** `RoleFolderPermission::create()` してもObserverが起動しない問題。`EventServiceProvider` の登録方法を `$observers` プロパティに統一することで解決した。
    *   **`InvalidCountException` (Mockery 捕捉せず):** Observerは動いているのにテストが失敗する問題。`TenantAccessService` がシングルトンとして登録されていなかったため、テストでスパイしたインスタンスがObserverに渡っていなかった。`AppServiceProvider` でシングルトン登録し、テストの `setUp` でモックを束縛することで解決した。
    *   **`InvalidCountException` (二重呼び出し):** `deleted` イベントで `clearCache` が2回呼ばれる問題。`LogsActivity` トレイトが原因で二重呼び出しが発生していた。`RoleFolderPermissionObserver` の `deleted` メソッドから `clearCache` の呼び出しを削除することで解決した。
    *   **`RoleFolderPermission` モデルの `delete()` 問題:** `Pivot` から `Model` への継承変更後も `delete()` が `TypeError` を起こした。`RoleFolderPermission` モデルの `delete()` メソッドをオーバーライドし、複合主キーで削除するロジックを記述することで解決した。

#### ステップ3.3: `FolderObserver` の実装
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Observers/FolderObserver.php` に、フォルダの `parent_id` または `tenant_id` が変更された場合、あるいはフォルダが削除された場合に、関連する全ユーザーのテナントアクセスキャッシュをクリアするロジックを実装した。
    *   `app/Services/TenantAccessService.php` にキャッシュタグ機能を追加し、関連キャッシュを一括で効率的にクリアできるように改修した。
    *   ユニットテスト `tests/Unit/Observers/FolderObserverTest.php` を作成し、Observerが期待通りに動作することを確認済み。

##### 3.2.1. 既存キャッシュ処理の移管と他のテストへの影響

*   RoleFolderPermission モデルの booted()メソッド内にあったキャッシュクリアロジックは、削除されたのではなく、RoleFolderPermissionObserverに処理を一本化しました。Observer内で、既存の WritableFolderRepository のキャッシュクリアと、今回実装した TenantAccessService のキャッシュクリアの両方が実行されるようにしています。これにより、キャッシュクリア機能が失われることはありません。
  
*   また、RoleFolderPermission モデルの継承元変更（Pivot から Model へ）や複合主キー対応は、このモデルを直接操作するテストや、関連するリレーションを介して操作するテストに影響を与える可能性があります。
  
*   担保: 計画のステップ3.3完了後、プロジェクト全体のテストスイートを実行し、すべてのテストがパスすることを確認することで、これらの変更が既存機能に影響を与えていないことを担保します。

### ステップ4: テナント作成処理の修正
*   **目的:** 新規テナント作成時に、初期ロールに対してアクセス権を自動で付与し、「空のテナント」問題を防ぐ。
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Console/Commands/SetupTenant.php` と `app/Filament/Resources/TenantResource/Pages/CreateTenant.php` の両方で、テナント作成時にデフォルトのルートフォルダを自動生成し、そのテナントの管理者（`Super Admin`ロールを持つユーザー）にフォルダへの `ADMIN` 権限を自動的に付与するロジックを実装した。
    *   `tests/Feature/SetupTenantCommandTest.php` に、権限が正しく付与されることを検証するアサーションを追加し、テストが成功することを確認済み。
    *   実装の過程で、`TenantAccessService` のメソッド名変更（`clearCache` -> `clearUserCache`）に伴い、`UserObserver` と `RoleFolderPermissionObserver` で古いメソッド名を呼び出していたことが原因でテストが失敗した。これを修正し、テストスイート全体が正常にパスすることを確認した。

### ステップ5: 既存UIへの統合
*   **目的:** `TenantSwitcher` など、テナントリストを必要とするUIコンポーネントが、新しい `TenantAccessService` を利用するように修正する。
*   **状況:** <span style="color: green;">完了</span>
*   **結果と証拠:**
    *   `app/Livewire/TenantSwitcher.php` を修正し、テナントリストの取得ロジックを、`UserService` への依存から `TenantAccessService` を直接利用する形にリファクタリングした。
    *   これにより、`TenantAccessService` に実装されたキャッシュ機構の恩恵を受けられるようになり、パフォーマンスが向上した。
    *   関連するフィーチャーテスト `tests/Feature/Livewire/TenantSwitcherTest.php` が、修正後もすべてパスすることを確認済み。

### ステップ6: `tenant_user` テーブルの廃止と関連コードのクリーンアップ
*   **目的:** `tenant_user` テーブルとそれに依存するリレーション (`User::tenants()`, `Tenant::users()`) を完全に廃止し、ユーザーのテナントへのアクセス権は `TenantAccessService` を通じて動的に決定されるアーキテクチャに統一する。
*   **状況:** <span style="color: blue;">計画済み</span>
*   **影響範囲と作業計画:**
    *   **影響範囲の特定:** `tenant_user` テーブル、および `User::tenants()` と `Tenant::users()` リレーションは、以下のファイルで参照されていることが確認された。
        *   モデル: `app/Models/User.php`, `app/Models/Tenant.php`
        *   コントローラー: `app/Http/Controllers/GlobalMyPortalController.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
        *   コマンド: `app/Console/Commands/SetupTenant.php`
        *   Filamentページ: `app/Filament/Resources/TenantResource/Pages/CreateTenant.php`
        *   テスト: `tests/Feature/SetupTenantCommandTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Http/Controllers/LedgerLookupControllerTest.php`
        *   マイグレーション: `database/migrations/2025_08_30_000000_create_tenant_user_table.php`, `database/migrations/2020_05_15_000010_create_tenant_user_impersonation_tokens_table.php`

    *   **作業計画:**
        1.  **リレーションの削除:** `User`モデルと`Tenant`モデルから、それぞれ`tenants()`と`users()`リレーションを削除する。
        2.  **リレーション利用箇所の修正:**
            *   `GlobalMyPortalController` と `AuthenticatedSessionController` で、`$user->tenants` へのアクセスを `app(TenantAccessService::class)->getAccessibleTenants($user)` の呼び出しに置き換える。
            *   `SetupTenant` コマンドと `CreateTenant` ページから、`$tenant->users()->syncWithoutDetaching(...)` の呼び出しを削除する。
        3.  **テストコードの修正:** `tenant_user` テーブルへのアサーション (`assertDatabaseHas`) や、リレーションへの操作 (`attach`) を、ロールとフォルダ権限の付与によって代替するロジックに修正する。
        4.  **データベースマイグレーション:**
            *   `database/migrations/2025_08_30_000000_create_tenant_user_table.php` ファイルを削除する。
            *   `database/migrations/2020_05_15_000010_create_tenant_user_impersonation_tokens_table.php` を調査し、不要であれば削除する。
*   **確認事項:**
    *   `php artisan migrate:fresh --seed` がエラーなく完了すること。
    *   アプリケーションが `tenant_user` テーブルに依存していないことを確認する。
    *   `vendor/bin/sail test` を実行し、既存のテストがすべてパスすること。

### ステップ7: フォルダ新規作成・編集時のテナントID設定問題の解決

*   **目的:** `Folder` モデルに `tenant_id` が正しく設定されるようにし、データ分離の整合性を確保する。
*   **状況:** <span style="color: blue;">計画中</span>
*   **作業計画:**
    1.  **Filamentでのフォルダ作成時の `tenant_id` 自動設定:**
        *   `app/Filament/Resources/FolderResource.php` の `form()` メソッドに隠しフィールド `Forms\Components\Hidden::make('tenant_id')` を追加し、`default(fn() => tenant()->id)` を設定する。
        *   **確認事項:** Filamentでフォルダを作成し、DBで `tenant_id` が正しく設定されていることを確認する。
    2.  **Filamentでのフォルダ編集時の `tenant_id` 変更不可化:**
        *   `app/Filament/Resources/FolderResource.php` の `form()` メソッドで、`tenant_id` フィールドを `disabledOn('edit')` に設定する。
        *   **確認事項:** Filamentで既存フォルダを編集し、`tenant_id` が変更できないことを確認する。
    3.  **スクラッチの編集ビューでのフォルダ作成・編集時の `tenant_id` 設定:**
        *   スクラッチで実装されているフォルダの作成・編集ビューを特定する。
        *   該当するコントローラ/Livewireコンポーネントで、フォルダ保存時に `tenant_id` を明示的に設定するロジックを追加する。
        *   **確認事項:** スクラッチのビューでフォルダを作成・編集し、`tenant_id` が正しく設定/維持されていることを確認する。
    4.  **既存のテストの確認と追加:**
        *   既存の `Folder` モデルや `FolderResource` に関連するテストが、今回の変更で影響を受けないか確認する。
        *   `tenant_id` の設定に関する新しいフィーチャーテストを追加する。

## 7. 関連ドキュメント
*   **[新マルチテナント実装計画書 (最終版)](./2025-08-30_new-multi-tenant-implementation-plan-final.md)**
