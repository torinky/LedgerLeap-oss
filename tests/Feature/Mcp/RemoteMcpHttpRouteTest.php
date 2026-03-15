<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LedgerLeapServer;
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
        $user = User::factory()->create();
        $token = $user->createToken('remote-mcp-test', ['mcp:*'])->plainTextToken;

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

    private function mcpUrl(): string
    {
        return "http://{$this->tenantDomain}/mcp/ledgerleap";
    }
}
