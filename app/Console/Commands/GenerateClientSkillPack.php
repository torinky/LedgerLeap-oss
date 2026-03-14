<?php

namespace App\Console\Commands;

use App\Services\Ai\ClientSkillBootstrapService;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateClientSkillPack extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:bootstrap-client-skills
        {--client=* : Target clients (copilot, claude-code, gemini-cli, openai-agents)}
        {--capability=* : Capability ids to include. Defaults to all active capabilities}
        {--output=.generated/ai-clients : Output directory}
        {--lang=ja : Output language label}
        {--include-planned : Include planned capabilities in generated README/snippets}
        {--force : Delete an existing non-empty output directory before generating}';

    /**
     * @var string
     */
    protected $description = 'Generate optional downstream client bootstrap export packs from AI capability manifests';

    public function __construct(
        private readonly ClientSkillBootstrapService $bootstrapService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->bootstrapService->generate(
                clients: (array) $this->option('client'),
                capabilityIds: (array) $this->option('capability'),
                outputPath: (string) $this->option('output'),
                language: (string) $this->option('lang'),
                includePlanned: (bool) $this->option('include-planned'),
                force: (bool) $this->option('force'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Generated LedgerLeap client bootstrap pack.');
        $this->line('Output: '.$result['output_path']);
        $this->line('Clients: '.implode(', ', $result['clients']));
        $this->line('Active capabilities: '.implode(', ', $result['active_capabilities']));
        $this->line('Planned capabilities: '.implode(', ', $result['planned_capabilities']));
        $this->line('Generated files: '.count($result['generated_files']));

        foreach ($result['generated_files'] as $file) {
            $this->line(' - '.$file);
        }

        return self::SUCCESS;
    }
}
