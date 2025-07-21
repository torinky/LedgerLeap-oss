<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ledger/{ledgerDefineId}/download-excel-csv', [App\Http\Controllers\Ledger\ExportController::class, 'downloadExcelCSV'])
        ->name('ledger.downloadExcelCSV');

    // Font Awesome アイコン配信ルート
    Route::get('/fontawesome/{style}/{icon}.svg', [App\Http\Controllers\FontAwesomeIconController::class, 'serveIcon'])
        ->name('api.fontawesome.icon');
});
