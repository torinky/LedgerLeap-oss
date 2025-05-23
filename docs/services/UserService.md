# UserService

## サービスの責務
ユーザー (`User`) モデルに関連するビジネスロジック、特に権限やロールの管理、ユーザーのフォルダアクセス権限の判定、設定画面へのアクセス可否判定などを担当します。

## 主要な公開メソッド

*   **`getAllPermissionsForUser(User $user): Collection`**:
    *   目的・機能: 指定されたユーザーが持つ全てのパーミッション（直接割り当てられたもの、所属組織から継承したものを含む）のユニークなコレクションを取得します。
    *   引数:
        *   `$user`: 対象の `User` モデル。
    *   戻り値: `Illuminate\Support\Collection` - パーミッションモデルのコレクション。
*   **`hasPermission(User $user, $permissionName): bool`**:
    *   目的・機能: 指定されたユーザーが特定のパーミッション（単一または複数）を持っているかを確認します。組織からの継承も考慮されます。
    *   引数:
        *   `$user`: 対象の `User` モデル。
        *   `$permissionName`: 確認するパーミッション名（文字列または文字列の配列）。
    *   戻り値: `bool` - パーミッションを持っていれば `true`。
*   **`getAllRolesForUser(User $user): Collection`**:
    *   目的・機能: 指定されたユーザーが持つ全てのロール（直接割り当てられたもの、所属組織から継承したものを含む）のユニークなコレクションを取得します。
    *   引数:
        *   `$user`: 対象の `User` モデル。
    *   戻り値: `Illuminate\Support\Collection` - ロールモデルのコレクション。
*   **`hasPermissionForOrganization(User $user, string $permission, Organization $organization): bool`**:
    *   目的・機能: ユーザーが特定の組織に対して特定のパーミッションを持っているか（組織階層を考慮して）を確認します。
    *   引数:
        *   `$user`: 対象ユーザー。
        *   `$permission`: パーミッション名。
        *   `$organization`: 対象組織。
    *   戻り値: `bool` - 持っていれば `true`。
*   **`hasRoleForOrganization(User $user, string $role, Organization $organization): bool`**:
    *   目的・機能: ユーザーが特定の組織に対して特定のロールを持っているか（組織階層を考慮して）を確認します。
    *   引数:
        *   `$user`: 対象ユーザー。
        *   `$role`: ロール名。
        *   `$organization`: 対象組織。
    *   戻り値: `bool` - 持っていれば `true`。
*   **`assignRoleToOrganization(User $user, $role, Organization $organization): void`**:
    *   目的・機能: ユーザーに特定の組織に紐づくロールを割り当てます。
    *   引数:
        *   `$user`: 対象ユーザー。
        *   `$role`: ロール名またはロールモデル。
        *   `$organization`: 対象組織。
    *   戻り値: なし。
*   **`hasRoleInOrganization(User $user, string $role, Organization $organization): bool`**:
    *   目的・機能: ユーザーが特定の組織に直接割り当てられた特定のロールを持っているかを確認します。
    *   引数:
        *   `$user`: 対象ユーザー。
        *   `$role`: ロール名。
        *   `$organization`: 対象組織。
    *   戻り値: `bool` - 持っていれば `true`。
*   **`getAllUniquePermissionsForUser(User $user): Collection`**:
    *   目的・機能: ユーザーが持つ全てのユニークなパーミッションを取得します。直接割り当て、組織経由、ロール経由の全てを考慮し、結果はキャッシュされます。
    *   引数:
        *   `$user`: 対象ユーザー。
    *   戻り値: `Illuminate\Support\Collection` - パーミッションモデルのコレクション。
*   **`clearUserPermissionsCache(User $user): void`**:
    *   目的・機能: `getAllUniquePermissionsForUser` で作成されたユーザーのパーミッションキャッシュをクリアします。
    *   引数:
        *   `$user`: 対象ユーザー。
    *   戻り値: なし。
*   **`getAllUniqueRolesForUser(User $user): Collection`**:
    *   目的・機能: ユーザーが持つ全てのユニークなロールを取得します。直接割り当て、組織経由の全てを考慮します。
    *   引数:
        *   `$user`: 対象ユーザー。
    *   戻り値: `Illuminate\Support\Collection` - ロールモデルのコレクション。
*   **`isManageableFolderForUser(User $user, Folder $folder): bool`**:
    *   目的・機能: ユーザーが指定されたフォルダに対して管理権限 (`FolderPermissionType::ADMIN`) を持っているか判定します。(`hasFolderPermission` を利用)
*   **`isWritableFolderForUser(User $user, Folder $folder): bool`**:
    *   目的・機能: ユーザーが指定されたフォルダに対して書き込み権限 (`FolderPermissionType::WRITE`) を持っているか判定します。(`hasFolderPermission` を利用)
