<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FilePondController extends Controller
{
    public function load(AttachedFile $attachedFile)
    {
        // 認可チェック
        Gate::authorize('view', $attachedFile->ledger);

        // ファイルの内容を直接レスポンス
        return Storage::disk('public')->response($attachedFile->path);
    }
}
