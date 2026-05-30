<?php

use App\Http\Controllers\Api\V1\BootstrapManifestController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\LedgerDefineController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Ledger\ExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

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
        [ExportController::class, 'downloadExcelCSV']
    )->name('ledger.downloadExcelCSV');

    // Ledger Search API (GET for simple queries, POST for complex/Japanese queries)
    Route::get('/v1/search', [SearchController::class, 'search'])->name('api.v1.search');
    Route::post('/v1/search', [SearchController::class, 'search'])->name('api.v1.search.post');

    // Ledger Defines API
    Route::get('/v1/ledger-defines', [LedgerDefineController::class, 'index'])->name('api.v1.ledger-defines.index');

    // AI Bootstrap Discovery API
    Route::get('/v1/ai/bootstrap-manifest', [BootstrapManifestController::class, 'show'])
        ->withoutMiddleware([InitializeTenancyByDomain::class])
        ->name('api.v1.ai.bootstrap-manifest.show');
    Route::post('/v1/ai/bootstrap-manifest/resolve', [BootstrapManifestController::class, 'resolve'])
        ->withoutMiddleware([InitializeTenancyByDomain::class])
        ->name('api.v1.ai.bootstrap-manifest.resolve');

    // Ledger Create API
    Route::post('/v1/ledgers', [LedgerController::class, 'store'])
        ->name('api.v1.ledgers.store');

    // Ledger Index API
    Route::get('/v1/ledgers', [LedgerController::class, 'index'])
        ->name('api.v1.ledgers.index');

    // Ledger Detail / Update API
    Route::get('/v1/ledgers/{ledger}', [LedgerController::class, 'show'])
        ->name('api.v1.ledgers.show');
    Route::patch('/v1/ledgers/{ledger}', [LedgerController::class, 'update'])
        ->name('api.v1.ledgers.update');
});

Route::get('/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        abort(404, 'API documentation file not found.');
    }

    return response()->file($path, ['Content-Type' => 'application/json']);
})->name('api.openapi');
