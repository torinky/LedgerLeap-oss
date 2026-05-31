<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFileDownloadControllerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        tenancy()->initialize($this->tenant);

        $folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $folder->id,
        ]);
    }

    #[Test]
    public function it_queues_thumbnail_generation_only_once_when_thumbnail_is_missing(): void
    {
        Storage::fake('public');
        Queue::fake();

        Gate::before(function () {
            return true;
        });

        $originalPath = 'attachments/originals/fallback-image.jpg';
        Storage::disk('public')->put($originalPath, UploadedFile::fake()->image('fallback-image.jpg')->get());

        $ledger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);

        $file = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_id' => 0,
            'filename' => 'fallback-image.pdf',
            'hashedbasename' => 'fallback-image.jpg',
            'path' => 'attachments/fallback-image.pdf',
            'original_file_path' => $originalPath,
            'mime' => 'application/pdf',
            'original_mime_type' => 'image/jpeg',
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);

        $response = $this->get(tenant_route_url('file.download', [
            'attachedFile' => $file->id,
            'thumbnail' => true,
        ]));

        $secondResponse = $this->get(tenant_route_url('file.download', [
            'attachedFile' => $file->id,
            'thumbnail' => true,
        ]));

        $response->assertOk();
        $secondResponse->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $secondResponse->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('Processing', $response->getContent());
        $this->assertSame(AttachedFileStatus::OPTIMIZING->value, $file->fresh()->status->value);

        Queue::assertPushed(GenerateThumbnail::class, 1);
    }

    #[Test]
    public function download_with_correct_hash_succeeds(): void
    {
        Storage::fake('public');
        Gate::before(fn () => true);

        $filePath = AttachedFilePathHelper::getAttachmentPath($this->ledgerDefine->id, 'correct_hash.pdf');
        Storage::disk('public')->put($filePath, 'test content');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);

        $file = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_id' => 0,
            'filename' => 'correct.pdf',
            'hashedbasename' => 'correct_hash.pdf',
            'path' => $filePath,
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);

        $response = $this->get(tenant_route_url('file.download', [
            'attachedFile' => $file->id,
            'hash' => 'correct_hash.pdf',
        ]));

        $response->assertOk();
    }

    #[Test]
    public function download_with_incorrect_hash_returns_404(): void
    {
        Storage::fake('public');
        Gate::before(fn () => true);

        $filePath = AttachedFilePathHelper::getAttachmentPath($this->ledgerDefine->id, 'real_hash.pdf');
        Storage::disk('public')->put($filePath, 'test content');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);

        $file = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_id' => 0,
            'filename' => 'real.pdf',
            'hashedbasename' => 'real_hash.pdf',
            'path' => $filePath,
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);

        $response = $this->get(tenant_route_url('file.download', [
            'attachedFile' => $file->id,
            'hash' => 'wrong_hash.pdf',
        ]));

        $response->assertNotFound();
    }

    #[Test]
    public function download_without_hash_still_works(): void
    {
        Storage::fake('public');
        Gate::before(fn () => true);

        $filePath = AttachedFilePathHelper::getAttachmentPath($this->ledgerDefine->id, 'no_hash_check.pdf');
        Storage::disk('public')->put($filePath, 'test content');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);

        $file = AttachedFile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_id' => 0,
            'filename' => 'nohash.pdf',
            'hashedbasename' => 'no_hash_check.pdf',
            'path' => $filePath,
            'mime' => 'application/pdf',
            'status' => AttachedFileStatus::COMPLETED->value,
        ]);

        $response = $this->get(tenant_route_url('file.download', [
            'attachedFile' => $file->id,
        ]));

        $response->assertOk();
    }
}
