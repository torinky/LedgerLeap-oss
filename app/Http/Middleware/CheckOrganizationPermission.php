<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response('Unauthorized.', 401);
        }

        $userPermissions = $user->getAllPermissions();
        $organizationPermissions = collect();

        foreach ($user->organizations as $organization) {
            $organizationPermissions = $organizationPermissions->merge($organization->getAllPermissions());
        }

        $allPermissions = $userPermissions->merge($organizationPermissions)->unique('id');

        if ($allPermissions->contains('name', $permission)) {
            return $next($request);
        }

        return response('Forbidden.', 403);

//        return $next($request);
    }
}
