<?php

namespace App\Services\Mcp;

use App\Exceptions\Mcp\BinaryPayloadTooLargeException;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Services\Ai\BootstrapCardService;
use App\Services\Ai\ClientSkillBootstrapService;
use App\Services\Ledger\LedgerAttachmentBinaryResourceService;
use App\Services\Ledger\LedgerAttachmentResourceService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class McpResourceBridgeService
{
    public function __construct(
        private readonly BootstrapCardService $bootstrapCardService,
        private readonly LedgerAttachmentResourceService $attachmentResourceService,
        private readonly LedgerAttachmentBinaryResourceService $attachmentBinaryResourceService,
    ) {}

    /**
     * Resolve a LedgerLeap resource URI into a normalized envelope.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function read(string $resourceUri, array $options = []): array
    {
        $resourceUri = trim($resourceUri);
        if ($resourceUri === '') {
            throw new InvalidArgumentException('Resource URI is required.');
        }

        if (preg_match('#^ledgerleap://bootstrap/(?P<client>[a-z0-9-]+)$#i', $resourceUri, $matches) === 1) {
            return $this->readBootstrap((string) $matches['client'], $options);
        }

        if (
            preg_match(
                '#^ledgerleap://ledger/(?P<tenant>[^/]+)/(?P<ledger>[^/]+)/attachments/(?P<attachment>[^/]+)(?P<blob>/blob)?$#i',
                $resourceUri,
                $matches
            ) === 1
        ) {
            return $this->readAttachment(
                tenantId: (string) $matches['tenant'],
                ledgerId: (string) $matches['ledger'],
                attachmentId: (string) $matches['attachment'],
                isBlob: ($matches['blob'] ?? '') === '/blob',
                options: $options,
            );
        }

        throw new InvalidArgumentException(
            'Unsupported LedgerLeap resource URI ['.$resourceUri.']. Supported resources: '
            .'ledgerleap://bootstrap/{client}, '
            .'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}, '
            .'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob.'
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function readBootstrap(string $client, array $options): array
    {
        if (! in_array($client, ClientSkillBootstrapService::SUPPORTED_CLIENTS, true)) {
            throw new InvalidArgumentException(
                'Unsupported bootstrap client ['.$client.']. Supported clients: '
                .implode(', ', ClientSkillBootstrapService::SUPPORTED_CLIENTS)
            );
        }

        $card = $this->bootstrapCardService->render($client);
        $maxChars = isset($options['max_chars']) && is_numeric($options['max_chars'])
            ? max(0, (int) $options['max_chars'])
            : null;

        $truncated = false;
        if ($maxChars !== null && $maxChars > 0 && mb_strlen($card) > $maxChars) {
            $card = mb_substr($card, 0, $maxChars);
            $truncated = true;
        }

        $envelope = [
            'resource_uri' => 'ledgerleap://bootstrap/'.$client,
            'resource_type' => 'bootstrap',
            'resource_template' => 'ledgerleap://bootstrap/{client}',
            'tenant' => null,
            'mime_type' => 'text/markdown',
            'delivery_mode' => 'text',
            'available_formats' => ['text', 'markdown'],
            'payloads' => [
                'text' => [
                    'available' => true,
                    'text' => $card,
                    'truncated' => $truncated,
                ],
                'markdown' => [
                    'available' => true,
                    'text' => $card,
                    'truncated' => $truncated,
                ],
            ],
            'access_guide' => [
                'resource_type' => 'mcp_resource',
                'read_via' => 'ReadMcpResourceTool',
                'uri' => 'ledgerleap://bootstrap/{client}',
                'instructions' => [
                    'ledgerleap://... は HTTP URL ではなく MCP resource URI です。',
                    'MCP クライアントが `resources/read` に対応していない場合は `ReadMcpResourceTool` に `resource_uri` を渡してください。',
                    '対応クライアントでは `resources/read(uri="ledgerleap://bootstrap/{client}")` をそのまま使えます。',
                ],
            ],
        ];

        return $this->finalizeEnvelope($envelope, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function readAttachment(
        string $tenantId,
        string $ledgerId,
        string $attachmentId,
        bool $isBlob,
        array $options
    ): array {
        $context = $this->loadAttachmentContext($tenantId, $ledgerId, $attachmentId);
        $ledger = $context['ledger'];
        $attachment = $context['attachment'];

        if (! $isBlob) {
            $envelope = $this->attachmentResourceService->buildAttachmentEnvelope($ledger, $attachment);

            $envelope['resource_type'] = 'attachment';
            $envelope['tenant'] = $tenantId;
            $envelope['access_guide']['read_via'] = 'ReadMcpResourceTool';
            $envelope['access_guide']['instructions'] = [
                'ledgerleap://... は HTTP URL ではなく MCP resource URI です。',
                'MCP クライアントが `resources/read` に対応していない場合は `ReadMcpResourceTool` に `resource_uri` を渡してください。',
                '対応クライアントでは `resources/read(uri="ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}")` をそのまま使えます。',
                'HTTP で取得したい場合は `routes.download` を使ってください。',
            ];

            return $this->finalizeEnvelope($envelope, $options);
        }

        $path = $this->attachmentBinaryResourceService->resolveAttachmentPath($attachment);
        $mimeType = $this->attachmentBinaryResourceService->resolveAttachmentMimeType($attachment, $path);
        $maxBytes = isset($options['max_bytes']) && is_numeric($options['max_bytes'])
            ? max(0, (int) $options['max_bytes'])
            : null;
        $includeBlob = filter_var($options['include_blob'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $resourceUri = $this->attachmentBinaryResourceService->buildResourceUri($ledger, $attachment);
        $fileSize = (int) ($attachment->size ?? 0);

        if ($includeBlob && $maxBytes !== null && $fileSize > 0 && $fileSize > $maxBytes) {
            throw new BinaryPayloadTooLargeException("Binary payload exceeds max_bytes limit of {$maxBytes}.");
        }

        $payloads = [
            'binary' => [
                'available' => $includeBlob,
                'blob' => null,
                'size' => $fileSize,
                'size_formatted' => $fileSize > 0 ? $this->formatFileSize($fileSize) : null,
                'truncated' => false,
                'note' => $includeBlob
                    ? null
                    : 'Inline blob is disabled by default. Set include_blob=true to receive base64 data.',
            ],
        ];

        if ($includeBlob) {
            $bytes = $this->attachmentBinaryResourceService->readAttachmentBytes($attachment);
            $payloads['binary']['blob'] = base64_encode($bytes);
            $payloads['binary']['size'] = strlen($bytes);
            $payloads['binary']['size_formatted'] = $this->formatFileSize(strlen($bytes));
            $payloads['binary']['available'] = true;

            if ($maxBytes !== null && strlen($bytes) > $maxBytes) {
                throw new BinaryPayloadTooLargeException("Binary payload exceeds max_bytes limit of {$maxBytes}.");
            }
        }

        $envelope = [
            'resource_uri' => $resourceUri,
            'resource_type' => 'attachment_blob',
            'resource_template' => 'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob',
            'tenant' => $tenantId,
            'mime_type' => $mimeType,
            'delivery_mode' => $includeBlob ? 'binary' : 'metadata',
            'available_formats' => ['binary'],
            'payloads' => $payloads,
            'access_guide' => [
                'resource_type' => 'mcp_resource',
                'read_via' => 'ReadMcpResourceTool',
                'uri' => 'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}/blob',
                'instructions' => [
                    'ledgerleap://... は HTTP URL ではなく MCP resource URI です。',
                    'Binary resource は既定で inline されません。`include_blob=true` を指定すると base64 blob を受け取れます。',
                    'HTTP で取得したい場合は `routes.download` を使ってください。',
                ],
                'fallback_routes' => [
                    'download' => route('file.download', [
                        'tenant' => $tenantId,
                        'attachedFile' => $attachment->id,
                        'original' => true,
                    ]),
                ],
            ],
        ];

        return $this->finalizeEnvelope($envelope, $options);
    }

    /**
     * @return array{ledger: Ledger, attachment: AttachedFile}
     */
    private function loadAttachmentContext(string $tenantId, string $ledgerId, string $attachmentId): array
    {
        $currentTenantId = tenant('id');
        if ($currentTenantId !== null && (string) $currentTenantId !== $tenantId) {
            throw new InvalidArgumentException(
                "Attachment resource tenant mismatch: requested {$tenantId}, current {$currentTenantId}."
            );
        }

        if ($currentTenantId === null) {
            try {
                tenancy()->initialize($tenantId);
            } catch (\Throwable $e) {
                Log::warning('[MCP Resource Bridge] Failed to initialize tenant', [
                    'tenant' => $tenantId,
                    'message' => $e->getMessage(),
                ]);

                throw new InvalidArgumentException("Attachment resource tenant [{$tenantId}] could not be initialized.");
            }
        }

        $ledger = Ledger::query()
            ->with([
                'attachedFiles' => fn ($query) => $query->orderBy('column_id')->orderBy('id'),
            ])
            ->find($ledgerId);

        if (! $ledger instanceof Ledger) {
            throw new InvalidArgumentException("Ledger [{$ledgerId}] was not found for tenant [{$tenantId}].");
        }

        if ((string) $ledger->tenant_id !== $tenantId) {
            throw new InvalidArgumentException("Ledger [{$ledgerId}] does not belong to tenant [{$tenantId}].");
        }

        $attachment = $ledger->attachedFiles
            ->first(fn (AttachedFile $file): bool => (string) $file->id === (string) $attachmentId);

        if (! $attachment instanceof AttachedFile) {
            throw new InvalidArgumentException("Attachment [{$attachmentId}] was not found on ledger [{$ledgerId}].");
        }

        if ((string) $attachment->tenant_id !== $tenantId) {
            throw new InvalidArgumentException("Attachment [{$attachmentId}] does not belong to tenant [{$tenantId}].");
        }

        return [
            'ledger' => $ledger,
            'attachment' => $attachment,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function finalizeEnvelope(array $envelope, array $options): array
    {
        $preferredFormat = isset($options['preferred_format']) ? (string) $options['preferred_format'] : null;
        if ($preferredFormat !== null && in_array($preferredFormat, $envelope['available_formats'] ?? [], true)) {
            $envelope['delivery_mode'] = $preferredFormat;
        }

        $includeMetadata = filter_var($options['include_metadata'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $includeMetadata) {
            unset($envelope['access_guide']);
        }

        return $envelope;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
