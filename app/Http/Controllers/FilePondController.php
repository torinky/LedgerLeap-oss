<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class FilePondController extends Controller
{
    public function load(AttachedFile $attachedFile)
    {
        // 認可チェック
        Gate::authorize('view', $attachedFile->ledger);

        $path = $attachedFile->path;

        // ファイルが存在しない場合は404を返す
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // ファイルの内容を取得
        $file = Storage::disk('public')->get($path);
        // MIMEタイプを取得
        $type = Storage::disk('public')->mimeType($path);

        // Content-Disposition ヘッダーなしでファイルコンテンツを返す
        return Response::make($file, 200, [
            'Content-Type' => $type,
        ]);
    }
}
