<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知関連ページのインデックス（タブコンテナ）を表示
     */
    public function index(Request $request, NotificationService $notificationService): View
    {
        $user = Auth::user();
        $initialNotificationCount = $user ? $notificationService->getUnreadNotificationCountForUser($user) : 0;
        $initialTaskCount = $user ? ($user->pending_inspection_count + $user->pending_approval_count) : 0;

        // URL クエリパラメータからアクティブなタブを取得 (デフォルトは 'notifications')
        $activeTab = $request->query('tab', 'notifications');
        // デフォルトで未処理タスクがあれば 'tasks' に切り替える
        if ($activeTab == 'notifications' && $initialTaskCount > 0) {
            $activeTab = 'tasks';
        }

        // 親ビューにアクティブなタブ情報を渡す
        return view('notifications.index', [
            'initialNotificationCount' => $initialNotificationCount,
            'initialTaskCount' => $initialTaskCount,
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
