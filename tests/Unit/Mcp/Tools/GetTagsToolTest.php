<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetTagsTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Laravel\Mcp\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetTagsToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Sanctum::actingAs(User::factory()->make(), ['mcp:*']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function testItReturnsTagsMatchingPartialFragmentWithinReadableFolders(): void
    {
        $creator = User::factory()->create();

        $readableFolder = Folder::factory()->create([
            'title' => '営業部',
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        $otherFolder = Folder::factory()->create([
            'title' => '監査部',
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);

        $define = LedgerDefine::factory()->create([
            'title' => '営業台帳',
            'folder_id' => $readableFolder->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        $otherDefine = LedgerDefine::factory()->create([
            'title' => '監査台帳',
            'folder_id' => $otherFolder->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);

        Tag::factory()->create([
            'name' => '営業',
            'folder_id' => $readableFolder->id,
            'ledger_define_id' => $define->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        Tag::factory()->create([
            'name' => '営業管理',
            'folder_id' => $readableFolder->id,
            'ledger_define_id' => $define->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        Tag::factory()->create([
            'name' => '営業外',
            'folder_id' => $otherFolder->id,
            'ledger_define_id' => $otherDefine->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->expects()
            ->getReadableFolderIds(Mockery::type(User::class))
            ->andReturn([$readableFolder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = app(GetTagsTool::class);
        $response = $tool->handle(new Request(['q' => '営業']), $mockRepository);

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('営業', $data['q']);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['tags']);
        $this->assertSame('営業', $data['tags'][0]['name']);
        $this->assertSame('営業管理', $data['tags'][1]['name']);
    }

    #[Test]
    public function testItReturnsEmptyWhenNoTagsMatch(): void
    {
        $creator = User::factory()->create();
        $folder = Folder::factory()->create([
            'title' => '経理部',
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        $define = LedgerDefine::factory()->create([
            'title' => '経理台帳',
            'folder_id' => $folder->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);
        Tag::factory()->create([
            'name' => '監査',
            'folder_id' => $folder->id,
            'ledger_define_id' => $define->id,
            'creator_id' => $creator->id,
            'modifier_id' => $creator->id,
            'tenant_id' => tenant()->id,
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->expects()
            ->getReadableFolderIds(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = app(GetTagsTool::class);
        $response = $tool->handle(new Request(['q' => '営業']), $mockRepository);

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['tags']);
    }
}

