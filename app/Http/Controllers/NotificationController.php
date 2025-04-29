<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * 通知関連ページのインデックス（タブコンテナ）を表示
     */
    public function index(Request $request): View
    {
        // URL クエリパラメータからアクティブなタブを取得 (デフォルトは 'notifications')
        $activeTab = $request->query('tab', 'notifications');
//        dd($activeTab);
        // 親ビューにアクティブなタブ情報を渡す
        return view('notifications.index', ['activeTab' => $activeTab]);
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
