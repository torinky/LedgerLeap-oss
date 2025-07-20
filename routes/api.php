<?php

use App\Http\Controllers\FilePondController;
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

    // FilePond 専用のファイルロードルート (Sanctum認証)
    Route::get('/filepond/load/{attachedFile}', [FilePondController::class, 'load'])
        ->name('api.filepond.load') // ルート名を変更
        ->where('attachedFile', '[0-9]+');
});
