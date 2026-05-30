<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Resources\BootstrapClientResource;
use App\Mcp\Servers\LedgerLeapServer;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(BootstrapClientResource::class)]
class BootstrapClientResourceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_lists_the_bootstrap_resource_template(): void
    {
        $response = $this->runServerMethod('resources/templates/list');

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('resourceTemplates', $response['result']);
        $this->assertCount(3, $response['result']['resourceTemplates']);
        $this->assertSame(
            'ledgerleap://bootstrap/{client}',
            $response['result']['resourceTemplates'][0]['uriTemplate']
        );
        $this->assertSame(
            'text/markdown',
            $response['result']['resourceTemplates'][0]['mimeType']
        );
        $this->assertSame(
            'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob',
            $response['result']['resourceTemplates'][1]['uriTemplate']
        );
        $this->assertSame(
            'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}',
            $response['result']['resourceTemplates'][2]['uriTemplate']
        );
    }

    #[Test]
    public function it_reads_a_bootstrap_card_for_a_supported_client_uri(): void
    {
        $response = $this->runServerMethod('resources/read', [
            'uri' => 'ledgerleap://bootstrap/copilot',
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertCount(1, $response['result']['contents']);
        $this->assertSame('ledgerleap://bootstrap/copilot', $response['result']['contents'][0]['uri']);
        $this->assertSame('text/markdown', $response['result']['contents'][0]['mimeType']);
        $this->assertStringContainsString(
            '# LedgerLeap bootstrap card: copilot',
            $response['result']['contents'][0]['text']
        );
        $this->assertStringContainsString(
            'GetClientBootstrapManifestTool',
            $response['result']['contents'][0]['text']
        );
        $this->assertStringNotContainsString(
            'BootstrapManifestService::resolve()',
            $response['result']['contents'][0]['text']
        );
    }

    #[Test]
    public function it_returns_a_resource_error_for_unsupported_clients(): void
    {
        $response = $this->runServerMethod('resources/read', [
            'uri' => 'ledgerleap://bootstrap/unknown-client',
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString(
            'Unsupported bootstrap client [unknown-client]',
            $response['error']['message']
        );
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
