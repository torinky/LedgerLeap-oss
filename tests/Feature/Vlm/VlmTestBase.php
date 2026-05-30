<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * VLM実コンテナ（vlm:8000）への接続が必要なテスト群の基底クラス。
 * CI環境ではコンテナが存在しないため `external` グループとして除外する。
 *
 * ローカルでVLMコンテナを起動した状態で実行する場合:
 *   ./vendor/bin/pest --group=external
 */
#[Group('external')]
abstract class VlmTestBase extends TestCase
{
    protected string $vlmBaseUrl;

    protected string $expectedModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vlmBaseUrl = 'http://vlm:8000';

        // Dockerコンテナが起動していない場合はスキップ
        try {
            $response = Http::timeout(5)->get($this->vlmBaseUrl.'/health');
            if (! $response->successful()) {
                $this->markTestSkipped('VLM service is not healthy');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('VLM service is not available: '.$e->getMessage());
        }
    }

    protected function getTestFilePath(string $filename): string
    {
        return base_path("tests/fixtures/files/{$filename}");
    }

    protected function assertTestFileExists(string $filename): void
    {
        $testFile = $this->getTestFilePath($filename);
        if (! file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }
    }

    protected function assertHealthCheckResponse(array $data): void
    {
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['healthy', 'warming_up']);
        $this->assertArrayHasKey('model', $data);
        $this->assertArrayHasKey('device', $data);
    }

    protected function assertUnifiedApiResponse(array $data): void
    {
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('html', $data);
        $this->assertArrayHasKey('markdown', $data);
        $this->assertArrayHasKey('structured_data', $data);
        $this->assertArrayHasKey('processing_time_s', $data);
        $this->assertArrayHasKey('model', $data);
        $this->assertArrayHasKey('device', $data);

        // Assert confidence field exists (may be null for some models like marker/mineru)
        $this->assertArrayHasKey('confidence', $data);

        // For PaddleOCR, confidence should be a valid float between 0 and 1
        if ($data['model'] === 'paddleocr' && $data['confidence'] !== null) {
            $this->assertIsFloat($data['confidence']);
            $this->assertGreaterThanOrEqual(0.0, $data['confidence']);
            $this->assertLessThanOrEqual(1.0, $data['confidence']);
        }
    }

    protected function assertStructuredDataFormat(array $structuredData): void
    {
        $this->assertArrayHasKey('pages', $structuredData);
        $this->assertArrayHasKey('text_blocks', $structuredData);
        $this->assertIsArray($structuredData['pages']);
        $this->assertIsArray($structuredData['text_blocks']);
    }

    protected function assertMarkdownQuality(string $markdown, int $minLength = 100): void
    {
        $this->assertGreaterThan($minLength, strlen($markdown),
            'Markdown output should have substantial content');

        $hasStructure =
            str_contains($markdown, "\n\n") ||
            str_contains($markdown, '# ') ||
            str_contains($markdown, '## ') ||
            str_contains($markdown, '|') ||
            str_contains($markdown, '<table>');

        $this->assertTrue($hasStructure,
            'Markdown should contain structured elements');
    }

    protected function extractStructured(string $filename, int $timeout = 240): array
    {
        $this->assertTestFileExists($filename);
        $testFile = $this->getTestFilePath($filename);

        $response = Http::timeout($timeout)
            ->attach(
                'file',
                file_get_contents($testFile),
                $filename
            )->post("{$this->vlmBaseUrl}/extract/structured");

        if ($response->status() === 503) {
            $this->markTestSkipped('Service is warming up or processing. Please retry later.');
        }

        $response->throw();

        return $response->json();
    }

    protected function checkExpectedModel(): void
    {
        if (! isset($this->expectedModel)) {
            return;
        }

        $response = Http::get("{$this->vlmBaseUrl}/health");
        $data = $response->json();

        if ($data['model'] !== $this->expectedModel) {
            $this->markTestSkipped(
                "Expected model '{$this->expectedModel}' but got '{$data['model']}'. ".
                "Run: ./bin/vlm-switch.sh {$this->expectedModel}"
            );
        }
    }
}
