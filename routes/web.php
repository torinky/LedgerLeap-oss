<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GlobalMyPortalController;
use App\Http\Controllers\LedgerLookupController;

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

    Route::get('/my-portal', [GlobalMyPortalController::class, 'index'])->name('global.my-portal');
});

