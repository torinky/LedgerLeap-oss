<?php

namespace App\Mcp\Resources;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Services\Ledger\LedgerAttachmentResourceService;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class LedgerAttachmentResource extends Resource implements HasUriTemplate
{
    use AuthenticatedMcpTool;

    protected string $name = 'ledgerleap-ledger-attachment';

    protected string $title = 'LedgerLeap Ledger Attachment Resource';

    protected string $description = 'Attachment resource for Continue.dev and MCP clients. Use MCP resources/read for ledgerleap:// URIs; HTTP fetch is not supported.';

    protected string $mimeType = 'application/json';

    public function __construct(
        private readonly LedgerAttachmentResourceService $attachmentResourceService,
    )
    {
    }

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}');
    }

    public function handle(Request $request): Response
    {
        $user = $this->authenticateOrError();
        if ($user instanceof Response) {
            return $user;
        }

        $tenantId = (string)$request->get('tenant', '');
        $ledgerId = $request->get('ledger');
        $attachmentId = $request->get('attachment');

        if ($tenantId === '' || !is_numeric($ledgerId) || !is_numeric($attachmentId)) {
            return Response::error('Invalid attachment resource request. tenant, ledger, and attachment are required.');
        }

        $currentTenantId = tenant('id');
        if ($currentTenantId !== null && (string)$currentTenantId !== $tenantId) {
            return Response::error("Attachment resource tenant mismatch: requested {$tenantId}, current {$currentTenantId}.");
        }

        if ($currentTenantId === null) {
            try {
                tenancy()->initialize($tenantId);
            } catch (\Throwable $e) {
                Log::warning('[MCP Attachment Resource] Failed to initialize tenant', [
                    'tenant' => $tenantId,
                    'message' => $e->getMessage(),
                ]);

                return Response::error("Attachment resource tenant [{$tenantId}] could not be initialized.");
            }
        }

        $ledger = Ledger::query()
            ->with([
                'attachedFiles' => fn($query) => $query->orderBy('column_id')->orderBy('id'),
            ])
            ->find($ledgerId);

        if (!$ledger) {
            return Response::error("Ledger [{$ledgerId}] was not found for tenant [{$tenantId}].");
        }

        if ((string)$ledger->tenant_id !== $tenantId) {
            return Response::error("Ledger [{$ledgerId}] does not belong to tenant [{$tenantId}].");
        }

        $attachment = $ledger->attachedFiles
            ->first(fn(AttachedFile $file): bool => (string)$file->id === (string)$attachmentId);

        if (!$attachment instanceof AttachedFile) {
            return Response::error("Attachment [{$attachmentId}] was not found on ledger [{$ledgerId}].");
        }

        if ((string)$attachment->tenant_id !== $tenantId) {
            return Response::error("Attachment [{$attachmentId}] does not belong to tenant [{$tenantId}].");
        }

        return Response::json($this->attachmentResourceService->buildAttachmentEnvelope($ledger, $attachment));
    }
}

