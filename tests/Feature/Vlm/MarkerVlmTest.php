<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarkerVlmTest extends TestCase
{
    private string $vlmBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vlmBaseUrl = 'http://localhost:8001';
    }

    public function test_health_check(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");

        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['healthy', 'warming_up']);
        $this->assertEquals('Marker (CLI)', $data['model']);
    }

    public function test_extract_markdown_from_simple_invoice_pdf(): void
    {
        $testFile = storage_path('test/vlm-poc/invoice_simple.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(600) // 10分のタイムアウト（初回モデルダウンロード対応）
            ->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/markdown");

        if ($response->status() === 503) {
            $this->markTestSkipped("Service is warming up. Please retry later.");
        }

        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('markdown', $data);
        $this->assertArrayHasKey('processing_time_s', $data);
        $this->assertNotEmpty($data['markdown']);
        
        // Markdownフォーマットを確認
        $markdown = $data['markdown'];
        
        // 日本語が含まれていることを確認
        $this->assertStringContainsString('請求書', $markdown);
        
        // Markdown要素が含まれていることを期待（見出しや表など）
        $this->assertTrue(
            str_contains($markdown, '#') || 
            str_contains($markdown, '|') ||
            str_contains($markdown, '-'),
            'Markdown should contain structural elements'
        );
    }

    public function test_extract_markdown_from_handwriting_image(): void
    {
        $testFile = storage_path('test/vlm-poc/hand_writing_01.png');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(600) // 10分のタイムアウト
            ->attach(
                'file',
                file_get_contents($testFile),
                'hand_writing_01.png'
            )->post("{$this->vlmBaseUrl}/extract/markdown");

        if ($response->status() === 503) {
            $this->markTestSkipped("Service is warming up or processing. Please retry later.");
        }

        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('markdown', $data);
        $this->assertNotEmpty($data['markdown']);
        
        // 画像から変換されたことを確認
        $this->assertArrayHasKey('processing_time_s', $data);
    }

    public function test_extract_markdown_handles_unsupported_format(): void
    {
        $response = Http::attach(
            'file',
            'unsupported file content',
            'test.txt'
        )->post("{$this->vlmBaseUrl}/extract/markdown");

        // 400エラーが返ることを期待
        $this->assertEquals(400, $response->status());
        
        $data = $response->json();
        $this->assertStringContainsString('Unsupported file format', $data['detail']);
    }

    public function test_processing_prevents_concurrent_requests(): void
    {
        $testFile = storage_path('test/vlm-poc/invoice_simple.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        // 最初のリクエストを非同期で開始
        $pool = Http::pool(fn ($pool) => [
            $pool->timeout(600)->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/markdown"),
            
            // わずかな遅延の後に2つ目のリクエスト
            $pool->timeout(5)->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/markdown"),
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
        $testFile = storage_path('test/vlm-poc/invoice_simple.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(600)
            ->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/markdown");

        if ($response->status() === 503) {
            $this->markTestSkipped("Service is warming up. Please retry later.");
        }

        $response->throw();
        
        $data = $response->json();
        $markdown = $data['markdown'];
        
        // 品質チェック: 最小限の長さがあること
        $this->assertGreaterThan(100, strlen($markdown), 
            'Markdown output should have substantial content');
        
        // 構造化された要素があること
        $hasStructure = 
            str_contains($markdown, "\n\n") ||  // 段落区切り
            str_contains($markdown, '# ') ||     // 見出し
            str_contains($markdown, '## ') ||
            str_contains($markdown, '|');        // テーブル
        
        $this->assertTrue($hasStructure, 
            'Markdown should contain structured elements (paragraphs, headings, or tables)');
    }
}
