<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class MimeTypeHelper
{
    /**
     * Get Font Awesome icon class from MIME type
     * ex: 'fa-solid fa-file-pdf'
     */
    public static function getIcon(?string $mime): string
    {
        if (empty($mime)) {
            return 'fa-solid fa-file text-gray-400';
        }

        return match (true) {
            // Images
            Str::startsWith($mime, 'image/') => 'fa-solid fa-file-image',
            // PDF
            $mime === 'application/pdf' => 'fa-solid fa-file-pdf',
            // Office - Word
            Str::contains($mime, 'word') => 'fa-solid fa-file-word',
            // Office - Excel
            Str::contains($mime, 'excel') || Str::contains($mime, 'spreadsheet') => 'fa-solid fa-file-excel',
            // Office - PowerPoint
            Str::contains($mime, 'powerpoint') || Str::contains($mime, 'presentation') => 'fa-solid fa-file-powerpoint',
            // Archives
            Str::contains($mime, 'zip') || Str::contains($mime, 'archive') ||
            Str::contains($mime, 'compressed') || Str::contains($mime, 'tar') ||
            Str::contains($mime, 'gzip') || Str::contains($mime, 'rar') => 'fa-solid fa-file-zipper',
            // Code
            Str::startsWith($mime, 'text/x-') ||
            $mime === 'application/json' ||
            Str::contains($mime, 'xml') ||
            $mime === 'text/javascript' => 'fa-solid fa-file-code',
            // Video
            Str::startsWith($mime, 'video/') => 'fa-solid fa-file-video',
            // Audio
            Str::startsWith($mime, 'audio/') => 'fa-solid fa-file-audio',
            // Text
            Str::startsWith($mime, 'text/') => 'fa-solid fa-file-lines',
            // CAD
            Str::contains($mime, 'autocad') || Str::contains($mime, 'dwg') || Str::contains($mime, 'dxf') => 'fa-solid fa-file-image',
            // Default
            default => 'fa-solid fa-file',
        };
    }

    /**
     * Get Tailwind CSS color class from MIME type
     * ex: 'text-red-500'
     */
    public static function getColor(?string $mime): string
    {
        if (empty($mime)) {
            return 'text-gray-400';
        }

        return match (true) {
            // Images
            Str::startsWith($mime, 'image/') => 'text-blue-500',
            // PDF
            $mime === 'application/pdf' => 'text-red-500',
            // Office - Word
            Str::contains($mime, 'word') => 'text-blue-700',
            // Office - Excel
            Str::contains($mime, 'excel') || Str::contains($mime, 'spreadsheet') => 'text-green-600',
            // Office - PowerPoint
            Str::contains($mime, 'powerpoint') || Str::contains($mime, 'presentation') => 'text-orange-600',
            // Archives
            Str::contains($mime, 'zip') || Str::contains($mime, 'archive') ||
            Str::contains($mime, 'compressed') || Str::contains($mime, 'tar') ||
            Str::contains($mime, 'gzip') || Str::contains($mime, 'rar') => 'text-purple-600',
            // Code
            Str::startsWith($mime, 'text/x-') ||
            $mime === 'application/json' ||
            Str::contains($mime, 'xml') ||
            $mime === 'text/javascript' => 'text-green-700',
            // Video
            Str::startsWith($mime, 'video/') => 'text-indigo-600',
            // Audio
            Str::startsWith($mime, 'audio/') => 'text-pink-500',
            // Text
            Str::startsWith($mime, 'text/') => 'text-gray-600',
            // CAD
            Str::contains($mime, 'autocad') || Str::contains($mime, 'dwg') || Str::contains($mime, 'dxf') => 'text-teal-600',
            // Default
            default => 'text-gray-400',
        };
    }

    /**
     * Get simplified category from MIME type
     * ex: 'pdf', 'image'
     */
    public static function getCategory(?string $mime): string
    {
        if (empty($mime)) {
            return 'unknown';
        }

        return match (true) {
            Str::startsWith($mime, 'image/') => 'image',
            $mime === 'application/pdf' => 'pdf',
            Str::contains($mime, 'word') => 'word',
            Str::contains($mime, 'excel') || Str::contains($mime, 'spreadsheet') => 'excel',
            Str::contains($mime, 'powerpoint') || Str::contains($mime, 'presentation') => 'powerpoint',
            Str::contains($mime, 'zip') || Str::contains($mime, 'archive') ||
            Str::contains($mime, 'compressed') || Str::contains($mime, 'tar') ||
            Str::contains($mime, 'gzip') || Str::contains($mime, 'rar') => 'archive',
            Str::startsWith($mime, 'text/x-') ||
            $mime === 'application/json' ||
            Str::contains($mime, 'xml') ||
            $mime === 'text/javascript' => 'code',
            Str::startsWith($mime, 'video/') => 'video',
            Str::startsWith($mime, 'audio/') => 'audio',
            Str::contains($mime, 'autocad') || Str::contains($mime, 'dwg') || Str::contains($mime, 'dxf') => 'cad',
            Str::startsWith($mime, 'text/') => 'text',
            default => 'other',
        };
    }

    /**
     * Get comprehensive file info array
     */
    public static function getInfo(?string $mime): array
    {
        return [
            'icon' => self::getIcon($mime),
            'color' => self::getColor($mime),
            'category' => self::getCategory($mime),
        ];
    }
}
