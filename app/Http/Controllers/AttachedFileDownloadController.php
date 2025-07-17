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
        Log::info('[DownloadController@download] Started.', [
            'attached_file_id' => $attachedFile->id,
            'is_thumbnail' => $request->boolean('thumbnail'),
            'is_original' => $request->boolean('original'),
        ]);

        // 1. 認可チェック
        try {
            Gate::authorize('view', $attachedFile->ledger);
            Log::info('[DownloadController@download] Authorization successful.');
        } catch (\Exception $e) {
            Log::error('[DownloadController@download] Authorization failed.', ['error' => $e->getMessage()]);
            abort(403, 'Forbidden');
        }

        // 2. パスとファイル名を決定
        $isThumbnailRequest = $request->boolean('thumbnail');
        $isOriginalRequest = $request->boolean('original');
        $filePath = '';
        $fileNameToServe = $attachedFile->original_filename ?? $attachedFile->filename;

        if ($isThumbnailRequest) {
            $filePath = 'Ledger/thumbs/' . $attachedFile->hashedbasename;
        } elseif ($isOriginalRequest && $attachedFile->original_file_path) {
            $filePath = $attachedFile->original_file_path;
            $fileNameToServe = $attachedFile->original_filename ?? $attachedFile->filename;
        } else {
            $filePath = $attachedFile->path;
            if ($attachedFile->optimized && $attachedFile->mime === 'application/pdf') {
                $fileNameToServe = pathinfo($fileNameToServe, PATHINFO_FILENAME) . '.pdf';
            }
        }
        Log::info('[DownloadController@download] Determined file path and name.', ['path' => $filePath, 'serve_as' => $fileNameToServe]);

        // 3. ファイルの物理的な存在を確認
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('[DownloadController@download] File not found in public disk.', ['path' => $filePath]);
            abort(404, 'File Not Found');
        }

        // 4. アクティビティログの記録
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event($isThumbnailRequest ? 'viewed_thumbnail' : ($isOriginalRequest ? 'downloaded_original' : 'downloaded'))
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'original_filename' => $fileNameToServe,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User activity logged for file: {$fileNameToServe}");

        // 5. レスポンス生成
        if ($isThumbnailRequest) {
            Log::info('[DownloadController@download] Returning thumbnail response.');
            return Storage::disk('public')->response($filePath);
        }

        $mimeType = Storage::disk('public')->mimeType($filePath);
        $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif']) ? 'inline' : 'attachment';

        Log::info('[DownloadController@download] Returning file response.', ['mime' => $mimeType, 'disposition' => $disposition]);

        return response()->file(Storage::disk('public')->path($filePath), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition . '; filename="' . $fileNameToServe . '"'
        ]);
    }
}