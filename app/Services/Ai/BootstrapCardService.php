<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class BootstrapCardService
{
    public function __construct(
        private readonly CapabilityManifestRepository $manifestRepository,
    ) {}

    public function render(string $clientType): string
    {
        if (! in_array($clientType, ClientSkillBootstrapService::SUPPORTED_CLIENTS, true)) {
            throw new InvalidArgumentException('Unsupported bootstrap client: '.$clientType);
        }

        $activeCapabilities = $this->manifestRepository
            ->active()
            ->map(fn (array $manifest): array => [
                'id' => (string) Arr::get($manifest, 'id', ''),
                'summary' => trim((string) Arr::get($manifest, 'summary', '')),
            ])
            ->filter(fn (array $capability): bool => $capability['id'] !== '')
            ->values();

        $capabilitySummary = $activeCapabilities
            ->map(function (array $capability): string {
                $summary = $capability['summary'] !== '' ? ': '.$capability['summary'] : '';

                return '- `'.$capability['id'].'`'.$summary;
            })
            ->implode("\n");

        if ($capabilitySummary === '') {
            $capabilitySummary = '- 現在公開中の client-facing capability はありません';
        }

        $resourceUri = 'ledgerleap://bootstrap/'.$clientType;
        $templateUri = 'ledgerleap://bootstrap/{client}';
        $suggestedRootDirectory = 'bootstrap/'.$clientType;
        $representativeFiles = $this->representativeFiles($clientType);

        return trim(<<<MARKDOWN
# LedgerLeap bootstrap card: {$clientType}

この resource は `{$clientType}` 向けの静的 bootstrap card です。
初回導線と配置イメージだけを返し、role / model ごとの動的 bundle 解決は行いません。

## Resource contract
- resource_template: `{$templateUri}`
- resource_uri: `{$resourceUri}`
- suggested_root_directory: `{$suggestedRootDirectory}`
- activation_strategy: `contract-first`

## 次に使う discovery contract
- MCP Tool: `GetClientBootstrapManifestTool`
- REST API: `GET /api/v1/ai/bootstrap-manifest`
- REST API: `POST /api/v1/ai/bootstrap-manifest/resolve`

## 代表的な配置ファイル
{$representativeFiles}

## 現在の active capabilities
{$capabilitySummary}

## 初回の進め方
1. この card で client 別の配置イメージを確認する
2. `GetClientBootstrapManifestTool` または bootstrap manifest API で `role_profile` / `model_profile` を解決する
3. 返ってきた `files` と `placement_instructions` に従って client ローカルへ保存する
4. `recommended_capabilities` を使って最初の業務フローへ進む

## 注意
- この card は静的 reference です
- developer-facing internals は返しません
- prompt starter (`bootstrap-client-skills`) は補助導線であり、discovery の主契約ではありません
MARKDOWN)."\n";
    }

    private function representativeFiles(string $clientType): string
    {
        return match ($clientType) {
            'copilot' => implode("\n", [
                '- `skills/{capability}/SKILL.md`',
                '- `prompts/{capability}.prompt.md`',
                '- `README.md`',
            ]),
            'claude-code' => implode("\n", [
                '- `skills/{capability}/SKILL.md`',
                '- `agents/{capability}-agent.md`',
                '- `CLAUDE.md.snippet`',
                '- `README.md`',
            ]),
            'gemini-cli' => implode("\n", [
                '- `skills/{capability}/SKILL.md`',
                '- `GEMINI.md.snippet`',
                '- `README.md`',
            ]),
            'openai-agents' => implode("\n", [
                '- `templates/ledger_agents.py`',
                '- `README.md`',
            ]),
            default => throw new InvalidArgumentException('Unsupported bootstrap client: '.$clientType),
        };
    }
}
