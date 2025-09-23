<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\AutoLinkService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutoLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        // テナントを作成し、コンテキストを設定
        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    #[Test]
    public function create_auto_number_link_generates_correct_url()
    {
        // テスト用のクエリ
        $query = '12345';

        // AutoLinkService のインスタンスを作成
        $htmlProcessorService = $this->createMock(HtmlProcessorService::class);
        $service = new AutoLinkService($htmlProcessorService);

        // Reflection を使用して protected メソッドにアクセス
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createAutoNumberLink');
        $method->setAccessible(true); // アクセス可能にする

        // createAutoNumberLink メソッドを呼び出し
        $linkHtml = $method->invokeArgs($service, [$query]);

        // 生成されるURLの期待値
        // routes/web.php で定義した 'ledger.lookup' ルート
        $expectedUrl = url('/ledgers/lookup/' . urlencode($query)); // 修正

        // 生成されたHTMLに期待されるURLが含まれていることをアサート
        $this->assertStringContainsString($expectedUrl, $linkHtml);
        $this->assertStringContainsString('target="_blank"', $linkHtml); // 新しいタブで開くことを確認
        $this->assertStringContainsString($query, $linkHtml); // クエリ文字列が含まれていることを確認
    }
}