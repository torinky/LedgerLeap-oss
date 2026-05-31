<?php

namespace Tests\Feature\Livewire\AttachedFile;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Jobs\Ledger\RetryVlmProcessingJob;
use App\Livewire\AttachedFile\FileInspector;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FileInspectorTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    protected Tenant $tenant;

    protected User $user;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefreshDatabaseWithTenant();
        // テナント初期化（RefreshDatabaseWithTenant が作成した共有テナントを使用）
        $this->tenant = $this->getTenant();
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
            'status' => AttachedFileStatus::COMPLETED->value,
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
    public function it_auto_opens_when_the_selected_file_is_present_in_the_query_string(): void
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'query_file.pdf',
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        Livewire::withQueryParams(['file' => $file->id])
            ->test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->assertSet('fileId', $file->id)
            ->assertSet('open', true)
            ->assertSet('isLoading', false)
            ->assertSee('query_file.pdf');
    }

    #[Test]
    public function it_prefills_search_keyword_from_the_query_string_when_payload_is_missing(): void
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'highlighted_file.pdf',
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        Livewire::withQueryParams(['file' => $file->id, 'highlight' => 'detail-keyword'])
            ->test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->assertSet('fileId', $file->id)
            ->assertSet('searchKeyword', 'detail-keyword')
            ->assertSet('open', true)
            ->assertSee('highlighted_file.pdf');
    }

    #[Test]
    public function it_keeps_search_keyword_when_reopening_the_inspector_with_the_same_payload(): void
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'reopen_search_file.pdf',
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id, 'search' => 'reopen-keyword'])
            ->assertSet('searchKeyword', 'reopen-keyword')
            ->assertSet('open', true)
            ->call('close')
            ->assertSet('open', false)
            ->call('openInspector', ['id' => $file->id, 'search' => 'reopen-keyword'])
            ->assertSet('searchKeyword', 'reopen-keyword')
            ->assertSet('open', true)
            ->assertSee('reopen_search_file.pdf');

        $this->assertSame($file->id, $component->get('fileId'));
    }

    #[Test]
    public function it_dispatches_selection_sync_events_when_opening_and_closing(): void
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'column_id' => 0,
            'filename' => 'sync_target.pdf',
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', [
                'id' => $file->id,
                'column_id' => $file->column_id,
                'search' => 'sync',
            ])
            ->assertDispatched('file-inspector-selection-changed',
                selectedFileId: $file->id,
                selectedColumnId: $file->column_id,
                isOpen: true,
            )
            ->call('close')
            ->assertDispatched('file-inspector-selection-changed',
                selectedFileId: null,
                selectedColumnId: null,
                isOpen: false,
            );
    }

    #[Test]
    public function it_constrains_drawer_and_tab_widths_to_prevent_horizontal_scroll()
    {
        config(['mock.attachment.enabled' => true]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => 10001])
            ->assertSet('open', true)
            ->assertSeeHtml('fixed inset-y-0 right-0 w-full md:w-[600px]')
            ->assertSeeHtml('flex flex-col flex-1 h-full min-w-0 overflow-x-hidden')
            ->assertSeeHtml('file-inspector-selection-changed.window')
            ->assertSeeHtml('bg-base-100 border-b border-base-300 p-3 flex gap-2 flex-none relative z-50 isolate min-w-0 overflow-x-hidden')
            ->assertSeeHtml('tooltip-left')
            ->assertSeeHtml('flex-1 min-h-0 overflow-y-auto overflow-x-hidden relative min-w-0')
            ->assertSeeHtml('tabs tabs-lift pl-3')
            ->assertSeeHtml('tab-content bg-base-100 border-base-300');
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
            'status' => AttachedFileStatus::COMPLETED->value,
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
        $expectedPreviewUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename]);
        $expectedDownloadUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename, 'original' => true]);

        $this->assertEquals($expectedPreviewUrl, $component->get('previewUrl'));
        $this->assertEquals($expectedPreviewUrl, $component->get('originalUrl'));
        $this->assertEquals($expectedDownloadUrl, $component->get('downloadUrl'));
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
            'status' => AttachedFileStatus::COMPLETED->value,
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
        $expectedPreviewUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename]);
        $expectedDownloadUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename, 'original' => true]);

        $this->assertEquals($expectedPreviewUrl, $component->get('previewUrl'));
        $this->assertEquals($expectedPreviewUrl, $component->get('originalUrl'));
        $this->assertEquals($expectedDownloadUrl, $component->get('downloadUrl'));
    }

    #[Test]
    public function it_exposes_available_formats_for_vlm_pdf_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'available_formats.pdf',
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'vlm_markdown' => '# VLM result',
            'vlm_structured_data' => [
                'pages' => [
                    ['page_index' => 1],
                ],
            ],
            'finalized_source' => 'vlm',
            'processing_finalized_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        $this->assertSame(['text', 'markdown', 'structured', 'json', 'visual'], $component->get('availableFormats'));
    }

    #[Test]
    public function it_falls_back_to_the_file_tenant_when_livewire_tenant_context_is_missing()
    {
        config(['mock.attachment.enabled' => false]);
        Gate::before(fn () => true);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'fallback_tenant.pdf',
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => 'attachments/fallback_tenant.pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
            'ocr_processed_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        tenancy()->end();

        $expectedOcrPdfUrl = route('file.download-ocr-pdf', [
            'tenant' => $this->tenant->id,
            'attachedFile' => $file->id,
        ]);
        $expectedPermissionsTabUrl = route('ledger.show', [
            'tenant' => $this->tenant->id,
            'ledgerId' => $this->ledger->id,
            'tab' => 'permissions',
        ]);

        $component = Livewire::test(FileInspector::class, ['tenantId' => null])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'details');

        $component->assertSee($expectedOcrPdfUrl);
        $this->assertSame($expectedPermissionsTabUrl, $component->get('permissionsTabUrl'));
    }

    #[Test]
    public function it_renders_pdf_iframe_for_low_id_real_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'id' => 5,
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'low_id_report.pdf',
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => 'attachments/low_id_report.pdf',
            'size' => 4096,
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        $this->assertTrue($component->get('isPdf'));
        $this->assertTrue($component->get('showPreview'));
        $component->assertSeeHtml('title="PDF Preview"');
    }

    #[Test]
    public function it_renders_real_download_urls_for_low_id_real_files()
    {
        // リグレッション: quick-actions.blade.php の $isMockFile チェックが
        // ID 1-12 を誤ってモックファイルと見なし #download-... アンカーを生成していたバグ
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'id' => 3,
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'low_id_real_download.pdf',
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => 'attachments/low_id_real_download.pdf',
            'size' => 4096,
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn ($user, $ability) => true);

        $expectedDownloadUrl = route('file.download', [
            'tenant' => $this->tenant->id,
            'attachedFile' => $file->id,
            'original' => true,
        ]);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        // quick-actions にリアルなルートURLが出力されること
        $component->assertSeeHtml('href="'.$expectedDownloadUrl.'"');
        // モック用アンカー（#download-original-3）が出力されないこと
        $component->assertDontSeeHtml('href="#download-original-'.$file->id.'"');
    }

    #[Test]
    public function it_renders_mock_download_anchors_for_mock_files()
    {
        // リグレッション: モックファイルは実ルートURLでなく #download-... アンカーになること
        config(['mock.attachment.enabled' => true]);

        Gate::before(fn ($user, $ability) => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => 10001])
            ->assertSet('open', true);

        // モックファイルは #download-original-10001 のアンカーになること
        $component->assertSeeHtml('href="#download-original-10001"');
        // リアルなルートURLが download href に使われていないこと
        $component->assertDontSeeHtml('href="'.route('file.download', [
            'tenant' => $this->tenant->id,
            'attachedFile' => 10001,
            'original' => true,
        ]).'"');
    }

    #[Test]
    public function it_treats_pdf_extension_as_previewable_when_mime_is_generic()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'id' => 6,
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'mime_generic.pdf',
            'mime' => 'application/octet-stream',
            'original_mime_type' => 'application/octet-stream',
            'path' => 'attachments/mime_generic.pdf',
            'size' => 4096,
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        $this->assertTrue($component->get('isPdf'));
        $this->assertTrue($component->get('showPreview'));
        $this->assertEquals(route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename]), $component->get('previewUrl'));
    }

    #[Test]
    public function it_uses_thumbnail_route_for_large_image_files_when_thumbnail_exists()
    {
        config(['mock.attachment.enabled' => false]);
        Storage::fake('public');

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'large_image.jpg',
            'mime' => 'image/jpeg',
            'original_mime_type' => 'image/jpeg',
            'hashedbasename' => 'large_image.jpg',
            'path' => 'attachments/large_image.jpg',
            'size' => 2 * 1024 * 1024,
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        $thumbnailPath = AttachedFilePathHelper::getThumbnailStoragePath($file->hashedbasename, $this->tenant->id);
        Storage::disk('public')->put($thumbnailPath, 'thumbnail-bytes');

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        $expectedThumbnailUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id, 'hash' => $file->hashedbasename, 'thumbnail' => true]);

        $this->assertEquals($expectedThumbnailUrl, $component->get('thumbnailUrl'));
        $this->assertEquals($expectedThumbnailUrl, $component->get('previewUrl'));
    }

    #[Test]
    public function it_queues_thumbnail_generation_only_once_when_thumbnail_is_missing()
    {
        config(['mock.attachment.enabled' => false]);
        Storage::fake('public');
        Queue::fake();

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'filename' => 'missing_thumbnail.jpg',
            'mime' => 'image/jpeg',
            'original_mime_type' => 'image/jpeg',
            'hashedbasename' => 'missing_thumbnail.jpg',
            'path' => 'attachments/missing_thumbnail.jpg',
            'status' => AttachedFileStatus::COMPLETED->value,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(function ($user, $ability) {
            return true;
        });

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->call('openInspector', ['id' => $file->id])
            ->assertSet('open', true);

        $this->assertSame(AttachedFileStatus::OPTIMIZING->value, $file->fresh()->status->value);
        Queue::assertPushed(GenerateThumbnail::class, 1);
        $this->assertTrue($component->get('showPreview'));
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
            'status' => AttachedFileStatus::COMPLETED->value,
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

        Gate::before(fn ($user, $ability) => true);

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

        Gate::before(fn ($user, $ability) => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'permissions')
            ->assertSee(__('ledger.file_inspector.access.your_permissions'))
            ->assertSee(__('ledger.file_inspector.actions.title'))
            ->assertSee($this->user->name);
    }

    #[Test]
    public function it_tracks_loaded_tabs_and_resets_them_on_close()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn ($user, $ability) => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertSame(['content'], $component->get('loadedTabs'));

        $component->set('selectedTab', 'details');
        $component->set('selectedTab', 'history');

        $loadedTabs = $component->get('loadedTabs');
        $this->assertContains('content', $loadedTabs);
        $this->assertContains('details', $loadedTabs);
        $this->assertContains('history', $loadedTabs);

        $component->call('close');
        $this->assertSame([], $component->get('loadedTabs'));

        $component->call('openInspector', ['id' => $file->id]);
        $this->assertSame(['content'], $component->get('loadedTabs'));
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

        Gate::before(fn ($user, $ability) => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->call('retryProcessing')
            ->assertDispatched('mary-toast');

        Bus::assertDispatched(ProcessAttachedFile::class, function ($job) use ($file) {
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

        Gate::before(fn ($user, $ability) => true);

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
        Gate::before(fn () => null);
        Gate::define('view', fn ($user, $ledger) => true);
        Gate::define('update', fn ($user, $ledger) => false);
        Gate::define('manage_attachments', fn ($user) => false);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->call('retryProcessing')
            ->assertDispatched('mary-toast', function ($name, $data) {
                return $data['type'] === 'error';
            });

        Bus::assertNotDispatched(ProcessAttachedFile::class);
    }

    #[Test]
    public function it_navigates_to_permissions_tab()
    {
        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
        ]);

        Gate::before(fn () => true);

        // 台帳詳細画面内からの場合（タブ切り替え）
        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id, 'isInLedgerDetailPage' => true])
            ->call('openInspector', ['id' => $file->id])
            ->call('navigateToPermissionsTab')
            ->assertDispatched('navigate-to-ledger-tab', function ($name, $data) {
                return $data['tab'] === 'permissions';
            })
            ->assertSet('open', false);

        // その他の画面からの場合（別タブで開く）
        $expectedUrl = route('ledger.show', [
            'tenant' => $this->tenant->id,
            'ledgerId' => $this->ledger->id,
            'tab' => 'permissions',
        ]);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id, 'isInLedgerDetailPage' => false])
            ->call('openInspector', ['id' => $file->id])
            ->call('navigateToPermissionsTab')
            ->assertDispatched('open-in-new-tab', function ($name, $data) use ($expectedUrl) {
                return str_contains($data['url'], $expectedUrl);
            })
            ->assertSet('open', false);
    }

    // ========================================
    // WBS 5.1.1: 未最終化ファイル表示テスト
    // ========================================

    #[Test]
    public function it_shows_not_finalized_badge_for_unfinalized_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => null, // 未最終化
            'tika_processed_at' => now(),
            'ocr_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'details')
            ->assertSee('最終化前')
            ->assertSee('最終化されていません');
    }

    #[Test]
    public function it_shows_finalization_waiting_in_history_tab()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => null, // 未最終化
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'history')
            ->assertSee('最終化待ち')
            ->assertSee('最終化処理を待っています');
    }

    #[Test]
    public function it_does_not_show_not_finalized_badge_for_finalized_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(), // 最終化済み
            'tika_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'details')
            ->assertDontSee('最終化前');
    }

    // ========================================
    // WBS 5.1.2: 全処理失敗ケーステスト
    // ========================================

    #[Test]
    public function it_shows_all_failed_error_message()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
            // 全てのテキストがnullまたは空 = 失敗
            'vlm_markdown' => null,
            'finalized_source' => null,
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'content')
            ->assertSee('テキスト抽出に失敗しました')
            ->assertSee('サポートに連絡');
    }

    #[Test]
    public function it_shows_retry_button_for_failed_files_with_permission()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
            'vlm_markdown' => null,
            'finalized_source' => null,
            'contain_content' => false,
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'content')
            ->assertSee('全ての抽出処理を再実行'); // retry_allの翻訳
    }

    #[Test]
    public function it_detects_all_processing_failed_correctly()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
            'vlm_markdown' => null,
            'finalized_source' => null,
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertTrue($component->instance()->isAllProcessingFailed());
    }

    // ========================================
    // WBS 5.1.3: 処理タイムアウト表示テスト
    // ========================================

    #[Test]
    public function it_shows_timeout_warning_for_long_running_files()
    {
        config(['mock.attachment.enabled' => false]);
        config(['ledgerleap.processing_timeout_hours' => 24]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => null, // 未最終化
            'created_at' => now()->subHours(25), // 24時間以上経過
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'content')
            ->assertSee('タイムアウト')
            ->assertSee('処理時間が制限を超えました');
    }

    #[Test]
    public function it_detects_timeout_correctly()
    {
        config(['mock.attachment.enabled' => false]);
        config(['ledgerleap.processing_timeout_hours' => 24]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => null,
            'created_at' => now()->subHours(25),
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertTrue($component->instance()->isProcessingTimedOut());
    }

    #[Test]
    public function it_does_not_show_timeout_for_finalized_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(), // 最終化済み
            'created_at' => now()->subHours(25),
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertFalse($component->instance()->isProcessingTimedOut());
    }

    // ========================================
    // WBS 5.1.4: Tika単独失敗テスト
    // ========================================

    #[Test]
    public function it_shows_tika_only_failed_info()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_markdown' => 'VLM解析結果があります',
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
            'finalized_source' => 'vlm',
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'content')
            ->assertSee('代替のテキストが利用可能');
    }

    #[Test]
    public function it_detects_tika_only_failed_correctly()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_markdown' => 'VLM解析結果',
            'vlm_processed_at' => now(),
            'tika_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertTrue($component->instance()->isTikaOnlyFailed());
    }

    // ========================================
    // WBS 5.1.5: MIMEタイプ不明テスト
    // ========================================

    #[Test]
    public function it_shows_unsupported_format_warning_for_zip_files()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'mime' => 'application/zip',
            'original_mime_type' => 'application/zip',
            'processing_finalized_at' => now(),
        ]);

        Gate::before(fn () => true);

        Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('selectedTab', 'content')
            ->assertSee('非対応のファイル形式')
            ->assertSee('テキスト抽出に対応していません');
    }

    #[Test]
    public function it_detects_unknown_mime_type_correctly()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'mime' => 'application/zip',
            'original_mime_type' => 'application/zip',
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertTrue($component->instance()->isUnknownMimeType());
    }

    #[Test]
    public function it_detects_video_files_as_unknown()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'mime' => 'video/mp4',
            'original_mime_type' => 'video/mp4',
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id]);

        $this->assertTrue($component->instance()->isUnknownMimeType());
    }

    // ========================================
    // WBS 5.2.1: キャッシング機能テスト
    // ========================================

    #[Test]
    public function it_caches_preview_text_for_performance()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_markdown' => 'VLM解析結果のテキスト',
            'vlm_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('searchKeyword', 'テスト');

        // 1回目の呼び出し（キャッシュなし）
        $firstResult = $component->instance()->hasKeywordHit;

        // 2回目の呼び出し（キャッシュあり）- 同じ結果が返ることを確認
        $secondResult = $component->instance()->hasKeywordHit;

        $this->assertEquals($firstResult, $secondResult);
    }

    #[Test]
    public function it_clears_cache_when_search_keyword_changes()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_markdown' => 'VLM解析結果のテキスト',
            'vlm_processed_at' => now(),
        ]);

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('searchKeyword', 'VLM')
            ->assertSet('searchKeyword', 'VLM');

        $firstResult = $component->instance()->hasKeywordHit;
        $this->assertTrue($firstResult); // 'VLM'は含まれる

        // キーワード変更時にキャッシュがクリアされることを確認
        $component->set('searchKeyword', '存在しないキーワード');
        $secondResult = $component->instance()->hasKeywordHit;

        $this->assertFalse($secondResult); // 新しいキーワードでは一致しない
    }

    #[Test]
    public function it_clears_cache_when_active_source_changes()
    {
        config(['mock.attachment.enabled' => false]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledger->ledger_define_id,
            'tenant_id' => $this->tenant->id,
            'processing_finalized_at' => now(),
            'vlm_markdown' => 'VLM解析結果',
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
        ]);

        // OCRテキストを設定
        $this->ledger->content_attached = [
            1 => [
                $file->hashedbasename => [
                    'meta' => [
                        'content' => 'OCR解析結果',
                    ],
                ],
            ],
        ];
        $this->ledger->save();

        Gate::before(fn () => true);

        $component = Livewire::test(FileInspector::class, ['tenantId' => $this->tenant->id])
            ->call('openInspector', ['id' => $file->id])
            ->set('activeSource', 'vlm')
            ->set('searchKeyword', 'VLM');

        $firstResult = $component->instance()->hasKeywordHit;
        $this->assertTrue($firstResult); // VLMソースに'VLM'が含まれる

        // ソース変更時にキャッシュがクリアされることを確認
        $component->set('activeSource', 'ocr');
        $secondResult = $component->instance()->hasKeywordHit;

        $this->assertFalse($secondResult); // OCRソースには'VLM'が含まれない
    }
}
