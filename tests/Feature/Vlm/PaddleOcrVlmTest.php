<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;

class PaddleOcrVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'paddleocr';
        $this->checkExpectedModel();
    }

    public function test_health_check(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");

        $response->throw();
        $this->assertEquals(200, $response->status());

        $data = $response->json();
        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('paddleocr', $data['model']);
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $data = $this->extractStructured('invoice_simple.pdf');

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('paddleocr', $data['model']);
        $this->assertNotEmpty($data['html']);
        $this->assertNotEmpty($data['markdown']);

        // 日本語が含まれていることを確認（HTMLまたはMarkdownのいずれかに）
        $hasJapanese = str_contains($data['html'], '請求') ||
                       str_contains($data['markdown'], '請求');
        $this->assertTrue($hasJapanese, 'Output should contain Japanese text');
    }

    public function test_extract_structured_from_handwriting_image(): void
    {
        $data = $this->extractStructured('hand_writing_01.png');

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('paddleocr', $data['model']);
        $this->assertNotEmpty($data['html']);
    }

    public function test_extract_structured_handles_invalid_file(): void
    {
        $response = Http::attach(
            'file',
            'invalid file content',
            'invalid.txt'
        )->post("{$this->vlmBaseUrl}/extract/structured");

        // 500エラーが返ることを確認（OCR処理エラー）
        $this->assertEquals(500, $response->status());
    }

    public function test_processing_time_is_reasonable(): void
    {
        $this->assertTestFileExists('hand_writing_01.png');
        $testFile = $this->getTestFilePath('hand_writing_01.png');

        $startTime = microtime(true);

        $response = Http::timeout(120)
            ->attach(
                'file',
                file_get_contents($testFile),
                'hand_writing_01.png'
            )->post("{$this->vlmBaseUrl}/extract/structured");

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $response->throw();

        $data = $response->json();

        // 処理時間が2分以内であることを確認
        $this->assertLessThan(120, $totalTime);

        // レスポンスの処理時間と実測値が近いことを確認（±10秒の誤差を許容）
        $this->assertEqualsWithDelta($data['processing_time_s'], $totalTime, 10);
    }
}
