# PermissionService

## 目的

`PermissionService` は、システム内の様々なリソース（フォルダ、台帳定義、台帳レコードなど）に対するユーザーのアクセス権限を詳細に判定し、関連するロールや組織の情報を取得するためのサービスです。特に、権限の継承関係や包含関係を考慮した複雑なロジックをカプセル化し、UIでの権限表示やアクセス制御の基盤を提供します。

## クラス概要

*   **クラス名**: `App\Services\PermissionService`
*   **役割**: 権限表示およびアクセス権限の判定ロジックを提供します。

## 主要な公開メソッド

*   **`__construct(UserService $userService)`**:
    *   目的・機能: `PermissionService` の新しいインスタンスを初期化します。`UserService` を注入して、ユーザーのロールや権限に関する情報取得に利用します。
    *   引数:
        *   `$userService`: ユーザーサービス (`App\Services\UserService`) のインスタンス。

*   **`getAccessRolesWithPermissions(int $resourceId, string $resourceType): Collection`**:
    *   目的・機能: 指定されたリソース（フォルダ、台帳定義、台帳）に対してアクセス可能なロールと、そのロールが持つ権限タイプ（読み取り、書き込み、管理など）のコレクションを取得します。フォルダの継承権限も考慮されます。
    *   引数:
        *   `$resourceId`: 対象リソースのID。
        *   `$resourceType`: 対象リソースのタイプ（`Ledger`, `LedgerDefine`, `Folder`）。
    *   戻り値: `Collection<object{role: Role, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>` - アクセス可能なロールと権限のコレクション。

*   **`getAccessOrganizationsWithPermissions(int $resourceId, string $resourceType): Collection`**:
    *   目的・機能: 指定されたリソースに対してアクセス可能な組織と、その組織が持つ権限タイプ、および組織に紐づくロールのコレクションを取得します。組織階層も考慮されます。
    *   引数:
        *   `$resourceId`: 対象リソースのID。
        *   `$resourceType`: 対象リソースのタイプ（`Ledger`, `LedgerDefine`, `Folder`）。
    *   戻り値: `Collection<object{organization: Organization, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool, direct_roles: Collection<Role>, inherited_roles: Collection<Role>}>` - アクセス可能な組織と権限のコレクション。

*   **`getAccessUsers(int $resourceId, string $resourceType, ?string $searchQuery = null, ?int $filterByRoleId = null, ?int $filterByOrganizationId = null, ?string $filterByPermissionValue = ''): LengthAwarePaginator`**:
    *   目的・機能: 指定されたリソースに対してアクセス可能なユーザーのリストを、検索クエリ、ロール、組織、権限タイプでフィルタリングして取得します。結果はページネーションされます。
    *   引数:
        *   `$resourceId`: 対象リソースのID。
        *   `$resourceType`: 対象リソースのタイプ。
        *   `$searchQuery`: ユーザー名またはメールアドレスでの検索文字列。
        *   `$filterByRoleId`: ロールIDでフィルタリング。
        *   `$filterByOrganizationId`: 組織IDでフィルタリング。
        *   `$filterByPermissionValue`: 権限タイプ（`FolderPermissionType` の値）でフィルタリング。
    *   戻り値: `LengthAwarePaginator<User>` - ページネーションされたユーザーのコレクション。

*   **`getCurrentUserHighestPermission(int $resourceId, string $resourceType): ?FolderPermissionType`**:
    *   目的・機能: ログイン中のユーザーが指定されたリソースに対して持つ、最も強いアクセス権限（`FolderPermissionType`）を取得します。スーパー管理者権限や継承権限も考慮されます。
    *   引数:
        *   `$resourceId`: 対象リソースのID。
        *   `$resourceType`: 対象リソースのタイプ。
    *   戻り値: `?FolderPermissionType` - 最も強い権限のEnumインスタンス、または `null`。

*   **`getCurrentUserAllPermissions(int $resourceId, string $resourceType): ?array`**:
    *   目的・機能: ログイン中のユーザーが指定されたリソースに対して持つ、すべてのアクセス権限（`FolderPermissionType` の配列）を取得します。スーパー管理者権限や継承権限も考慮されます。
    *   引数:
        *   `$resourceId`: 対象リソースのID。
        *   `$resourceType`: 対象リソースのタイプ。
    *   戻り値: `?array` - 権限のEnumインスタンスの配列、または `null`。

## 依存する他のクラスや設定

*   **サービス**:
    *   `App\Services\UserService`
*   **モデル**:
    *   `App\Models\Folder`
    *   `App\Models\Ledger`
    *   `App\Models\LedgerDefine`
    *   `App\Models\Organization`
    *   `App\Models\Role`
    *   `App\Models\User`
    *   `App\Models\RoleFolderPermission`
*   **Enum**:
    *   `App\Enums\FolderPermissionType`
*   **ファサード**:
    *   `Illuminate\Support\Facades\Auth`
    *   `Illuminate\Support\Facades\Cache`

## その他

*   権限の判定には、`FolderPermissionType` の `includes()` メソッドを用いて、権限の包含関係（例: ADMIN は WRITE を含む）を考慮しています。
*   パフォーマンス向上のため、一部のメソッドではキャッシュを利用しています。
*   `getAccessUsers` メソッドでは、ユーザーの直接のロール、所属組織のロール、およびその祖先の組織のロールを考慮して、アクセス可能なユーザーを特定します。