*   **`isReadableFolderForUser(User $user, Folder $folder): bool`**:
    *   目的・機能: ユーザーが指定されたフォルダに対して読み取り権限 (`FolderPermissionType::READ`) を持っているか判定します。(`hasFolderPermission` を利用)
*   **`getNotifiableRoles(string $eventType, $eventSubject): Collection`**:
    *   目的・機能: 特定のイベントタイプとイベント対象に基づいて、通知を受け取るべきロールのコレクションを取得します。（現状はPoCとして "All Users" ロールを固定で返しています）
*   **`getUsersByRoleIds(array $roleIds): Collection`**:
    *   目的・機能: 指定されたロールIDの配列に紐づくユーザーのコレクションを取得します。
*   **`getAllRolesForOrganization(Organization $organization): Collection`**:
    *   目的・機能: 指定された組織が持つ全てのロール（上位組織からの継承を含む）のユニークなコレクションを取得します。
*   **`canUserAccessSettings(User $user): bool`**:
    *   目的・機能: ユーザーがシステムの設定画面（Filament Admin Panelなど）にアクセスする権限を持っているか判定します。ユーザーの全権限名と定義済みのキーワード・権限・管理対象を照合して判定します。
*   **`hasFolderPermission(User $user, Folder $folder, FolderPermissionType $requiredPermission): bool`**:
    *   目的・機能: ユーザーが特定のフォルダに対して、指定された最低限必要なフォルダアクセス権限（例: 読み取り、書き込み、管理）を持っているか、フォルダ階層の継承を考慮して確認します。
    *   引数:
        *   `$user`: 対象ユーザー。
        *   `$folder`: 対象フォルダ。
        *   `$requiredPermission`: 必要な最低限の `FolderPermissionType`。
    *   戻り値: `bool` - 権限を持っていれば `true`。
*   **`getUsersWithFolderPermission(Folder $folder, FolderPermissionType $requiredPermission, string $searchQuery = ''): Collection`**:
    *   目的・機能: 指定されたフォルダに対して特定のアクセス権限を持つユーザーのリストを、ユーザー名での検索オプション付きで取得します。
    *   引数:
        *   `$folder`: 対象フォルダ。
        *   `$requiredPermission`: 必要な `FolderPermissionType`。
        *   `$searchQuery`: ユーザー名での検索文字列 (オプション)。
    *   戻り値: `Collection<User>` - 条件に合うユーザーのコレクション。
*   **`getClaimableTasks(User $user): Collection`**:
    *   目的・機能: ユーザーが引き継ぎ可能なワークフロータスク（点検待ちまたは承認待ちの `Ledger`）のコレクションを取得します。自分が申請者や現在の担当者ではなく、かつ対象フォルダに対する適切な権限（点検または承認）を持っているタスクが対象となります。
    *   引数:
        *   `$user`: 対象ユーザー。
    *   戻り値: `Collection<Ledger>` - 引き継ぎ可能な台帳のコレクション。

## 依存する他のクラスや設定

*   **モデル**:
    *   `App\Models\User`
    *   `App\Models\Organization`
    *   `App\Models\Role`
    *   `App\Models\Permission` (間接的にSpatie経由)
    *   `App\Models\Folder`
    *   `App\Models\RoleFolderPermission`
    *   `App\Models\Ledger`
*   **リポジトリ**:
    *   `App\Repositories\WritableFolderRepository`: (旧メソッド群 `isManageableFolderForUser` 等で使用されていたが、`hasFolderPermission` にリファクタリングされた模様。コンストラクタインジェクションは残っている)
*   **Enum**:
    *   `App\Enums\FolderPermissionType`
    *   `App\Enums\WorkflowStatus`
*   **Facades**:
    *   `Illuminate\Support\Facades\Cache`: ユーザー権限などのキャッシュに使用。
*   **Traits**:
    *   `Mary\Traits\Toast`: (UI通知用トレイト。サービスロジックとは直接関係薄い可能性あり)

## その他

*   多くの権限判定メソッドで、ユーザーに直接割り当てられた権限/ロールと、所属する組織階層から継承される権限/ロールの両方を考慮しています。
*   `getAllUniquePermissionsForUser` メソッドではキャッシュを利用してパフォーマンスを向上させています。
*   フォルダアクセス権限の判定ロジック (`hasFolderPermission`) は、ロールとフォルダに直接紐づく `RoleFolderPermission` モデルを参照し、権限の包含関係（管理権限は書き込み・読み取り権限を含むなど）も考慮しています。
*   `getClaimableTasks` メソッドは、ユーザーが担当者でも申請者でもない進行中のワークフロータスクで、かつそのタスクの対象フォルダに対して適切な操作権限（点検または承認）を持つものをリストアップします。
