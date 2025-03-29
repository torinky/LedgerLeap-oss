<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    /*    public function edit(Request $request): View
        {
            return view('profile.edit', [
                'user' => $request->user(),
            ]);
        }*/
    // App\Http\Controllers\ProfileController の edit メソッド内 (想定)
    public function edit(Request $request): View // 戻り値の型を View に
    {
        $user = $request->user();
        // ユーザーが所属する組織を名前順で取得
        $organizations = $user->organizations()->orderBy('name')->get();
        // 主所属を取得
        $primaryOrganization = $user->primaryOrganization(); // NULLの可能性あり

        // Breeze が元々渡しているデータに加えて、組織情報を渡す
        return view('profile.edit', [
            'user' => $user,
            'organizations' => $organizations,
            'primaryOrganizationId' => $primaryOrganization?->id, // 主所属のID (比較用)
            // 'mustVerifyEmail' や 'status' など、Breezeが必要とする他のデータも渡す
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
