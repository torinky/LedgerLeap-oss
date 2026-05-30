<?php

namespace Tests\Unit\Console;

use App\Console\Commands\GenerateClientSkillPack;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GenerateClientSkillPack::class)]
class GenerateClientSkillPackCommandTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('framework/testing/command-client-skills');
        File::deleteDirectory($this->outputPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputPath);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_client_pack_via_artisan_command(): void
    {
        $this->artisan('ai:bootstrap-client-skills', [
            '--client' => ['copilot'],
            '--output' => $this->outputPath,
            '--include-planned' => true,
        ])
            ->expectsOutput('Generated LedgerLeap client bootstrap pack.')
            ->assertExitCode(0);

        $this->assertFileExists($this->outputPath.'/copilot/README.md');
        $this->assertFileExists($this->outputPath.'/copilot/skills/ledger-search/SKILL.md');
    }

    #[Test]
    public function it_fails_for_unsupported_clients(): void
    {
        $this->artisan('ai:bootstrap-client-skills', [
            '--client' => ['unknown-client'],
            '--output' => $this->outputPath,
        ])
            ->expectsOutputToContain('Unsupported client(s): unknown-client')
            ->assertExitCode(1);
    }
}
