<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

class BootstrapManifestService
{
    /**
     * @var array<string, array{label:string, capabilities: array<int, string>}>
     */
    public const ROLE_PROFILES = [
        'operator' => [
            'label' => '実務担当者',
            'capabilities' => ['ledger-search', 'ledger-create', 'workflow-review'],
        ],
        'administrator' => [
            'label' => '管理者',
            'capabilities' => ['ledger-search', 'workflow-review', 'activity-audit', 'analytics-report'],
        ],
        'field-leader' => [
            'label' => '現場リーダー',
            'capabilities' => ['ledger-search', 'ledger-update', 'workflow-review'],
        ],
    ];

    /**
     * @var array<string, array{label:string, text_budget:string, schema_budget:string, guidance: array<int, string>}>
     */
    public const MODEL_PROFILES = [
        'small-local' => [
            'label' => 'small-local',
            'text_budget' => 'compact',
            'schema_budget' => 'minimal',
            'guidance' => [
                '最小 bundle を優先し、一覧→詳細の順で段階的に展開する',
                '長い説明や大きな schema を避け、短い capability card を優先する',
            ],
        ],
        'general-local' => [
            'label' => 'general-local',
            'text_budget' => 'balanced',
            'schema_budget' => 'standard',
            'guidance' => [
                '標準 bundle を返しつつ、必要に応じて詳細 guide へ進める',
                '必須入力は短い箇条書きで返し、説明の重複を避ける',
            ],
        ],
        'remote-capable' => [
            'label' => 'remote-capable',
            'text_budget' => 'expanded',
            'schema_budget' => 'standard',
            'guidance' => [
                '詳細 guide と補助説明を返せるが、公開契約を冗長にしすぎない',
                'まず bundle 概要を返し、必要時だけ追加説明へ進める',
            ],
        ],
    ];

    public function __construct(
        private readonly CapabilityManifestRepository $manifestRepository,
    ) {}

