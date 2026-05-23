<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\Storage;

class ExportCacheService
{
    /**
     * 検索条件に基づいて一意なエクスポートファイル名を生成する
     */
    public function buildFilename(int $ledgerDefineId, array $keywords, array $filter): string
    {
        $hash = md5(json_encode([$keywords, $filter], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return "ledger-export-{$ledgerDefineId}-{$hash}.csv";
    }

    /**
     * 指定されたファイル名が public disk に存在するか
     */
    public function exists(string $filename): bool
    {
        return Storage::disk('public')->exists($filename);
    }

    /**
     * 指定された LedgerDefine の全CSVエクスポートを削除する
     */
    public function clearByLedgerDefineId(int $ledgerDefineId): void
    {
        $files = Storage::disk('public')->files();

        $prefix = "ledger-export-{$ledgerDefineId}-";

        foreach ($files as $file) {
            if (str_starts_with($file, $prefix)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
