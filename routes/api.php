<?php

use App\Http\Controllers\Api\V1\LedgerDefineController;
use App\Http\Controllers\Api\V1\SearchController;
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

    // Ledger Search API
    Route::get('/v1/search', [SearchController::class, 'search'])->name('api.v1.search');

    // Ledger Defines API
    Route::get('/v1/ledger-defines', [LedgerDefineController::class, 'index'])->name('api.v1.ledger-defines.index');

    // Ledger Create API
    Route::post('/v1/ledgers', [\App\Http\Controllers\Api\V1\LedgerController::class, 'store'])->name('api.v1.ledgers.store');

    // Ledger Index API
    Route::get('/v1/ledgers', [\App\Http\Controllers\Api\V1\LedgerController::class, 'index'])->name('api.v1.ledgers.index');
});

Route::get('/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        abort(404, 'API documentation file not found.');
    }

    return response()->file($path, ['Content-Type' => 'application/json']);
})->name('api.openapi');
