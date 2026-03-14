<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Prompts\BootstrapClientSkillsPrompt;
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

#[CoversClass(BootstrapClientSkillsPrompt::class)]
class BootstrapClientSkillsPromptTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_lists_the_bootstrap_client_skills_prompt(): void
    {
        $response = $this->runServerMethod('prompts/list');

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('prompts', $response['result']);

        $prompt = collect($response['result']['prompts'])->firstWhere('name', 'bootstrap-client-skills');

        $this->assertNotNull($prompt);
        $this->assertSame('Bootstrap Client Skills', $prompt['title']);
        $this->assertSame(
            'Returns a short onboarding prompt starter with first questions, checks, '
            .'and next actions for LedgerLeap clients.',
            $prompt['description']
        );
        $this->assertSame(
            ['client_type', 'role_profile', 'model_profile', 'language'],
            array_column($prompt['arguments'], 'name')
        );
        $this->assertTrue($prompt['arguments'][0]['required']);
        $this->assertTrue($prompt['arguments'][1]['required']);
        $this->assertFalse($prompt['arguments'][2]['required']);
        $this->assertFalse($prompt['arguments'][3]['required']);
    }

    #[Test]
    public function it_returns_a_compact_prompt_starter_for_small_local_operator(): void
    {
        $response = $this->runServerMethod('prompts/get', [
            'name' => 'bootstrap-client-skills',
            'arguments' => [
                'client_type' => 'copilot',
                'role_profile' => 'operator',
                'model_profile' => 'small-local',
                'language' => 'ja',
            ],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertCount(2, $response['result']['messages']);
        $this->assertSame('assistant', $response['result']['messages'][0]['role']);
        $this->assertSame('user', $response['result']['messages'][1]['role']);

        $combined = collect($response['result']['messages'])
            ->map(fn (array $message) => $message['content']['text'] ?? '')
            ->implode("\n");

        $this->assertStringContainsString('最初の質問例', $combined);
        $this->assertStringContainsString('確認事項', $combined);
        $this->assertStringContainsString('次アクション', $combined);
        $this->assertStringContainsString('今日対応すべき承認待ちを見せて。', $combined);
        $this->assertStringContainsString('ledgerleap://bootstrap/copilot', $combined);
        $this->assertStringNotContainsString('新しい日報を作る前に必要な項目を教えて。', $combined);
        $this->assertStringNotContainsString('Laravel', $combined);
        $this->assertStringNotContainsString('Mroonga', $combined);
        $this->assertStringNotContainsString('DB', $combined);
    }

    #[Test]
    public function it_validates_supported_prompt_arguments(): void
    {
        LedgerLeapServer::prompt(BootstrapClientSkillsPrompt::class, [
            'client_type' => 'unknown-client',
            'role_profile' => 'operator',
        ])
            ->assertHasErrors([
                'client_type は copilot, claude-code, gemini-cli, openai-agents のいずれかを指定してください。',
            ]);
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
