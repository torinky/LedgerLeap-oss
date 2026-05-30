<?php

namespace Tests\Feature\Livewire\AttachedFile;

use App\Livewire\AttachedFile\TextPreviewModal;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class TextPreviewModalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = Tenant::create(['id' => 'test-'.uniqid()]);
    }

    #[Test]
    public function it_opens_modal_with_vlm_text()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test VLM Content',
            'vlm_confidence' => 0.85,
            'contain_content' => true,
        ]);

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->assertSet('file.id', $file->id)
            ->assertSee('Test VLM Content');
    }

    #[Test]
    public function it_handles_file_not_found()
    {
        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: 99999)
            ->assertSet('showModal', false)
            ->assertDispatched('test-mary-toast-warning');
    }

    #[Test]
    public function it_handles_file_without_previewable_text()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'processing_finalized_at' => null, // 未完了
            'finalized_source' => null,
        ]);

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', false)
            ->assertDispatched('test-mary-toast-warning');
    }

    #[Test]
    public function it_closes_modal_and_resets_state()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test Content',
            'contain_content' => true,
        ]);

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('file', null)
            ->assertSet('badgeInfo', null)
            ->assertSet('previewText', null)
            ->assertSet('tenantId', null);
    }

    #[Test]
    public function it_displays_correct_badge_for_ocr_source()
    {
        $hashedName = 'abc123def456.jpg'; // ハッシュ化されたファイル名

        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'type' => 'text', 'name' => 'Test', 'order' => 1],
                ['id' => 1, 'type' => 'files', 'name' => 'Files', 'order' => 2],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'test', 1 => [$hashedName => 'original-test.jpg']],
            'content_attached' => [
                0 => [], // Column 0 must exist for column 1 index
                1 => [
                    $hashedName => [
                        'meta' => [
                            'content' => 'OCR extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'original-test.jpg',
            'hashedbasename' => $hashedName,
            'processing_finalized_at' => now(),
            'finalized_source' => 'ocr',
            'contain_content' => true,
        ]);

        // ledgerリレーションをロード（hasPreviewableText用）
        $file->load('ledger');

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->assertSet('badgeInfo.color', 'warning');
    }

    #[Test]
    public function it_truncates_large_text()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        // 500KB以上のテキストを作成
        $largeText = str_repeat('A very long text content. ', 30000);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => $largeText,
            'contain_content' => true,
        ]);

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->assertSet('isTruncated', true)
            ->assertSee('truncated');
    }

    #[Test]
    public function it_dispatches_copy_success_notification()
    {
        Livewire::test(TextPreviewModal::class)
            ->call('notifyCopySuccess')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_dispatches_copy_failed_notification()
    {
        Livewire::test(TextPreviewModal::class)
            ->call('notifyCopyFailed')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function it_generates_correct_download_urls_for_vlm_files()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# VLM Content',
            'contain_content' => true,
        ]);

        $expectedMarkdownUrl = route('file.download-vlm', [
            'tenant' => $file->tenant_id,
            'attachedFile' => $file->id,
            'format' => 'markdown',
        ]);

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->assertSee($expectedMarkdownUrl);
    }

    #[Test]
    public function it_displays_correct_buttons_for_non_vlm_files()
    {
        $hashedName = 'test123.jpg';
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 0, 'type' => 'text', 'name' => 'Test', 'order' => 1],
                ['id' => 1, 'type' => 'files', 'name' => 'Files', 'order' => 2],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [0 => 'test', 1 => [$hashedName => 'original.jpg']],
            'content_attached' => [
                0 => [],
                1 => [
                    'test123.pdf' => [ // OCR後のキー
                        'meta' => [
                            'content' => 'OCR text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'original.jpg',
            'hashedbasename' => $hashedName,
            'processing_finalized_at' => now(),
            'finalized_source' => 'ocr',
            'contain_content' => true,
        ]);

        // ledgerリレーションをロード
        $file->load('ledger');

        Livewire::test(TextPreviewModal::class)
            ->dispatch('showTextPreview', attachedFileId: $file->id)
            ->assertSet('showModal', true)
            ->assertSee('OCR text'); // コンテンツは表示される
    }
}
