<?php

namespace Tests\Feature\Helpers;

use App\Helpers\AttachedFilePathHelper;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachedFilePathHelperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
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

    /** @test */
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

    /** @test */
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
}
