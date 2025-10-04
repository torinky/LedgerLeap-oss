<?php

namespace Tests\Unit\Observers;

use App\Models\User;
use App\Services\TenantAccessService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class UserObserverTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private MockInterface $serviceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // TenantAccessServiceをモックし、サービスコンテナに束縛する
        $this->serviceMock = $this->spy(TenantAccessService::class);
    }

    #[Test]
    public function it_clears_cache_when_user_is_updated(): void
    {
        // 準備 (Arrange)
        $user = User::factory()->create();

        // 実行 (Act)
        // updatedイベントをトリガーする
        $user->name = 'New Name';
        $user->save();

        // 評価 (Assert)
        $this->serviceMock->shouldHaveReceived('clearUserCache')->with($user)->once();
    }

    #[Test]
    public function it_clears_cache_when_user_is_deleted(): void
    {
        // 準備 (Arrange)
        $user = User::factory()->create();

        // 実行 (Act)
        // deletedイベントをトリガーする
        $user->delete();

        // 評価 (Assert)
        $this->serviceMock->shouldHaveReceived('clearUserCache')->with($user)->once();
    }
}
