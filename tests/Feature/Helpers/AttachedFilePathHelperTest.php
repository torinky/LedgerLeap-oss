<?php

namespace Tests\Feature\Helpers;

use App\Helpers\AttachedFilePathHelper;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachedFilePathHelperTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function getAttachmentPath_generates_tenant_specific_path()
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $ledgerDefineId = 1;
        $hashedBasename = 'testfile.jpg';

        $path = AttachedFilePathHelper::getAttachmentPath($ledgerDefineId, $hashedBasename);

        $expectedPath = 'tenants/' . $tenant->id . '/Ledger/Attachments/' . $ledgerDefineId . '/' . $hashedBasename;

        $this->assertEquals($expectedPath, $path);
        Storage::disk('public')->assertExists('tenants/' . $tenant->id . '/Ledger/Attachments/' . $ledgerDefineId);
    }

    #[Test]
    public function getOriginalAttachmentPath_generates_tenant_specific_path()
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $ledgerDefineId = 1;
        $hashedBasename = 'testfile.jpg';

        $path = AttachedFilePathHelper::getOriginalAttachmentPath($ledgerDefineId, $hashedBasename);

        $expectedPath = 'tenants/' . $tenant->id . '/Ledger/Attachments/' . $ledgerDefineId . '/Originals/' . $hashedBasename;

        $this->assertEquals($expectedPath, $path);
        Storage::disk('public')->assertExists('tenants/' . $tenant->id . '/Ledger/Attachments/' . $ledgerDefineId . '/Originals');
    }

    #[Test]
    public function getThumbnailStoragePath_generates_tenant_specific_path()
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $hashedBasename = 'testfile.jpg';

        $path = AttachedFilePathHelper::getThumbnailStoragePath($hashedBasename);

        $expectedPath = 'tenants/' . $tenant->id . '/Ledger/thumbs/' . $hashedBasename;

        $this->assertEquals($expectedPath, $path);
        Storage::disk('public')->assertExists('tenants/' . $tenant->id . '/Ledger/thumbs');
    }

    #[Test]
    public function it_logs_error_when_tenant_is_not_initialized()
    {
        // テナントを初期化しない
        // tenancy()->initialize($tenant);

        Log::spy();

        $path = AttachedFilePathHelper::getAttachmentPath(1, 'test.jpg');

        // パスがnullまたは空であることを確認（実装による）
        $this->assertEmpty($path);

        // エラーログが記録されたことを確認
        Log::shouldHaveReceived('error')->once()->with('Tenant ID not found while generating attachment path.');
    }
}
