<?php

namespace Tests\Unit\Observers;

use App\Models\Folder;
use App\Services\TenantAccessService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FolderObserverTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected MockInterface|TenantAccessService $tenantAccessServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // TenantAccessServiceをモックして、サービスコンテナに束縛する
        $this->tenantAccessServiceMock = Mockery::mock(TenantAccessService::class);
        $this->app->instance(TenantAccessService::class, $this->tenantAccessServiceMock);
    }

    protected function getTablesToTruncate(): array
    {
        return [
            'folders',
            'ledgers',
            'ledger_defines',
            'ledger_diffs',
            'role_folder_permissions',
            'model_has_roles',
            'personal_access_tokens',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_does_not_clear_cache_when_folder_is_created(): void
    {
        // arrange
        $this->tenantAccessServiceMock->shouldNotReceive('clearAllCache');

        // act
        Folder::factory()->create();

        // assert - Mockeryが検証
        $this->tenantAccessServiceMock->mockery_verify();
        $this->assertTrue(true); // PHPUnit assertion to avoid risky test warning
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
        $this->tenantAccessServiceMock->mockery_verify();
        $this->assertTrue(true); // PHPUnit assertion
    }

    #[Test]
    public function it_clears_cache_when_parent_id_is_changed(): void
    {
        // arrange
        $parentFolder = Folder::factory()->create();
        $folder = Folder::factory()->create(['parent_id' => null]);

        $this->tenantAccessServiceMock->shouldReceive('clearAllCache')->once();

        // act
        $folder->parent_id = $parentFolder->id;
        $folder->save();

        // assert - Mockeryが検証
        $this->tenantAccessServiceMock->mockery_verify();
        $this->assertTrue(true); // PHPUnit assertion
    }

    #[Test]
    public function it_clears_cache_when_tenant_id_is_changed(): void
    {
        // arrange
        $folder = Folder::factory()->create();
        
        // Folderモデルをパーシャルモックして、wasChanged()の動作を制御
        $folderMock = \Mockery::mock($folder)->makePartial();
        $folderMock->shouldReceive('wasChanged')
            ->with('parent_id')
            ->andReturn(false);
        $folderMock->shouldReceive('wasChanged')
            ->with('tenant_id')
            ->andReturn(true);

        $this->tenantAccessServiceMock->shouldReceive('clearAllCache')->once();

        // act
        // Observerのsaved()メソッドを直接呼び出してテスト
        $observer = new \App\Observers\FolderObserver($this->tenantAccessServiceMock);
        $observer->saved($folderMock);

        // assert - Mockeryが検証
        $this->tenantAccessServiceMock->mockery_verify();
        $this->assertTrue(true); // PHPUnit assertion
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
        $this->tenantAccessServiceMock->mockery_verify();
        $this->assertTrue(true); // PHPUnit assertion
    }
}
