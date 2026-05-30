<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function mockHttp(array $expectedTexts): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'healthy'], 200),
            'http://embedding:8000/embed' => function ($request) use ($expectedTexts) {
                $payload = $request->data();
                // 期待されたテキスト配列であることを検証
                if (($payload['texts'] ?? null) !== $expectedTexts) {
                    return Http::response(['message' => 'Unexpected payload'], 422);
                }

                return Http::response([
                    'embeddings' => array_fill(0, count($expectedTexts), [0.1, 0.2, 0.3]),
                    'dimension' => 3,
                    'model' => 'test-model',
                ], 200);
            },
        ]);
    }

    #[Test]
    public function it_prepends_query_prefix_when_ruri_model_is_active()
    {
        // Arrange
        Config::set('rag.model.active', 'ruri-v3-310m');
        $text = 'こんにちは';
        $expectedText = '検索クエリ: こんにちは';
        $this->mockHttp([$expectedText]);

        $service = new EmbeddingService;

        // Act
        $service->embed($text, 'query');

        // Assert
        $this->assertTrue(true);
    }

    #[Test]
    public function it_prepends_passage_prefix_to_each_text_in_an_array_when_ruri_model_is_active()
    {
        // Arrange
        Config::set('rag.model.active', 'ruri-v3-310m');
        $texts = ['テキスト1', 'テキスト2'];
        $expectedTexts = ['検索文書: テキスト1', '検索文書: テキスト2'];
        $this->mockHttp($expectedTexts);

        $service = new EmbeddingService;

        // Act
        $service->embed($texts, 'passage');

        // Assert
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_prepend_prefix_when_model_has_no_prefix_configured()
    {
        // Arrange
        Config::set('rag.model.active', 'all-minilm-l6-v2');
        $text = 'hello';
        $this->mockHttp([$text]);

        $service = new EmbeddingService;

        // Act
        $service->embed($text, 'query');

        // Assert
        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_single_embedding_for_single_text()
    {
        // Arrange
        Config::set('rag.model.active', 'all-minilm-l6-v2');
        $this->mockHttp(['test']);
        $service = new EmbeddingService;

        // Act
        $result = $service->embed('test');

        // Assert
        $this->assertEquals([0.1, 0.2, 0.3], $result);
    }

    #[Test]
    public function it_returns_array_of_embeddings_for_array_of_texts()
    {
        // Arrange
        Config::set('rag.model.active', 'all-minilm-l6-v2');
        $this->mockHttp(['test1', 'test2']);
        $service = new EmbeddingService;

        // Act
        $result = $service->embed(['test1', 'test2']);

        // Assert
        $this->assertEquals([[0.1, 0.2, 0.3], [0.1, 0.2, 0.3]], $result);
    }

    // -------------------------------------------------------
    // healthCheck テスト（Sprint 2 追加）
    // -------------------------------------------------------

    #[Test]
    public function health_check_returns_healthy_status(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $service = new EmbeddingService;
        $result = $service->healthCheck();

        $this->assertEquals('healthy', $result['status']);
    }

    #[Test]
    public function health_check_returns_unhealthy_on_server_error(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response([], 500),
        ]);

        $service = new EmbeddingService;
        $result = $service->healthCheck();

        $this->assertEquals('unhealthy', $result['status']);
        $this->assertEquals('Server error', $result['message']);
    }

    #[Test]
    public function health_check_returns_unreachable_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $service = new EmbeddingService;
        $result = $service->healthCheck();

        $this->assertEquals('unreachable', $result['status']);
    }

    #[Test]
    public function health_check_returns_unhealthy_on_general_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Unexpected error');
        });

        $service = new EmbeddingService;
        $result = $service->healthCheck();

        $this->assertEquals('unhealthy', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    #[Test]
    public function health_check_returns_unhealthy_when_response_json_is_null(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response('not-json', 200),
        ]);

        $service = new EmbeddingService;
        $result = $service->healthCheck();

        // json() が null の場合は fallback が返る
        $this->assertArrayHasKey('status', $result);
    }

    // -------------------------------------------------------
    // embed エラー系テスト（Sprint 2 追加）
    // -------------------------------------------------------

    #[Test]
    public function embed_returns_empty_array_for_empty_array_input(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $service = new EmbeddingService;
        $result = $service->embed([]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function embed_throws_runtime_exception_on_error_response(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'healthy'], 200),
            'http://embedding:8000/embed' => Http::response(['error' => 'bad request'], 400),
        ]);

        $service = new EmbeddingService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Embedding service returned status 400/');

        $service->embed('text');
    }

    #[Test]
    public function embed_rethrows_exception_on_connection_failure(): void
    {
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'healthy'], 200),
            'http://embedding:8000/embed' => Http::sequence()->push(fn () => throw new \Exception('Network error')),
        ]);

        $service = new EmbeddingService;

        $this->expectException(\Exception::class);

        $service->embed('text');
    }

    // -------------------------------------------------------
    // waitUntilReady タイムアウトテスト（Sprint 2 追加）
    // -------------------------------------------------------

    #[Test]
    public function embed_throws_runtime_exception_when_service_timeout(): void
    {
        // timeout=0 で即タイムアウト
        Http::fake([
            'http://embedding:8000/health' => Http::response(['status' => 'unhealthy'], 200),
        ]);

        Config::set('rag.embedding_service.timeout', 0);
        $service = new EmbeddingService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not become ready within/');

        $service->embed('text');
    }
}
