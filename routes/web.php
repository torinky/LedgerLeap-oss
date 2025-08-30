<?php

use App\Http\Controllers\AttachedFileDownloadController;
use App\Http\Controllers\Folder\CreateController as FolderCreateController;
use App\Http\Controllers\Folder\UpdateController as FolderUpdateController;
use App\Http\Controllers\Ledger\CreateController as LedgerCreateController;
use App\Http\Controllers\Ledger\ImportController;
use App\Http\Controllers\Ledger\IndexController as LedgerIndexController;
use App\Http\Controllers\Ledger\ShowController as LedgerShowController;
use App\Http\Controllers\Ledger\UpdateController;
use App\Http\Controllers\LedgerDefine\CreateController as LedgerDefineCreateController;
use App\Http\Controllers\LedgerDefine\IndexController as LedgerDefineIndexController;
use App\Http\Controllers\LedgerDefine\UpdateController as LedgerDefineUpdateController;
use App\Http\Controllers\LedgerDiff\ShowController as LedgerDiffShowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SynonymController;
use App\Http\Controllers\LedgerLookupController;
use App\Livewire\Folder\FolderForm;
use App\Livewire\MyPortal;
use App\Livewire\Notifications\Settings;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
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

Route::redirect('/', '/ledger'); // サーバールートにアクセスされたときにledgerにリダイレクト

Route::middleware('auth')->group(function () {

    // Auto-link lookup
    

    //ledgerDefine

    Route::get('/ledgerDefine', LedgerDefineIndexController::class)->name('ledgerDefine.index');

    Route::get('/ledgerDefine/folder/{folderId}', LedgerDefineIndexController::class)
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

    Route::put('/ledgerDefine/{ledgerDefineId}', [LedgerDefineUpdateController::class, 'update'])
        ->name('ledgerDefine.update')
        ->where('ledgerDefineId', '[0-9]+');

    Route::delete('/ledgerDefine/{ledgerDefineId}', [LedgerDefineUpdateController::class, 'delete'])
        ->name('ledgerDefine.delete')
        ->where('ledgerDefineId', '[0-9]+');

    //    ledger
    Route::get('/ledger/define/{ledgerDefineId}', LedgerIndexController::class)->name('ledgerByDefineId')
        ->where('ledgerDefineId', '[0-9]+');

    Route::get('/ledger/folder/{folderId}', LedgerIndexController::class)->name('ledgersByFolderId')
        ->where('folderId', '[0-9]+');

    

    Route::get('/ledger/create/{ledgerDefineId}', [LedgerCreateController::class, 'create'])->name('ledger.create')
        ->where('ledgerDefineId', '[0-g]+');

    Route::post('/ledger/create/{ledgerDefineId}', [LedgerCreateController::class, 'store'])->name('ledger.store')
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

    //folder
    Route::get('/folders/create/{parentId?}', FolderForm::class)->name('folder.create');
    Route::get('/folders/{folder}/edit', FolderForm::class)->name('folder.edit');

    Route::get('/synonyms/{word}', [SynonymController::class, 'search']);

    // --- 通知関連ルート ---
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/settings', Settings::class)->name('notifications.settings');

    Route::redirect('/activity-log', '/notifications?tab=activity', 301)->name('activity-log');

    // --- ワークフロー関連ルート ---
    Route::redirect('/workflow/pending', '/notifications?tab=tasks', 301)->name('workflow.pending');

    Route::get('/my-portal', MyPortal::class)->name('my-portal');

    Route::get('/test-activity', function () {
        return view('test-activity');
    })->middleware(['auth', 'verified'])->name('test-activity');

    // Attachment Download Route
    Route::get('/files/{attachedFile}/download', [AttachedFileDownloadController::class, 'download'])
        ->name('file.download')
        ->where('attachedFile', '[0-9]+');
});