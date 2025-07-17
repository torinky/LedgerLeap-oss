<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FilePondController extends Controller
{
    /**
     * FilePondが既存のファイルをロードするためのエンドポイント。
     *
     * @param AttachedFile $attachedFile
     * @return \Illuminate\Http\Response
     */
    public function load(AttachedFile $attachedFile)
    {
        Log::info('[FilePondController@load] Started.', ['attached_file_id' => $attachedFile->id]);

        // 認可チェック: このファイルが属する台帳を閲覧できるか
        try {
            Gate::authorize('view', $attachedFile->ledger);
            Log::info('[FilePondController@load] Authorization successful.');
        } catch (\Exception $e) {
            Log::error('[FilePondController@load] Authorization failed.', ['error' => $e->getMessage()]);
            abort(403, 'Forbidden');
        }

        // ファイルのパスを取得
        $filePath = $attachedFile->path;
        Log::info('[FilePondController@load] File path from DB.', ['path' => $filePath]);

        // ファイルが存在するか確認
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('[FilePondController@load] File not found in public disk.', ['path' => $filePath]);
            abort(404, 'File Not Found');
        }

        Log::info('[FilePondController@load] File found. Returning response.');
        // Content-Dispositionヘッダーなしでファイルの内容を直接返す
        return Storage::disk('public')->response($filePath);
    }
}