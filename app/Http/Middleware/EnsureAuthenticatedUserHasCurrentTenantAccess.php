<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedUserHasCurrentTenantAccess
{
    public function __construct(
        private readonly TenantAccessService $tenantAccessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Authentication required.');
        }

        $currentTenant = tenant();

        if ($currentTenant === null) {
            abort(404, 'Tenant context could not be resolved.');
        }

        $hasTenantAccess = $this->tenantAccessService
            ->getAccessibleTenants($user)
            ->contains(fn ($tenant): bool => $tenant->getTenantKey() === $currentTenant->getTenantKey());

        if (! $hasTenantAccess) {
            abort(403, 'You do not have access to this tenant.');
        }

        return $next($request);
    }
}
