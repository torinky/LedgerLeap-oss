<?php

namespace App\Mcp\Resources;

use App\Services\Ai\BootstrapCardService;
use App\Services\Ai\ClientSkillBootstrapService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class BootstrapClientResource extends Resource implements HasUriTemplate
{
    protected string $name = 'ledgerleap-bootstrap-card';

    protected string $title = 'LedgerLeap Bootstrap Card';

    protected string $description = 'Static client-facing bootstrap card for LedgerLeap onboarding.';

    protected string $mimeType = 'text/markdown';

    public function __construct(
        private readonly BootstrapCardService $bootstrapCardService,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('ledgerleap://bootstrap/{client}');
    }

    public function handle(Request $request): Response
    {
        $client = (string) $request->get('client', '');

        if (! in_array($client, ClientSkillBootstrapService::SUPPORTED_CLIENTS, true)) {
            return Response::error(
                'Unsupported bootstrap client ['.$client.']. Supported clients: '
                .implode(', ', ClientSkillBootstrapService::SUPPORTED_CLIENTS)
            );
        }

        return Response::text($this->bootstrapCardService->render($client));
    }
}
