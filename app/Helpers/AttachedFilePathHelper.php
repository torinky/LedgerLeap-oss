<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AttachedFilePathHelper
{
    /**
     * 添付ファイルの保存パスを生成します。
     *
     * @param int $ledgerDefineId 台帳定義ID
     * @param string $hashedBasename ハッシュ化されたファイル名（拡張子含む）
     * @return string
     */
    public static function getAttachmentPath(int $ledgerDefineId, string $hashedBasename): string
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            Log::error('Tenant ID not found while generating attachment path.');
            return '';
        }
        $directory = 'tenants/' . $tenantId . '/Ledger/Attachments/' . $ledgerDefineId;
        // ディレクトリが存在しない場合は作成
        Storage::disk('public')->makeDirectory($directory);
        // publicディスク内の相対パスを返す
        return $directory . '/' . $hashedBasename;
    }

    /**
     * オリジナル添付ファイルの保存パスを生成します。
     *
     * @param int $ledgerDefineId 台帳定義ID
     * @param string $hashedBasename ハッシュ化されたファイル名（拡張子含む）
     * @return string
     */
    public static function getOriginalAttachmentPath(int $ledgerDefineId, string $hashedBasename): string
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            Log::error('Tenant ID not found while generating original attachment path.');
            return '';
        }
        Log::info('getOriginalAttachmentPath: Tenant ID obtained: ' . $tenantId); // 追加
        $directory = 'tenants/' . $tenantId . '/Ledger/Attachments/' . $ledgerDefineId . '/Originals';
        // ディレクトリが存在しない場合は作成
        Storage::disk('public')->makeDirectory($directory);
        // publicディスク内の相対パスを返す
        return $directory . '/' . $hashedBasename;
    }

    /**
     * 添付ファイルのサムネイルパスを生成します。
     *
     * @param int $attachedFileId AttachedFileのID
     * @return string
     */
    public static function getThumbnailPath(int $attachedFileId): string
    {
        // サムネイルはAttachedFileDownloadController経由で提供されるため、
        // ここではダウンロードルートを返す。
        // 実際のサムネイルパスはコントローラ内で解決される。
        return Route::has('file.download') ? route('file.download', ['attachedFile' => $attachedFileId, 'thumbnail' => true]) : '';
    }

    /**
     * サムネイルのストレージパスを生成します。
     *
     * @param string $hashedBasename ハッシュ化されたファイル名（拡張子含む）
     * @return string
     */
    public static function getThumbnailStoragePath(string $hashedBasename): string
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            Log::error('Tenant ID not found while generating thumbnail path.');
            return '';
        }
        $directory = 'tenants/' . $tenantId . '/Ledger/thumbs';
        // ディレクトリが存在しない場合は作成
        Storage::disk('public')->makeDirectory($directory);
        // publicディスク内の相対パスを返す
        return $directory . '/' . $hashedBasename;
    }
}
