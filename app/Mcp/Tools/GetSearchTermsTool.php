<?php

namespace App\Mcp\Tools;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetSearchTermsTool extends Tool
{
    use AuthenticatedMcpTool;

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Extract synonym and technical-term candidates from a search phrase before searching ledgers.

        Use this tool when you want to:
        - confirm candidate words for a rough query
        - distinguish synonym terms from technical terms
        - build a better `q` for `SearchLedgersTool`

        The response returns candidate terms with their `kind` so the client can choose the best query.
MARKDOWN;

    public function __construct(
        private readonly SynonymService $synonymService,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return Response::error('q is required.');
        }

        $kind = (string) $request->get('kind', 'all');

        $searchContext = new SearchContext($this->synonymService);
        $searchContext->setSearch($q);
        $trace = $searchContext->getTrace();

        $candidates = collect($trace['selected_terms'] ?? [])
            ->filter(function (array $candidate) use ($kind): bool {
                $candidateKind = (string) ($candidate['kind'] ?? '');

                if (! in_array($candidateKind, ['synonym', 'technical'], true)) {
                    return false;
                }

                return $kind === 'all' || $candidateKind === $kind;
            })
            ->values()
            ->all();

        $suggestedQuery = collect($candidates)
            ->pluck('term')
            ->filter()
            ->implode(' ');

        $response = [
            'q' => $q,
            'kind' => $kind,
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'suggested_query' => $suggestedQuery,
            'search_trace' => Arr::only($trace, ['original_q', 'normalized_q', 'keywords', 'tags']),
            '__summary__' => $suggestedQuery === ''
                ? '検索候補は見つかりませんでした。'
                : sprintf('「%s」から %d 件の候補を取得しました。', $q, count($candidates)),
        ];

        return Response::json($response);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string(
                'Search phrase or keyword to expand into synonym / technical-term candidates.'
            )->required(),
            'kind' => $schema->string('Candidate type to return.')
                ->enum(['all', 'synonym', 'technical'])
                ->default('all'),
        ];
    }
}
