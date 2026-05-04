<?php

namespace Tests\Feature\Components;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\RecordsTableRow;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Tests\TestCase;

class TableRowAttachmentLoggingTest extends TestCase
{
    protected bool $tenancy = true;

    public function testTableRowLogsAttachmentHtmlMetricsForAttachmentColumns(): void
    {
        Log::spy();

        $service = $this->makeColumnHtmlService();
        $columnDefine = new ColumnDefine([
            'id' => 10,
            'name' => '添付',
            'type' => 'files',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $attachment = $this->makeAttachment(
            id: 123,
            columnId: 10,
            hashedbasename: 'hash-1',
            filename: 'hash-1.pdf',
            originalFilename: 'invoice.pdf',
        );

        $ledger = new Ledger();
        $ledger->forceFill(['id' => 1]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        $html = $service
            ->setAttachmentCollection(collect([$attachment])->keyBy('hashedbasename'))
            ->setAttachmentContents([])
            ->setSource('table-row')
            ->show(
                $columnDefine,
                ['hash-1' => 'invoice.pdf'],
                true,
                [],
                '',
                false,
                $ledger,
                null,
                'demo-tenant'
            )
            ->toHtml();

        $this->assertStringContainsString('direct-download-link', $html);

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

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === '[AttachmentHtml] getFileHtml'
                ? ($context['source'] ?? null) === 'table-row'
                : true;
        })->atLeast()->once();
    }

    public function testTableRowLogsFilenameAttachedLookupMetricsWhenOriginalFilenameIsMissing(): void
    {
        Log::spy();

        $service = $this->makeColumnHtmlService();
        $columnDefine = new ColumnDefine([
            'id' => 10,
            'name' => '添付',
            'type' => 'files',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $attachment = $this->makeAttachment(
            id: 124,
            columnId: 10,
            hashedbasename: 'hash-2',
            filename: 'hash-2.pdf',
            originalFilename: null,
        );

        $ledger = new Ledger();
        $ledger->forceFill(['id' => 2]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        $html = $service
            ->setAttachmentCollection(collect([$attachment])->keyBy('hashedbasename'))
            ->setAttachmentContents([])
            ->setSource('table-row')
            ->show(
                $columnDefine,
                ['hash-2' => 'invoice-from-content.pdf'],
                true,
                [],
                '',
                false,
                $ledger,
                null,
                'demo-tenant'
            )
            ->toHtml();

        $this->assertStringContainsString('direct-download-link', $html);

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

    private function makeColumnHtmlService(): ColumnHtmlService
    {
        return new ColumnHtmlService(
            Mockery::mock(AutoLinkService::class),
            Mockery::mock(MarkdownRenderer::class),
            Mockery::mock(HtmlProcessorService::class),
        );
    }

    private function makeAttachment(
        int $id,
        int $columnId,
        string $hashedbasename,
        string $filename,
        ?string $originalFilename = 'invoice.pdf',
    ): object {
        return new class($id, $columnId, $hashedbasename, $filename, $originalFilename)
        {
            public int $id;
            public int $column_id;
            public string $original_mime_type = 'application/pdf';
            public bool $optimized = true;
            public int $size = 2048;
            public mixed $created_at;
            public string $hashedbasename;
            public string $filename;
            public string $status = 'completed';
            public int $originalFilenameAccessCount = 0;

            public function __construct(
                int $id,
                int $columnId,
                string $hashedbasename,
                string $filename,
                private ?string $originalFilename,
            ) {
                $this->id = $id;
                $this->column_id = $columnId;
                $this->hashedbasename = $hashedbasename;
                $this->filename = $filename;
                $this->created_at = now();
            }

            public function getDisplayStatus(): object
            {
                return (object) ['value' => 'completed'];
            }

            public function getOcrTikaFormattedText(string $type): string
            {
                return '';
            }

            public function __get(string $name): mixed
            {
                if ($name === 'original_filename') {
                    $this->originalFilenameAccessCount++;

                    return $this->originalFilename;
                }

                if ($name === 'vlm_markdown') {
                    return '';
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
                return in_array($name, ['original_filename', 'vlm_markdown'], true) || isset($this->{$name});
            }
        };
    }

    public function testTableRowFallsBackToScalarContentForAttachmentNames(): void
    {
        $folder = Folder::factory()->create();

        $user = User::factory()->create();

        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'column_define' => [
                ['id' => 0, 'name' => '添付', 'type' => 'files', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'content' => [0 => ['hash-attachment' => 'invoice-from-content.pdf']],
            'content_attached' => [0 => []],
            'status' => WorkflowStatus::NONE,
        ]);
        $ledger->load('define');

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 0,
            'tenant_id' => $this->tenant->id,
            'filename' => 'hash-attachment.pdf',
            'hashedbasename' => 'hash-attachment',
            'original_mime_type' => 'application/pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'optimized' => true,
        ]);

        $component = Livewire::withoutLazyLoading()->test(RecordsTableRow::class, [
            'ledgerId' => $ledger->id,
            'columnId' => 0,
            'highlightKeyword' => null,
            'canView' => true,
            'currentTenantId' => $this->tenant->id,
            'selectedFileId' => $file->id,
        ]);

        $html = $component->html();

        $this->assertStringContainsString('invoice-from-content.pdf', $html);
        $this->assertStringContainsString('direct-download-link', $html);
    }
}
