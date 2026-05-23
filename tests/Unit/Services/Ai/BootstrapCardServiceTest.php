<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\BootstrapCardService;
use App\Services\Ai\CapabilityManifestRepository;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(BootstrapCardService::class)]
class BootstrapCardServiceTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/bootstrap-cards');
        File::deleteDirectory($this->manifestPath);
        File::ensureDirectoryExists($this->manifestPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestPath);

        parent::tearDown();
    }

    #[Test]
    public function it_renders_a_static_bootstrap_card_for_copilot(): void
    {
        $this->putManifest('ledger-search', 'active', '検索 capability');
        $this->putManifest('ledger-update', 'active', '更新 capability');
        $this->putManifest('workflow-review', 'planned', 'planned capability');

        $service = new BootstrapCardService(new CapabilityManifestRepository($this->manifestPath));

        $card = $service->render('copilot');

        $this->assertStringContainsString('# LedgerLeap bootstrap card: copilot', $card);
        $this->assertStringContainsString('resource_template: `ledgerleap://bootstrap/{client}`', $card);
        $this->assertStringContainsString('resource_uri: `ledgerleap://bootstrap/copilot`', $card);
        $this->assertStringContainsString('GetClientBootstrapManifestTool', $card);
        $this->assertStringContainsString('`ledger-search`: 検索 capability', $card);
        $this->assertStringContainsString('`ledger-update`: 更新 capability', $card);
        $this->assertStringNotContainsString('planned capability', $card);
        $this->assertStringContainsString('`skills/{capability}/SKILL.md`', $card);
        $this->assertStringContainsString('`prompts/{capability}.prompt.md`', $card);
        $this->assertStringContainsString('developer-facing internals は返しません', $card);
    }

    #[Test]
    public function it_renders_client_specific_layout_for_openai_agents(): void
    {
        $this->putManifest('ledger-search', 'active', '検索 capability');

        $service = new BootstrapCardService(new CapabilityManifestRepository($this->manifestPath));

        $card = $service->render('openai-agents');

        $this->assertStringContainsString('suggested_root_directory: `bootstrap/openai-agents`', $card);
        $this->assertStringContainsString('`templates/ledger_agents.py`', $card);
        $this->assertStringNotContainsString('`prompts/{capability}.prompt.md`', $card);
    }

    #[Test]
    public function it_rejects_unsupported_clients(): void
    {
        $service = new BootstrapCardService(new CapabilityManifestRepository($this->manifestPath));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported bootstrap client: unknown-client');

        $service->render('unknown-client');
    }

    private function putManifest(string $id, string $status, string $summary): void
    {
        File::put($this->manifestPath.'/'.$id.'.yaml', implode("\n", [
            'id: '.$id,
            'status: '.$status,
            'summary: '.$summary,
            'primary_user_goals:',
            '  - '.$id.' goal',
            'required_guides:',
            '  - ledgerleap://guides/'.$id,
            '',
        ]));
    }
}
