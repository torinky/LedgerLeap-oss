<?php

namespace App\Services\Ledger;

use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class LedgerAttachmentBinaryResourceService
{
    public function buildResourceUri(Ledger $ledger, AttachedFile $attachedFile): string
    {
        return sprintf(
            'ledgerleap://ledger/%s/%s/attachments/%s/blob',
            (string) $ledger->tenant_id,
            (string) $ledger->id,
            (string) $attachedFile->id
        );
    }

    public function resolveAttachmentPath(AttachedFile $attachedFile, bool $original = false): string
    {
        if ($original && is_string($attachedFile->original_file_path) && $attachedFile->original_file_path !== '') {
            return $attachedFile->original_file_path;
        }

        return (string) $attachedFile->path;
    }

    public function resolveAttachmentMimeType(AttachedFile $attachedFile, ?string $path = null): string
    {
        $mimeType = $path !== null ? (Storage::disk('public')->mimeType($path) ?: null) : null;

        $mimeType = $mimeType
            ?? $attachedFile->original_mime_type
            ?? $attachedFile->mime
            ?? 'application/octet-stream';

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }

    public function readAttachmentBytes(AttachedFile $attachedFile, bool $original = false): string
    {
        $path = $this->resolveAttachmentPath($attachedFile, $original);

        if ($path === '') {
            throw new InvalidArgumentException('Attachment storage path could not be resolved.');
        }

        if (! Storage::disk('public')->exists($path)) {
            throw new InvalidArgumentException("Attachment file [{$path}] could not be found.");
        }

        $bytes = Storage::disk('public')->get($path);

        return is_string($bytes) ? $bytes : (string) $bytes;
    }
}
