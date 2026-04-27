<?php

use App\Http\Controllers\FontAwesomeIconController;
use App\Http\Controllers\GlobalMyPortalController;
use App\Http\Controllers\LedgerLookupController;
use App\Http\Controllers\NotificationController;
use App\Livewire\Notifications\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/login'); // サーバールートにアクセスされたときにloginにリダイレクト

Route::middleware('auth')->group(function () {
    Route::get('/ledgers/lookup/{query?}', [LedgerLookupController::class, 'searchAllTenants'])->name('ledger.lookup');
    Route::get('/l/{query}', [LedgerLookupController::class, 'searchAllTenants'])->name('ledger.shortcut_lookup');

    Route::get('/my-portal', [GlobalMyPortalController::class, 'index'])->name('global.my-portal');

    // --- 通知関連ルート ---
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/settings', Settings::class)->name('notifications.settings');
    Route::redirect('/activity-log', '/notifications?tab=activity', 301)->name('activity-log');

    // --- ワークフロー関連ルート ---
    Route::redirect('/workflow/pending', '/notifications?tab=tasks', 301)->name('workflow.pending');

    // --- アイコン取得用ルート (FilePondプレビュー等で使用) ---
    // MIMEタイプからアイコンを取得
    Route::get('/icons/mime', [FontAwesomeIconController::class, 'serveIconByMime'])
        ->name('api.fontawesome.icon.by_mime');

    // スタイルとアイコン名で直接アイコンを取得 (サムネイルのフォールバック用)
    Route::get('/icons/{style}/{icon}', [FontAwesomeIconController::class, 'serveIcon'])
        ->whereIn('style', ['solid', 'regular', 'brands'])
        ->name('api.fontawesome.icon');
});
