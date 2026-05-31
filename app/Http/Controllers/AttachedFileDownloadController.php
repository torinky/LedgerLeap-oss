<?php

namespace App\Http\Controllers;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Models\AttachedFile;
use App\Services\Ledger\LedgerAttachmentBinaryResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachedFileDownloadController extends Controller
{
    public function __construct(
        private readonly LedgerAttachmentBinaryResourceService $attachmentBinaryResourceService,
    ) {}

    public function download(Request $request, AttachedFile $attachedFile)
    {
        Log::info('[DownloadController@download] Started.', [
            'attached_file_id' => $attachedFile->id,
            'is_thumbnail' => $request->boolean('thumbnail'),
            'is_original' => $request->boolean('original'),
        ]);

        if ($request->filled('hash') && $request->query('hash') !== $attachedFile->hashedbasename) {
            Log::warning('[DownloadController@download] hashedbasename mismatch.', [
                'attached_file_id' => $attachedFile->id,
                'url_hash' => $request->query('hash'),
                'db_hash' => $attachedFile->hashedbasename,
            ]);
            abort(404, 'File Not Found');
        }

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

        Log::info('[DownloadController@download] AttachedFile path.', [
            'attached_file_path' => $attachedFile->path,
            'original_file_path' => $attachedFile->original_file_path,
        ]);

        if ($isThumbnailRequest) {
            $filePath = AttachedFilePathHelper::getThumbnailStoragePath(
                $attachedFile->hashedbasename,
                $attachedFile->tenant_id,
            );
        } elseif ($isOriginalRequest && $attachedFile->original_file_path) {
            $filePath = $this->attachmentBinaryResourceService->resolveAttachmentPath($attachedFile, true);
            $fileNameToServe = $attachedFile->original_filename ?? $attachedFile->filename;
            Log::info('[DownloadController@download] Original file path from helper.', [
                'path' => $filePath,
            ]);
        } else {
            $filePath = $this->attachmentBinaryResourceService->resolveAttachmentPath($attachedFile);
            Log::info('[DownloadController@download] Attachment file path from helper.', [
                'path' => $filePath,
            ]);
            if ($attachedFile->optimized && $attachedFile->mime === 'application/pdf') {
                $fileNameToServe = pathinfo($fileNameToServe, PATHINFO_FILENAME).'.pdf';
            }
        }
        Log::info('[DownloadController@download] Determined file path and name.', [
            'path' => $filePath,
            'serve_as' => $fileNameToServe,
        ]);

        // 5. レスポンス生成
        if ($isThumbnailRequest) {
            $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath(
                $attachedFile->hashedbasename,
                $attachedFile->tenant_id,
            );
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Log::info('[DownloadController@download] Returning actual thumbnail response with inline disposition.');
                $mimeType = Storage::disk('public')->mimeType($thumbnailPath);

                return response()->file(Storage::disk('public')->path($thumbnailPath), [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="'.$fileNameToServe.'"',
                ]);
            }

            $previewMime = $attachedFile->original_mime_type ?? $attachedFile->mime ?? '';
            $hasOriginalImage = str_starts_with($previewMime, 'image/')
                && $attachedFile->original_file_path
                && Storage::disk('public')->exists($attachedFile->original_file_path);

            if ($hasOriginalImage) {
                $this->queueThumbnailGeneration($attachedFile);

                return $this->processingThumbnailResponse($fileNameToServe);
            }

            Log::info('[DownloadController@download] Thumbnail not found, returning icon instead of redirecting.');

            // FontAwesomeIconControllerを使用して直接アイコンを返す
            return app(FontAwesomeIconController::class)
                ->serveIconByMime($request->merge(['type' => $previewMime]));
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
            ->event($this->getDownloadEventName($isThumbnailRequest, $isOriginalRequest))
            ->withProperties([
                'ledger_id' => $attachedFile->ledger->id,
                'ledger_define_id' => $attachedFile->ledger->ledger_define_id,
                'original_filename' => $fileNameToServe,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User activity logged for file: '.$fileNameToServe);

        $mimeType = $this->attachmentBinaryResourceService->resolveAttachmentMimeType($attachedFile, $filePath);

        // MIME種別に基づいてContent-Dispositionを決定
        $inlineMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];

        if ($isOriginalRequest && ! in_array($mimeType, $inlineMimeTypes, true)) {
            $disposition = 'attachment';
        } else {
            $disposition = in_array($mimeType, $inlineMimeTypes, true) ? 'inline' : 'attachment';
        }

        Log::info('[DownloadController@download] Returning file response.', [
            'mime' => $mimeType,
            'disposition' => $disposition,
        ]);

        return response()->file(Storage::disk('public')->path($filePath), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$fileNameToServe.'"',
        ]);
    }

    private function getDownloadEventName(bool $isThumbnailRequest, bool $isOriginalRequest): string
    {
        if ($isThumbnailRequest) {
            return 'viewed_thumbnail';
        }

        if ($isOriginalRequest) {
            return 'downloaded_original';
        }

        return 'downloaded';
    }

    private function queueThumbnailGeneration(AttachedFile $attachedFile): void
    {
        if ($attachedFile->status === AttachedFileStatus::OPTIMIZING) {
            Log::info('[DownloadController@download] Thumbnail generation already in progress; skipping dispatch.', [
                'attached_file_id' => $attachedFile->id,
            ]);

            return;
        }

        $attachedFile->update(['status' => AttachedFileStatus::OPTIMIZING->value]);

        GenerateThumbnail::dispatch($attachedFile->id);

        Log::info('[DownloadController@download] Thumbnail generation queued.', [
            'attached_file_id' => $attachedFile->id,
            'status' => AttachedFileStatus::OPTIMIZING->value,
        ]);
    }

    private function processingThumbnailResponse(string $fileNameToServe)
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Processing">
  <rect width="64" height="64" rx="10" fill="#f8fafc"/>
  <circle cx="32" cy="32" r="18" fill="none" stroke="#cbd5e1" stroke-width="6"/>
  <path d="M32 14a18 18 0 1 1-18 18" fill="none" stroke="#3b82f6" stroke-width="6" stroke-linecap="round"/>
  <text x="32" y="40" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#64748b">Processing</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$fileNameToServe.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
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
            Log::error('[DownloadController@downloadVlm] VLM result not found.', [
                'attached_file_id' => $attachedFile->id,
            ]);
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

        Log::info('[DownloadController@downloadVlm] Returning VLM result.', [
            'format' => $format,
            'filename' => $filename,
        ]);

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
            Log::error('[DownloadController@downloadOcrPdf] OCR not processed yet.', [
                'attached_file_id' => $attachedFile->id,
            ]);
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
            $downloadFileName = pathinfo(
                $attachedFile->original_filename ?? $attachedFile->filename,
                PATHINFO_FILENAME
            ).'.pdf';
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
