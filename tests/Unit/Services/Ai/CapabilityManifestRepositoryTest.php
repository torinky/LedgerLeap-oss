<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\CapabilityManifestRepository;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CapabilityManifestRepository::class)]
class CapabilityManifestRepositoryTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/capability-manifests');
        File::deleteDirectory($this->manifestPath);
        File::ensureDirectoryExists($this->manifestPath);

        File::put($this->manifestPath.'/ledger-search.yaml', <<<'YAML'
id: ledger-search
status: active
summary: Search ledgers
YAML);

        File::put($this->manifestPath.'/ledger-update.yaml', <<<'YAML'
id: ledger-update
status: planned
summary: Update ledgers
YAML);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestPath);

        parent::tearDown();
    }

    #[Test]
    public function it_loads_all_yaml_manifests(): void
    {
        $repository = new CapabilityManifestRepository($this->manifestPath);

        $manifests = $repository->all();

        $this->assertCount(2, $manifests);
        $this->assertSame(['ledger-search', 'ledger-update'], $manifests->pluck('id')->all());
    }

    #[Test]
    public function it_filters_active_manifests(): void
    {
        $repository = new CapabilityManifestRepository($this->manifestPath);

        $active = $repository->active();
        $planned = $repository->planned();

        $this->assertSame(['ledger-search'], $active->pluck('id')->all());
        $this->assertSame(['ledger-update'], $planned->pluck('id')->all());
    }

    #[Test]
    public function it_filters_by_capability_ids(): void
    {
        $repository = new CapabilityManifestRepository($this->manifestPath);

        $manifests = $repository->findByIds(['ledger-update']);

        $this->assertCount(1, $manifests);
        $this->assertSame('ledger-update', $manifests->first()['id']);
    }
}
