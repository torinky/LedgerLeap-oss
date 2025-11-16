<?php

namespace App\Jobs;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\Embedding\KeywordEnhancedTextGenerator;
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
     */
    public function __construct(
        public int $ledgerId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService, ?KeywordEnhancedTextGenerator $keywordGenerator = null): void
    {
        if (! config('rag.enabled', false)) {
            return;
        }

        // KeywordEnhancedTextGeneratorがnullの場合は新規作成（テスト互換性）
        $keywordGenerator = $keywordGenerator ?? new KeywordEnhancedTextGenerator;

        $logChannel = config('rag.log_channel', 'stack');
        $startTime = microtime(true);

        // QueueTenancyBootstrapperが自動的にtenancyを初期化済み
        $ledger = Ledger::find($this->ledgerId);

        if (! $ledger) {
            Log::channel($logChannel)->warning('Ledger not found in job', [
                'ledger_id' => $this->ledgerId,
            ]);

            return;
        }

        Log::channel($logChannel)->info('Start chunking process for ledger', [
            'ledger_id' => $ledger->id,
            'tenant_id' => $ledger->tenant_id ?? 'N/A',
        ]);

        // ★★★ STEP 1: データ準備フェーズ ★★★
        $this->updateContentAttachedWithVlmResult($ledger);

        // 1. Delete existing chunks to ensure data consistency
        DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->delete();

        // 2. Build structured Markdown from ledger
        try {
            $markdown = $this->buildMarkdownFromLedger($ledger);
            $generationTime = (microtime(true) - $startTime) * 1000;

            if ($generationTime > 1000) {
                Log::channel($logChannel)->warning('Markdown generation took too long', [
                    'ledger_id' => $ledger->id,
                    'generation_time_ms' => $generationTime,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Markdown generation failed', [
                'ledger_id' => $ledger->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (empty($markdown)) {
            Log::channel($logChannel)->info('No content to chunk for ledger', [
                'ledger_id' => $ledger->id,
            ]);

            return;
        }

        // 3. Chunk the Markdown text
        $chunks = $this->chunkText($markdown);

        if (empty($chunks)) {
            Log::channel($logChannel)->info('No chunks generated for ledger', [
                'ledger_id' => $ledger->id,
            ]);

            return;
        }

        // 4. Generate embeddings and save to DB
        try {
            // Phase 2.5: キーワード拡張を適用
            $chunkTexts = array_column($chunks, 'text');
            $enhancedChunkTexts = array_map(
                fn ($text) => $keywordGenerator->generateEnhancedText($text),
                $chunkTexts
            );

            $embeddings = $embeddingService->embed($enhancedChunkTexts, 'passage');

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
                    'chunk_index' => $index,
                    'chunk_text' => $chunk['text'],
                    'embedding' => json_encode($embeddings[$index]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('ledger_chunks')->insert($chunkData);

            Log::channel($logChannel)->info('Chunking process completed for ledger', [
                'ledger_id' => $ledger->id,
                'chunks_created' => count($chunkData),
                'markdown_length' => mb_strlen($markdown),
                'total_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Chunking process failed for ledger', [
                'ledger_id' => $ledger->id,
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

        if (empty($columnDefinesData)) {
            return '';
        }

        $columnDefines = collect($columnDefinesData)->map(function ($data) {
            return new ColumnDefine($data);
        });

        $groupedColumns = $columnDefines->groupBy('group');

        foreach ($groupedColumns as $groupName => $columns) {
            // Handle empty group name as "その他"
            $displayGroupName = ! empty($groupName) ? $groupName : 'その他';
            $lines[] = "## {$displayGroupName}";
            $lines[] = '';

            foreach ($columns as $column) {
                $value = $this->getColumnValue($ledger, $column);

                if ($value === null) {
                    continue;
                }

                // display_levelに応じてヘッダーレベルを調整
                // level 1 → ###, level 2 → ####, level 3 → #####
                $headerLevel = str_repeat('#', $column->display_level + 2);
                $lines[] = "{$headerLevel} {$column->name}";
                $lines[] = '';
                $lines[] = $value;
                $lines[] = '';
            }
        }

        // 3. Add attachments section
        $contentAttached = $ledger->content_attached ?? [];

        if (! empty($contentAttached)) {
            $lines[] = '---';
            $lines[] = '## 添付ファイル';
            $lines[] = '';

            foreach ($contentAttached as $columnId => $files) {
                if (! is_array($files) || empty($files)) {
                    continue;
                }

                $column = $columnDefines->firstWhere('id', $columnId);

                if (! $column) {
                    continue;
                }

                $lines[] = "### {$column->name}";
                $lines[] = '';

                foreach ($files as $hashedBasename => $fileData) {
                    if (! is_array($fileData)) {
                        continue;
                    }

                    $originalName = $fileData['originalName'] ?? $hashedBasename;
                    $meta = $fileData['meta'] ?? [];
                    $content = $meta['content'] ?? null;

                    $lines[] = "#### ファイル: {$originalName}";
                    $lines[] = '';

                    if (! empty($content)) {
                        $trimmedContent = mb_strlen($content) > 5000
                            ? mb_substr($content, 0, 5000).'...'
                            : $content;

                        $lines[] = $trimmedContent;
                        $lines[] = '';
                    }
                }
            }
        }

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

    private function updateContentAttachedWithVlmResult(Ledger $ledger): void
    {
        $logChannel = config('rag.log_channel', 'stack');
        $wasUpdated = false;
        $contentAttached = $ledger->content_attached ?? [];

        Log::channel($logChannel)->info('[VLM Debug] Start updateContentAttachedWithVlmResult', [
            'ledger_id' => $ledger->id,
            'initial_content_attached' => $contentAttached,
        ]);

        // 関連ファイルをEager Load
        $ledger->load('attachedFiles');

        Log::channel($logChannel)->info('[VLM Debug] Loaded attachedFiles', [
            'ledger_id' => $ledger->id,
            'attachedFiles_count' => $ledger->attachedFiles->count(),
            'attachedFiles' => $ledger->attachedFiles->map(fn ($f) => [
                'id' => $f->id,
                'hashedbasename' => $f->hashedbasename,
                'column_id' => $f->column_id,
                'vlm_markdown_length' => mb_strlen($f->vlm_markdown ?? ''),
            ])->toArray(),
        ]);

        // Ensure content_attached has all column positions initialized (required by AsColumnArrayJson)
        $columnDefines = $ledger->define->column_define;
        $maxColumnId = $columnDefines->max('id');

        // Initialize all column positions to preserve array indices
        for ($i = 0; $i <= $maxColumnId; $i++) {
            if (! isset($contentAttached[$i])) {
                $contentAttached[$i] = [];
            }
        }

        foreach ($ledger->attachedFiles as $file) {
            if (empty($file->vlm_markdown)) {
                continue;
            }

            $vlmText = $file->vlm_markdown;
            $vlmTextLength = mb_strlen($vlmText);
            $columnId = $file->column_id;

            // content_attachedの構造: [column_id][hashedbasename]['meta']['content']
            $existingText = $contentAttached[$columnId][$file->hashedbasename]['meta']['content'] ?? '';
            $existingTextLength = mb_strlen($existingText);

            if ($vlmTextLength > $existingTextLength) {
                // VLMのテキストで上書き
                // 配列構造を確保
                if (! isset($contentAttached[$columnId])) {
                    $contentAttached[$columnId] = [];
                }
                if (! isset($contentAttached[$columnId][$file->hashedbasename])) {
                    $contentAttached[$columnId][$file->hashedbasename] = [];
                }
                if (! isset($contentAttached[$columnId][$file->hashedbasename]['meta'])) {
                    $contentAttached[$columnId][$file->hashedbasename]['meta'] = [];
                }

                $contentAttached[$columnId][$file->hashedbasename]['meta']['content'] = $vlmText;

                // originalNameがない場合は、contentから取得
                if (! isset($contentAttached[$columnId][$file->hashedbasename]['originalName'])) {
                    $content = $ledger->content ?? [];
                    $originalName = $content[$columnId][$file->hashedbasename] ?? $file->filename;
                    $contentAttached[$columnId][$file->hashedbasename]['originalName'] = $originalName;
                }

                $wasUpdated = true;

                Log::channel($logChannel)->info('[RAG Pre-processing] Updated content_attached with VLM result.', [
                    'ledger_id' => $ledger->id,
                    'file_id' => $file->id,
                    'column_id' => $columnId,
                    'hashedbasename' => $file->hashedbasename,
                    'vlm_text_length' => $vlmTextLength,
                    'old_text_length' => $existingTextLength,
                ]);
            }
        }

        if ($wasUpdated) {
            $ledger->content_attached = $contentAttached;

            Log::channel($logChannel)->info('[VLM Debug] Before save', [
                'ledger_id' => $ledger->id,
                'content_attached_to_save' => $contentAttached,
            ]);

            Ledger::withoutEvents(fn () => $ledger->save());

            Log::channel($logChannel)->info('[RAG Pre-processing] Saved updated content_attached to database.', [
                'ledger_id' => $ledger->id,
            ]);

            // Verify save
            $ledger->refresh();
            Log::channel($logChannel)->info('[VLM Debug] After save and refresh', [
                'ledger_id' => $ledger->id,
                'content_attached_from_db' => $ledger->content_attached,
            ]);
        } else {
            Log::channel($logChannel)->info('[VLM Debug] No updates made', [
                'ledger_id' => $ledger->id,
            ]);
        }
    }
}
