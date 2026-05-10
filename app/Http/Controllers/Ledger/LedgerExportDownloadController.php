<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerExportDownloadController extends Controller
{
    /**
     * CSVエクスポートファイルをダウンロードする
     */
    public function __invoke(Request $request, int $ledgerDefineId, string $filename): StreamedResponse
    {
        Log::info('[LedgerExportDownloadController] Started.', [
            'ledger_define_id' => $ledgerDefineId,
            'filename' => $filename,
        ]);

        $ledgerDefine = LedgerDefine::findOrFail($ledgerDefineId);

        // 認可チェック
        try {
            Gate::authorize('ledgerView', $ledgerDefine);
            Log::info('[LedgerExportDownloadController] Authorization successful.');
        } catch (\Exception $e) {
            Log::error('[LedgerExportDownloadController] Authorization failed.', ['error' => $e->getMessage()]);
            abort(403, 'Forbidden');
        }

        if (! Storage::disk('public')->exists($filename)) {
            Log::error('[LedgerExportDownloadController] File not found.', [
                'filename' => $filename,
            ]);
            abort(404, 'File Not Found');
        }

        $headers = ['Content-Disposition' => 'attachment; filename="'.$filename.'"'];

        Log::info('[LedgerExportDownloadController] Downloading file.', [
            'filename' => $filename,
        ]);

        return Storage::disk('public')->download($filename, $filename, $headers);
    }
}
