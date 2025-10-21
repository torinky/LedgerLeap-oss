<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockHttp(array $expectedTexts)
    {
        $mock = Mockery::mock('alias:Illuminate\Support\Facades\Http');

        // 1. healthCheck()からのget()呼び出しをモック
        $healthResponseMock = Mockery::mock(\Illuminate\Http\Client\Response::class);
        $healthResponseMock->shouldReceive('serverError')->andReturn(false); // serverError()はfalseを返す
        $healthResponseMock->shouldReceive('json')->andReturn(['status' => 'healthy']);

        $mock->shouldReceive('timeout')->with(5)->andReturnSelf();
        $mock->shouldReceive('get')
            ->with('http://embedding:8000/health')
            ->andReturn($healthResponseMock);

        // 2. embed()からのpost()呼び出しをモック
        $embedResponseMock = Mockery::mock(\Illuminate\Http\Client\Response::class);
        $embedResponseMock->shouldReceive('successful')->andReturn(true);
        $embedResponseMock->shouldReceive('json')->andReturn([
            'embeddings' => array_fill(0, count($expectedTexts), [0.1, 0.2, 0.3]),
            'dimension' => 3,
            'model' => 'test-model',
        ]);

        $mock->shouldReceive('timeout')->with(60)->andReturnSelf();
        $mock->shouldReceive('post')
            ->once()
            ->with('http://embedding:8000/embed', Mockery::on(function ($data) use ($expectedTexts) {
                return $data['texts'] === $expectedTexts;
            }))
            ->andReturn($embedResponseMock);
    }

    #[Test]
    public function it_prepends_query_prefix_when_ruri_model_is_active()
    {
        // Arrange
        Config::set('rag.model.active', 'ruri-v3-310m');
        $text = 'こんにちは';
        $expectedText = '検索クエリ: こんにちは';
        $this->mockHttp([$expectedText]);

        $service = new EmbeddingService();

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

        $service = new EmbeddingService();

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

        $service = new EmbeddingService();

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
        $service = new EmbeddingService();

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
        $service = new EmbeddingService();

        // Act
        $result = $service->embed(['test1', 'test2']);

        // Assert
        $this->assertEquals([[0.1, 0.2, 0.3], [0.1, 0.2, 0.3]], $result);
    }
}