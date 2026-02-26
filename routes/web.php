<?php

use App\Http\Controllers\GlobalMyPortalController;
use App\Http\Controllers\LedgerLookupController;
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

    // --- アイコン取得用ルート (FilePondプレビュー等で使用) ---
    // MIMEタイプからアイコンを取得
    Route::get('/icons/mime', [\App\Http\Controllers\FontAwesomeIconController::class, 'serveIconByMime'])
        ->name('api.fontawesome.icon.by_mime');

    // スタイルとアイコン名で直接アイコンを取得 (サムネイルのフォールバック用)
    Route::get('/icons/{style}/{icon}', [\App\Http\Controllers\FontAwesomeIconController::class, 'serveIcon'])
        ->whereIn('style', ['solid', 'regular', 'brands'])
        ->name('api.fontawesome.icon');
});
