<?php

namespace Tests\Feature\Models;

use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachedFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを初期化
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
    }

    private function createFinalizedAttachment(
        string $source,
        ?float $vlmConfidence = null
    ): AttachedFile {
        $ledger = Ledger::factory()->create([
            'content' => [
                0 => [],  // カラムID 0（空）
                1 => ['hashed123' => 'test.pdf'],  // カラムID 1
            ],
            'content_attached' => [
                0 => [],  // カラムID 0（空）
                1 => [    // カラムID 1
                    'test.pdf' => [
                        'meta' => ['content' => 'OCR extracted text'],
                    ],
                ],
            ],
        ]);

        return AttachedFile::factory()
            ->forLedger($ledger)
            ->create([
                'column_id' => 1,
                'filename' => 'test.pdf',
                'hashedbasename' => 'hashed123',
                'finalized_source' => $source,
                'processing_finalized_at' => now(),
                'vlm_markdown' => $source === 'vlm' ? '# VLM Result' : null,
                'vlm_confidence' => $vlmConfidence,
            ]);
    }

    public function test_previewable_text_returns_vlm_markdown()
    {
        $attachment = $this->createFinalizedAttachment('vlm', 0.95);

        $this->assertTrue($attachment->hasPreviewableText());
        $this->assertEquals('# VLM Result', $attachment->getPreviewableText());
    }

    public function test_previewable_text_returns_ocr_text_with_code_block()
    {
        $attachment = $this->createFinalizedAttachment('ocr');
        
        // DBから再取得してリレーションをEager Load
        $attachment = AttachedFile::with('ledger')->find($attachment->id);

        $this->assertTrue($attachment->hasPreviewableText());
        $this->assertStringContainsString('```', $attachment->getPreviewableText());
        $this->assertStringContainsString('OCR extracted text', $attachment->getPreviewableText());
    }

    public function test_previewable_text_returns_null_without_eager_loading()
    {
        $attachment = $this->createFinalizedAttachment('ocr');
        // ledgerリレーションを読み込まない

        // hasPreviewableTextはfalseを返す（リレーションが読み込まれていないため）
        $this->assertFalse($attachment->hasPreviewableText());
        $this->assertNull($attachment->getPreviewableText());
    }

    public function test_has_previewable_text_returns_false_before_finalization()
    {
        $attachment = AttachedFile::factory()->create([
            'processing_finalized_at' => null,
        ]);

        $this->assertFalse($attachment->hasPreviewableText());
    }

    public function test_confidence_badge_info_for_high_quality_vlm()
    {
        $attachment = $this->createFinalizedAttachment('vlm', 0.95);

        $badge = $attachment->getConfidenceBadgeInfo();

        $this->assertEquals(__('ledger.vlm.source.vlm'), $badge['label']);
        $this->assertEquals('success', $badge['color']);
        $this->assertEquals('95.0%', $badge['score']);
    }

    public function test_confidence_badge_info_for_low_quality_vlm()
    {
        $attachment = $this->createFinalizedAttachment('vlm', 0.45);

        $badge = $attachment->getConfidenceBadgeInfo();

        $this->assertEquals('error', $badge['color']);
    }

    public function test_confidence_badge_info_for_ocr()
    {
        $attachment = $this->createFinalizedAttachment('ocr');

        $badge = $attachment->getConfidenceBadgeInfo();

        $this->assertEquals(__('ledger.vlm.source.ocr'), $badge['label']);
        $this->assertEquals('warning', $badge['color']);
        $this->assertNull($badge['score']);
    }
}
