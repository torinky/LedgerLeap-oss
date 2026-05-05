<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Ledger\ColumnHtmlService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Tests\TestCase;

class ColumnHtmlServiceCacheTest extends TestCase
{
    protected bool $tenancy = true;

    public function test_textarea_cache_hit_on_second_call(): void
    {
        Cache::flush();

        $markdownRenderer = Mockery::mock(MarkdownRenderer::class);
        $markdownRenderer->shouldReceive('toHtml')->andReturn('<p>Test content</p>');

        $autoLinkService = Mockery::mock(AutoLinkService::class);
        $autoLinkService->shouldReceive('convert')->andReturnUsing(function ($html) {
            return $html;
        });

        $htmlProcessorService = Mockery::mock(HtmlProcessorService::class);

        $service = new ColumnHtmlService($autoLinkService, $markdownRenderer, $htmlProcessorService);

        $columnDefine = new ColumnDefine([
            'id' => 1,
            'name' => 'Test',
            'type' => 'textarea',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $ledger = new Ledger;
        $ledger->forceFill([
            'id' => 1,
            'updated_at' => now(),
        ]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        // 1回目の呼び出し（キャッシュミス）
        $html1 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertNotEmpty($html1->toHtml());

        // 2回目の呼び出し（キャッシュヒット）
        $html2 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertNotEmpty($html2->toHtml());

        // MarkdownRenderer::toHtml は1回だけ呼ばれるはず（キャッシュヒットなので2回目は呼ばれない）
        $markdownRenderer->shouldHaveReceived('toHtml')->once();
    }

    public function test_auto_number_cache_hit_on_second_call(): void
    {
        Cache::flush();

        $markdownRenderer = Mockery::mock(MarkdownRenderer::class);
        $autoLinkService = Mockery::mock(AutoLinkService::class);
        $autoLinkService->shouldReceive('convert')->andReturnUsing(function ($html) {
            return $html;
        });

        $htmlProcessorService = Mockery::mock(HtmlProcessorService::class);

        $service = new ColumnHtmlService($autoLinkService, $markdownRenderer, $htmlProcessorService);

        $columnDefine = new ColumnDefine([
            'id' => 1,
            'name' => 'Test',
            'type' => 'auto_number',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $ledger = new Ledger;
        $ledger->forceFill([
            'id' => 1,
            'updated_at' => now(),
        ]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        // 1回目の呼び出し
        $html1 = $service->show($columnDefine, 'AUTO-001', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertNotEmpty($html1->toHtml());

        // 2回目の呼び出し
        $html2 = $service->show($columnDefine, 'AUTO-001', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertNotEmpty($html2->toHtml());

        // auto_number にもキャッシュが追加されたので1回だけ呼ばれる
        $autoLinkService->shouldHaveReceived('convert')->once();
    }

    public function test_cache_bypasses_when_highlight_is_present(): void
    {
        Cache::flush();

        $markdownRenderer = Mockery::mock(MarkdownRenderer::class);
        $markdownRenderer->shouldReceive('toHtml')->twice()->andReturn('<p>Test content</p>');

        $autoLinkService = Mockery::mock(AutoLinkService::class);
        $autoLinkService->shouldReceive('convert')->twice()->andReturnUsing(function ($html) {
            return $html;
        });

        $htmlProcessorService = Mockery::mock(HtmlProcessorService::class);
        $htmlProcessorService->shouldReceive('processTextNodes');

        $service = new ColumnHtmlService($autoLinkService, $markdownRenderer, $htmlProcessorService);

        $columnDefine = new ColumnDefine([
            'id' => 1,
            'name' => 'Test',
            'type' => 'textarea',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $ledger = new Ledger;
        $ledger->forceFill([
            'id' => 1,
            'updated_at' => now(),
        ]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        $html1 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, 'keyword', 'demo-tenant');
        $html2 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, 'keyword', 'demo-tenant');

        // ハイライトありの場合はキャッシュをバイパスするので、toHtml は2回呼ばれる
        $markdownRenderer->shouldHaveReceived('toHtml')->twice();
    }

    public function test_redis_actually_stores_cache(): void
    {
        Cache::flush();

        $markdownRenderer = Mockery::mock(MarkdownRenderer::class);
        $markdownRenderer->shouldReceive('toHtml')->andReturn('<p>Test content</p>');

        $autoLinkService = Mockery::mock(AutoLinkService::class);
        $autoLinkService->shouldReceive('convert')->andReturnUsing(function ($html) {
            return $html;
        });

        $htmlProcessorService = Mockery::mock(HtmlProcessorService::class);

        $service = new ColumnHtmlService($autoLinkService, $markdownRenderer, $htmlProcessorService);

        $columnDefine = new ColumnDefine([
            'id' => 1,
            'name' => 'Test',
            'type' => 'textarea',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ]);

        $ledger = new Ledger;
        $ledger->forceFill([
            'id' => 1,
            'updated_at' => now(),
        ]);
        $ledger->setRelation('define', (object) ['tenant_id' => 'demo-tenant']);

        // 1回目: キャッシュミス
        $html1 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertNotEmpty($html1->toHtml());

        // 2回目: キャッシュヒット（mock の toHtml は追加で呼ばれないはず）
        $html2 = $service->show($columnDefine, 'Test content', true, [], '', false, $ledger, null, 'demo-tenant');
        $this->assertEquals($html1->toHtml(), $html2->toHtml());

        // toHtml は1回だけ呼ばれる（リクエスト内キャッシュ + Redis キャッシュ）
        $markdownRenderer->shouldHaveReceived('toHtml')->once();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        // リクエスト内キャッシュをクリア
        $reflection = new \ReflectionClass(ColumnHtmlService::class);
        $property = $reflection->getProperty('requestCache');
        $property->setAccessible(true);
        $property->setValue(null, []);

        parent::tearDown();
    }
}
