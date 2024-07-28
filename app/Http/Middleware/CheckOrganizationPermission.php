<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;

class CheckOrganizationPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response('Unauthorized.', 401);
        }

        // 現在のリクエストに関連する組織を取得
        $organization = $this->getCurrentOrganization($request);

        if (!$organization) {
            return response('Organization not found.', 404);
        }

        if ($this->hasHierarchicalPermission($user, $permission, $organization)) {
            return $next($request);
        }

        return response('Forbidden.', 403);
    }

    private function getCurrentOrganization(Request $request)
    {
        // リクエストから組織を特定する方法をここに実装
        // 例: ルートパラメータ、クエリパラメータ、またはカスタムヘッダーから組織IDを取得
        $organizationId = $request->route('organization') ?? $request->query('organization_id');

        return $organizationId ? Organization::find($organizationId) : null;
    }

    private function hasHierarchicalPermission($user, $permission, $organization)
    {
        // ユーザーが直接権限を持っているかチェック
        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        // 組織の階層を上へ遡りながらチェック
        while ($organization) {
            if ($user->hasPermissionForOrganization($permission, $organization)) {
                return true;
            }
            $organization = $organization->parent;
        }

        return false;
    }
}
