<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;

class MinerUVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'mineru';
        $this->checkExpectedModel();
    }

    public function test_health_check(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");

        $response->throw();
        $this->assertEquals(200, $response->status());

        $data = $response->json();
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('mineru', $data['model']);
        $this->assertEquals('cpu', $data['device']);
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $data = $this->extractStructured('invoice_simple.pdf', 300);

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('mineru', $data['model']);
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
        $data = $this->extractStructured('meeting_notes.pdf', 300);

        $this->assertUnifiedApiResponse($data);
        $this->assertEquals('mineru', $data['model']);
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
        $data = $this->extractStructured('invoice_simple.pdf', 300);

        $markdown = $data['markdown'];

        // 品質チェック: 最小限の長さがあること
        $this->assertMarkdownQuality($markdown);

        // 構造化された要素があること
        $hasStructure =
            str_contains($markdown, "\n\n") ||
            str_contains($markdown, '# ') ||
            str_contains($markdown, '## ') ||
            str_contains($markdown, '<table>');

        $this->assertTrue($hasStructure,
            'Markdown should contain structured elements');

        // 処理時間が妥当な範囲（CPU環境で60秒以内）
        $this->assertLessThan(60, $data['processing_time_s'],
            'Processing should complete within 60 seconds on CPU');
    }

    public function test_device_is_cpu(): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");
        $data = $response->json();

        // CPU環境で動作していることを確認
        $this->assertEquals('cpu', $data['device']);
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
