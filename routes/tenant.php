<?php

use App\Http\Controllers\AttachedFileDownloadController;
use App\Http\Controllers\Ledger\CreateController;
use App\Http\Controllers\Ledger\DuplicateController;
use App\Http\Controllers\Ledger\ImportController;
use App\Http\Controllers\Ledger\LedgerExportDownloadController;
use App\Http\Controllers\Ledger\ShowController as LedgerShowController;
use App\Http\Controllers\Ledger\UpdateController;
use App\Http\Controllers\LedgerDefine\CreateController as LedgerDefineCreateController; // 追加
use App\Http\Controllers\LedgerDefine\IndexController as LedgerDefineIndexController;
use App\Http\Controllers\LedgerDefine\UpdateController as LedgerDefineUpdateController;
use App\Http\Controllers\LedgerDefineBackgroundImageController;
use App\Http\Controllers\LedgerDiff\ShowController as LedgerDiffShowController;
use App\Http\Controllers\LedgerLookupController;
use App\Http\Controllers\SynonymController;
use App\Livewire\Folder\FolderForm;
use App\Livewire\Ledger\IndexManager;
use App\Livewire\MyPortal;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

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
        'auth', // ここを追加
    ],
], function () {
    // Auto-link lookup
    Route::get('/l/{query}', [LedgerLookupController::class, 'handle'])->name('ledger.lookup');

    // ledger
    Route::get('/ledger/{ledgerId}', LedgerShowController::class)->name('ledger.show')
        ->where('ledgerId', '[0-9]+');
    Route::get('/ledger', IndexManager::class)->name('ledger.index'); // LedgerIndexController から変更

    // ledger duplicate
    Route::get('/ledger/duplicate/{ledgerId}', [DuplicateController::class, 'duplicate'])
        ->name('ledger.duplicate')
        ->where('ledgerId', '[0-9]+');

    // ledgerDefine
    Route::get('/ledgerDefine', [LedgerDefineIndexController::class, 'index'])->name('ledgerDefine.index');
    Route::get('/ledgerDefine/folder/{folderId}', [LedgerDefineIndexController::class, 'index'])
        ->name('ledgerDefinesByFolderId')
        ->where('folderId', '[0-9]+');
    Route::get('/ledgerDefine/create', [LedgerDefineCreateController::class, 'create'])
        ->name('ledgerDefine.create');
    Route::get('/ledgerDefine/create/folder/{folderId}', [LedgerDefineCreateController::class, 'create'])
        ->name('ledgerDefine.createWithFolderId')
        ->where('folderId', '[0-9]+');
    Route::post('/ledgerDefine/create', [LedgerDefineCreateController::class, 'store'])->name('ledgerDefine.store');
    Route::get('/ledgerDefine/edit/{ledgerDefineId}', [LedgerDefineUpdateController::class, 'edit'])
        ->name('ledgerDefine.edit')
        ->where('ledgerDefineId', '[0-9]+');
    Route::get('/ledgerDefine/{ledgerDefineId}/background-image/{columnId}', [LedgerDefineBackgroundImageController::class, 'download'])
        ->name('ledgerDefine.background-image')
        ->where('ledgerDefineId', '[0-9]+')
        ->where('columnId', '[0-9]+');
    Route::put('/ledgerDefine/{ledgerDefineId}', [LedgerDefineUpdateController::class, 'update'])
        ->name('ledgerDefine.update')
        ->where('ledgerDefineId', '[0-9]+');
    Route::delete('/ledgerDefine/{ledgerDefineId}', [LedgerDefineUpdateController::class, 'delete'])
        ->name('ledgerDefine.delete')
        ->where('ledgerDefineId', '[0-9]+');

    //    ledger (残りのルート)
    Route::get('/ledger/define/{defineId}', IndexManager::class)->name('ledgersByDefineId') // LedgerIndexController から変更
        ->where('defineId', '[0-9]+');
    Route::get('/ledger/folder/{folderId}', IndexManager::class)->name('ledgersByFolderId') // LedgerIndexController から変更
        ->where('folderId', '[0-9]+');
    Route::get('/ledger/create/{ledgerDefineId}', [CreateController::class, 'create'])->name('ledger.create')
        ->where('ledgerDefineId', '[0-9]+');

    Route::get('/ledger/edit/{ledgerId}', [UpdateController::class, 'edit'])->name('ledger.edit')
        ->where('ledgerId', '[0-9]+');
    Route::get('/ledger/import/{ledgerDefineId}', [ImportController::class, 'showUploadForm'])->name('ledger.import.show')
        ->where('ledgerDefineId', '[0-9]+');
    Route::post('/ledger/import', [ImportController::class, 'importExcelCSV'])->name('ledger.import');
    Route::put('/ledger/{ledgerId}', [UpdateController::class, 'update'])->name('ledger.update')
        ->where('ledgerId', '[0-9]+');
    Route::delete('/ledgers/{ledger}', [UpdateController::class, 'destroy'])->name('ledger.destroy');

    //    ledgerDiff
    Route::get('/ledgerDiff/{ledgerId}/{diffId?}', LedgerDiffShowController::class) // ShowController を使う場合
        ->name('ledgerDiff.show')
        ->where('ledgerId', '[0-9]+')
        ->where('diffId', '[0-9]+'); // diffId も数字のみ

    // folder
    Route::get('/folders/create/{parentId?}', FolderForm::class)->name('folder.create');
    Route::get('/folders/{folder}/edit', FolderForm::class)->name('folder.edit');

    Route::get('/synonyms/{word}', [SynonymController::class, 'search']);

    Route::get('/my-portal', MyPortal::class)->name('my-portal');

    Route::get('/test-activity', function () {
        return view('test-activity');
    })->middleware(['auth', 'verified'])->name('test-activity');

    // Attachment Download Route
    Route::get('/files/{attachedFile}/download', [AttachedFileDownloadController::class, 'download'])
        ->name('file.download')
        ->where('attachedFile', '[0-9]+');

    // VLM Result Download Route
    Route::get('/files/{attachedFile}/download-vlm', [AttachedFileDownloadController::class, 'downloadVlm'])
        ->name('file.download-vlm')
        ->where('attachedFile', '[0-9]+');

    // OCR PDF Download Route
    Route::get('/files/{attachedFile}/download-ocr-pdf', [AttachedFileDownloadController::class, 'downloadOcrPdf'])
        ->name('file.download-ocr-pdf')
        ->where('attachedFile', '[0-9]+');

    // Ledger CSV Export Download Route
    Route::get('/ledger/export/{ledgerDefineId}/download/{filename}', LedgerExportDownloadController::class)
        ->name('ledger.export.download')
        ->where('ledgerDefineId', '[0-9]+')
        ->where('filename', '.*');

});
