<?php

namespace Tests\Feature\Mcp;

use App\Enums\AttachedFileStatus;
use App\Mcp\Resources\LedgerAttachmentBinaryResource;
use App\Mcp\Servers\LedgerLeapServer;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(LedgerAttachmentBinaryResource::class)]
#[CoversClass(LedgerLeapServer::class)]
class LedgerAttachmentBinaryResourceTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabaseWithTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['mcp:*']);
        Auth::setUser($this->user);

        Storage::fake('public');
    }

    #[Test]
    public function it_reads_attachment_binary_content_via_resources_read(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [],
        ]);

        $attachmentPath = 'tenants/'.$tenantId.'/Ledger/Attachments/'.$ledger->ledger_define_id.'/hash-blob.pdf';
        $binaryContent = "%PDF-1.4\n%LedgerLeap binary attachment test\n";

        Storage::disk('public')->put($attachmentPath, $binaryContent);

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'receipt.pdf',
            'hashedbasename' => 'hash-blob.pdf',
            'column_id' => 1,
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => $attachmentPath,
            'status' => AttachedFileStatus::COMPLETED,
        ]);

        $response = $this->runServerMethod('resources/read', [
            'uri' => sprintf('ledgerleap://ledger/%s/%s/attachments/%s/blob', $tenantId, $ledger->id, $attachment->id),
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertCount(1, $response['result']['contents']);
        $this->assertSame(
            sprintf('ledgerleap://ledger/%s/%s/attachments/%s/blob', $tenantId, $ledger->id, $attachment->id),
            $response['result']['contents'][0]['uri']
        );
        $this->assertSame('application/pdf', $response['result']['contents'][0]['mimeType']);
        $this->assertSame(base64_encode($binaryContent), $response['result']['contents'][0]['blob']);
    }

    #[Test]
    public function it_returns_an_error_when_the_tenant_does_not_match_the_current_tenant(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [],
        ]);

        $attachmentPath = 'tenants/'.$tenantId.'/Ledger/Attachments/'.$ledger->ledger_define_id.'/hash-tenant-mismatch.pdf';
        Storage::disk('public')->put($attachmentPath, '%PDF-1.4 tenant mismatch test');

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'tenant-mismatch.pdf',
            'hashedbasename' => 'hash-tenant-mismatch.pdf',
            'column_id' => 1,
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => $attachmentPath,
            'status' => AttachedFileStatus::COMPLETED,
        ]);

        $response = $this->runServerMethod('resources/read', [
            'uri' => sprintf('ledgerleap://ledger/%s/%s/attachments/%s/blob', 'other-tenant', $ledger->id, $attachment->id),
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('tenant mismatch', $response['error']['message']);
    }

    #[Test]
    public function it_returns_an_error_when_the_attachment_file_is_missing(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [],
        ]);

        $attachmentPath = 'tenants/'.$tenantId.'/Ledger/Attachments/'.$ledger->ledger_define_id.'/missing-blob.pdf';

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'missing-blob.pdf',
            'hashedbasename' => 'missing-blob.pdf',
            'column_id' => 1,
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => $attachmentPath,
            'status' => AttachedFileStatus::COMPLETED,
        ]);

        $response = $this->runServerMethod('resources/read', [
            'uri' => sprintf('ledgerleap://ledger/%s/%s/attachments/%s/blob', $tenantId, $ledger->id, $attachment->id),
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('could not be found', $response['error']['message']);
    }

    #[Test]
    public function it_returns_an_authentication_error_when_the_user_is_not_authenticated(): void
    {
        $tenantId = (string) tenant('id');

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenantId,
            'content_attached' => [],
        ]);

        $attachmentPath = 'tenants/'.$tenantId.'/Ledger/Attachments/'.$ledger->ledger_define_id.'/hash-unauthenticated.pdf';
        Storage::disk('public')->put($attachmentPath, '%PDF-1.4 unauthenticated test');

        $attachment = AttachedFile::factory()->forLedger($ledger)->create([
            'tenant_id' => $tenantId,
            'filename' => 'unauthenticated.pdf',
            'hashedbasename' => 'hash-unauthenticated.pdf',
            'column_id' => 1,
            'mime' => 'application/pdf',
            'original_mime_type' => 'application/pdf',
            'path' => $attachmentPath,
            'status' => AttachedFileStatus::COMPLETED,
        ]);

        $previousToken = getenv('MCP_AUTH_TOKEN');
        $this->actingAsGuest();
        putenv('MCP_AUTH_TOKEN=this-token-does-not-exist');

        try {
            $response = $this->runServerMethod('resources/read', [
                'uri' => sprintf('ledgerleap://ledger/%s/%s/attachments/%s/blob', $tenantId, $ledger->id, $attachment->id),
            ]);
        } finally {
            if ($previousToken === false) {
                putenv('MCP_AUTH_TOKEN');
            } else {
                putenv('MCP_AUTH_TOKEN='.$previousToken);
            }
        }

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Authentication failed', $response['error']['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runServerMethod(string $method, array $params = []): array
    {
        $server = new class(new FakeTransporter) extends LedgerLeapServer
        {
            public function runForTest(JsonRpcRequest $request): iterable|JsonRpcResponse
            {
                return $this->runMethodHandle($request, $this->createContext());
            }
        };

        $server->start();

        $request = new JsonRpcRequest(
            id: uniqid('mcp-', true),
            method: $method,
            params: $params,
        );

        try {
            $response = $server->runForTest($request);
        } catch (JsonRpcException $exception) {
            return $exception->toJsonRpcResponse()->toArray();
        }

        if (is_iterable($response)) {
            foreach ($response as $message) {
                if ($message instanceof JsonRpcResponse && array_key_exists('id', $message->toArray())) {
                    return $message->toArray();
                }
            }
        }

        return $response->toArray();
    }
}
