<?php

namespace Tests\Unit\Observers;

use App\Models\Folder;
use App\Models\Tenant;
use App\Services\TenantAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FolderObserverTest extends TestCase
{
    use RefreshDatabase;

    protected MockInterface|TenantAccessService $tenantAccessServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // TenantAccessServiceをモックして、サービスコンテナに束縛する
        $this->tenantAccessServiceMock = Mockery::mock(TenantAccessService::class);
        $this->app->instance(TenantAccessService::class, $this->tenantAccessServiceMock);
    }

    #[Test]
    public function it_does_not_clear_cache_when_folder_is_created(): void
    {
        // arrange
        $this->tenantAccessServiceMock->shouldNotReceive('clearAllCache');

        // act
        Folder::factory()->create();

        // assert - Mockeryが検証
    }

    #[Test]
    public function it_does_not_clear_cache_when_unrelated_field_is_updated(): void
    {
        // arrange
        $folder = Folder::factory()->create();
        $this->tenantAccessServiceMock->shouldNotReceive('clearAllCache');

        // act
        $folder->title = 'New Title';
        $folder->save();

        // assert - Mockeryが検証
    }

    #[Test]
    public function it_clears_cache_when_parent_id_is_changed(): void
    {
        // arrange
        $tenant = Tenant::factory()->create();
        $parentFolder = Folder::factory()->create(['tenant_id' => $tenant->id]);
        $folder = Folder::factory()->create(['tenant_id' => $tenant->id, 'parent_id' => null]);

        $this->tenantAccessServiceMock->shouldReceive('clearAllCache')->once();

        // act
        $folder->parent_id = $parentFolder->id;
        $folder->save();

        // assert - Mockeryが検証
    }

    #[Test]
    public function it_clears_cache_when_tenant_id_is_changed(): void
    {
        // arrange
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $folder = Folder::factory()->create(['tenant_id' => $tenant1->id]);

        $this->tenantAccessServiceMock->shouldReceive('clearAllCache')->once();

        // act
        $folder->tenant_id = $tenant2->id;
        $folder->save();

        // assert - Mockeryが検証
    }

    #[Test]
    public function it_clears_cache_when_folder_is_deleted(): void
    {
        // arrange
        $folder = Folder::factory()->create();
        $this->tenantAccessServiceMock->shouldReceive('clearAllCache')->once();

        // act
        $folder->delete();

        // assert - Mockeryが検証
    }
}
