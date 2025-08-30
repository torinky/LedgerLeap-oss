<?php

use App\Http\Controllers\LedgerLookupController;
use App\Http\Controllers\Ledger\IndexController as LedgerIndexController; // 追加
use App\Http\Controllers\Ledger\ShowController as LedgerShowController; // 追加
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath; // 変更
use Stancl\Tenancy\Middleware\ScopeTenancyByTenantId; // 追加

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here is where you can register tenant routes for your application. These
| routes are loaded by the TenantRouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::group([
    'prefix' => '/{tenant}', // テナントIDをパスから取得
    'middleware' => [
        'web',
        InitializeTenancyByPath::class,
    ],
], function () {
    // Auto-link lookup
    Route::get('/l/{query}', [LedgerLookupController::class, 'handle'])->name('ledger.lookup');

    // ledger
    Route::get('/ledger/{ledgerId}', LedgerShowController::class)->name('ledger.show')
        ->where('ledgerId', '[0-9]+');
    Route::get('/ledger', LedgerIndexController::class)->name('ledger.index');
});
