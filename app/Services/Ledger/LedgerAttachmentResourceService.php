<?php

namespace App\Services\Ledger;

use App\Models\AttachedFile;
use App\Models\Ledger;

class LedgerAttachmentResourceService
{
    private const ATTACHMENT_TEXT_PREVIEW_LIMIT = 500;

    private const ATTACHMENT_LINE_PREVIEW_LIMIT = 10;

    public function buildAttachmentEnvelope(
        Ledger $ledger,
        AttachedFile $attachedFile,
        ?string $inlineVisualBase64 = null
    ): array
    {
        $ledger->loadMissing([
            'attachedFiles' => fn ($query) => $query->orderBy('column_id')->orderBy('id'),
        ]);

        $attachedFiles = collect($ledger->getRelation('attachedFiles') ?? []);
        $position = $attachedFiles->search(
            static fn ($item): bool => $item instanceof AttachedFile && (int) $item->id === (int) $attachedFile->id
        );

        $order = $position === false ? 1 : $position + 1;
        $total = max($attachedFiles->count(), 1);
        $fileInfo = $this->resolveFileInfo($ledger, $attachedFile);
        $mimeType = $this->resolveAttachmentMimeType($fileInfo, $attachedFile);
        $availableFormats = $this->resolveAttachmentAvailableFormats($fileInfo, $attachedFile, $mimeType);
        $deliveryMode = $this->resolveAttachmentDeliveryMode($availableFormats);
        $resourceTemplate = 'ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}';
        $resourceUri = $this->buildResourceUri($ledger, $attachedFile);

        return [
            'resource_template' => $resourceTemplate,
            'resource_uri' => $resourceUri,
            'access_guide' => $this->buildAttachmentAccessGuide($ledger, $attachedFile, $resourceUri),
            'attachment_id' => $attachedFile->id,
            'filename' => $attachedFile->filename ?: ($fileInfo['name'] ?? $fileInfo['filename'] ?? trans('common.unknown', [], 'ja')),
            'name' => $attachedFile->filename ?: ($fileInfo['name'] ?? $fileInfo['filename'] ?? trans('common.unknown', [], 'ja')),
            'role' => $order === 1 ? 'primary' : 'supporting',
            'order' => $order,
            'source' => $this->resolveAttachmentSource($fileInfo, $attachedFile),
            'mime_type' => $mimeType,
            'delivery_mode' => $deliveryMode,
            'available_formats' => $availableFormats,
            'routes' => $this->buildAttachmentRoutes($ledger, $attachedFile),
            'payloads' => $this->buildAttachmentPayloads(
                $fileInfo,
                $attachedFile,
                $ledger,
                $mimeType,
                $availableFormats,
                $inlineVisualBase64
            ),
            'size' => $fileInfo['size'] ?? $attachedFile->size ?? 0,
            'size_formatted' => $this->formatFileSize((int) ($fileInfo['size'] ?? $attachedFile->size ?? 0)),
            'mime' => $mimeType,
            'column_id' => $attachedFile->column_id,
            'hash' => $attachedFile->hashedbasename,
            'total_attachments' => $total,
        ];
    }

