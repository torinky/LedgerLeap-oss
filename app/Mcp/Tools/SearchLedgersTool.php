<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use App\Services\LedgerService;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Sanctum\PersonalAccessToken; // 追加

class SearchLedgersTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search for ledgers based on various criteria.
MARKDOWN;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $token = getenv('MCP_AUTH_TOKEN'); // 環境変数からトークンを取得

        if (! $token) {
            return Response::error('Authentication token not provided.', 401);
        }

        // トークンからユーザーを検索
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken || ! $accessToken->tokenable) {
            return Response::error('Invalid authentication token.', 401);
        }

        $user = $accessToken->tokenable; // トークンに紐づくユーザー

        // 認証済みユーザーとして設定（ArtisanコンテキストでAuth::user()が機能しない場合のため）
        Auth::setUser($user); // 必要であればコメント解除

        $parameters = $request->toArray();

        $results = $this->ledgerService->searchLedgersForApi(
            user: $user, // 認証済みユーザーを直接渡す
            params: $parameters,
        );

        return Response::json($results);
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string('The search keyword for full-text search.'),
            'tags' => $schema->string('Comma-separated tag names to filter by (AND condition).'),
            'folder_id' => $schema->integer('The folder ID to recursively search within.'),
            'ledger_define_id' => $schema->integer('The ledger definition ID to filter by.'),
            'exclude_q' => $schema->string('Keywords to exclude from the results.'),
            'exclude_tags' => $schema->string('Comma-separated tag names to exclude.'),
            'mode' => $schema->string('The search mode.')->enum(['search', 'count'])->default('search'),
            'limit' => $schema->integer('The maximum number of items to return.'),
            'offset' => $schema->integer('The number of items to skip for pagination.'),
        ];
    }
}
