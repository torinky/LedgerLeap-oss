<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetFoldersTool;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetFoldersToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $tokenResult = $this->user->createToken('test-token');
        $this->validToken = $tokenResult->plainTextToken;
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_folders_matching_partial_title_fragment(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $matchedFolder = Folder::factory()->create(['title' => '営業部']);
        $otherFolder = Folder::factory()->create(['title' => '監査部']);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$matchedFolder->id, $otherFolder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetFoldersTool;
        $response = $tool->handle(new Request(['q' => '営業']), $mockRepository);

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('営業', $data['q']);
        $this->assertSame(1, $data['count']);
        $this->assertCount(1, $data['folders']);
        $this->assertSame($matchedFolder->id, $data['folders'][0]['id']);
        $this->assertSame('営業部', $data['folders'][0]['title']);
    }

    #[Test]
    public function it_returns_empty_when_no_folders_match(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create(['title' => '経理部']);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetFoldersTool;
        $response = $tool->handle(new Request(['q' => '営業']), $mockRepository);

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['folders']);
    }
}
