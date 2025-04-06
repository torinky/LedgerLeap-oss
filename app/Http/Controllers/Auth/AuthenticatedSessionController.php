<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginLandingPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user(); // ログインしたユーザーを取得

        // デフォルトのリダイレクト先ルート名を設定
        $landingPageRouteName = 'my-portal'; // マイポータルのルート名
        // ユーザーの設定を確認し、必要ならリダイレクト先を変更
        if ($user->login_landing_page === LoginLandingPage::Ledgers) {
            $landingPageRouteName = 'ledger.index'; // 台帳/フォルダ一覧画面のルート名
        }
//        return redirect()->route($landingPageRouteName); // intended() を使わない形

        // intended() はログイン前にアクセスしようとしたページがあればそちらを優先
        // なければ、決定したランディングページのルートへリダイレクト
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
