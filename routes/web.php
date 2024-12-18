<?php

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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SynonymController;
use App\Livewire\LedgerDefine\Create as LedgerDefineCreateComponent;
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
/*Route::get('/', function () {
    return view('welcome');
});*/

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {

    //ledgerDefine

    Route::get('/ledgerDefine', LedgerDefineIndexController::class)->name('ledgerDefine.index');

    Route::get('/ledgerDefine/folder/{folderId}', LedgerDefineIndexController::class)
        ->name('ledgerDefinesByFolderId')
        ->where('folderId', '[0-9]+');

    //    Route::get('/ledgerDefine/create', [LedgerDefineCreateController::class, 'create'])
    Route::get('/ledgerDefine/create', LedgerDefineCreateComponent::class)
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
    Route::get('/ledger', LedgerIndexController::class)->name('ledger.index');
    Route::get('/ledger/define/{ledgerDefineId}', LedgerIndexController::class)->name('ledgerByDefineId')
        ->where('ledgerDefineId', '[0-9]+');

    //    Route::post('/ledger', SearchController::class)
    //        ->name('ledger.search');

    Route::get('/ledger/folder/{folderId}', LedgerIndexController::class)->name('ledgersByFolderId')
        ->where('folderId', '[0-9]+');

    Route::get('/ledger/{ledgerId}', LedgerShowController::class)->name('ledger.show')
        ->where('ledgerId', '[0-9]+');

    Route::get('/ledger/create/{ledgerDefineId}', [LedgerCreateController::class, 'create'])->name('ledger.create')
        ->where('ledgerDefineId', '[0-9]+');

    Route::post('/ledger/create/{ledgerDefineId}', [LedgerCreateController::class, 'store'])->name('ledger.store')
        ->where('ledgerDefineId', '[0-9]+');

    Route::get('/ledger/edit/{ledgerId}', [UpdateController::class, 'edit'])->name('ledger.edit')
        ->where('ledgerId', '[0-9]+');

    Route::get('/ledger/import/{ledgerDefineId}', [ImportController::class, 'showUploadForm'])->name('ledger.import.show')
        ->where('ledgerDefineId', '[0-9]+');
    Route::post('/ledger/import', [ImportController::class, 'importExcelCSV'])->name('ledger.import');

    Route::put('/ledger/{ledgerId}', [UpdateController::class, 'update'])->name('ledger.update')
        ->where('ledgerId', '[0-9]+');

    Route::delete('/ledger/{ledgerId}', [UpdateController::class, 'delete'])
        ->name('ledger.delete')
        ->where('ledgerId', '[0-9]+');

    //    ledgerDiff
    Route::get('/ledgerDiff/{ledgerId}', LedgerDiffShowController::class)->name('ledgerDiff.show')
        ->where('ledgerId', '[0-9]+');

    //folder
    Route::get('/folder/edit/{folderId}', [FolderUpdateController::class, 'edit'])
        ->name('folder.edit')
        ->where('folderId', '[0-9]+');

    Route::put('/folder/{folderId}', [FolderUpdateController::class, 'update'])
        ->name('folder.update')
        ->where('folderId', '[0-9]+');

    Route::delete('/folder/{folderId}', [FolderUpdateController::class, 'delete'])
        ->name('folder.delete')
        ->where('folderId', '[0-9]+');

    Route::get('/folder/create/folder/{folderId}', [FolderCreateController::class, 'create'])
        ->name('folder.createWithFolderId')
        ->where('folderId', '[0-9]+');

    Route::post('/folder/create', [FolderCreateController::class, 'store'])->name('folder.store');

    Route::get('/synonyms/{word}', [SynonymController::class, 'search']);

});

Route::get('/phpinfo', function () {
    phpinfo();
});

// 使用例 (ルートファイルなど)
//Route::get('/organizations/{organization}/users', [UserController::class, 'index'])
//    ->middleware(['auth', 'check.organization.permission:view-users']);
