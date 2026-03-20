<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TableRowAttachmentLoggingTest extends TestCase
{
    protected bool $tenancy = true;

    public function testTableRowLogsAttachmentHtmlMetricsForAttachmentColumns(): void
    {
        Log::spy();

        $ledgerRecord = new class
        {
            public int $id = 1;

            public object $define;

            public mixed $updated_at;

            public int|float $semantic_score = 0;

            public int|float $composite_score = 0;

            public object $status;

            public array $content;

            public array $content_attached;

            public function __construct()
            {
                $this->define = (object) ['id' => 99, 'workflow_enabled' => false];
                $this->updated_at = now();
                $this->status = new class
                {
                    public function icon(): string
                    {
                        return 'fa-solid fa-circle';
                    }

                    public function colorClass(): string
                    {
                        return 'badge-neutral';
                    }

                    public function label(): string
                    {
                        return 'completed';
                    }
                };
                $this->content = [
                    10 => [
                        'hash-1' => 'invoice.pdf',
                    ],
                ];
                $this->content_attached = [];
            }

            public function isLocked(): bool
            {
                return false;
            }
        };

        $attachment = new class
        {
            public int $id = 123;
            public int $column_id = 10;
            public string $original_mime_type = 'application/pdf';
            public bool $optimized = true;
            public int $size = 2048;
            public $created_at;
            public string $hashedbasename = 'hash-1';
            public string $filename = 'hash-1.pdf';
            public int $originalFilenameAccessCount = 0;

            public function __construct()
            {
                $this->created_at = now();
            }

            public function getDisplayStatus(): object
            {
                return (object) ['value' => 'completed'];
            }

            public function __get(string $name): mixed
            {
                if ($name === 'original_filename') {
                    $this->originalFilenameAccessCount++;

                    return 'invoice.pdf';
                }

                trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name), E_USER_NOTICE);

                return null;
            }

            public function __set(string $name, mixed $value): void
            {
                $this->{$name} = $value;
            }

            public function __isset(string $name): bool
            {
                return $name === 'original_filename' || isset($this->{$name});
            }
        };

        $filteredColumnDefines = [
            (object) [
                'id' => 10,
                'type' => 'files',
                'input_type' => 'files',
                'name' => '添付',
                'hint' => null,
                'required' => false,
                'group' => '',
                'order' => 1,
                'display_level' => 3,
            ],
        ];

        $allAttachments = collect([
            1 => collect([$attachment]),
        ]);

        $view = $this->blade(
            '<x-ledger.table-row '
                . ':ledgerRecord="$ledgerRecord" '
                . ':highlightKeyword="null" '
                . ':canUpdate="false" '
                . ':canView="true" '
                . ':allAttachments="$allAttachments" '
                . ':filteredColumnDefines="$filteredColumnDefines" '
                . 'currentTenantId="demo-tenant" />',
            compact('ledgerRecord', 'allAttachments', 'filteredColumnDefines')
        );

        $view->assertSee('direct-download-link');

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === '[AttachmentHtml] prepareFilesData'
                    && ($context['source'] ?? null) === 'table-row'
                    && ($context['ledger_id'] ?? null) === 1
                    && ($context['column_id'] ?? null) === 10
                    && ($context['file_count'] ?? null) === 1
                    && ($context['attachment_count'] ?? null) === 1
                    && array_key_exists('filename_map_build_ms', $context)
                    && array_key_exists('filename_original_ms', $context)
                    && array_key_exists('filename_attached_lookup_ms', $context)
                    && isset($context['duration_ms']);
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === '[AttachmentHtml] getFileHtml'
                    && ($context['source'] ?? null) === 'table-row'
                    && ($context['ledger_id'] ?? null) === 1
                    && ($context['column_id'] ?? null) === 10
                    && ($context['mode'] ?? null) === 'compact'
                    && ($context['file_count'] ?? null) === 1
                    && isset($context['duration_ms']);
            })
            ->once();

        self::assertSame(0, $attachment->originalFilenameAccessCount);
    }

    public function testTableRowLogsFilenameAttachedLookupMetricsWhenOriginalFilenameIsMissing(): void
    {
        Log::spy();

        $ledgerRecord = new class
        {
            public int $id = 2;

            public object $define;

            public mixed $updated_at;

            public int|float $semantic_score = 0;

            public int|float $composite_score = 0;

            public object $status;

            public array $content;

            public array $content_attached;

            public function __construct()
            {
                $this->define = (object) ['id' => 99, 'workflow_enabled' => false];
                $this->updated_at = now();
                $this->status = new class
                {
                    public function icon(): string
                    {
                        return 'fa-solid fa-circle';
                    }

                    public function colorClass(): string
                    {
                        return 'badge-neutral';
                    }

                    public function label(): string
                    {
                        return 'completed';
                    }
                };
                $this->content = [
                    10 => [
                        'hash-2' => 'invoice-from-content.pdf',
                    ],
                ];
                $this->content_attached = [];
            }

            public function isLocked(): bool
            {
                return false;
            }
        };

        $attachment = new class
        {
            public int $id = 124;
            public int $column_id = 10;
            public string $original_mime_type = 'application/pdf';
            public bool $optimized = true;
            public int $size = 1024;
            public mixed $created_at;
            public string $hashedbasename = 'hash-2';
            public string $filename = 'hash-2.pdf';
            public int $originalFilenameAccessCount = 0;

            public function __construct()
            {
                $this->created_at = now();
            }

            public function getDisplayStatus(): object
            {
                return (object) ['value' => 'completed'];
            }

            public function __get(string $name): mixed
            {
                if ($name === 'original_filename') {
                    $this->originalFilenameAccessCount++;

                    return null;
                }

                trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name), E_USER_NOTICE);

                return null;
            }

            public function __set(string $name, mixed $value): void
            {
                $this->{$name} = $value;
            }

            public function __isset(string $name): bool
            {
                return $name === 'original_filename' || isset($this->{$name});
            }
        };

        $filteredColumnDefines = [
            (object) [
                'id' => 10,
                'type' => 'files',
                'input_type' => 'files',
                'name' => '添付',
                'hint' => null,
                'required' => false,
                'group' => '',
                'order' => 1,
                'display_level' => 3,
            ],
        ];

        $allAttachments = collect([
            2 => collect([$attachment]),
        ]);

        $view = $this->blade(
            '<x-ledger.table-row '
                . ':ledgerRecord="$ledgerRecord" '
                . ':highlightKeyword="null" '
                . ':canUpdate="false" '
                . ':canView="true" '
                . ':allAttachments="$allAttachments" '
                . ':filteredColumnDefines="$filteredColumnDefines" '
                . 'currentTenantId="demo-tenant" />',
            compact('ledgerRecord', 'allAttachments', 'filteredColumnDefines')
        );

        $view->assertSee('direct-download-link');

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === '[AttachmentHtml] prepareFilesData'
                    && ($context['source'] ?? null) === 'table-row'
                    && ($context['ledger_id'] ?? null) === 2
                    && ($context['column_id'] ?? null) === 10
                    && ($context['file_count'] ?? null) === 1
                    && ($context['attachment_count'] ?? null) === 1
                    && array_key_exists('filename_map_build_ms', $context)
                    && array_key_exists('filename_original_ms', $context)
                    && array_key_exists('filename_attached_lookup_ms', $context)
                    && isset($context['duration_ms']);
            })
            ->once();

        self::assertSame(0, $attachment->originalFilenameAccessCount);
    }
}
