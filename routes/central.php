<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register central web routes for your application.
| These routes are loaded by the RouteServiceProvider and are not
| subject to tenant middleware. Make something great!
|
*/

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

if (app()->environment('testing')) {
    Route::get('/test/ledger-diff-viewer/{ledger}', function (\App\Models\Ledger $ledger) {
        return view('testing.ledger-diff-viewer-test', ['ledger' => $ledger]);
    })->name('testing.ledger-diff-viewer');
}

Route::get('/phpinfo', function () {
    phpinfo();
});