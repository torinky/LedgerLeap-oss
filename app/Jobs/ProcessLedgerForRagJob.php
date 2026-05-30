<?php

namespace App\Jobs;

use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\Embedding\RuriChunkFormatter;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $attachedFileId  If null, process the ledger body. If set, process only the specific file.
     */
    public function __construct(
        public int $ledgerId,
        public ?int $attachedFileId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService, RuriChunkFormatter $formatter): void
    {
        if (! config('rag.enabled', false)) {
            return;
        }

        $logChannel = config('rag.log_channel', 'stack');
        $ledger = Ledger::find($this->ledgerId);

        if (! $ledger) {
            Log::channel($logChannel)->warning('Ledger not found in job', [
                'ledger_id' => $this->ledgerId,
            ]);

            return;
        }

        if ($this->attachedFileId) {
            $this->processAttachedFile($ledger, $this->attachedFileId, $formatter, $embeddingService, $logChannel);
        } else {
            $this->processLedgerBody($ledger, $formatter, $embeddingService, $logChannel);
        }
    }

    /**
     * Process a specific attached file.
     */
    private function processAttachedFile(Ledger $ledger, int $attachedFileId, RuriChunkFormatter $formatter, EmbeddingService $embeddingService, string $logChannel): void
    {
        $file = AttachedFile::find($attachedFileId);

        if (! $file) {
            Log::channel($logChannel)->warning('Attached file not found', ['id' => $attachedFileId]);

            return;
        }

        // Load ledger relationship for previewable_text accessor
        $file->load('ledger');

        // Check if there is content to process (VLM result or OCR text)
        $content = $file->vlm_markdown;
        // Fallback to OCR/Tika if VLM is empty? For now, let's stick to vlm_markdown as primary RAG source.
        // If we want to support OCR fallback, we should get it here.
        if (empty($content)) {
            // Try to get previewable text (OCR/Tika)
            $content = $file->previewable_text;
        }

        // 1. Delete existing chunks for this file
        DB::table('ledger_chunks')
            ->where('attached_file_id', $attachedFileId)
            ->delete();

        if (empty($content)) {
            Log::channel($logChannel)->info('No content to chunk for attached file', ['id' => $attachedFileId]);

            return;
        }

        // 2. Format text with metadata
        $formattedText = $formatter->formatForAttachedFile($file, $content);

        // 3. Chunk and Embed
        $this->generateAndSaveChunks($ledger, $formattedText, $embeddingService, $logChannel, $attachedFileId);
    }

    /**
     * Process the ledger body (excluding attachments).
     */
    private function processLedgerBody(Ledger $ledger, RuriChunkFormatter $formatter, EmbeddingService $embeddingService, string $logChannel): void
    {
        // 1. Delete existing chunks for ledger body (where attached_file_id is NULL)
        DB::table('ledger_chunks')
            ->where('ledger_id', $ledger->id)
            ->whereNull('attached_file_id')
            ->delete();

        // 2. Generate markdown for ledger body
        $markdown = $this->buildMarkdownFromLedgerBody($ledger);

        if (empty($markdown)) {
            Log::channel($logChannel)->info('No content to chunk for ledger body', ['ledger_id' => $ledger->id]);

            return;
        }

        // 3. Format text with metadata
        $formattedText = $formatter->formatForLedger($ledger, $markdown);

        // 4. Chunk and Embed
        $this->generateAndSaveChunks($ledger, $formattedText, $embeddingService, $logChannel, null);
    }

    /**
     * Common logic for chunking, embedding, and saving.
     */
    private function generateAndSaveChunks(Ledger $ledger, string $text, EmbeddingService $embeddingService, string $logChannel, ?int $attachedFileId): void
    {
        $startTime = microtime(true);

        // Chunk the text
        $chunks = $this->chunkText($text);

        if (empty($chunks)) {
            return;
        }

        // Generate embeddings (Ruri expects 'passage' type for documents)
        $chunkTexts = array_column($chunks, 'text');
        try {
            $embeddings = $embeddingService->embed($chunkTexts, 'passage');
        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Embedding generation failed', [
                'ledger_id' => $ledger->id,
                'attached_file_id' => $attachedFileId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Prepare data for insertion
        $chunkData = [];
        $now = now();

        foreach ($chunks as $index => $chunk) {
            if (! isset($embeddings[$index])) {
                continue;
            }

            $chunkData[] = [
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledger->ledger_define_id,
                'folder_id' => $ledger->define->folder_id,
                'attached_file_id' => $attachedFileId,
                'chunk_index' => $index,
                'chunk_text' => $chunk['text'], // This now includes metadata header
                'embedding' => json_encode($embeddings[$index]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert
        DB::table('ledger_chunks')->insert($chunkData);

        Log::channel($logChannel)->info('Chunking process completed', [
            'ledger_id' => $ledger->id,
            'attached_file_id' => $attachedFileId,
            'chunks_created' => count($chunkData),
            'text_length' => mb_strlen($text),
            'total_time_ms' => (microtime(true) - $startTime) * 1000,
        ]);
    }

    /**
     * Build structured Markdown text from ledger body ONLY.
     */
    private function buildMarkdownFromLedgerBody(Ledger $ledger): string
    {
        $lines = [];
        $ledgerDefine = $ledger->define;

        // 1. Add metadata header
        if (! empty($ledgerDefine->title)) {
            $lines[] = "# {$ledgerDefine->title}";
            $lines[] = '';
        }

        if (! empty($ledgerDefine->create_description)) {
            $lines[] = "> {$ledgerDefine->create_description}";
            $lines[] = '';
            $lines[] = '---';
        }

        // 2. Process columns by group
        $columnDefinesData = $ledgerDefine->column_define;

        if (empty($columnDefinesData)) {
            return '';
        }

        $columnDefines = collect($columnDefinesData)->map(function ($data) {
            return new ColumnDefine($data);
        });

        $groupedColumns = $columnDefines->groupBy('group');

        foreach ($groupedColumns as $groupName => $columns) {
            $displayGroupName = ! empty($groupName) ? $groupName : 'その他';
            $lines[] = "## {$displayGroupName}";
            $lines[] = '';

            foreach ($columns as $column) {
                $value = $this->getColumnValue($ledger, $column);

                if ($value === null) {
                    continue;
                }

                $headerLevel = str_repeat('#', $column->display_level + 2);
                $lines[] = "{$headerLevel} {$column->name}";
                $lines[] = '';
                $lines[] = $value;
                $lines[] = '';
            }
        }

        // Attachments section is intentionally omitted

        return implode("\n", $lines);
    }

    /**
     * Get formatted column value.
     */
    private function getColumnValue(Ledger $ledger, ColumnDefine $column): ?string
    {
        $type = $column->type;
        $content = $ledger->content ?? [];
        $value = $content[$column->id] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        // Handle select type
        if ($type === 'select') {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }

            $options = $column->options ?? [];

            if ($this->isAssocArray($options) && isset($options[$value])) {
                return $options[$value];
            }

            return (string) $value;
        }

        // Handle checkbox type
        if ($type === 'checkbox' || $type === 'chk') {
            if (! is_array($value)) {
                return null;
            }

            // Extract checked items: {"option1": true, "option2": false, "option3": true}
            $checkedKeys = array_keys(array_filter($value, fn ($v) => $v === true));

            if (empty($checkedKeys)) {
                return null;
            }

            $options = $column->options ?? [];
            $labels = [];

            foreach ($checkedKeys as $key) {
                if ($this->isAssocArray($options) && isset($options[$key])) {
                    $labels[] = $options[$key];
                } else {
                    $labels[] = $key;
                }
            }

            return implode('、', $labels);
        }

        // Handle number type with unit
        if ($type === 'number') {
            $options = $column->options ?? [];
            $unit = $options['unit'] ?? '';

            if (! empty($unit)) {
                return $value.' '.$unit;
            }

            return (string) $value;
        }

        // Handle files type
        if ($type === 'files') {
            if (! is_array($value) || empty($value)) {
                return null;
            }

            // Extract original filenames: {"hashed.pdf": "original.pdf", "hashed.jpg": "photo.jpg"}
            $fileNames = array_values($value);

            return implode('、', $fileNames);
        }

        // Handle other types
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Check if an array is associative (has string keys).
     */
    private function isAssocArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Split text into chunks.
     */
    private function chunkText(string $text): array
    {
        $chunkSize = config('rag.chunking.size', 2000);
        $overlapSize = config('rag.chunking.overlap', 400);
        $chunks = [];

        if (empty($text)) {
            return [];
        }

        $textLength = mb_strlen($text);
        $position = 0;

        while ($position < $textLength) {
            $chunkText = mb_substr($text, $position, $chunkSize);
            $chunks[] = [
                'text' => $chunkText,
            ];
            $position += ($chunkSize - $overlapSize);
            if ($position >= $textLength) {
                break;
            }
        }

        return $chunks;
    }
}
