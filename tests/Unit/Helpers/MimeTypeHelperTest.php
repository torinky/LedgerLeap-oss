<?php

namespace Tests\Unit\Helpers;

use App\Helpers\MimeTypeHelper;
use PHPUnit\Framework\TestCase;

class MimeTypeHelperTest extends TestCase
{
    public function test_get_icon_returns_correct_icon_class()
    {
        // Image
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/jpeg'));
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('image/png'));

        // PDF
        $this->assertEquals('fa-solid fa-file-pdf', MimeTypeHelper::getIcon('application/pdf'));

        // Office
        $this->assertEquals('fa-solid fa-file-word', MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertEquals('fa-solid fa-file-excel', MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        $this->assertEquals('fa-solid fa-file-powerpoint', MimeTypeHelper::getIcon('application/vnd.openxmlformats-officedocument.presentationml.presentation'));

        // Archive
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/zip'));
        $this->assertEquals('fa-solid fa-file-zipper', MimeTypeHelper::getIcon('application/x-tar'));

        // Code
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('application/json'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('text/javascript'));
        $this->assertEquals('fa-solid fa-file-code', MimeTypeHelper::getIcon('application/xml'));

        // Media
        $this->assertEquals('fa-solid fa-file-video', MimeTypeHelper::getIcon('video/mp4'));
        $this->assertEquals('fa-solid fa-file-audio', MimeTypeHelper::getIcon('audio/mpeg'));

        // Text
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/plain'));
        $this->assertEquals('fa-solid fa-file-lines', MimeTypeHelper::getIcon('text/csv'));

        // CAD
        $this->assertEquals('fa-solid fa-file-image', MimeTypeHelper::getIcon('application/x-autocad'));

        // Default / Null
        $this->assertEquals('fa-solid fa-file', MimeTypeHelper::getIcon('application/octet-stream'));
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
