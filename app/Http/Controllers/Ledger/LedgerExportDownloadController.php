<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\LedgerDefine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $downloadName = $this->buildDownloadName($ledgerDefine, $filename);

        Log::info('[LedgerExportDownloadController] Downloading file.', [
            'storage_filename' => $filename,
            'download_name' => $downloadName,
        ]);

        return Storage::disk('public')->download($filename, $downloadName);
    }

    /**
     * ペルソナにとって理解しやすいダウンロードファイル名を組み立てる
     *
     * 形式: 台帳名-フォルダ階層-生成日時.csv
     */
    private function buildDownloadName(LedgerDefine $ledgerDefine, string $storageFilename): string
    {
        $title = $ledgerDefine->title;

        $folderPath = $this->resolveFolderPath($ledgerDefine);

        $generatedAt = Carbon::createFromTimestamp(
            Storage::disk('public')->lastModified($storageFilename)
        )->format('Y-m-d_H-i-s');

        $segments = array_filter([$title, $folderPath, $generatedAt]);

        return $this->sanitizeFilename(implode('-', $segments)).'.csv';
    }

    /**
     * 台帳定義が属するフォルダの階層パスを文字列で返す
     */
    private function resolveFolderPath(LedgerDefine $ledgerDefine): ?string
    {
        $folder = $ledgerDefine->folder;

        if (! $folder) {
            return null;
        }

        $pathFolders = $folder->ancestors->push($folder);

        $titles = $pathFolders->reject(function ($f) {
            return $f->parent_id === null;
        })->map(function ($f) {
            return $f->title;
        });

        return $titles->implode(' › ');
    }

    /**
     * ファイル名に使用できない文字を安全な文字に置き換える
     */
    private function sanitizeFilename(string $name): string
    {
        // Windows/macOS/Linux で問題になりやすい文字を除去・置換
        $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"], '-', $name);

        // 先頭末尾の空白・ドットを除去
        $name = trim($name, " \t\n\r\0\x0B.");

        // 連続するハイフンを1つに
        $name = preg_replace('/-+/', '-', $name);

        // 空になった場合のフォールバック
        if ($name === '') {
            $name = 'ledger-export';
        }

        // 長すぎる場合は切る（OS上限を考慮）
        return Str::limit($name, 200, '');
    }
}
