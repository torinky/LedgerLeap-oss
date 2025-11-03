<?php

namespace App\Services;

use App\Models\AttachedFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VlmClientService
{
    private string $vlmServiceUrl;
    private int $timeout;
    private string $defaultModel;
    private string $logChannel;

    public function __construct()
    {
        $this->vlmServiceUrl = config('vlm.url', 'http://vlm:8000');
        $this->timeout = config('vlm.timeout', 300);
        $this->defaultModel = config('vlm.default_model', 'paddleocr-vl');
        $this->logChannel = config('vlm.log_channel', 'stack');
    }

    /**
     * Extracts markdown and structured data from an attached file using the VLM service.
     *
     * @param AttachedFile $attachedFile The attached file model instance to process.
     * @return array An associative array containing the VLM extraction results,
     *               typically including 'markdown', 'structured_data', 'html', 'model', etc.
     *               Example:
     *               [
     *                   'success' => true,
     *                   'html' => '<html><body>...</body></html>',
     *                   'markdown' => '# Document Title\n\n...',
     *                   'structured_data' => [
     *                       'pages' => [...],
     *                       'tables' => [...],
     *                       'key_value_pairs' => [...],
     *                       'text_blocks' => [...]
     *                   ],
     *                   'processing_time_s' => 12.34,
     *                   'model' => 'paddleocr-vl',
     *                   'device' => 'cpu'
     *               ]
     * @throws RuntimeException If the file path is not available, or VLM service returns an error.
     * @throws ConnectionException If unable to connect to the VLM service.
     * @throws \Exception For any other unexpected errors during the process.
     */
    public function extract(AttachedFile $attachedFile): array
    {
        $this->waitUntilReady($this->timeout);

        $filePath = $attachedFile->getPhysicalPath();
        if (!$filePath) {
            throw new RuntimeException("File path is not available for AttachedFile ID: {$attachedFile->id}");
        }

        $startTime = microtime(true);

        try {
            $response = Http::asMultipart()
                ->timeout($this->timeout)
                ->attach(
                    'file',
                    file_get_contents($filePath),
                    $attachedFile->getOriginalFilenameAttribute() ?? basename($filePath)
                )
                ->post("{$this->vlmServiceUrl}/extract/structured");

            if (!$response->successful()) {
                Log::channel($this->logChannel)->error('VLM service returned an error.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'file_id' => $attachedFile->id,
                ]);
                throw new RuntimeException(
                    "VLM service returned status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();
            $duration = microtime(true) - $startTime;

            Log::channel($this->logChannel)->info('VLM extraction completed successfully.', [
                'file_id' => $attachedFile->id,
                'duration_ms' => round($duration * 1000, 2),
                'model' => $data['model'] ?? 'N/A',
            ]);

            return $data;

        } catch (ConnectionException $e) {
            Log::channel($this->logChannel)->error('Failed to connect to VLM service.', [
                'error' => $e->getMessage(),
                'file_id' => $attachedFile->id,
            ]);
            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw RuntimeException to be handled by the caller
            throw $e;
        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('An unexpected error occurred during VLM extraction.', [
                'error' => $e->getMessage(),
                'file_id' => $attachedFile->id,
            ]);
            throw new RuntimeException('An unexpected error occurred during VLM extraction.', 0, $e);
        }
    }

    public function healthCheck(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->vlmServiceUrl}/health");

            if ($response->serverError()) {
                return ['status' => 'unhealthy', 'message' => 'Server error'];
            }

            return $response->json() ?? ['status' => 'unhealthy', 'message' => 'Invalid response'];

        } catch (ConnectionException $e) {
            Log::channel($this->logChannel)->warning('VLM service is not reachable yet.', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'unreachable'];
        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('VLM service health check failed.', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function waitUntilReady(int $timeoutSeconds): void
    {
        $startTime = time();
        while (time() - $startTime < $timeoutSeconds) {
            $health = $this->healthCheck();
            $status = $health['status'] ?? 'unhealthy';

            if ($status === 'healthy') {
                Log::channel($this->logChannel)->info('VLM service is ready.');
                return;
            }

            Log::channel($this->logChannel)->warning('VLM service is not healthy, waiting...', ['health' => $health]);

            sleep(10); // Wait for 10 seconds before retrying
        }

        throw new RuntimeException("VLM service did not become ready within {$timeoutSeconds} seconds.");
    }
}
