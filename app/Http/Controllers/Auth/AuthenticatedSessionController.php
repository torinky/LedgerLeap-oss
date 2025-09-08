<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginLandingPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, TenantAccessService $tenantAccessService): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // 認証済みユーザーを取得
        $user = $request->user();
        if (!$user) {
            // 異常系フォールバック（念のため）
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login');
        }

        // ユーザーがアクセス可能な最初のテナントを取得
        $tenant = $tenantAccessService->getAccessibleTenants($user)->first();
        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        // デフォルトのリダイレクト先ルート名を設定
        $landingPageRouteName = 'my-portal'; // マイポータルのルート名
        // ユーザーの設定を確認し、必要ならリダイレクト先を変更
        if ($user->login_landing_page === LoginLandingPage::Ledgers) {
            $landingPageRouteName = 'ledger.index'; // 台帳/フォルダ一覧画面のルート名
        }

        // intended() はログイン前にアクセスしようとしたページがあればそちらを優先
        // なければ、決定したランディングページのルートへリダイレクト
        // テナントが無い場合はテナントパラメータを渡さない
        if ($tenant) {
            return redirect()->intended(route($landingPageRouteName, ['tenant' => $tenant->id]));
        }
        return redirect()->intended(route($landingPageRouteName));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}