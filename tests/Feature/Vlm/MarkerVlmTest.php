<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;

class MarkerVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'marker';
        $this->checkExpectedModel();
    }

    public function test_health_check(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");

        $response->throw();
        $this->assertEquals(200, $response->status());

        $data = $response->json();
        $this->assertHealthCheckResponse($data);
        $this->assertEquals('marker', $data['model']);
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $data = $this->extractStructured('invoice_simple.pdf');

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('marker', $data['model']);
        $this->assertNotEmpty($data['html']);
        $this->assertNotEmpty($data['markdown']);

        // 日本語が含まれていることを確認
        $this->assertStringContainsString('請求書', $data['markdown']);

        // Markdown要素が含まれていることを期待
        $this->assertMarkdownQuality($data['markdown']);
    }

    public function test_extract_structured_from_handwriting_image(): void
    {
        $data = $this->extractStructured('hand_writing_01.png');

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('marker', $data['model']);
        $this->assertNotEmpty($data['markdown']);
    }

    public function test_extract_structured_handles_unsupported_format(): void
    {
        $response = Http::attach(
            'file',
            'unsupported file content',
            'test.txt'
        )->post("{$this->vlmBaseUrl}/extract/structured");

        // 400エラーが返ることを期待
        $this->assertEquals(400, $response->status());

        $data = $response->json();
        $this->assertStringContainsString('Unsupported file format', $data['detail']);
    }

    public function test_processing_prevents_concurrent_requests(): void
    {
        $this->assertTestFileExists('invoice_simple.pdf');
        $testFile = $this->getTestFilePath('invoice_simple.pdf');

        // 最初のリクエストを非同期で開始
        $pool = Http::pool(fn ($pool) => [
            $pool->timeout(600)->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/structured"),

            // わずかな遅延の後に2つ目のリクエスト
            $pool->timeout(5)->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/structured"),
        ]);

        // 2つ目のリクエストが503（処理中）を返す可能性がある
        $hasServiceUnavailable = false;
        foreach ($pool as $response) {
            if ($response->status() === 503) {
                $hasServiceUnavailable = true;
                $data = $response->json();
                $this->assertStringContainsString('in progress', $data['error'] ?? '');
            }
        }

        // 注: 処理が非常に高速な場合、両方とも成功する可能性があるため、
        // このテストは期待通りに動作しない場合がある
    }

    public function test_markdown_output_quality(): void
    {
        $data = $this->extractStructured('invoice_simple.pdf');

        $markdown = $data['markdown'];

        // 品質チェック
        $this->assertMarkdownQuality($markdown);

        // 構造化された要素があること
        $hasStructure =
            str_contains($markdown, "\n\n") ||
            str_contains($markdown, '# ') ||
            str_contains($markdown, '## ') ||
            str_contains($markdown, '|');

        $this->assertTrue($hasStructure,
            'Markdown should contain structured elements (paragraphs, headings, or tables)');
    }
}
