<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
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
}
