<?php

namespace App\Http\Controllers;

use App\Services\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalMyPortalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request, TenantAccessService $tenantAccessService): RedirectResponse|View
    {
        $user = $request->user();
        $tenants = $tenantAccessService->getAccessibleTenants($user);

        // 所属テナントが1つの場合は、そのテナントのマイポータルへ自動リダイレクト
        if ($tenants->count() === 1) {
            $tenant = $tenants->first();

            return redirect()->route('my-portal', ['tenant' => $tenant->id]);
        }

        // 所属テナントが0個または2個以上の場合は、テナント選択ビューを表示
        return view('my-portal', ['tenants' => $tenants]);
    }
}
