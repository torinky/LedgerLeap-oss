<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\Ai\BootstrapManifestService;
use App\Services\Ai\ClientSkillBootstrapService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetClientBootstrapManifestTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Resolve the client-facing bootstrap manifest for an authenticated MCP client.

        Use this tool when the client needs the same dynamic bootstrap bundle that the REST
        bootstrap manifest API returns. The response reuses LedgerLeap's shared bootstrap
        resolution service and includes only client-facing fields:
        - recommended_capabilities
        - resources
        - prompts
        - files
        - placement_instructions
        - warnings

        Required inputs:
        - client_type
        - role_profile

        Optional inputs:
        - model_profile (defaults to general-local)
        - language (defaults to ja)

        This tool returns the same bundle resolution as the REST bootstrap manifest contract.
        It must not expose developer-facing internals.
    MARKDOWN;

    public function __construct(
        private readonly BootstrapManifestService $bootstrapManifestService,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        try {
            return Response::json(
                $this->bootstrapManifestService->resolve($this->validatedInput($request))
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return Response::error($message);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_type' => $schema->string('Client type: copilot, claude-code, gemini-cli, or openai-agents.')
                ->enum(ClientSkillBootstrapService::SUPPORTED_CLIENTS)
                ->required(),
            'role_profile' => $schema->string('Role profile: operator, administrator, or field-leader.')
                ->enum(array_keys(BootstrapManifestService::ROLE_PROFILES))
                ->required(),
            'model_profile' => $schema->string('Model profile: small-local, general-local, or remote-capable.')
                ->enum(array_keys(BootstrapManifestService::MODEL_PROFILES))
                ->default('general-local'),
            'language' => $schema->string('Response language. Defaults to ja.')
                ->default('ja'),
        ];
    }

    /**
     * @return array{client_type:string, role_profile:string, model_profile:string, language:string}
     *
     * @throws ValidationException
     */
    private function validatedInput(Request $request): array
    {
        $input = [
            'client_type' => $request->get('client_type'),
            'role_profile' => $request->get('role_profile'),
            'model_profile' => $request->get('model_profile', 'general-local'),
            'language' => $request->get('language', 'ja'),
        ];

        $validator = Validator::make($input, [
            'client_type' => [
                'required',
                'string',
                'in:'.implode(',', ClientSkillBootstrapService::SUPPORTED_CLIENTS),
            ],
            'role_profile' => [
                'required',
                'string',
                'in:'.implode(',', array_keys(BootstrapManifestService::ROLE_PROFILES)),
            ],
            'model_profile' => [
                'required',
                'string',
                'in:'.implode(',', array_keys(BootstrapManifestService::MODEL_PROFILES)),
            ],
            'language' => ['required', 'string', 'max:16'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{client_type:string, role_profile:string, model_profile:string, language:string} $validated */
        $validated = $validator->validated();

        return $validated;
    }
}
