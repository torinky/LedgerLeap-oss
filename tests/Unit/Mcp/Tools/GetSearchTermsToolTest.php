<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetSearchTermsTool;
use App\Models\User;
use App\Services\SynonymService;
use Laravel\Mcp\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetSearchTermsToolTest extends TestCase
{
    private SynonymService $synonymService;

    private GetSearchTermsTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->synonymService = Mockery::mock(SynonymService::class);
        $this->tool = new GetSearchTermsTool($this->synonymService);

        Sanctum::actingAs(User::factory()->make(), ['mcp:*']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_synonym_and_technical_candidates(): void
    {
        $this->synonymService->expects('getSearchTermsFromWord')
            ->with('請求')
            ->andReturn([
                ['term' => '請求', 'kind' => 'original'],
                ['term' => '請求書', 'kind' => 'synonym'],
                ['term' => 'インボイス', 'kind' => 'technical'],
            ]);

        $this->synonymService->expects('getSynonymsFromWord')
            ->with('請求')
            ->andReturn(['請求書', 'インボイス']);

        $response = $this->tool->handle(new Request([
            'q' => '請求',
            'kind' => 'all',
        ]));

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('請求', $data['q']);
        $this->assertSame('all', $data['kind']);
        $this->assertSame(2, $data['candidate_count']);
        $this->assertSame('請求書 インボイス', $data['suggested_query']);
        $this->assertSame([
            ['term' => '請求書', 'kind' => 'synonym'],
            ['term' => 'インボイス', 'kind' => 'technical'],
        ], $data['candidates']);
        $this->assertSame('請求', $data['search_trace']['original_q']);
    }

    #[Test]
    public function it_can_filter_candidates_by_kind(): void
    {
        $this->synonymService->expects('getSearchTermsFromWord')
            ->with('請求')
            ->andReturn([
                ['term' => '請求', 'kind' => 'original'],
                ['term' => '請求書', 'kind' => 'synonym'],
                ['term' => 'インボイス', 'kind' => 'technical'],
            ]);

        $this->synonymService->expects('getSynonymsFromWord')
            ->with('請求')
            ->andReturn(['請求書', 'インボイス']);

        $response = $this->tool->handle(new Request([
            'q' => '請求',
            'kind' => 'technical',
        ]));

        $this->assertFalse($response->isError());
        $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('technical', $data['kind']);
        $this->assertSame(1, $data['candidate_count']);
        $this->assertSame([
            ['term' => 'インボイス', 'kind' => 'technical'],
        ], $data['candidates']);
        $this->assertSame('インボイス', $data['suggested_query']);
    }
}
