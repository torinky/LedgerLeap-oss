<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\Mcp\McpResourceBridgeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ReadMcpResourceTool extends Tool
{
    use AuthenticatedMcpTool;

    protected string $description = <<<'MARKDOWN'
        Resolve a LedgerLeap MCP resource URI into a normalized envelope.

        Use this tool when a client cannot call standard `resources/read` directly.

        Supported resource URIs:
        - `ledgerleap://bootstrap/{client}`
        - `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}`
        - `ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob`

        The response keeps client-facing metadata in a stable envelope with:
        - `resource_uri`
        - `resource_type`
        - `mime_type`
        - `delivery_mode`
        - `available_formats`
        - `payloads`
        - `access_guide`

        Binary blob payloads stay opt-in through `include_blob=true`.
        For attachment envelopes, this can inline `payloads.visual.base64` and suppress `signed_url` so image-capable agents do not need internal HTTP access.
    MARKDOWN;

    public function __construct(
        private readonly McpResourceBridgeService $resourceBridgeService,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $response = Response::error('Resource bridge failed.');

        try {
            $validated = $this->validatedInput($request);
            $response = Response::json(
                $this->resourceBridgeService->read($validated['resource_uri'], $validated)
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $response = Response::error($message);
        } catch (\Throwable $e) {
            $response = Response::error($e->getMessage());
        }

        return $response;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource_uri' => $schema->string('LedgerLeap MCP resource URI to resolve.')->required(),
            'preferred_format' => $schema->string('Preferred format when available: text, markdown, json, structured, visual, or binary.')
                ->enum(['text', 'markdown', 'json', 'structured', 'visual', 'binary'])
                ->nullable(),
            'include_metadata' => $schema->boolean('Include access guide and resource metadata in the response.')->default(true),
            'max_bytes' => $schema->integer('Maximum inline bytes for binary resources.')->nullable(),
            'max_chars' => $schema->integer('Maximum character budget for text resources.')->nullable(),
            'include_blob' => $schema->boolean('Inline blob content for blob resources.')->default(false),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatedInput(Request $request): array
    {
        $input = [
            'resource_uri' => $request->get('resource_uri'),
            'preferred_format' => $request->get('preferred_format'),
            'include_metadata' => $request->get('include_metadata', true),
            'max_bytes' => $request->get('max_bytes'),
            'max_chars' => $request->get('max_chars'),
            'include_blob' => $request->get('include_blob', false),
        ];

        $validator = Validator::make($input, [
            'resource_uri' => ['required', 'string', 'max:2048'],
            'preferred_format' => ['nullable', 'string', 'in:text,markdown,json,structured,visual,binary'],
            'include_metadata' => ['required', 'boolean'],
            'max_bytes' => ['nullable', 'integer', 'min:0'],
            'max_chars' => ['nullable', 'integer', 'min:0'],
            'include_blob' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
