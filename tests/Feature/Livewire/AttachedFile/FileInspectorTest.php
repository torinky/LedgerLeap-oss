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

    protected bool $fakeQueue = false;

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

        Bus::assertNotDispatched(\App\Jobs\Ledger\ProcessAttachedFile::class);
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
