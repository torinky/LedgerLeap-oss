<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MinerUVlmTest extends TestCase
{
    private string $vlmBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        // Docker内部ネットワークではサービス名でアクセス
        $this->vlmBaseUrl = env('MINERU_URL', 'http://vlm:8000');
    }

    public function test_health_check(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");

        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('MinerU', $data['model']);
        $this->assertEquals('CPU', $data['backend']);
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $testFile = base_path('tests/fixtures/files/invoice_simple.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(120) // 2分のタイムアウト（CPU処理）
            ->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/structured");

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
        $hasMarkdownElements = 
            str_contains($markdown, '#') || 
            str_contains($markdown, '<table>') ||
            str_contains($markdown, '|');
        
        $this->assertTrue(
            $hasMarkdownElements,
            'Markdown should contain structural elements'
        );
        
        // HTMLテーブルが含まれていることを確認（MinerUの特徴）
        $this->assertStringContainsString('<table>', $markdown);
    }

    public function test_extract_structured_from_meeting_notes_pdf(): void
    {
        $testFile = base_path('tests/fixtures/files/meeting_notes.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(120)
            ->attach(
                'file',
                file_get_contents($testFile),
                'meeting_notes.pdf'
            )->post("{$this->vlmBaseUrl}/extract/structured");

        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('markdown', $data);
        $this->assertNotEmpty($data['markdown']);
    }

    public function test_extract_structured_handles_unsupported_format(): void
    {
        $response = Http::attach(
            'file',
            'unsupported file content',
            'test.txt'
        )->post("{$this->vlmBaseUrl}/extract/structured");

        // MinerUはPDFのみサポート
        $this->assertContains($response->status(), [400, 500]);
    }

    public function test_markdown_output_quality(): void
    {
        $testFile = base_path('tests/fixtures/files/invoice_simple.pdf');
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }

        $response = Http::timeout(120)
            ->attach(
                'file',
                file_get_contents($testFile),
                'invoice_simple.pdf'
            )->post("{$this->vlmBaseUrl}/extract/structured");

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
            str_contains($markdown, '<table>');  // テーブル
        
        $this->assertTrue($hasStructure, 
            'Markdown should contain structured elements');
        
        // 処理時間が妥当な範囲（CPU環境で60秒以内）
        $this->assertLessThan(60, $data['processing_time_s'],
            'Processing should complete within 60 seconds on CPU');
    }

    public function test_backend_is_cpu(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");
        $data = $response->json();
        
        // CPU環境で動作していることを確認
        $this->assertEquals('CPU', $data['backend']);
    }

    public function test_large_pdf_processing(): void
    {
        $this->markTestIncomplete('Large PDF test needs implementation with actual large file');
        
        // 大きなPDFファイルの処理テスト（将来実装）
        // - 処理時間の妥当性
        // - メモリ使用量
        // - エラーハンドリング
    }
}