    /**
     * @param  array{client_type:string, role_profile:string, model_profile?:string, language?:string}  $input
     * @return array<string, mixed>
     */
    public function resolve(array $input): array
    {
        $clientType = (string) $input['client_type'];
        $roleProfile = (string) $input['role_profile'];
        $modelProfile = (string) ($input['model_profile'] ?? 'general-local');
        $language = (string) ($input['language'] ?? 'ja');

        $roleDefinition = self::ROLE_PROFILES[$roleProfile];
        $modelDefinition = self::MODEL_PROFILES[$modelProfile];
        $requestedCapabilityIds = $roleDefinition['capabilities'];

        $activeManifestsById = $this->manifestRepository
            ->active($requestedCapabilityIds)
            ->keyBy(fn (array $manifest) => (string) Arr::get($manifest, 'id'));

        $recommendedCapabilities = collect($requestedCapabilityIds)
            ->map(function (string $capabilityId) use ($activeManifestsById) {
                /** @var array<string, mixed>|null $manifest */
                $manifest = $activeManifestsById->get($capabilityId);
                if ($manifest === null) {
                    return null;
                }

                return [
                    'id' => (string) Arr::get($manifest, 'id'),
                    'summary' => (string) Arr::get($manifest, 'summary', ''),
                    'primary_user_goals' => array_values((array) Arr::get($manifest, 'primary_user_goals', [])),
                    'required_guides' => array_values((array) Arr::get($manifest, 'required_guides', [])),
                ];
            })
            ->filter()
            ->values();

        $missingCapabilities = array_values(array_diff(
            $requestedCapabilityIds,
            $recommendedCapabilities->pluck('id')->all(),
        ));

        return [
            'client_type' => $clientType,
            'language' => $language,
            'role_profile' => [
                'id' => $roleProfile,
                'label' => $roleDefinition['label'],
            ],
            'model_profile' => [
                'id' => $modelProfile,
                'label' => $modelDefinition['label'],
                'text_budget' => $modelDefinition['text_budget'],
                'schema_budget' => $modelDefinition['schema_budget'],
                'guidance' => $modelDefinition['guidance'],
            ],
            'recommended_capabilities' => $recommendedCapabilities->all(),
            'resources' => $this->buildResources($clientType, $recommendedCapabilities->all()),
            'prompts' => $this->buildPrompts($clientType, $roleProfile),
            'files' => $this->buildFiles($clientType, $recommendedCapabilities->pluck('id')->all()),
            'placement_instructions' => $this->buildPlacementInstructions($clientType, $modelProfile),
            'warnings' => collect($missingCapabilities)
                ->map(fn (string $capabilityId) => 'Capability manifest is missing or inactive: '.$capabilityId)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendedCapabilities
     * @return array<int, array<string, mixed>>
     */
    private function buildResources(string $clientType, array $recommendedCapabilities): array
    {
        $guideMap = [];

        foreach ($recommendedCapabilities as $capability) {
            $capabilityId = (string) Arr::get($capability, 'id', '');
            foreach ((array) Arr::get($capability, 'required_guides', []) as $guideUri) {
                $guideUri = (string) $guideUri;
                if ($guideUri === '') {
                    continue;
                }

                $guideMap[$guideUri] ??= [];
                $guideMap[$guideUri][] = $capabilityId;
            }
        }

        $resources = [[
            'uri' => 'ledgerleap://bootstrap/'.$clientType,
            'type' => 'bootstrap-card',
            'status' => 'implemented',
            'description' => 'クライアント別の静的 bootstrap card。role / model ごとの動的 bundle 解決は別 contract に委譲する。',
        ]];

        foreach ($guideMap as $guideUri => $capabilityIds) {
            $resources[] = [
                'uri' => $guideUri,
                'type' => 'guide',
                'status' => 'logical-reference',
                'capability_ids' => array_values(array_unique($capabilityIds)),
                'description' => 'capability card / guide resource の論理参照先',
            ];
        }

        return $resources;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPrompts(string $clientType, string $roleProfile): array
    {
        return [[
            'id' => 'bootstrap-client-skills',
            'status' => 'candidate',
            'type' => 'supplementary-starter',
            'client_type' => $clientType,
            'role_profile' => $roleProfile,
            'description' => '最初の質問例と確認事項だけを返す補助 prompt。discovery の主契約にはしない。',
        ]];
    }

    /**
     * @param  array<int, string>  $capabilityIds
     * @return array<int, array<string, mixed>>
     */
    private function buildFiles(string $clientType, array $capabilityIds): array
    {
        return match ($clientType) {
            'copilot' => $this->buildCopilotFiles($capabilityIds),
            'claude-code' => $this->buildClaudeCodeFiles($capabilityIds),
            'gemini-cli' => $this->buildGeminiCliFiles($capabilityIds),
            'openai-agents' => $this->buildOpenAiAgentsFiles(),
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $capabilityIds
     * @return array<int, array<string, mixed>>
     */
    private function buildCopilotFiles(array $capabilityIds): array
    {
        return collect($capabilityIds)
            ->flatMap(fn (string $capabilityId) => [
                [
                    'relative_path' => 'skills/'.$capabilityId.'/SKILL.md',
                    'kind' => 'skill',
                    'required' => true,
                    'capability_id' => $capabilityId,
                ],
                [
                    'relative_path' => 'prompts/'.$capabilityId.'.prompt.md',
                    'kind' => 'prompt',
                    'required' => false,
                    'capability_id' => $capabilityId,
                ],
            ])
            ->push([
                'relative_path' => 'README.md',
                'kind' => 'readme',
                'required' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $capabilityIds
     * @return array<int, array<string, mixed>>
     */
    private function buildClaudeCodeFiles(array $capabilityIds): array
    {
        return collect($capabilityIds)
            ->flatMap(fn (string $capabilityId) => [
                [
                    'relative_path' => 'skills/'.$capabilityId.'/SKILL.md',
                    'kind' => 'skill',
                    'required' => true,
                    'capability_id' => $capabilityId,
                ],
                [
                    'relative_path' => 'agents/'.$capabilityId.'-agent.md',
                    'kind' => 'agent',
                    'required' => false,
                    'capability_id' => $capabilityId,
                ],
            ])
            ->push([
                'relative_path' => 'CLAUDE.md.snippet',
                'kind' => 'snippet',
                'required' => false,
            ])
            ->push([
                'relative_path' => 'README.md',
                'kind' => 'readme',
                'required' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $capabilityIds
     * @return array<int, array<string, mixed>>
     */
    private function buildGeminiCliFiles(array $capabilityIds): array
    {
        return collect($capabilityIds)
            ->map(fn (string $capabilityId) => [
                'relative_path' => 'skills/'.$capabilityId.'/SKILL.md',
                'kind' => 'skill',
                'required' => true,
                'capability_id' => $capabilityId,
            ])
            ->push([
                'relative_path' => 'GEMINI.md.snippet',
                'kind' => 'snippet',
                'required' => false,
            ])
            ->push([
                'relative_path' => 'README.md',
                'kind' => 'readme',
                'required' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOpenAiAgentsFiles(): array
    {
        return [
            [
                'relative_path' => 'templates/ledger_agents.py',
                'kind' => 'template',
                'required' => true,
            ],
            [
                'relative_path' => 'README.md',
                'kind' => 'readme',
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlacementInstructions(string $clientType, string $modelProfile): array
    {
        return [
            'suggested_root_directory' => 'bootstrap/'.$clientType,
            'activation_strategy' => 'contract-first',
            'activation_steps' => [
                'まず REST または MCP から bootstrap contract を取得する',
                'files の relative_path に従って client ローカルへ保存する',
                'skills / agents / templates を優先し、prompt は補助導線として扱う',
                'capability や guide が更新されたら bootstrap manifest を再取得して同期する',
            ],
            'model_profile_notes' => self::MODEL_PROFILES[$modelProfile]['guidance'],
        ];
    }
}
