<?php

namespace Tests\Unit\Services;

use App\Models\AttachedFile;
use App\Services\VlmClientService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class VlmClientServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // VLMサービスURLとタイムアウトをテスト用に固定
        Config::set('vlm.url', 'http://vlm.test');
        Config::set('vlm.timeout', 5); // テスト用に短く
        Config::set('vlm.default_model', 'test-model');
        Config::set('vlm.log_channel', 'stack');
    }

    #[Test]
    public function extract_successfully_calls_vlm_service_and_returns_data(): void
    {
        // Arrange
        Storage::fake('public');
        $fileName = 'test.pdf';
        Storage::disk('public')->put($fileName, 'dummy pdf content');

        $attachedFile = AttachedFile::factory()->create([
            'path' => $fileName,
        ]);

        // healthCheckをhealthyにすることでwaitUntilReadyを即座に完了させる
        Http::fake([
            'http://vlm.test/health' => Http::response(['status' => 'healthy'], 200),
            'http://vlm.test/extract/structured' => Http::response([
                'success' => true,
                'markdown' => '# Test Invoice\n\nAmount: $100',
                'structured_data' => ['amount' => 100],
                'model' => 'test-model',
                'processing_time_s' => 1.5,
            ], 200),
        ]);

        $service = app(VlmClientService::class);

        // Act
        $result = $service->extract($attachedFile);

        // Assert
        Http::assertSent(function ($request) {
            return $request->url() === 'http://vlm.test/extract/structured'
                && $request->hasFile('file');
        });

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('# Test Invoice\n\nAmount: $100', $result['markdown']);
        $this->assertEquals('test-model', $result['model']);
        $this->assertArrayHasKey('structured_data', $result);
    }

    #[Test]
    public function extract_throws_exception_on_vlm_service_error(): void
    {
        // Arrange
        Storage::fake('public');
        $fileName = 'test.pdf';
        Storage::disk('public')->put($fileName, 'dummy content');

        $attachedFile = AttachedFile::factory()->create(['path' => $fileName]);

        Http::fake([
            'http://vlm.test/health' => Http::response(['status' => 'healthy'], 200),
            'http://vlm.test/extract/structured' => Http::response('Internal Server Error', 500),
        ]);

        $service = app(VlmClientService::class);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('VLM service returned status 500');

        $service->extract($attachedFile);
    }

    #[Test]
    public function extract_throws_exception_on_connection_timeout(): void
    {
        // Arrange
        Storage::fake('public');
        $fileName = 'test.pdf';
        Storage::disk('public')->put($fileName, 'dummy content');

        $attachedFile = AttachedFile::factory()->create(['path' => $fileName]);

        Http::fake([
            'http://vlm.test/health' => Http::response(['status' => 'healthy'], 200),
            'http://vlm.test/extract/structured' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $service = app(VlmClientService::class);

        // Act & Assert
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection timed out');

        $service->extract($attachedFile);
    }

    #[Test]
    public function health_check_handles_healthy_status(): void
    {
        // Arrange
        Http::fake([
            'http://vlm.test/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $service = app(VlmClientService::class);

        // Act
        $result = $service->healthCheck();

        // Assert
        $this->assertEquals('healthy', $result['status']);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://vlm.test/health';
        });
    }

    #[Test]
    public function health_check_handles_server_error(): void
    {
        // Arrange
        Http::fake([
            'http://vlm.test/health' => Http::response(['error' => 'Service unavailable'], 500),
        ]);

        $service = app(VlmClientService::class);

        // Act
        $result = $service->healthCheck();

        // Assert
        $this->assertEquals('unhealthy', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    #[Test]
    public function health_check_handles_connection_failure(): void
    {
        // Arrange
        Http::fake([
            'http://vlm.test/health' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $service = app(VlmClientService::class);

        // Act
        $result = $service->healthCheck();

        // Assert
        $this->assertEquals('unreachable', $result['status']);
    }
}
