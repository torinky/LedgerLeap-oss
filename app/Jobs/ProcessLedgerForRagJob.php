<?php

namespace App\Jobs;

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

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        if (!config('rag.enabled', false)) {
            return;
        }

        $logChannel = config('rag.log_channel', 'stack');
        Log::channel($logChannel)->info('Start chunking process for ledger', [
            'ledger_id' => $this->ledger->id
        ]);

        // 1. Delete existing chunks to ensure data consistency
        DB::table('ledger_chunks')->where('ledger_id', $this->ledger->id)->delete();

        $chunks = [];

        // 2. Chunk ledger.content
        if (!empty($this->ledger->content)) {
            $contentText = $this->extractTextFromContent($this->ledger->content);
            if (!empty($contentText)) {
                $contentChunks = $this->chunkText($contentText, 'content');
                $chunks = array_merge($chunks, $contentChunks);
            }
        }

        // 3. Chunk ledger.content_attached
        if (!empty($this->ledger->content_attached)) {
            $attachedText = $this->ledger->content_attached;
            if (is_array($attachedText)) {
                $attachedText = implode("\n\n", $attachedText);
            }

            if (!empty($attachedText)) {
                $attachedChunks = $this->chunkText(
                    $attachedText,
                    'content_attached'
                );
                $chunks = array_merge($chunks, $attachedChunks);
            }
        }

        if (empty($chunks)) {
            Log::channel($logChannel)->info('No content to chunk for ledger', [
                'ledger_id' => $this->ledger->id
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
                if (!isset($embeddings[$index])) continue;

                $chunkData[] = [
                    'ledger_id' => $this->ledger->id,
                    'ledger_define_id' => $this->ledger->ledger_define_id,
                    'folder_id' => $this->ledger->define->folder_id,
                    'chunk_index' => $index,
                    'chunk_text' => $chunk['text'],
                    'chunk_source' => $chunk['source'],
                    'embedding' => json_encode($embeddings[$index]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('ledger_chunks')->insert($chunkData);

            Log::channel($logChannel)->info('Chunking process completed for ledger', [
                'ledger_id' => $this->ledger->id,
                'chunks_created' => count($chunkData)
            ]);

        } catch (\Exception $e) {
            Log::channel($logChannel)->error('Chunking process failed for ledger', [
                'ledger_id' => $this->ledger->id,
                'error' => $e->getMessage(),
            ]);
            // Optionally, re-throw the exception to let the queue handle retries
            throw $e;
        }
    }

    /**
     * Extract text from the JSON content of a ledger.
     */
    private function extractTextFromContent(array $content): string
    {
        $textFragments = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($content));

        foreach ($iterator as $key => $value) {
            // Extract only string values, ignore keys and non-string values
            if (is_string($value) && !empty(trim($value))) {
                $textFragments[] = trim($value);
            }
        }

        return implode("\n\n", $textFragments);
    }

    /**
     * Split text into chunks.
     */
    private function chunkText(string $text, string $source): array
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
                'source' => $source
            ];
            $position += ($chunkSize - $overlapSize);
            if ($position >= $textLength) break;
        }

        return $chunks;
    }


}