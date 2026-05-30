<?php

namespace App\Mcp\Traits;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * MCPツール用共通認証トレイト
 *
 * 全てのMCPツールで統一された認証と権限チェックを提供します。
 */
trait AuthenticatedMcpTool
{
    /**
     * 認証済みユーザーを取得
     *
     * @throws \Exception
     */
    protected function authenticateUser(): User
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser instanceof User) {
            $this->ensureAuthenticatedUserHasMcpAccess($authenticatedUser);
            Auth::setUser($authenticatedUser);

            return $authenticatedUser;
        }

        $token = getenv('MCP_AUTH_TOKEN');
        if (! $token) {
            throw new \Exception(
                'Authentication failed: MCP_AUTH_TOKEN environment variable is not set. '
                .'Please set the MCP_AUTH_TOKEN in your .env file with a valid Sanctum token.'
            );
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (! $accessToken) {
            throw new \Exception(
                'Authentication failed: The provided token is invalid or has been revoked. '
                .'Please generate a new token using: php artisan demo:generate-mcp-token'
            );
        }

        if (! $accessToken->tokenable) {
            throw new \Exception(
                'Authentication failed: The token is not associated with any user. '
                .'Please generate a new token using: php artisan demo:generate-mcp-token'
            );
        }

        $user = $accessToken->tokenable;
        if (! $user instanceof User) {
            throw new \Exception('Authentication failed: The token is associated with an invalid user type.');
        }

        // トークンの能力（abilities）をチェック
        if (! $accessToken->can('mcp:*')) {
            throw new \Exception(
                'Authentication failed: The token does not have MCP access permissions. '
                .'Please generate a token with mcp:* ability.'
            );
        }

        // 現在のユーザーを設定
        Auth::setUser($user);

        return $user;
    }

    /**
     * 認証済みユーザーに関連づくSanctumトークン能力を検証
     *
     * HTTP transport では auth:sanctum によりユーザーが解決されるため、
     * currentAccessToken() がある場合は従来どおり mcp:* 能力を要求する。
     * セッション認証やテストの actingAs() など、現在トークンが無い経路は後方互換のため許可する。
     *
     * @throws \Exception
     */
    protected function ensureAuthenticatedUserHasMcpAccess(User $user): void
    {
        if (! method_exists($user, 'currentAccessToken')) {
            return;
        }

        $currentAccessToken = $user->currentAccessToken();

        if ($currentAccessToken === null) {
            return;
        }

        if (! $currentAccessToken->can('mcp:*')) {
            throw new \Exception(
                'Authentication failed: The authenticated token does not have MCP access permissions. '
                .'Please generate a token with mcp:* ability.'
            );
        }
    }

    /**
     * フォルダに対する権限をチェック
     *
     * @param  string  $permission  (READ, WRITE, ADMIN)
     */
    protected function checkFolderPermission(User $user, Folder $folder, string $permission): bool
    {
        $repository = app(WritableFolderRepository::class);

        // 文字列権限をEnumに変換
        $permissionEnum = match (strtoupper($permission)) {
            'READ' => FolderPermissionType::READ,
            'WRITE' => FolderPermissionType::WRITE,
            'ADMIN' => FolderPermissionType::ADMIN,
            default => throw new \InvalidArgumentException("Invalid permission: {$permission}")
        };

        $accessibleFolderIds = $repository->getAccessibleFolderIds($user, $permissionEnum);

        return in_array($folder->id, $accessibleFolderIds);
    }

    /**
     * 認証エラーレスポンスを生成
     */
    protected function authenticationError(string $message = 'Authentication failed'): Response
    {
        return Response::error($message);
    }

    /**
     * 権限エラーレスポンスを生成
     */
    protected function permissionError(string $message = 'Permission denied'): Response
    {
        return Response::error($message);
    }

    /**
     * 認証とエラーハンドリングを統合したヘルパー
     *
     * @return User|Response User オブジェクトまたはエラーレスポンス
     */
    protected function authenticateOrError()
    {
        try {
            return $this->authenticateUser();
        } catch (\Exception $e) {
            return $this->authenticationError($e->getMessage());
        }
    }

    /**
     * フォルダ権限チェックと権限エラーハンドリングのヘルパー
     *
     * @return bool|Response true または権限エラーレスポンス
     */
    protected function checkFolderPermissionOrError(User $user, Folder $folder, string $permission)
    {
        if (! $this->checkFolderPermission($user, $folder, $permission)) {
            return $this->permissionError("Insufficient permission ({$permission}) for folder: {$folder->name}");
        }

        return true;
    }
}
