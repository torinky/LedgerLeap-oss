<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachedFileDownloadController extends Controller
{
    public function download(Request $request, AttachedFile $attachedFile)
    {
        // 1. ファイルの物理的な存在を先に確認する
        if (!Storage::exists($attachedFile->path)) {
            abort(404);
        }

        // 2. 認可チェックを行い、権限がなければ404を返す
        if (Gate::denies('view', $attachedFile->ledger)) {
            abort(404);
        }

        // 3. アクティビティログの記録
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event('downloaded')
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'original_filename' => $attachedFile->filename,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User downloaded an attachment: {$attachedFile->filename}");

        // 4. ダウンロードレスポンスの生成
        return Storage::download($attachedFile->path, $attachedFile->filename);
    }
}