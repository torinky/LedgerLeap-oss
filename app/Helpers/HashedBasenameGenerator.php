<?php

namespace App\Helpers;

use App\Models\AttachedFile;
use Illuminate\Http\UploadedFile;

class HashedBasenameGenerator
{
    public function generate(UploadedFile $file): string
    {
        try {
            $mtime = $file->getMTime();
        } catch (\RuntimeException $e) {
            $mtime = now()->timestamp;
        }

        return hash('sha256', implode('|', [
                $file->getClientOriginalName(),
                $file->getSize(),
                (string) $mtime,
            ])).'.'.$file->getClientOriginalExtension();
    }

    public function generateWithRetry(UploadedFile $file, int $maxRetries = 3): string
    {
        $basename = $this->generate($file);
        $retries = 0;

        try {
            $mtime = $file->getMTime();
        } catch (\RuntimeException $e) {
            $mtime = now()->timestamp;
        }

        while (AttachedFile::where('hashedbasename', $basename)->exists() && $retries < $maxRetries) {
            $basename = hash('sha256', implode('|', [
                    $file->getClientOriginalName(),
                    $file->getSize(),
                    (string) $mtime,
                    (string) microtime(true),
                    (string) $retries,
                ])).'.'.$file->getClientOriginalExtension();
            $retries++;
        }

        return $basename;
    }

    public function generateRaw(string $filename, string $extension, int $size): string
    {
        return hash('sha256', implode('|', [
                $filename,
                (string) $size,
                now()->format('YmdHisv'),
            ])).'.'.$extension;
    }

    public function generateForSeeder(string $sanitizedName, string $extension, ?int $fileSize = null): string
    {
        return $this->generateRaw(
            $sanitizedName,
            $extension,
            $fileSize ?? (int) (now()->timestamp * 1000)
        );
    }
}
