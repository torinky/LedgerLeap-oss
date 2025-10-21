<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

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
     * @param string $type The type of text being embedded ('query' or 'passage').
     * @return array The embedding(s).
     * @throws \Exception If the embedding process fails or times out.
     */
    public function embed(string|array $texts, string $type = 'query'): array
    {
        // Wait for the service to become ready before proceeding.
        $this->waitUntilReady($this->timeout);

        $isSingleText = is_string($texts);
        $textsToEmbed = $isSingleText ? [$texts] : $texts;

        if (empty($textsToEmbed)) {
            return [];
        }

        // Get prefix from config based on active model and type
        $activeModel = config('rag.model.active');
        $prefix = config("rag.model.available_models.{$activeModel}.prefix.{$type}", '');

        if (!empty($prefix)) {
            $textsToEmbed = array_map(fn($text) => $prefix . $text, $textsToEmbed);
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
     * @return array The health status response from the service.
     */
    public function healthCheck(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->embeddingServiceUrl}/health");

            if ($response->serverError()) {
                return ['status' => 'unhealthy', 'message' => 'Server error'];
            }
            
            return $response->json() ?? ['status' => 'unhealthy', 'message' => 'Invalid response'];

        } catch (ConnectionException $e) {
            Log::channel($this->logChannel)->warning('Embedding service is not reachable yet.', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'unreachable'];
        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('Embedding service health check failed.', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Wait until the embedding service is ready.
     *
     * @param int $timeoutSeconds The maximum time to wait in seconds.
     * @throws \RuntimeException If the service does not become ready within the timeout.
     */
    private function waitUntilReady(int $timeoutSeconds): void
    {
        $startTime = time();
        while (time() - $startTime < $timeoutSeconds) {
            $health = $this->healthCheck();
            $status = $health['status'] ?? 'unhealthy';

            if ($status === 'healthy') {
                Log::channel($this->logChannel)->info('Embedding service is ready.');
                return;
            }

            if ($status === 'loading') {
                Log::channel($this->logChannel)->info('Embedding service is loading, waiting...');
            } else {
                Log::channel($this->logChannel)->warning('Embedding service is not healthy, waiting...', ['health' => $health]);
            }

            sleep(10); // Wait for 10 seconds before retrying
        }

        throw new \RuntimeException("Embedding service did not become ready within {$timeoutSeconds} seconds.");
    }
}
