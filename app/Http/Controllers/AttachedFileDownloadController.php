<?php

namespace App\Http\Controllers;

use App\Helpers\AttachedFilePathHelper;
use App\Models\AttachedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        Log::info('[DownloadController@download] AttachedFile path: '.$attachedFile->path.', original_file_path: '.$attachedFile->original_file_path);

        if ($isThumbnailRequest) {
            $filePath = AttachedFilePathHelper::getThumbnailStoragePath($attachedFile->hashedbasename, $attachedFile->tenant_id);
        } elseif ($isOriginalRequest && $attachedFile->original_file_path) {
            $filePath = $attachedFile->original_file_path;
            $fileNameToServe = $attachedFile->original_filename ?? $attachedFile->filename;
            Log::info('[DownloadController@download] Original file path from helper: '.$filePath);
        } else {
            $filePath = $attachedFile->path;
            Log::info('[DownloadController@download] Attachment file path from helper: '.$filePath);
            if ($attachedFile->optimized && $attachedFile->mime === 'application/pdf') {
                $fileNameToServe = pathinfo($fileNameToServe, PATHINFO_FILENAME).'.pdf';
            }
        }
        Log::info('[DownloadController@download] Determined file path and name.', ['path' => $filePath, 'serve_as' => $fileNameToServe]);

        // 5. レスポンス生成
        if ($isThumbnailRequest) {
            $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($attachedFile->hashedbasename, $attachedFile->tenant_id);
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Log::info('[DownloadController@download] Returning actual thumbnail response with inline disposition.');
                $mimeType = Storage::disk('public')->mimeType($thumbnailPath);

                return response()->file(Storage::disk('public')->path($thumbnailPath), [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="'.$fileNameToServe.'"',
                ]);
            } else {
                Log::info('[DownloadController@download] Thumbnail not found, checking status for re-dispatch.');
                // サムネイルが存在しない場合、ステータスがTHUMBNAIL_FAILEDであれば再ディスパッチを試みる
                if ($attachedFile->status === \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED) {
                    // ジョブの試行回数が最大試行回数未満の場合のみ再ディスパッチ
                    // ここでは簡易的に、AttachedFileのstatusがTHUMBNAIL_FAILEDであれば再試行とみなす
                    // より厳密な試行回数チェックはジョブ側で行う
                    \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\Ledger\GenerateThumbnail($attachedFile->id));
                    Log::info('[DownloadController@download] Re-dispatched GenerateThumbnail job for ID: '.$attachedFile->id);
                }
                // 既存のフォールバックロジック（FontAwesomeアイコンへのリダイレクト）
                $displayMimeType = $attachedFile->mime;
                switch ($displayMimeType) {
                    case 'application/pdf':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-pdf']);
                    case 'application/zip':
                    case 'application/x-zip-compressed':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-zipper']);
                    case 'application/msword':
                    case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-word']);
                    case 'application/vnd.ms-excel':
                    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-excel']);
                    case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-powerpoint']);
                    case 'text/plain':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-lines']);
                    case 'text/html':
                    case 'text/css':
                    case 'application/javascript':
                    case 'application/json':
                    case 'application/xml':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-code']);
                    case 'text/csv':
                        return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-csv']);
                    default:
                        if (str_starts_with($displayMimeType, 'audio/')) {
                            return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-audio']);
                        } elseif (str_starts_with($displayMimeType, 'video/')) {
                            return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-video']);
                        } elseif (str_starts_with($displayMimeType, 'image/')) {
                            return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file-image']);
                        } else {
                            return redirect()->route('api.fontawesome.icon', ['style' => 'solid', 'icon' => 'file']);
                        }
                }
            }
        }

        // 3. ファイルの物理的な存在を確認
        if (! Storage::disk('public')->exists($filePath)) {
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

        $mimeType = Storage::disk('public')->mimeType($filePath);

        // original=trueの場合は必ずダウンロード（attachment）
        if ($isOriginalRequest) {
            $disposition = 'attachment';
        } else {
            // 通常のダウンロード: PDFや画像はinline、その他はattachment
            $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif']) ? 'inline' : 'attachment';
        }

        Log::info('[DownloadController@download] Returning file response.', ['mime' => $mimeType, 'disposition' => $disposition]);

        return response()->file(Storage::disk('public')->path($filePath), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$fileNameToServe.'"',
        ]);
    }

    public function downloadVlm(Request $request, AttachedFile $attachedFile)
    {
        Log::info('[DownloadController@downloadVlm] Started.', [
            'attached_file_id' => $attachedFile->id,
            'format' => $request->query('format', 'markdown'),
        ]);

        // 1. 認可チェック
        try {
            Gate::authorize('view', $attachedFile->ledger);
            Log::info('[DownloadController@downloadVlm] Authorization successful.');
        } catch (\Exception $e) {
            Log::error('[DownloadController@downloadVlm] Authorization failed.', ['error' => $e->getMessage()]);
            abort(403, 'Forbidden');
        }

        // 2. VLM結果の存在確認
        if (! $attachedFile->hasVlmResult()) {
            Log::error('[DownloadController@downloadVlm] VLM result not found.', ['attached_file_id' => $attachedFile->id]);
            abort(404, 'VLM Result Not Found');
        }

        // 3. フォーマット決定
        $format = $request->query('format', 'markdown');
        $baseFilename = pathinfo($attachedFile->original_filename ?? $attachedFile->filename, PATHINFO_FILENAME);

        if ($format === 'json' && $attachedFile->vlm_structured_data) {
            $content = json_encode($attachedFile->vlm_structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $contentType = 'application/json';
            $filename = $baseFilename.'_vlm.json';
        } else {
            $content = $attachedFile->vlm_markdown;
            $contentType = 'text/markdown';
            $filename = $baseFilename.'_vlm.md';
        }

        // 4. アクティビティログの記録
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event('downloaded_vlm')
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'format' => $format,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User downloaded VLM result for file: {$filename}");

        Log::info('[DownloadController@downloadVlm] Returning VLM result.', ['format' => $format, 'filename' => $filename]);

        // 5. レスポンス生成
        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * OCR処理後のPDFファイルをダウンロード
     * 画像ファイルの場合: 変換されたPDF
     * PDFファイルの場合: OCR最適化されたPDF
     */
    public function downloadOcrPdf(Request $request, AttachedFile $attachedFile)
    {
        Log::info('[DownloadController@downloadOcrPdf] Started.', [
            'attached_file_id' => $attachedFile->id,
        ]);

        // 1. 認可チェック
        try {
            Gate::authorize('view', $attachedFile->ledger);
            Log::info('[DownloadController@downloadOcrPdf] Authorization successful.');
        } catch (\Exception $e) {
            Log::error('[DownloadController@downloadOcrPdf] Authorization failed.', ['error' => $e->getMessage()]);
            abort(403, 'Forbidden');
        }

        // 2. OCR処理完了確認
        if (! $attachedFile->ocr_processed_at) {
            Log::error('[DownloadController@downloadOcrPdf] OCR not processed yet.', ['attached_file_id' => $attachedFile->id]);
            abort(404, 'OCR PDF Not Found');
        }

        // 3. 画像ファイルかPDFファイルか判定
        $isImageFile = str_starts_with($attachedFile->original_mime_type ?? '', 'image/');

        Log::info('[DownloadController@downloadOcrPdf] File type determination.', [
            'original_mime_type' => $attachedFile->original_mime_type,
            'mime' => $attachedFile->mime,
            'is_image_file' => $isImageFile,
            'hashedbasename' => $attachedFile->hashedbasename,
            'path' => $attachedFile->path,
            'original_file_path' => $attachedFile->original_file_path,
        ]);

        // 4. ファイルパスとファイル名を決定
        if ($isImageFile) {
            // 画像ファイルの場合: .pdfに変換されたファイル
            // pathから.pdfに変更（元のpathと同じディレクトリ）
            $filePath = $attachedFile->path; // これがすでに正しいPDFパス
            $downloadFileName = pathinfo($attachedFile->original_filename ?? $attachedFile->filename, PATHINFO_FILENAME).'.pdf';
        } else {
            // PDFファイルの場合: OCR最適化されたPDF（元のファイルパス）
            $filePath = $attachedFile->path;
            $downloadFileName = $attachedFile->original_filename ?? $attachedFile->filename;
        }

        Log::info('[DownloadController@downloadOcrPdf] Determined file path.', [
            'is_image_file' => $isImageFile,
            'path' => $filePath,
            'filename' => $downloadFileName,
        ]);

        // 5. ファイルの物理的な存在を確認
        if (! Storage::disk('public')->exists($filePath)) {
            // デバッグ情報: attachmentsディレクトリの内容を確認
            $attachmentsDir = 'attachments';
            $filesInDir = Storage::disk('public')->exists($attachmentsDir)
                ? Storage::disk('public')->files($attachmentsDir)
                : [];

            Log::error('[DownloadController@downloadOcrPdf] OCR PDF file not found in public disk.', [
                'path' => $filePath,
                'full_path' => Storage::disk('public')->path($filePath),
                'attachments_dir_exists' => Storage::disk('public')->exists($attachmentsDir),
                'files_in_attachments' => array_slice($filesInDir, 0, 10), // 最初の10件のみ
                'total_files' => count($filesInDir),
            ]);
            abort(404, 'OCR PDF File Not Found');
        }

        // 6. アクティビティログの記録
        activity()
            ->performedOn($attachedFile)
            ->causedBy(auth()->user())
            ->event('downloaded_ocr_pdf')
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'is_image_conversion' => $isImageFile,
                'filename' => $downloadFileName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log("User downloaded OCR PDF: {$downloadFileName}");

        Log::info('[DownloadController@downloadOcrPdf] Returning OCR PDF file response.');

        // 7. レスポンス生成
        return response()->file(Storage::disk('public')->path($filePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$downloadFileName.'"',
        ]);
    }
}
