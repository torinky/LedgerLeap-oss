<?php

use App\Http\Controllers\FontAwesomeIconController;
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
    Route::get(
        '/ledger/{ledgerDefineId}/download-excel-csv',
        [App\Http\Controllers\Ledger\ExportController::class, 'downloadExcelCSV']
    )->name('ledger.downloadExcelCSV');


    // MIMEタイプからアイコンを取得
    Route::get('/icons/mime', [FontAwesomeIconController::class, 'serveIconByMime'])
        ->name('api.fontawesome.icon.by_mime');

// スタイルとアイコン名で直接アイコンを取得 (サムネイルのフォールバック用)
    Route::get('/icons/{style}/{icon}', [FontAwesomeIconController::class, 'serveIcon'])
        ->whereIn('style', ['solid', 'regular', 'brands'])
        ->name('api.fontawesome.icon');
});
