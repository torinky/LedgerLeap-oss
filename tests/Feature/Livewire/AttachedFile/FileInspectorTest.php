<?php

namespace Tests\Feature\Livewire\AttachedFile;

use App\Jobs\Ledger\RetryVlmProcessingJob;
use App\Livewire\AttachedFile\FileInspector;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FileInspectorTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefreshDatabaseWithTenant();

        // テナント初期化（RefreshDatabaseWithTenantがstatic::$sharedTenantを作成・初期化している）
        $this->tenant = static::$sharedTenant;
        tenancy()->initialize($this->tenant);

        // テストユーザー作成とログイン
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $define = LedgerDefine::factory()->create();
        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'creator_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_opens_inspector_and_loads_mock_data()
    {
        config(['mock.attachment.enabled' => true]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => 10001])
            ->assertSet('open', true)
            ->assertSet('isLoading', false)
            ->assertSet('fileId', 10001)
            ->assertSee('領収書_2025-12-01.jpg');
    }

    #[Test]
    public function it_opens_inspector_and_loads_real_data()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'real_data_test.pdf',
            'mime' => 'application/pdf',
            'status' => \App\Enums\AttachedFileStatus::COMPLETED->value,
            'finalized_source' => 'tika',
            'processing_finalized_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Gate::before で一括許可（Policyをバイパス）
        Gate::before(function ($user, $ability) {
            return true;
        });

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('fileId', $file->id)
            ->assertSet('open', true)
            ->assertSet('isLoading', false)
            ->assertSee('real_data_test.pdf');
    }

    #[Test]
    public function it_shows_error_when_file_not_found()
    {
        config(['mock.attachment.enabled' => false]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => 99999])
            ->assertSet('open', false)
            ->assertSet('isLoading', false)
            ->assertDispatched('mary-toast');
    }

    #[Test]
    public function it_handles_permission_restriction()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Gate::before で一括拒否
        Gate::before(function ($user, $ability) {
            return false;
        });

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', false)
            ->assertSet('file', null);
    }

    #[Test]
    public function it_generates_preview_url_for_image_file()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'test_image.jpg',
            'mime' => 'image/jpeg',
            'original_mime_type' => 'image/jpeg',
            'path' => 'attachments/test_image.jpg',
            'status' => \App\Enums\AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        // computed properties をテスト
        $this->assertTrue($component->get('isImage'));
        $this->assertFalse($component->get('isPdf'));
        $this->assertTrue($component->get('showPreview'));
        $this->assertStringContainsString('storage/attachments/test_image.jpg', $component->get('previewUrl'));
    }

    #[Test]
    public function it_generates_preview_url_for_pdf_file()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'test_document.pdf',
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => 'attachments/test_document.pdf',
            'status' => \App\Enums\AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        // computed properties をテスト
        $this->assertFalse($component->get('isImage'));
        $this->assertTrue($component->get('isPdf'));
        $this->assertTrue($component->get('showPreview'));
        $this->assertStringContainsString('storage/attachments/test_document.pdf', $component->get('previewUrl'));
    }

    #[Test]
    public function it_does_not_show_preview_for_non_previewable_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'test_document.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'path' => 'attachments/test_document.docx',
            'status' => \App\Enums\AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        // computed properties をテスト
        $this->assertFalse($component->get('isImage'));
        $this->assertFalse($component->get('isPdf'));
        $this->assertFalse($component->get('showPreview'));
    }

    #[Test]
    public function it_calculates_user_permissions_correctly()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn($user, $ability) => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $permissions = $component->get('userPermissions');
        $this->assertTrue($permissions['read']);
        $this->assertTrue($permissions['write']);
        $this->assertTrue($permissions['download']);
        $this->assertTrue($permissions['is_admin']);
    }

    #[Test]
    public function it_shows_permissions_tab_content()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn($user, $ability) => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'permissions')
            ->assertSee(__('file.inspector.permissions.summary_title'))
            ->assertSee(__('file.inspector.actions.title'))
            ->assertSee($this->user->name);
    }

    #[Test]
    public function it_dispatches_process_attached_file_on_retry_processing()
    {
        config(['mock.attachment.enabled' => false]);
        Bus::fake();

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'vlm_failed_at' => now(), // Both VLM and OCR failed
            'ocr_failed_at' => now(), 
            'contain_content' => false,
        ]);

        Gate::before(fn($user, $ability) => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->call('retryProcessing')
            ->assertDispatched('mary-toast');

        Bus::assertDispatched(\App\Jobs\Ledger\ProcessAttachedFile::class, function ($job) use ($file) {
            return $job->attachedFile->id === $file->id;
        });
    }

    #[Test]
    public function it_dispatches_retry_vlm_processing_job_on_retry_vlm_processing()
    {
        config(['mock.attachment.enabled' => false]);
        Bus::fake();

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'finalized_source' => 'vlm',
            'vlm_confidence' => 0.5, // Low confidence to allow admin retry
        ]);

        Gate::before(fn($user, $ability) => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'permissions')
            ->call('retryVlmProcessing')
            ->assertDispatched('mary-toast');

        Bus::assertDispatched(RetryVlmProcessingJob::class, function ($job) use ($file) {
            return $job->attachedFile->id === $file->id;
        });
    }

    #[Test]
    public function it_blocks_retry_actions_for_unauthorized_users()
    {
        config(['mock.attachment.enabled' => false]);
        Bus::fake();

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'vlm_failed_at' => now(),
            'ocr_failed_at' => now(),
            'contain_content' => false,
        ]);

        // Clear Gate::before and set specific rules
        Gate::before(fn() => null);
        Gate::define('view', fn($user, $ledger) => true);
        Gate::define('update', fn($user, $ledger) => false);
        Gate::define('manage_attachments', fn($user) => false);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->call('retryProcessing')
            ->assertDispatched('mary-toast', function ($name, $data) {
                return $data['type'] === 'error';
            });

        Bus::assertNotDispatched(\App\Jobs\Ledger\ProcessAttachedFile::class);
    }
}
