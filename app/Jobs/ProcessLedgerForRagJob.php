<?php

namespace App\Jobs;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLedgerForRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Ledger $ledger
    ) {}

    public function getLedger(): Ledger
    {
        return $this->ledger;
    }

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        if (! config('rag.enabled', false)) {
            return;
        }

        $logChannel = config('rag.log_channel', 'stack');
        $startTime = microtime(true);

        Log::channel($logChannel)->info('Start chunking process for ledger', [
            'ledger_id' => $this->ledger->id,
        ]);

        // 1. Delete existing chunks to ensure data consistency
        DB::table('ledger_chunks')->where('ledger_id', $this->ledger->id)->delete();

        // 2. Build structured Markdown from ledger
        try {
            $markdown = $this->buildMarkdownFromLedger($this->ledger);
            $generationTime = (microtime(true) - $startTime) * 1000;

            if ($generationTime > 1000) {
                Log::channel($logChannel)->warning('Markdown generation took too long', [
                    'ledger_id' => $this->ledger->id,
                    'generation_time_ms' => $generationTime,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Markdown generation failed', [
                'ledger_id' => $this->ledger->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (empty($markdown)) {
            Log::channel($logChannel)->info('No content to chunk for ledger', [
                'ledger_id' => $this->ledger->id,
            ]);

            return;
        }

        // 3. Chunk the Markdown text
        $chunks = $this->chunkText($markdown);

        if (empty($chunks)) {
            Log::channel($logChannel)->info('No chunks generated for ledger', [
                'ledger_id' => $this->ledger->id,
            ]);

            return;
        }

        // 4. Generate embeddings and save to DB
        try {
            $chunkTexts = array_column($chunks, 'text');
            $embeddings = $embeddingService->embed($chunkTexts, 'passage');

            $chunkData = [];
            $now = now();

            foreach ($chunks as $index => $chunk) {
                if (! isset($embeddings[$index])) {
                    continue;
                }

                $chunkData[] = [
                    'ledger_id' => $this->ledger->id,
                    'ledger_define_id' => $this->ledger->ledger_define_id,
                    'folder_id' => $this->ledger->define->folder_id,
                    'chunk_index' => $index,
                    'chunk_text' => $chunk['text'],
                    'embedding' => json_encode($embeddings[$index]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('ledger_chunks')->insert($chunkData);

            Log::channel($logChannel)->info('Chunking process completed for ledger', [
                'ledger_id' => $this->ledger->id,
                'chunks_created' => count($chunkData),
                'markdown_length' => mb_strlen($markdown),
                'total_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Chunking process failed for ledger', [
                'ledger_id' => $this->ledger->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build structured Markdown text from ledger and its definition.
     */
    private function buildMarkdownFromLedger(Ledger $ledger): string
    {
        $lines = [];
        $logChannel = config('rag.log_channel', 'stack');

        $ledgerDefine = $ledger->define;
        $content = $ledger->content ?? [];

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

        // Ensure it's a collection
        if (! ($columnDefinesData instanceof \Illuminate\Support\Collection)) {
            $columnDefinesData = collect($columnDefinesData);
        }

        if ($columnDefinesData->isEmpty()) {
            Log::channel($logChannel)->info('No column definitions for ledger', [
                'ledger_id' => $ledger->id,
            ]);
        }

        // Group columns
        $groupedColumns = $columnDefinesData->groupBy(function ($column) {
            $col = is_array($column) ? (object) $column : $column;
            $group = $col->group ?? '';

            return $group === '' ? __('ledger.form.group_default') : $group;
        });

        // Sort groups by the order of their first column
        $sortedGroups = $groupedColumns->sortBy(function ($columns, $groupName) {
            if ($columns->isNotEmpty()) {
                $firstColumn = $columns->first();
                $col = is_array($firstColumn) ? (object) $firstColumn : $firstColumn;

                return $col->order ?? PHP_INT_MAX;
            }

            return PHP_INT_MAX;
        });

        $totalColumns = 0;
        $skippedColumns = 0;

        foreach ($sortedGroups as $groupName => $columnsInGroup) {
            $hasGroupContent = false;
            $groupLines = [];

            // Sort columns within group
            $sortedColumns = collect($columnsInGroup)->sortBy(function ($column) {
                $col = is_array($column) ? (object) $column : $column;

                return $col->order ?? PHP_INT_MAX;
            });

            foreach ($sortedColumns as $columnData) {
                $totalColumns++;
                $columnDefine = new ColumnDefine($columnData);

                // Get value from content
                // Note: content array uses column ID as index after normalization
                // e.g., column_define = [{id:1, ...}, {id:3, ...}]
                //       content = [0=>'', 1=>'value1', 2=>'', 3=>'value3']
                $value = $content[$columnDefine->id] ?? null;
                $textValue = $this->extractValueAsText($columnDefine, $value);

                if ($textValue === null) {
                    $skippedColumns++;

                    continue;
                }

                // Determine heading level based on display_level
                $displayLevel = $columnDefine->display_level ?? 1;
                $headingLevel = match ($displayLevel) {
                    1 => '###',
                    2 => '####',
                    3 => '#####',
                    default => '###'
                };

                if (! $hasGroupContent) {
                    $groupLines[] = "## {$groupName}";
                    $hasGroupContent = true;
                }

                $groupLines[] = "{$headingLevel} {$columnDefine->name}";
                $groupLines[] = $textValue;
                $groupLines[] = '';
            }

            if ($hasGroupContent) {
                $lines = array_merge($lines, $groupLines);
            }
        }

        // 3. Add attached file content
        if (! empty($ledger->content_attached)) {
            $attachedText = $ledger->content_attached;
            if (is_array($attachedText)) {
                $attachedText = implode("\n\n", $attachedText);
            }

            $maxAttachedLength = config('rag.chunking.max_attached_text_length', 50000);
            $originalLength = mb_strlen($attachedText);

            if ($originalLength > $maxAttachedLength) {
                $attachedText = mb_substr($attachedText, 0, $maxAttachedLength)
                    ."\n\n[... 以降のテキストは省略されました]";

                Log::channel($logChannel)->warning('Attached text truncated for RAG', [
                    'ledger_id' => $ledger->id,
                    'original_length' => $originalLength,
                    'truncated_length' => $maxAttachedLength,
                ]);
            }

            $lines[] = '## 添付ファイル内容';
            $lines[] = $attachedText;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Extract human-readable text value from a column's raw data.
     */
    private function extractValueAsText(ColumnDefine $column, $value): ?string
    {
        // Handle null or empty values
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $type = $column->getType();

        // Handle basic text types
        if (in_array($type, ['text', 'textarea', 'url', 'auto_number', 'date', 'phone_number', 'user_name'])) {
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        }

        // Handle number type with unit
        if ($type === 'number') {
            $text = (string) $value;
            $unit = $column->getInputType()->unit ?? null;
            if ($unit) {
                $text .= " {$unit}";
            }
            $text = trim($text);

            return $text === '' ? null : $text;
        }

        // Handle select type
        if ($type === 'select') {
            $options = $column->options ?? [];

            // Associative array: ["draft" => "下書き", "published" => "公開"]
            if ($this->isAssocArray($options) && isset($options[$value])) {
                return $options[$value];
            }

            // Simple array: ["option1", "option2"]
            if (in_array($value, $options, true)) {
                return $value;
            }

            // Fallback: return value as-is
            return trim((string) $value) ?: null;
        }

        // Handle checkbox type
        if ($type === 'chk') {
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
