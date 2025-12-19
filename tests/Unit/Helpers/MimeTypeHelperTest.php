<?php

namespace Tests\Unit\Helpers;

use App\Helpers\MimeTypeHelper;
use PHPUnit\Framework\TestCase;

class MimeTypeHelperTest extends TestCase
{
    public function test_get_icon_returns_correct_icon_class()
    {
        // Images (7種類)
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/jpeg'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/png'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/gif'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/webp'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/svg+xml'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/bmp'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/tiff'));

        // PDF
        $this->assertEquals('fa-solid fa-file-pdf', MimeTypeHelper::getIcon('application/pdf'));

        // Office - Word (2種類)
        $this->assertEquals(
            'fa-solid fa-file-word',
            MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        );
        $this->assertEquals('fa-solid fa-file-word', MimeTypeHelper::getIcon('application/msword'));

        // Office - Excel (2種類)
        $this->assertEquals(
            'fa-solid fa-file-excel',
            MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        );
        $this->assertEquals('fa-solid fa-file-excel', MimeTypeHelper::getIcon('application/vnd.ms-excel'));

        // Office - PowerPoint (2種類)
        $this->assertEquals(
            'fa-solid fa-file-powerpoint',
            MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.presentationml.presentation')
        );
        $this->assertEquals('fa-solid fa-file-powerpoint', MimeTypeHelper::getIcon('application/vnd.ms-powerpoint'));

        // Archives (5種類)
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/zip'));
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/x-7z-compressed'));
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/x-tar'));
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/gzip'));
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/x-rar-compressed'));

        // Text (4種類)
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/plain'));
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/csv'));
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/markdown'));
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/html'));

        // Code (8種類)
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/x-php'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/x-python'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/javascript'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('application/json'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/x-java'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/x-c'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/x-ruby'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('application/xml'));

        // Video (5種類)
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/mp4'));
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/quicktime'));
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/x-msvideo'));
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/webm'));
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/x-matroska'));

        // Audio (4種類)
        $this->assertEquals('fa-solid fa-file-audio', MimeTypeHelper::getIcon('audio/mpeg'));
        $this->assertEquals('fa-solid fa-file-audio', MimeTypeHelper::getIcon('audio/wav'));
        $this->assertEquals('fa-solid fa-file-audio', MimeTypeHelper::getIcon('audio/ogg'));
        $this->assertEquals('fa-solid fa-file-audio', MimeTypeHelper::getIcon('audio/flac'));

        // CAD (3種類)
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('application/x-autocad'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/vnd.dwg'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/vnd.dxf'));

        // Others (2種類)
        $this->assertEquals('fa-solid fa-file', MimeTypeHelper::getIcon('application/octet-stream'));
        $this->assertEquals('fa-solid fa-file', MimeTypeHelper::getIcon('application/rtf'));

        // Null/Empty
        $this->assertEquals('fa-solid fa-file text-gray-400', MimeTypeHelper::getIcon(null));
        $this->assertEquals('fa-solid fa-file text-gray-400', MimeTypeHelper::getIcon(''));
    }

    public function test_get_color_returns_correct_tailwind_class()
    {
        $this->assertEquals('text-blue-500', MimeTypeHelper::getColor('image/jpeg'));
        $this->assertEquals('text-red-500', MimeTypeHelper::getColor('application/pdf'));
        $this->assertEquals('text-green-600', MimeTypeHelper::getColor('application/vnd.ms-excel'));
        $this->assertEquals('text-gray-400', MimeTypeHelper::getColor(null));
    }

    public function test_get_category_returns_correct_category_string()
    {
        $this->assertEquals('image', MimeTypeHelper::getCategory('image/gif'));
        $this->assertEquals('pdf', MimeTypeHelper::getCategory('application/pdf'));
        $this->assertEquals('word', MimeTypeHelper::getCategory('application/msword'));
        $this->assertEquals('code', MimeTypeHelper::getCategory('application/json'));
        $this->assertEquals('unknown', MimeTypeHelper::getCategory(null));
    }

    public function test_get_info_returns_array_with_all_properties()
    {
        $info = MimeTypeHelper::getInfo('application/pdf');

        $this->assertIsArray($info);
        $this->assertArrayHasKey('icon', $info);
        $this->assertArrayHasKey('color', $info);
        $this->assertArrayHasKey('category', $info);

        $this->assertEquals('fa-solid fa-file-pdf', $info['icon']);
        $this->assertEquals('text-red-500', $info['color']);
        $this->assertEquals('pdf', $info['category']);
    }
}
