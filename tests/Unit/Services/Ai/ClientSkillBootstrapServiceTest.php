<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\CapabilityManifestRepository;
use App\Services\Ai\ClientSkillBootstrapService;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ClientSkillBootstrapService::class)]
class ClientSkillBootstrapServiceTest extends TestCase
{
    private string $manifestPath;

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/client-skill-manifests');
        $this->outputPath = storage_path('framework/testing/generated-client-skills');

        File::deleteDirectory($this->manifestPath);
        File::deleteDirectory($this->outputPath);
        File::ensureDirectoryExists($this->manifestPath);

        File::put($this->manifestPath.'/ledger-search.yaml', <<<'YAML'
id: ledger-search
status: active
summary: Search LedgerLeap records
when_to_use:
  - Search existing ledgers
recommended_flow:
  - step: keyword-search
    description: Start with keyword search
required_mcp_tools:
  - SearchLedgersTool
ledgerleap_constraints:
  - Use count mode when browsing
examples:
  - user: 昨日の日報を探して
    intent: Search by date
YAML);

        File::put($this->manifestPath.'/ledger-update.yaml', <<<'YAML'
id: ledger-update
status: planned
summary: Update LedgerLeap records
when_to_use:
  - Update a ledger
recommended_flow:
  - step: identify-target
    description: Find the record first
required_mcp_tools:
  - UpdateLedgerTool
ledgerleap_constraints:
  - Confirm the target before updating
YAML);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestPath);
        File::deleteDirectory($this->outputPath);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_client_specific_files_for_active_capabilities(): void
    {
        $service = new ClientSkillBootstrapService(new CapabilityManifestRepository($this->manifestPath));

        $result = $service->generate(
            clients: ['copilot', 'claude-code', 'gemini-cli'],
            capabilityIds: [],
            outputPath: $this->outputPath,
            language: 'ja',
            includePlanned: true,
            force: false,
        );

        $this->assertSame(['ledger-search'], $result['active_capabilities']);
        $this->assertSame(['ledger-update'], $result['planned_capabilities']);
        $this->assertFileExists($this->outputPath.'/copilot/skills/ledger-search/SKILL.md');
        $this->assertFileExists($this->outputPath.'/copilot/prompts/ledger-search.prompt.md');
        $this->assertFileExists($this->outputPath.'/claude-code/agents/ledger-search-agent.md');
        $this->assertFileExists($this->outputPath.'/gemini-cli/GEMINI.md.snippet');
        $this->assertStringContainsString('ledger-update', File::get($this->outputPath.'/copilot/README.md'));
    }

    #[Test]
    public function it_refuses_to_overwrite_a_non_empty_directory_without_force(): void
    {
        File::ensureDirectoryExists($this->outputPath);
        File::put($this->outputPath.'/keep.txt', 'existing');

        $service = new ClientSkillBootstrapService(new CapabilityManifestRepository($this->manifestPath));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output directory is not empty');

        $service->generate(
            clients: ['copilot'],
            capabilityIds: [],
            outputPath: $this->outputPath,
            language: 'ja',
            includePlanned: false,
            force: false,
        );
    }

    #[Test]
    public function it_overwrites_a_non_empty_directory_when_force_is_enabled(): void
    {
        File::ensureDirectoryExists($this->outputPath);
        File::put($this->outputPath.'/keep.txt', 'existing');

        $service = new ClientSkillBootstrapService(new CapabilityManifestRepository($this->manifestPath));

        $service->generate(
            clients: ['openai-agents'],
            capabilityIds: [],
            outputPath: $this->outputPath,
            language: 'ja',
            includePlanned: false,
            force: true,
        );

        $this->assertFileDoesNotExist($this->outputPath.'/keep.txt');
        $this->assertFileExists($this->outputPath.'/openai-agents/templates/ledger_agents.py');
    }
}
