<?php

use App\Http\Controllers\AttachedFileDownloadController;
use App\Http\Controllers\FontAwesomeIconController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SynonymController;
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

require __DIR__ . '/auth.php';

Route::middleware('auth')->group(function () {

    //ledgerDefine

    Route::get('/ledgerDefine', LedgerDefineIndexController::class)->name('ledgerDefine.index');

    Route::get('/ledgerDefine/folder/{folderId}', LedgerDefineIndexController::class)
        ->name('ledgerDefinesByFolderId')
        ->where('folderId', '[0-9]+');

    Route::get('/ledgerDefine/create', [LedgerDefineCreateController::class, 'create'])
//    Route::get('/ledgerDefine/create', LedgerDefineCreateComponent::class)
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

    Route::delete('/ledgers/{ledger}', [UpdateController::class, 'destroy'])->name('ledger.destroy');

    /*    Route::delete('/ledger/{ledgerId}', [UpdateController::class, 'delete'])
            ->name('ledger.delete')
            ->where('ledgerId', '[0-9]+');*/

    //    ledgerDiff
    Route::get('/ledgerDiff/{ledgerId}/{diffId?}', LedgerDiffShowController::class) // ShowController を使う場合
    ->name('ledgerDiff.show')
        ->where('ledgerId', '[0-9]+')
        ->where('diffId', '[0-9]+'); // diffId も数字のみ

    // もし Livewire を直接ルートにバインドする場合
    // Route::get('/ledgerDiff/{ledgerId}/{diffId?}', App\Livewire\Ledger\ShowDiff::class)
    //     ->name('ledgerDiff.show')
    //     ->where('ledgerId', '[0-9]+')
    //     ->where('diffId', '[0-9]+');

    //folder
    // Folder Routes (Livewire に移行)
    Route::get('/folders/create/{parentId?}', FolderForm::class)->name('folder.create');
    Route::get('/folders/{folder}/edit', FolderForm::class)->name('folder.edit');


/*    Route::get('/folder/edit/{folderId}', [FolderUpdateController::class, 'edit'])
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

    Route::post('/folder/create', [FolderCreateController::class, 'store'])->name('folder.store');*/

    Route::get('/synonyms/{word}', [SynonymController::class, 'search']);

    // --- 通知関連ルート ---
    // 修正: /notifications はコントローラーを向く
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    /*    Route::get('/notifications', UserNotificationList::class)
            ->middleware(['auth'])
            ->name('notifications.index');*/
    // 設定画面はそのまま
    Route::get('/notifications/settings', Settings::class)->name('notifications.settings');

    // 削除 or リダイレクト: /activity-log は /notifications?tab=activity へ
    // Route::get('/activity-log', UserActivityLog::class)->name('activity-log'); // <<<--- 削除またはリダイレクトに変更
    Route::redirect('/activity-log', '/notifications?tab=activity', 301)->name('activity-log'); // 例: リダイレクト

    // --- ワークフロー関連ルート ---
    // 削除: 承認待ちリスト単独ページは廃止
    Route::redirect('/workflow/pending', '/notifications?tab=tasks', 301)->name('workflow.pending'); // 例: リダイレクト
    // Route::get('/workflow/pending', PendingList::class)->name('workflow.pending'); // <<<--- 削除


    Route::get('/my-portal', MyPortal::class)->name('my-portal');

    Route::get('/test-activity', function () {
        return view('test-activity');
    })->middleware(['auth', 'verified'])->name('test-activity');


/*    Route::get('/test-policy-check', function () {
        if (!Auth::check()) {
            return "ユーザーが認証されていません。ログインしてください。";
        }

        $user = Auth::user();
        dump("テストルート: ユーザー: " . $user->name);

        // viewAny が呼ばれることを期待
        $canViewAny = $user->can('viewAny', CustomActivity::class);
        dump("user->can('viewAny', CustomActivity::class): ", $canViewAny);

        // can('view', Model::class) は viewAny を探す
        $canView = $user->can('view', CustomActivity::class);
        dump("user->can('view', CustomActivity::class): ", $canView);


        // Gateファサードを直接使用
        $gateAllowsViewAny = Gate::forUser($user)->allows('viewAny', CustomActivity::class);
        dump("Gate::allows('viewAny', CustomActivity::class): ", $gateAllowsViewAny);

        $gateAllowsView = Gate::forUser($user)->allows('view', CustomActivity::class);
        dump("Gate::allows('view', CustomActivity::class): ", $gateAllowsView);


        return "ポリシーテスト完了。ActivityLogPolicy内のdd出力を確認してください。";
    });*/


    // 新しい汎用Livewireコンポーネントテストルート
    Route::get('/test-component/{component}', function ($component, \Illuminate\Http\Request $request) {
        // コンポーネント名を完全修飾クラス名に変換
        $componentClass = "App\\Livewire\\Common\\" . Str::studly($component); // 例: 'activity-history-display' -> 'ActivityHistoryDisplay'

        if (!class_exists($componentClass)) {
            abort(404, "Livewire component [{$componentClass}] not found.");
        }

        // クエリパラメータからコンポーネントのプロパティを抽出
        $props = $request->except('component');

        // resourceId と resourceType が数値や特定文字列でない場合はキャスト
        if (isset($props['resourceId'])) {
            $props['resourceId'] = (int) $props['resourceId'];
        }
        if (isset($props['includeRelatedResources'])) {
            $props['includeRelatedResources'] = filter_var($props['includeRelatedResources'], FILTER_VALIDATE_BOOLEAN);
        }

        return view('test-livewire-component', [
            'componentName' => $componentClass,
            'componentProps' => $props,
        ]);
    })->middleware(['auth', 'verified'])->name('test-livewire-component');

// 特定のリソースのテスト用短縮ルート (オプション、上記汎用ルートで十分だが利便性のため)
    Route::get('/test-activity-ledger/{ledger}', function (Ledger $ledger) {
        return redirect()->route('test-livewire-component', [
            'component' => 'activity-history-display',
            'resourceId' => $ledger->id,
            'resourceType' => 'Ledger',
            'includeRelatedResources' => true,
        ]);
    })->middleware(['auth', 'verified'])->name('test-activity-ledger');

    Route::get('/test-activity-folder/{folder}', function (Folder $folder) {
        return redirect()->route('test-livewire-component', [
            'component' => 'activity-history-display',
            'resourceId' => $folder->id,
            'resourceType' => 'Folder',
        ]);
    })->middleware(['auth', 'verified'])->name('test-activity-folder');

    Route::get('/test-activity-ledger-define/{ledgerDefine}', function (LedgerDefine $ledgerDefine) {
        return redirect()->route('test-livewire-component', [
            'component' => 'activity-history-display',
            'resourceId' => $ledgerDefine->id,
            'resourceType' => 'LedgerDefine',
        ]);
    })->middleware(['auth', 'verified'])->name('test-activity-ledger-define');

    Route::get('/test-activity-all', function () {
        return redirect()->route('test-livewire-component', [
            'component' => 'activity-history-display',
        ]);
    })->middleware(['auth', 'verified'])->name('test-activity-all');


    Route::get('/test-permissions-ledger/{ledger}', function (Ledger $ledger) {
        return redirect()->route('test-livewire-component', [
            'component' => 'permission-display',
            'resourceId' => $ledger->id,
            'resourceType' => 'Ledger',
        ]);
    })->middleware(['auth', 'verified'])->name('test-permissions-ledger');

    Route::get('/test-permissions-ledger-define/{ledgerDefine}', function (LedgerDefine $ledgerDefine) {
        return redirect()->route('test-livewire-component', [
            'component' => 'permission-display',
            'resourceId' => $ledgerDefine->id,
            'resourceType' => 'LedgerDefine',
        ]);
    })->middleware(['auth', 'verified'])->name('test-permissions-ledger-define');

    Route::get('/test-permissions-folder/{folder}', function (Folder $folder) {
        return redirect()->route('test-livewire-component', [
            'component' => 'permission-display',
            'resourceId' => $folder->id,
            'resourceType' => 'Folder',
        ]);
    })->middleware(['auth', 'verified'])->name('test-permissions-folder');

    // Attachment Download Route
    Route::get('/files/{attachedFile}/download', [AttachedFileDownloadController::class, 'download'])
        ->name('file.download')
        ->where('attachedFile', '[0-9]+');

    // Font Awesome アイコン配信ルート
    Route::get('/fontawesome/{style}/{icon}.svg', [App\Http\Controllers\FontAwesomeIconController::class, 'serveIcon'])
        ->name('fontawesome.icon');

});

Route::get('/phpinfo', function () {
    phpinfo();
});

// 使用例 (ルートファイルなど)
//Route::get('/organizations/{organization}/users', [UserController::class, 'index'])
//    ->middleware(['auth', 'check.organization.permission:view-users']);
