<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\BootstrapManifestService;
use App\Services\Ai\CapabilityManifestRepository;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(BootstrapManifestService::class)]
class BootstrapManifestServiceTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/bootstrap-manifests');
        File::deleteDirectory($this->manifestPath);
        File::ensureDirectoryExists($this->manifestPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestPath);

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_a_bootstrap_bundle_for_an_operator_profile(): void
    {
        $this->putManifest('ledger-search', 'active', ['ledgerleap://guides/search-strategy']);
        $this->putManifest('ledger-create', 'active', ['ledgerleap://guides/create-strategy']);
        $this->putManifest('workflow-review', 'active', ['ledgerleap://guides/workflow-review-strategy']);

        $service = new BootstrapManifestService(new CapabilityManifestRepository($this->manifestPath));

        $manifest = $service->resolve([
            'client_type' => 'copilot',
            'role_profile' => 'operator',
            'model_profile' => 'small-local',
            'language' => 'ja',
        ]);

        $this->assertSame('copilot', $manifest['client_type']);
        $this->assertSame('operator', $manifest['role_profile']['id']);
        $this->assertSame('small-local', $manifest['model_profile']['id']);
        $this->assertSame(
            ['ledger-search', 'ledger-create', 'workflow-review'],
            array_column($manifest['recommended_capabilities'], 'id')
        );
        $this->assertSame([], $manifest['warnings']);
        $this->assertSame('skills/ledger-search/SKILL.md', $manifest['files'][0]['relative_path']);
        $this->assertSame('ledgerleap://bootstrap/copilot', $manifest['resources'][0]['uri']);
        $this->assertSame('implemented', $manifest['resources'][0]['status']);
        $this->assertSame('implemented', $manifest['prompts'][0]['status']);
    }

    #[Test]
    public function it_reports_missing_or_inactive_capabilities_in_warnings(): void
    {
        $this->putManifest('ledger-search', 'active', ['ledgerleap://guides/search-strategy']);
        $this->putManifest('workflow-review', 'planned', ['ledgerleap://guides/workflow-review-strategy']);

        $service = new BootstrapManifestService(new CapabilityManifestRepository($this->manifestPath));

        $manifest = $service->resolve([
            'client_type' => 'gemini-cli',
            'role_profile' => 'field-leader',
            'model_profile' => 'general-local',
            'language' => 'ja',
        ]);

        $this->assertSame(['ledger-search'], array_column($manifest['recommended_capabilities'], 'id'));
        $this->assertSame([
            'Capability manifest is missing or inactive: ledger-update',
            'Capability manifest is missing or inactive: workflow-review',
        ], $manifest['warnings']);
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
