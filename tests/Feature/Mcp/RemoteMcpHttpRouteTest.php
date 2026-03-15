<?php

namespace Tests\Feature\Mcp;

use App\Enums\FolderPermissionType;
use App\Mcp\Servers\LedgerLeapServer;
use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(LedgerLeapServer::class)]
class RemoteMcpHttpRouteTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    private string $tenantDomain;

    private string $secondTenantDomain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        /** @var Tenant $tenant */
        $tenant = $this->getTenant();
        $this->tenant = $tenant;
        tenancy()->initialize($tenant);

        $this->tenantDomain = 'mcp-http-test.localhost';
        $this->tenant->domains()->firstOrCreate([
            'domain' => $this->tenantDomain,
        ]);

        $this->secondTenantDomain = 'mcp-http-test-tenant-b.localhost';

        config(['app.url' => "http://{$this->tenantDomain}"]);
        \URL::forceRootUrl(config('app.url'));
    }

    #[Test]
    public function remote_mcp_http_route_requires_bearer_authentication(): void
    {
        $response = $this->postJson($this->mcpUrl(), $this->toolCallPayload());

        $response->assertUnauthorized();
    }

    #[Test]
    public function remote_mcp_http_route_allows_authenticated_tool_calls_via_bearer_token(): void
    {
        $token = $this->createAuthorizedTokenForTenant($this->tenant, 'remote-mcp-test');

        $response = $this->withToken($token)
            ->postJson($this->mcpUrl(), $this->toolCallPayload());

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.content.0.type', 'text');

        $payloadText = $response->json('result.content.0.text');

        $this->assertIsString($payloadText);
        $this->assertStringContainsString('"client_type":"copilot"', $payloadText);
        $this->assertStringContainsString('"language":"ja"', $payloadText);
    }

    #[Test]
    public function remote_mcp_http_route_rejects_cross_tenant_access_even_with_valid_bearer_token(): void
    {
        $firstTenantToken = $this->createAuthorizedTokenForTenant($this->tenant, 'remote-mcp-test-a');

        $secondTenant = Tenant::create(['id' => 'mcp-http-test-tenant-b']);
        $secondTenant->domains()->create([
            'domain' => $this->secondTenantDomain,
        ]);

        $this->artisan('tenants:migrate', ['--tenants' => [$secondTenant->id]]);

        $this->withToken($firstTenantToken)
            ->postJson($this->mcpUrl($this->tenantDomain), $this->toolCallPayload())
            ->assertOk()
            ->assertJsonPath('result.isError', false);

        $this->assertNotSame(
            $this->mcpUrl($this->tenantDomain),
            $this->mcpUrl($this->secondTenantDomain)
        );

        $this->withToken($firstTenantToken)
            ->postJson($this->mcpUrl($this->secondTenantDomain), $this->toolCallPayload())
            ->assertForbidden();
    }

    #[Test]
    public function remote_mcp_http_route_supports_path_based_tenant_urls(): void
    {
        $token = $this->createAuthorizedTokenForTenant($this->tenant, 'remote-mcp-path-test');

        $this->withToken($token)
            ->postJson($this->pathBasedMcpUrl(), $this->toolCallPayload())
            ->assertOk()
            ->assertJsonPath('result.isError', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolCallPayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 'remote-mcp-http-test',
            'method' => 'tools/call',
            'params' => [
                'name' => 'get-client-bootstrap-manifest-tool',
                'arguments' => [
                    'client_type' => 'copilot',
                    'role_profile' => 'operator',
                    'model_profile' => 'general-local',
                    'language' => 'ja',
                ],
            ],
        ];
    }

    private function mcpUrl(?string $domain = null): string
    {
        $domain ??= $this->tenantDomain;

        return "http://{$domain}/mcp/ledgerleap";
    }

    private function pathBasedMcpUrl(?string $tenantId = null): string
    {
        $tenantId ??= (string) $this->tenant->getTenantKey();

        return "http://localhost/{$tenantId}/mcp/ledgerleap";
    }

    private function createAuthorizedTokenForTenant(Tenant $tenant, string $tokenName): string
    {
        return $tenant->run(function () use ($tokenName) {
            $user = User::factory()->create();
            $role = Role::firstOrCreate([
                'name' => 'MCP '.$tokenName,
                'guard_name' => 'web',
            ]);

            $user->assignRole($role);

            $folder = Folder::create([
                'title' => 'MCP '.$tokenName,
                'creator_id' => $user->id,
                'modifier_id' => $user->id,
            ]);

            RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $folder->id,
                'permission' => FolderPermissionType::ADMIN,
                'creator_id' => $user->id,
                'modifier_id' => $user->id,
            ]);

            return $user->createToken($tokenName, ['mcp:*'])->plainTextToken;
        });
    }
}
