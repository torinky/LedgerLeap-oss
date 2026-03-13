<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ClientSkillBootstrapService
{
    /**
     * @var array<int, string>
     */
    public const SUPPORTED_CLIENTS = [
        'claude-code',
        'copilot',
        'gemini-cli',
        'openai-agents',
    ];

    public function __construct(
        private readonly CapabilityManifestRepository $manifestRepository,
    ) {}

    /**
     * @return array{
     *     output_path:string,
     *     clients: array<int, string>,
     *     active_capabilities: array<int, string>,
     *     planned_capabilities: array<int, string>,
     *     generated_files: array<int, string>
     * }
     */
    public function generate(
        array $clients,
        array $capabilityIds,
        string $outputPath,
        string $language = 'ja',
        bool $includePlanned = false,
        bool $force = false,
    ): array {
        $clients = collect($clients)
            ->map(fn (string $client) => trim($client))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($clients === []) {
            $clients = self::SUPPORTED_CLIENTS;
        }

        $unsupportedClients = array_values(array_diff($clients, self::SUPPORTED_CLIENTS));
        if ($unsupportedClients !== []) {
            throw new RuntimeException('Unsupported client(s): '.implode(', ', $unsupportedClients));
        }

        $activeManifests = $this->manifestRepository->active($capabilityIds)->values();
        $plannedManifests = $includePlanned
            ? $this->manifestRepository->planned($capabilityIds)->values()
            : collect();

        if ($activeManifests->isEmpty() && $plannedManifests->isEmpty()) {
            throw new RuntimeException('No capability manifests matched the given filters.');
        }

        $resolvedOutputPath = $this->resolveOutputPath($outputPath);
        $this->prepareOutputDirectory($resolvedOutputPath, $force);

        $generatedFiles = [];

        foreach ($clients as $client) {
            $clientFiles = $this->generateClientPack(
                client: $client,
                activeManifests: $activeManifests->all(),
                plannedManifests: $plannedManifests->all(),
                outputPath: $resolvedOutputPath,
                language: $language,
            );

            foreach ($clientFiles as $file) {
                $generatedFiles[] = $file;
            }
        }

        sort($generatedFiles);

        return [
            'output_path' => $resolvedOutputPath,
            'clients' => $clients,
            'active_capabilities' => $activeManifests->pluck('id')->values()->all(),
            'planned_capabilities' => $plannedManifests->pluck('id')->values()->all(),
            'generated_files' => $generatedFiles,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     * @return array<int, string>
     */
    private function generateClientPack(
        string $client,
        array $activeManifests,
        array $plannedManifests,
        string $outputPath,
        string $language,
    ): array {
        $clientPath = $outputPath.DIRECTORY_SEPARATOR.$client;
        File::ensureDirectoryExists($clientPath);

        return match ($client) {
            'copilot' => $this->generateCopilotPack(
                $clientPath,
                $activeManifests,
                $plannedManifests,
                $language,
            ),
            'claude-code' => $this->generateClaudeCodePack(
                $clientPath,
                $activeManifests,
                $plannedManifests,
                $language,
            ),
            'gemini-cli' => $this->generateGeminiCliPack(
                $clientPath,
                $activeManifests,
                $plannedManifests,
                $language,
            ),
            'openai-agents' => $this->generateOpenAiAgentsPack(
                $clientPath,
                $activeManifests,
                $plannedManifests,
                $language,
            ),
            default => throw new RuntimeException('Unsupported client: '.$client),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     * @return array<int, string>
     */
    private function generateCopilotPack(
        string $clientPath,
        array $activeManifests,
        array $plannedManifests,
        string $language,
    ): array {
        $files = [];

        foreach ($activeManifests as $manifest) {
            $capabilityId = (string) Arr::get($manifest, 'id');
            $files[] = $this->writeFile(
                $clientPath.'/skills/'.$capabilityId.'/SKILL.md',
                $this->renderSkill($manifest, 'copilot', $language),
            );
            $files[] = $this->writeFile(
                $clientPath.'/prompts/'.$capabilityId.'.prompt.md',
                $this->renderCopilotPrompt($manifest, $language),
            );
        }

        $files[] = $this->writeFile(
            $clientPath.'/README.md',
            $this->renderClientReadme(
                'copilot',
                $activeManifests,
                $plannedManifests,
                $language,
            ),
        );

        return $files;
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     * @return array<int, string>
     */
    private function generateClaudeCodePack(
        string $clientPath,
        array $activeManifests,
        array $plannedManifests,
        string $language,
    ): array {
        $files = [];

        foreach ($activeManifests as $manifest) {
            $capabilityId = (string) Arr::get($manifest, 'id');
            $files[] = $this->writeFile(
                $clientPath.'/skills/'.$capabilityId.'/SKILL.md',
                $this->renderSkill($manifest, 'claude-code', $language),
            );
            $files[] = $this->writeFile(
                $clientPath.'/agents/'.$this->toAgentName($capabilityId).'.md',
                $this->renderClaudeAgent($manifest, $language),
            );
        }

        $files[] = $this->writeFile(
            $clientPath.'/CLAUDE.md.snippet',
            $this->renderClaudeSnippet($activeManifests, $plannedManifests),
        );
        $files[] = $this->writeFile(
            $clientPath.'/README.md',
            $this->renderClientReadme(
                'claude-code',
                $activeManifests,
                $plannedManifests,
                $language,
            ),
        );

        return $files;
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     * @return array<int, string>
     */
    private function generateGeminiCliPack(
        string $clientPath,
        array $activeManifests,
        array $plannedManifests,
        string $language,
    ): array {
        $files = [];

        foreach ($activeManifests as $manifest) {
            $capabilityId = (string) Arr::get($manifest, 'id');
            $files[] = $this->writeFile(
                $clientPath.'/skills/'.$capabilityId.'/SKILL.md',
                $this->renderSkill($manifest, 'gemini-cli', $language),
            );
        }

        $files[] = $this->writeFile(
            $clientPath.'/GEMINI.md.snippet',
            $this->renderGeminiSnippet($activeManifests, $plannedManifests),
        );
        $files[] = $this->writeFile(
            $clientPath.'/README.md',
            $this->renderClientReadme(
                'gemini-cli',
                $activeManifests,
                $plannedManifests,
                $language,
            ),
        );

        return $files;
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     * @return array<int, string>
     */
    private function generateOpenAiAgentsPack(
        string $clientPath,
        array $activeManifests,
        array $plannedManifests,
        string $language,
    ): array {
        $files = [];

        $files[] = $this->writeFile(
            $clientPath.'/templates/ledger_agents.py',
            $this->renderOpenAiAgentsTemplate($activeManifests),
        );
        $files[] = $this->writeFile(
            $clientPath.'/README.md',
            $this->renderClientReadme(
                'openai-agents',
                $activeManifests,
                $plannedManifests,
                $language,
            ),
        );

        return $files;
    }

    private function renderSkill(array $manifest, string $client, string $language): string
    {
        $capabilityId = (string) Arr::get($manifest, 'id');
        $summary = (string) Arr::get($manifest, 'summary');
        $whenToUse = $this->renderBulletList((array) Arr::get($manifest, 'when_to_use', []));
        $recommendedFlow = $this->renderOrderedFlow((array) Arr::get($manifest, 'recommended_flow', []));
        $constraints = $this->renderBulletList((array) Arr::get($manifest, 'ledgerleap_constraints', []));
        $examples = $this->renderExamples((array) Arr::get($manifest, 'examples', []));
        $toolList = $this->renderBulletList((array) Arr::get($manifest, 'required_mcp_tools', []));

        return trim(<<<MARKDOWN
---
name: {$capabilityId}
description: {$summary}. Use this when the task matches LedgerLeap {$capabilityId} work on {$client}.
---

# {$capabilityId}

## Purpose
{$summary}

## Target client
- {$client}
- language: {$language}

## When to use
{$whenToUse}

## Recommended flow
{$recommendedFlow}

## Required LedgerLeap tools
{$toolList}

## LedgerLeap-specific constraints
{$constraints}

## Example requests
{$examples}
MARKDOWN)."\n";
    }

    private function renderCopilotPrompt(array $manifest, string $language): string
    {
        $capabilityId = (string) Arr::get($manifest, 'id');
        $summary = (string) Arr::get($manifest, 'summary');

        return trim(<<<MARKDOWN
---
description: 'LedgerLeap {$capabilityId} workflow'
agent: 'agent'
---
Use the `{$capabilityId}` skill for this task.

Goal: {$summary}
Language: {$language}

Follow the recommended LedgerLeap flow from the skill.
If the request is ambiguous, ask only for the missing minimum details.

Request:

after the blank line, paste the user request or append details in chat.
MARKDOWN)."\n";
    }

    private function renderClaudeAgent(array $manifest, string $language): string
    {
        $capabilityId = (string) Arr::get($manifest, 'id');
        $agentName = $this->toAgentName($capabilityId);
        $summary = (string) Arr::get($manifest, 'summary');

        return trim(<<<MARKDOWN
---
name: {$agentName}
description: {$summary}. Use proactively for LedgerLeap {$capabilityId} tasks.
skills:
  - {$capabilityId}
model: inherit
---
You are the {$agentName} specialist.

Operate in {$language} unless the user asks otherwise.
Use the preloaded `{$capabilityId}` skill as your primary workflow source.
Keep responses concise, verify missing details, and stay within LedgerLeap constraints.
MARKDOWN)."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     */
    private function renderClaudeSnippet(array $activeManifests, array $plannedManifests): string
    {
        $skills = collect($activeManifests)
            ->pluck('id')
            ->map(fn ($id) => '- '.$id)
            ->implode("\n");
        $planned = collect($plannedManifests)
            ->pluck('id')
            ->map(fn ($id) => '- '.$id.' (planned)')
            ->implode("\n");

        return trim(<<<MARKDOWN
# LedgerLeap bootstrap snippet for Claude Code

## Recommended skills
{$skills}

## Planned capabilities
{$planned}

## Notes
- Load the skills into `.claude/skills/`
- Load the generated agents into `.claude/agents/`
- Connect the LedgerLeap MCP server before using these assets
MARKDOWN)."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     */
    private function renderGeminiSnippet(array $activeManifests, array $plannedManifests): string
    {
        $skills = collect($activeManifests)
            ->pluck('id')
            ->map(fn ($id) => '- '.$id)
            ->implode("\n");
        $planned = collect($plannedManifests)
            ->pluck('id')
            ->map(fn ($id) => '- '.$id.' (planned)')
            ->implode("\n");

        return trim(<<<MARKDOWN
# LedgerLeap bootstrap snippet for Gemini CLI

## Recommended skills
{$skills}

## Planned capabilities
{$planned}

## Notes
- Sync or copy these skills into the Gemini skills directory
- Keep `.github` as the source of truth and treat this pack as generated output
- Connect the LedgerLeap MCP server before using these skills
MARKDOWN)."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     */
    private function renderOpenAiAgentsTemplate(array $activeManifests): string
    {
        $agentLines = collect($activeManifests)
            ->map(function (array $manifest) {
                $capabilityId = (string) Arr::get($manifest, 'id');
                $agentName = Str::studly(str_replace('-', '_', $capabilityId)).'Agent';
                $summary = addslashes((string) Arr::get($manifest, 'summary'));

                return <<<PY
{$agentName} = Agent(
    name="{$capabilityId}",
    instructions="{$summary}",
    # TODO: attach MCP-backed tools / handoffs for this capability
)
PY;
            })
            ->implode("\n\n");

        return trim(<<<PY
from agents import Agent

# Generated by LedgerLeap ai:bootstrap-client-skills
# Replace TODO sections with concrete MCP manager / tool wiring.

{$agentLines}
PY)."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeManifests
     * @param  array<int, array<string, mixed>>  $plannedManifests
     */
    private function renderClientReadme(
        string $client,
        array $activeManifests,
        array $plannedManifests,
        string $language,
    ): string {
        $active = $this->renderCapabilitySummary($activeManifests);
        $planned = $this->renderCapabilitySummary($plannedManifests);

        return trim(<<<MARKDOWN
# LedgerLeap {$client} bootstrap pack

This directory was generated from `resources/ai/capabilities/*.yaml`.

## Language
- {$language}

## Included active capabilities
{$active}

## Planned capabilities
{$planned}

## Usage notes
- Treat this directory as generated output and regenerate when manifests change.
- Review MCP server names, auth settings, and local file placement before copying into a real client config.
- `ledger-update` remains planned until the Update API / Update MCP Tool are implemented.
MARKDOWN)."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $manifests
     */
    private function renderCapabilitySummary(array $manifests): string
    {
        if ($manifests === []) {
            return '- none';
        }

        return collect($manifests)
            ->map(fn (array $manifest) => '- `'.Arr::get($manifest, 'id').'`: '.Arr::get($manifest, 'summary'))
            ->implode("\n");
    }

    /**
     * @param  array<int, string>  $items
     */
    private function renderBulletList(array $items): string
    {
        $items = array_values(array_filter(array_map(
            fn ($item) => is_scalar($item) ? (string) $item : null,
            $items,
        )));

        if ($items === []) {
            return '- none';
        }

        return collect($items)
            ->map(fn (string $item) => '- '.$item)
            ->implode("\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function renderOrderedFlow(array $steps): string
    {
        if ($steps === []) {
            return '1. No defined flow';
        }

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) {
                $title = (string) Arr::get($step, 'step', 'step');
                $description = (string) Arr::get($step, 'description', '');

                return ($index + 1).'. `'.$title.'`: '.$description;
            })
            ->implode("\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $examples
     */
    private function renderExamples(array $examples): string
    {
        if ($examples === []) {
            return '- none';
        }

        return collect($examples)
            ->map(function (array $example) {
                $user = (string) Arr::get($example, 'user', '');
                $intent = (string) Arr::get($example, 'intent', '');

                return '- `'.$user.'`'.($intent !== '' ? ' — '.$intent : '');
            })
            ->implode("\n");
    }

    private function toAgentName(string $capabilityId): string
    {
        return $capabilityId.'-agent';
    }

    private function resolveOutputPath(string $outputPath): string
    {
        if ($outputPath === '') {
            throw new RuntimeException('Output path must not be empty.');
        }

        if (Str::startsWith($outputPath, ['/', '\\']) || preg_match('/^[A-Za-z]:\\\\/', $outputPath) === 1) {
            return $outputPath;
        }

        return base_path($outputPath);
    }

    private function prepareOutputDirectory(string $outputPath, bool $force): void
    {
        if (! File::exists($outputPath)) {
            File::ensureDirectoryExists($outputPath);

            return;
        }

        $isEmpty = File::isEmptyDirectory($outputPath);

        if (! $isEmpty && ! $force) {
            throw new RuntimeException('Output directory is not empty. Use --force to overwrite: '.$outputPath);
        }

        if (! $isEmpty && $force) {
            File::deleteDirectory($outputPath);
            File::ensureDirectoryExists($outputPath);
        }
    }

    private function writeFile(string $path, string $contents): string
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }
}
