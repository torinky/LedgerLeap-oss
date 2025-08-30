<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginLandingPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Tenant;

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
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

                // ユーザーが所属するテナントを初期化
        // ユーザーが複数のテナントに所属する可能性があるため、ここでは最初のテナントを取得
        $tenant = $user->tenants()->first();
        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        // デフォルトのリダイレクト先ルート名を設定
        $landingPageRouteName = 'my-portal'; // マイポータルのルート名
        // ユーザーの設定を確認し、必要ならリダイレクト先を変更
        if ($user->login_landing_page === LoginLandingPage::Ledgers) {
            $landingPageRouteName = 'ledger.index'; // 台帳/フォルダ一覧画面のルート名
        }
//        return redirect()->route($landingPageRouteName); // intended() を使わない形

        // intended() はログイン前にアクセスしようとしたページがあればそちらを優先
        // なければ、決定したランディングページのルートへリダイレクト
        return redirect()->intended(route($landingPageRouteName, ['tenant' => tenant()->id]));

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
