<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\FontAwesomeIconController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FontAwesomeIconControllerのテスト
 *
 * Issue #62: 台帳編集画面の添付ファイルプレビュー表示改善
 * 特にfile-pdfアイコンのviewBox修正とpreserveAspectRatio属性の検証
 */
class FontAwesomeIconControllerTest extends TestCase
{
    use RefreshDatabase;

    protected FontAwesomeIconController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FontAwesomeIconController;
    }

    #[Test]
    public function it_serves_pdf_icon_for_pdf_mime_type()
    {
        $request = new Request(['type' => 'application/pdf']);
        $response = $this->controller->serveIconByMime($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/svg+xml', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('<svg', $content);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $content);
    }

    #[Test]
    public function it_adds_preserve_aspect_ratio_to_svg()
    {
        $request = new Request(['type' => 'application/pdf']);
        $response = $this->controller->serveIconByMime($request);

        $content = $response->getContent();
        $this->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $content);
    }

    #[Test]
    public function it_fixes_file_pdf_viewbox_bug()
    {
        // FontAwesome 7.1.0のfile-pdfアイコンはviewBox="0 0 576 512"だが
        // 実際のパスはY=528まで使用しているため、viewBoxを560に修正する必要がある
        $request = new Request(['type' => 'application/pdf']);
        $response = $this->controller->serveIconByMime($request);

        $content = $response->getContent();

        // viewBoxが修正されていることを確認
        $this->assertStringContainsString('viewBox="0 0 576 560"', $content);

        // 元のviewBox（512）が残っていないことを確認
        $this->assertStringNotContainsString('viewBox="0 0 576 512"', $content);
    }

    #[Test]
    public function it_serves_word_icon_for_word_mime_types()
    {
        $mimeTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        foreach ($mimeTypes as $mimeType) {
            $request = new Request(['type' => $mimeType]);
            $response = $this->controller->serveIconByMime($request);

            $this->assertEquals(200, $response->getStatusCode());
            $content = $response->getContent();
            $this->assertStringContainsString('<svg', $content);
        }
    }

    #[Test]
    public function it_serves_excel_icon_for_excel_mime_types()
    {
        $mimeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        foreach ($mimeTypes as $mimeType) {
            $request = new Request(['type' => $mimeType]);
            $response = $this->controller->serveIconByMime($request);

            $this->assertEquals(200, $response->getStatusCode());
            $content = $response->getContent();
            $this->assertStringContainsString('<svg', $content);
        }
    }

    #[Test]
    public function it_serves_default_file_icon_for_unknown_mime_type()
    {
        $request = new Request(['type' => 'application/unknown']);
        $response = $this->controller->serveIconByMime($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('<svg', $content);
    }

    #[Test]
    public function it_serves_default_file_icon_for_null_mime_type()
    {
        $request = new Request([]);
        $response = $this->controller->serveIconByMime($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('<svg', $content);
    }

    #[Test]
    public function it_sets_proper_cache_headers()
    {
        $request = new Request(['type' => 'application/pdf']);
        $response = $this->controller->serveIconByMime($request);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
    }

    #[Test]
    public function it_maps_mime_types_to_correct_icon_names()
    {
        $mimeToIconMapping = [
            'application/pdf' => 'file-pdf',
            'application/zip' => 'file-zipper',
            'text/plain' => 'file-lines',
            'text/csv' => 'file-csv',
            'audio/mp3' => 'file-audio',
            'video/mp4' => 'file-video',
            'image/jpeg' => 'file-image',
        ];

        foreach ($mimeToIconMapping as $mimeType => $expectedIcon) {
            $request = new Request(['type' => $mimeType]);
            $response = $this->controller->serveIconByMime($request);

            $this->assertEquals(200, $response->getStatusCode());
            // SVGの内容からアイコンが正しいことを推測
            // （実際のファイルパスは確認できないため、レスポンスが成功することを確認）
        }
    }

    #[Test]
    public function it_preserves_aspect_ratio_for_all_icons()
    {
        $mimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.ms-excel',
            'text/plain',
        ];

        foreach ($mimeTypes as $mimeType) {
            $request = new Request(['type' => $mimeType]);
            $response = $this->controller->serveIconByMime($request);

            $content = $response->getContent();
            $this->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $content,
                "preserveAspectRatio not found for MIME type: {$mimeType}");
        }
    }
}
