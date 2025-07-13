<?php

namespace App\Http\Controllers;

use App\Models\AttachedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
        $isOriginalRequest = $request->boolean('original'); // New
        $filePath = '';
        $fileNameToServe = $attachedFile->filename; // Default to current filename

        if ($isThumbnailRequest) {
            $filePath = 'Ledger/thumbs/' . $attachedFile->hashedbasename;
            Log::info('Thumbnail request: filePath = ' . $filePath); // 追加
        } elseif ($isOriginalRequest && $attachedFile->original_file_path) { // New
            // original_file_path は public ディスクからの相対パスとして保存されていることを想定
            $filePath = $attachedFile->original_file_path;
            $fileNameToServe = $attachedFile->original_filename; // Use original filename
            Log::info('Original request: filePath = ' . $filePath); // 追加
        } else {
            $filePath = $attachedFile->path;
            Log::info('Normal download request: filePath = ' . $filePath); // 追加
        }

        // 3. ファイルの物理的な存在を確認
        // Storage::exists() はデフォルトディスク (local) を見るため、public ディスクを明示
        if (!Storage::disk('public')->exists($filePath)) {
            Log::warning('File not found: ' . $filePath); // 追加
            abort(404);
        }

        // 4. アクティビティログの記録 (サムネイル閲覧でも記録)
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event($isThumbnailRequest ? 'viewed_thumbnail' : ($isOriginalRequest ? 'downloaded_original' : 'downloaded')) // イベント名を変更
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'original_filename' => $fileNameToServe,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User " . ($isThumbnailRequest ? 'viewed thumbnail for' : ($isOriginalRequest ? 'downloaded original of' : 'downloaded an attachment:')) . " {$fileNameToServe}");

        // 5. ダウンロード/インライン表示レスポンスの生成
        if ($isThumbnailRequest) {
            $mimeType = Storage::disk('public')->mimeType($filePath); // 追加
            Log::info('Thumbnail response: mimeType = ' . $mimeType); // 追加
            // サムネイルはインラインで表示
            return Storage::disk('public')->response($filePath);
        } else {
            $mimeType = Storage::disk('public')->mimeType($filePath);
            $disposition = 'attachment'; // デフォルトはダウンロード

            // ブラウザでプレビュー可能なMIMEタイプの場合、インライン表示を試みる
            if (in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'])) {
                $disposition = 'inline';
            }
            Log::info('Normal/Original response: mimeType = ' . $mimeType . ', disposition = ' . $disposition); // 追加

            return response()->file(Storage::disk('public')->path($filePath), [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $fileNameToServe . '"'
            ]);
        }
    }
}