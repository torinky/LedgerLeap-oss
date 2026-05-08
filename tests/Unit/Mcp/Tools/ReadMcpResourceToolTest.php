<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\AttachedFileStatus;
use App\Mcp\Tools\ReadMcpResourceTool;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Laravel\Mcp\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ReadMcpResourceToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private ReadMcpResourceTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['mcp:*']);
        Auth::setUser($this->user);
        putenv('MCP_AUTH_TOKEN='.$this->user->createToken('test-token', ['mcp:*'])->plainTextToken);

        $this->tool = app(ReadMcpResourceTool::class);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        parent::tearDown();
    }

    #[Test]
    public function it_returns_a_bootstrap_envelope_for_supported_clients(): void
    {
        $response = $this->tool->handle(new Request([
            'resource_uri' => 'ledgerleap://bootstrap/copilot',
        ]));

        $this->assertFalse($response->isError());

        $payload = json_decode($response->content()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('bootstrap', $payload['resource_type']);
        $this->assertSame('ledgerleap://bootstrap/copilot', $payload['resource_uri']);
        $this->assertSame('text/markdown', $payload['mime_type']);
        $this->assertSame('text', $payload['delivery_mode']);
        $this->assertSame(['text', 'markdown'], $payload['available_formats']);
        $this->assertStringContainsString('# LedgerLeap bootstrap card: copilot', $payload['payloads']['text']['text']);
        $this->assertSame($payload['payloads']['text']['text'], $payload['payloads']['markdown']['text']);
        $this->assertSame('ReadMcpResourceTool', $payload['access_guide']['read_via']);
    }

    #[Test]
    public function it_returns_an_attachment_envelope_for_supported_attachment_uris(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [
                1 => [
                    'hash-123' => [
                        'name' => 'invoice.json',
                        'mime' => 'application/json',
                        'size' => 4096,
                        'source' => 'vlm',
                        'meta' => [
                            'content' => '{"invoice":"INV-001"}',
                        ],
                        'pages' => [
                            ['page_index' => 1],
                        ],
                        'text_blocks' => [],
                        'key_value_pairs' => [
                            ['key' => 'invoice', 'value' => 'INV-001'],
                        ],
                    ],
                ],
            ],
        ]);

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'invoice.json',
            'hashedbasename' => 'hash-123',
            'column_id' => 1,
            'mime' => 'application/json',
            'original_mime_type' => 'application/json',
            'status' => AttachedFileStatus::COMPLETED,
            'vlm_markdown' => '# invoice INV-001',
            'vlm_structured_data' => [
                'pages' => [
                    ['page_index' => 1],
                ],
                'text_blocks' => [],
                'key_value_pairs' => [
                    ['key' => 'invoice', 'value' => 'INV-001'],
                ],
            ],
            'finalized_source' => 'vlm',
        ]);

        $response = $this->tool->handle(new Request([
            'resource_uri' => sprintf('ledgerleap://ledger/%s/%s/attachments/%s', $tenantId, $ledger->id, $attachment->id),
        ]));

        $this->assertFalse($response->isError());

        $payload = json_decode($response->content()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('attachment', $payload['resource_type']);
        $this->assertSame(sprintf('ledgerleap://ledger/%s/%s/attachments/%s', $tenantId, $ledger->id, $attachment->id), $payload['resource_uri']);
        $this->assertSame('application/json', $payload['mime_type']);
        $this->assertSame('text', $payload['delivery_mode']);
        $this->assertSame(['text', 'markdown', 'structured', 'json'], $payload['available_formats']);
        $this->assertSame('# invoice INV-001', $payload['payloads']['text']['text']);
        $this->assertTrue($payload['payloads']['structured']['available']);
        $this->assertSame([['page_index' => 1]], $payload['payloads']['structured']['pages']);
        $this->assertSame('ReadMcpResourceTool', $payload['access_guide']['read_via']);
    }

    #[Test]
    public function it_returns_an_error_for_unsupported_resource_uris(): void
    {
        $response = $this->tool->handle(new Request([
            'resource_uri' => 'ledgerleap://unsupported/resource',
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Unsupported LedgerLeap resource URI', $response->content());
    }

    #[Test]
    public function it_returns_an_authentication_error_when_no_user_is_authenticated(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->tool->handle(new Request([
            'resource_uri' => 'ledgerleap://bootstrap/copilot',
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('MCP_AUTH_TOKEN environment variable is not set', $response->content());
    }
}
