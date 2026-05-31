<?php

namespace Tests\Unit\Mcp\Resources;

use App\Enums\AttachedFileStatus;
use App\Mcp\Resources\LedgerAttachmentResource;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerAttachmentResourceService;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerAttachmentResourceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private LedgerAttachmentResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['mcp:*']);
        Auth::setUser($this->user);

        $this->resource = new LedgerAttachmentResource(
            app(LedgerAttachmentResourceService::class)
        );
    }

    #[Test]
    public function it_returns_attachment_envelope_for_the_current_tenant(): void
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

        $response = $this->resource->handle(new Request([
            'tenant' => $tenantId,
            'ledger' => $ledger->id,
            'attachment' => $attachment->id,
        ]));

        $this->assertFalse($response->isError());
        $payload = json_decode($response->content()->__toString(), true);

        $this->assertSame('ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}', $payload['resource_template']);
        $this->assertSame("ledgerleap://ledger/{$tenantId}/{$ledger->id}/attachments/{$attachment->id}", $payload['resource_uri']);
        $this->assertSame('mcp_resource', $payload['access_guide']['resource_type']);
        $this->assertSame('resources/read', $payload['access_guide']['read_via']);
        $this->assertSame($payload['resource_uri'], $payload['access_guide']['uri']);
        $this->assertStringContainsString('MCP resource URI', $payload['access_guide']['instructions'][0]);
        $this->assertStringContainsString('resources/read', $payload['access_guide']['instructions'][1]);
        $this->assertStringContainsString('resources/read(uri="ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}")', $payload['access_guide']['instructions'][1]);
        $this->assertStringContainsString('MCP トークン', $payload['access_guide']['instructions'][2]);
        $this->assertStringContainsString('routes.download', $payload['access_guide']['instructions'][5]);
        $this->assertStringContainsString('認証済みセッション前提', $payload['access_guide']['instructions'][6]);
        $this->assertStringContainsString('ログイン HTML', $payload['access_guide']['instructions'][6]);
        $this->assertSame(7, count($payload['access_guide']['instructions']));
        $this->assertSame($attachment->id, $payload['attachment_id']);
        $this->assertSame('invoice.json', $payload['filename']);
        $this->assertSame('primary', $payload['role']);
        $this->assertSame(1, $payload['order']);
        $this->assertSame('vlm', $payload['source']);
        $this->assertSame('application/json', $payload['mime_type']);
        $this->assertSame(['text', 'markdown', 'structured', 'json'], $payload['available_formats']);
        $this->assertSame('text', $payload['delivery_mode']);
        $this->assertSame('# invoice INV-001', $payload['payloads']['text']['text']);
        $this->assertTrue($payload['payloads']['structured']['available']);
        $this->assertSame([['page_index' => 1]], $payload['payloads']['structured']['pages']);
        $this->assertFalse($payload['payloads']['visual']['available']);
        $this->assertTrue($payload['routes']['download']['available']);
        $this->assertTrue($payload['routes']['inspector']['available']);
    }

    #[Test]
    public function it_rejects_tenant_mismatch_for_attachment_resources(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [],
        ]);

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'invoice.json',
            'hashedbasename' => 'hash-123',
            'column_id' => 1,
            'mime' => 'application/json',
            'original_mime_type' => 'application/json',
            'status' => AttachedFileStatus::COMPLETED,
        ]);

        $otherTenant = Tenant::factory()->create();
        tenancy()->initialize($otherTenant);

        $response = $this->resource->handle(new Request([
            'tenant' => $tenantId,
            'ledger' => $ledger->id,
            'attachment' => $attachment->id,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('tenant mismatch', $response->content());
        $this->assertStringContainsString((string) $otherTenant->id, $response->content());
    }

    #[Test]
    public function it_rejects_unauthenticated_attachment_resource_requests(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->resource->handle(new Request([
            'tenant' => 'tenant-a',
            'ledger' => 1,
            'attachment' => 1,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('MCP_AUTH_TOKEN environment variable is not set', $response->content());
    }
}