    public function buildResourceUri(Ledger $ledger, AttachedFile $attachedFile): string
    {
        return sprintf(
            'ledgerleap://ledger/%s/%s/attachments/%s',
            (string) $ledger->tenant_id,
            (string) $ledger->id,
            (string) $attachedFile->id
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAttachmentAccessGuide(Ledger $ledger, AttachedFile $attachedFile, ?string $resourceUri = null): array
    {
        $resourceUri ??= $this->buildResourceUri($ledger, $attachedFile);

        return [
            'resource_type' => 'mcp_resource',
            'read_via' => 'resources/read',
            'uri' => $resourceUri,
            'instructions' => [
                'ledgerleap://... は HTTP URL ではなく MCP resource URI です。',
                'MCP クライアントでは `resources/read` に `resource_uri` をそのまま渡して読み込んでください。例: `resources/read(uri="ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}")`。',
                'HTTP で取得したい場合は `routes.download` を使ってください。例: `GET {download_url}`。',
                'HTTP の `routes.download` は認証済みセッション前提です。未認証の場合はログイン HTML にリダイレクトされることがあります。',
            ],
            'fallback_routes' => [
                'download' => route('file.download', [
                    'tenant' => $ledger->tenant_id,
                    'attachedFile' => $attachedFile->id,
                    'original' => true,
                ]),
                'inspector' => route('ledger.show', [
                    'tenant' => $ledger->tenant_id,
                    'ledgerId' => $ledger->id,
                    'file' => $attachedFile->id,
                ]),
            ],
            'note' => 'resource_uri は Continue.dev などの MCP クライアント向け、fallback_routes は人間向けの HTTP 導線です。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFileInfo(Ledger $ledger, AttachedFile $attachedFile): array
    {
        $contentAttached = $ledger->content_attached ?? [];
        $columnContent = data_get($contentAttached, $attachedFile->column_id, []);

        if (is_array($columnContent) && isset($columnContent[$attachedFile->hashedbasename])) {
            $fileInfo = $columnContent[$attachedFile->hashedbasename];

            return is_array($fileInfo) ? $fileInfo : (array) $fileInfo;
        }

        foreach ($contentAttached as $columnItems) {
            if (! is_array($columnItems) || ! isset($columnItems[$attachedFile->hashedbasename])) {
                continue;
            }

            $fileInfo = $columnItems[$attachedFile->hashedbasename];

            return is_array($fileInfo) ? $fileInfo : (array) $fileInfo;
        }

        return [];
    }

    private function resolveAttachmentSource(array $fileInfo, AttachedFile $attachedFile): string
    {
        return $attachedFile->finalized_source
            ?? ($fileInfo['source'] ?? null)
            ?? ($fileInfo['meta']['source'] ?? null)
            ?? 'unknown';
    }

    private function resolveAttachmentMimeType(array $fileInfo, AttachedFile $attachedFile): string
    {
        return $attachedFile->original_mime_type
            ?? $attachedFile->mime
            ?? ($fileInfo['mime_type'] ?? null)
            ?? ($fileInfo['mime'] ?? null)
            ?? 'application/octet-stream';
    }

    /**
     * @return array<int, string>
     */
    private function resolveAttachmentAvailableFormats(
        array $fileInfo,
        AttachedFile $attachedFile,
        string $mimeType
    ): array {
        $formats = ['text'];

        if ($attachedFile->hasVlmResult()) {
            $formats[] = 'markdown';
        }

        if ($this->isStructuredMimeType($mimeType)
            || ! empty($attachedFile->vlm_structured_data)
            || ! empty($fileInfo['pages'])
            || ! empty($fileInfo['text_blocks'])
            || ! empty($fileInfo['key_value_pairs'])) {
            $formats[] = 'structured';
            $formats[] = 'json';
        }

        if ($this->isVisualMimeType($mimeType)) {
            $formats[] = 'visual';
        }

        return array_values(array_unique($formats));
    }

    private function resolveAttachmentDeliveryMode(array $availableFormats): string
    {
        return $availableFormats[0] ?? 'text';
    }

    /**
     * @param  array<int, string>  $availableFormats
     * @return array<string, array<string, mixed>>
     */
    private function buildAttachmentPayloads(
        array $fileInfo,
        AttachedFile $attachedFile,
        Ledger $ledger,
        string $mimeType,
        array $availableFormats,
        ?string $inlineVisualBase64 = null
    ): array {
        return [
            'text' => $this->buildTextPayload($fileInfo, $attachedFile),
            'structured' => $this->buildStructuredPayload(
                $fileInfo,
                $attachedFile,
                in_array('structured', $availableFormats, true)
            ),
            'visual' => $this->buildVisualPayload(
                $ledger,
                $attachedFile,
                $mimeType,
                in_array('visual', $availableFormats, true),
                $inlineVisualBase64
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildAttachmentRoutes(Ledger $ledger, AttachedFile $attachedFile): array
    {
        $context = $this->resolveAttachmentRouteContext($ledger, $attachedFile);
        $canBuildDownload = $context['tenant_id'] !== null && $context['attachment_id'] !== null;
        $canBuildInspector = $canBuildDownload && $context['ledger_id'] !== null;

        return [
            'download' => [
                'available' => $canBuildDownload,
                'url' => $canBuildDownload
                    ? route('file.download', [
                        'tenant' => $context['tenant_id'],
                        'attachedFile' => $context['attachment_id'],
                        'original' => true,
                    ])
                    : null,
            ],
            'inspector' => [
                'available' => $canBuildInspector,
                'url' => $canBuildInspector
                    ? route('ledger.show', [
                        'tenant' => $context['tenant_id'],
                        'ledgerId' => $context['ledger_id'],
                        'file' => $context['attachment_id'],
                    ])
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTextPayload(array $fileInfo, AttachedFile $attachedFile): array
    {
        $rawText = $attachedFile->vlm_markdown
            ?? $fileInfo['meta']['content']
            ?? $fileInfo['content']
            ?? null;

        if (! is_string($rawText) || trim($rawText) === '') {
            return [
                'available' => true,
                'text' => null,
                'lines' => [],
                'truncated' => false,
            ];
        }

        $normalizedText = trim($rawText);
        $truncated = mb_strlen($normalizedText) > self::ATTACHMENT_TEXT_PREVIEW_LIMIT;
        $previewText = mb_substr($normalizedText, 0, self::ATTACHMENT_TEXT_PREVIEW_LIMIT);
        $previewLines = preg_split('/\R/u', $previewText) ?: [];
        $lineLimited = count($previewLines) > self::ATTACHMENT_LINE_PREVIEW_LIMIT;
        $previewLines = array_slice($previewLines, 0, self::ATTACHMENT_LINE_PREVIEW_LIMIT);

        return [
            'available' => true,
            'text' => $previewText,
            'lines' => array_map(
                static fn (string $line, int $index): array => [
                    'line_number' => $index + 1,
                    'text' => $line,
                ],
                $previewLines,
                array_keys($previewLines)
            ),
            'truncated' => $truncated || $lineLimited,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStructuredPayload(array $fileInfo, AttachedFile $attachedFile, bool $available): array
    {
        $structuredData = is_array($attachedFile->vlm_structured_data) ? $attachedFile->vlm_structured_data : [];

        $pages = $this->normalizeStructuredPayloadSection(
            $structuredData['pages'] ?? $fileInfo['pages'] ?? []
        );
        $textBlocks = $this->normalizeStructuredPayloadSection(
            $structuredData['text_blocks'] ?? $fileInfo['text_blocks'] ?? []
        );
        $keyValuePairs = $this->normalizeStructuredPayloadSection(
            $structuredData['key_value_pairs'] ?? $fileInfo['key_value_pairs'] ?? []
        );

        return [
            'available' => $available,
            'pages' => $pages,
            'text_blocks' => $textBlocks,
            'key_value_pairs' => $keyValuePairs,
            'confidence' => $attachedFile->vlm_confidence,
            'optional_fields' => [
                'page_index' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'page_index'),
                'bbox' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'bbox'),
                'source_span' => $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'source_span'),
                'confidence' => $attachedFile->vlm_confidence !== null
                    || $this->sectionContainsField([$pages, $textBlocks, $keyValuePairs], 'confidence'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVisualPayload(
        Ledger $ledger,
        AttachedFile $attachedFile,
        string $mimeType,
        bool $available,
        ?string $inlineBase64 = null
    ): array {
        $context = $this->resolveAttachmentRouteContext($ledger, $attachedFile);
        $canBuildVisual = $available
            && $context['tenant_id'] !== null
            && $context['attachment_id'] !== null;

        return [
            'available' => $canBuildVisual,
            'mime_type' => $mimeType,
            'signed_url' => $canBuildVisual && $inlineBase64 === null
                ? route('file.download', [
                    'tenant' => $context['tenant_id'],
                    'attachedFile' => $context['attachment_id'],
                ])
                : null,
            'base64' => $inlineBase64,
            'expires_at' => null,
            'auth_required' => true,
        ];
    }

    /**
     * @return array{tenant_id:int|string|null, ledger_id:int|string|null, attachment_id:int|string|null}
     */
    private function resolveAttachmentRouteContext(Ledger $ledger, AttachedFile $attachedFile): array
    {
        return [
            'tenant_id' => $ledger->tenant_id ?? $attachedFile->tenant_id,
            'ledger_id' => $ledger->id ?? $attachedFile->ledger_id,
            'attachment_id' => $attachedFile->id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStructuredPayloadSection(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        return array_values(array_map(
            static fn ($item): array => is_array($item) ? $item : (array) $item,
            array_filter($section, static fn ($item): bool => is_array($item) || is_object($item))
        ));
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $sections
     */
    private function sectionContainsField(array $sections, string $field): bool
    {
        foreach ($sections as $section) {
            foreach ($section as $item) {
                if ($this->arrayContainsField($item, $field)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function arrayContainsField(array $payload, string $field): bool
    {
        if (array_key_exists($field, $payload)) {
            return true;
        }

        foreach ($payload as $value) {
            if (is_array($value) && $this->arrayContainsField($value, $field)) {
                return true;
            }
        }

        return false;
    }

    private function isVisualMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf';
    }

    private function isStructuredMimeType(string $mimeType): bool
    {
        return $mimeType === 'application/json';
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes) / log(1024));
        $exp = min($exp, count($units) - 1);

        $size = $bytes / (1024 ** $exp);

        return round($size, 2).' '.$units[$exp];
    }
}
