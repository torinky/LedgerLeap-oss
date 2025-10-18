<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $embeddingServiceUrl;
    private int $timeout;
    private string $logChannel;

    public function __construct()
    {
        $this->embeddingServiceUrl = config('rag.embedding_service.url', 'http://embedding:8000');
        $this->timeout = config('rag.embedding_service.timeout', 60);
        $this->logChannel = config('rag.log_channel', 'stack');
    }

    /**
     * Embed texts using the Python embedding service.
     *
     * @param string|array $texts The text(s) to embed.
     * @return array The embedding(s).
     * @throws \Exception If the embedding process fails.
     */
    public function embed(string|array $texts): array
    {
        $isSingleText = is_string($texts);
        $textsToEmbed = $isSingleText ? [$texts] : $texts;

        if (empty($textsToEmbed)) {
            return [];
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->embeddingServiceUrl}/embed", [
                    'texts' => $textsToEmbed,
                    'normalize' => true,
                ]);

            if (!$response->successful()) {
                Log::channel($this->logChannel)->error('Embedding service returned an error.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new \RuntimeException(
                    "Embedding service returned status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();
            $duration = microtime(true) - $startTime;

            Log::channel($this->logChannel)->info('Embedding completed successfully.', [
                'text_count' => count($textsToEmbed),
                'duration_ms' => round($duration * 1000, 2),
                'dimension' => $data['dimension'] ?? 'N/A',
                'model' => $data['model'] ?? 'N/A',
            ]);

            return $isSingleText ? $data['embeddings'][0] : $data['embeddings'];

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('Failed to call embedding service.', [
                'error' => $e->getMessage(),
                'text_count' => count($textsToEmbed),
            ]);
            throw $e;
        }
    }

    /**
     * Check the health of the embedding service.
     *
     * @return bool True if the service is healthy, false otherwise.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->embeddingServiceUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('Embedding service health check failed.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
