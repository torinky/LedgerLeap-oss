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
        // 1. 認可チェック (先に行う方がセキュア)
        if (Gate::denies('view', $attachedFile->ledger)) {
            abort(404);
        }

        // 2. サムネイルリクエストか判定
        $isThumbnailRequest = $request->boolean('thumbnail');
        $filePath = $isThumbnailRequest
            ? 'public/Ledger/thumbs/' . $attachedFile->hashedbasename
            : $attachedFile->path;

        // 3. ファイルの物理的な存在を確認
        if (!Storage::exists($filePath)) {
            // サムネイルが存在しない場合は、代替画像やエラーではなく、実ファイルを返すなどのフォールバックも検討可能
            // ここではシンプルに404を返す
            abort(404);
        }

        // 4. アクティビティログの記録 (サムネイル閲覧でも記録)
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event($isThumbnailRequest ? 'viewed_thumbnail' : 'downloaded') // イベント名を変更
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'original_filename' => $attachedFile->filename,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User " . ($isThumbnailRequest ? 'viewed thumbnail for' : 'downloaded an attachment:') . " {$attachedFile->filename}");

        // 5. ダウンロード/インライン表示レスポンスの生成
        if ($isThumbnailRequest) {
            // サムネイルはインラインで表示
            return Storage::response($filePath);
        } else {
            // 実ファイルはダウンロード
            return Storage::download($filePath, $attachedFile->filename);
        }
    }
}