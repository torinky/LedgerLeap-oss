<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetClientBootstrapManifestTool;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\Ai\BootstrapManifestService;
use App\Services\Ai\CapabilityManifestRepository;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(GetClientBootstrapManifestTool::class)]
class GetClientBootstrapManifestToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private string $manifestPath;

    private string $plainTextToken;

    private BootstrapManifestService $bootstrapManifestService;

    private GetClientBootstrapManifestTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $folderRepository = Mockery::mock(WritableFolderRepository::class);
        $folderRepository->allows()->clearAllCache(Mockery::any())->andReturnNull();
        $folderRepository->allows()->refreshAllCache(Mockery::any())->andReturnNull();
        $this->app->instance(WritableFolderRepository::class, $folderRepository);
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['mcp:*']);
        $this->plainTextToken = $token->plainTextToken;
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);
        $this->manifestPath = storage_path('framework/testing/bootstrap-manifests-mcp');
        File::deleteDirectory($this->manifestPath);
        File::ensureDirectoryExists($this->manifestPath);
        $this->bootstrapManifestService = new BootstrapManifestService(
            new CapabilityManifestRepository($this->manifestPath)
        );
        $this->tool = new GetClientBootstrapManifestTool($this->bootstrapManifestService);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        File::deleteDirectory($this->manifestPath);
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_the_same_bundle_as_the_rest_bootstrap_service(): void
    {
        $this->putManifest('ledger-search', 'active', ['ledgerleap://guides/search-strategy']);
        $this->putManifest('ledger-create', 'active', ['ledgerleap://guides/create-strategy']);
        $this->putManifest('workflow-review', 'active', ['ledgerleap://guides/workflow-review-strategy']);
        $request = new Request([
            'client_type' => 'copilot',
            'role_profile' => 'operator',
            'model_profile' => 'small-local',
            'language' => 'ja',
        ]);
        $response = $this->tool->handle($request);
        $this->assertFalse($response->isError());
        $this->assertSame(
            $this->bootstrapManifestService->resolve([
                'client_type' => 'copilot',
                'role_profile' => 'operator',
                'model_profile' => 'small-local',
                'language' => 'ja',
            ]),
            json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[Test]
    public function it_applies_the_same_defaults_as_the_rest_bootstrap_contract(): void
    {
        $this->putManifest('ledger-search', 'active', ['ledgerleap://guides/search-strategy']);
        $this->putManifest('ledger-create', 'active', ['ledgerleap://guides/create-strategy']);
        $this->putManifest('workflow-review', 'active', ['ledgerleap://guides/workflow-review-strategy']);
        $response = $this->tool->handle(new Request([
            'client_type' => 'copilot',
            'role_profile' => 'operator',
        ]));
        $this->assertFalse($response->isError());
        $this->assertSame(
            $this->bootstrapManifestService->resolve([
                'client_type' => 'copilot',
                'role_profile' => 'operator',
                'model_profile' => 'general-local',
                'language' => 'ja',
            ]),
            json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[Test]
    public function it_returns_service_warnings_for_missing_or_inactive_capabilities(): void
    {
        $this->putManifest('ledger-search', 'active', ['ledgerleap://guides/search-strategy']);
        $this->putManifest('workflow-review', 'planned', ['ledgerleap://guides/workflow-review-strategy']);
        $response = $this->tool->handle(new Request([
            'client_type' => 'gemini-cli',
            'role_profile' => 'field-leader',
            'model_profile' => 'general-local',
            'language' => 'ja',
        ]));
        $this->assertFalse($response->isError());
        $this->assertSame(
            $this->bootstrapManifestService->resolve([
                'client_type' => 'gemini-cli',
                'role_profile' => 'field-leader',
                'model_profile' => 'general-local',
                'language' => 'ja',
            ]),
            json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[Test]
    public function it_rejects_invalid_bootstrap_manifest_inputs(): void
    {
        $response = $this->tool->handle(new Request([
            'client_type' => 'unknown-client',
            'role_profile' => 'operator',
        ]));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('client type', strtolower($response->content()));
    }

    #[Test]
    public function it_rejects_requests_without_an_auth_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        $response = $this->tool->handle(new Request([
            'client_type' => 'copilot',
            'role_profile' => 'operator',
        ]));
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('MCP_AUTH_TOKEN environment variable is not set', $response->content());
    }

    /**
     * @param  array<int, string>  $guides
     */
    private function putManifest(string $id, string $status, array $guides): void
    {
        File::put($this->manifestPath.'/'.$id.'.yaml', implode("\n", [
            'id: '.$id,
            'status: '.$status,
            'summary: '.$id.' summary',
            'primary_user_goals:',
            '  - '.$id.' goal',
            'required_guides:',
            ...array_map(fn (string $guide) => '  - '.$guide, $guides),
            '',
        ]));
    }
}
