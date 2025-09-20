<?php

namespace Tests\Unit\Livewire\Traits;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\URL;

// テスト用のダミーLivewireコンポーネント
class TestComponent extends \Livewire\Component
{
    use InitializesTenantContext;

    public function render()
    {
        return '<div></div>';
    }
}

class InitializesTenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    #[Test]
    public function it_initializes_tenant_context_if_not_initialized(): void
    {
        // テナントが存在しないことを確認
        $this->assertFalse(tenancy()->tenant !== null);

        // ダミーのテナントを作成
        $tenant = Tenant::create(['id' => 'testtenant']);

        // Log::spy() を使用してログ出力を監視
        Log::spy();

        // withRoute を使ってテナントIDを渡す
        Livewire::withRoute(function ($route) {
            $route->parameter('tenant', 'testtenant');
        })->test(TestComponent::class);

        // ログが出力されたことを確認
        Log::shouldHaveReceived('info')
            ->with('Tenant re-initialized via InitializesTenantContext trait', ['tenant_id' => 'testtenant'])
            ->once();

        // テナントが初期化されたことを確認
        $this->assertTrue(tenancy()->tenant !== null);
        $tenant->delete();
    }

    #[Test]
    public function it_does_not_reinitialize_tenant_context_if_already_initialized(): void
    {
        // 既にテナントが初期化されている状態にする
        $tenant = Tenant::create(['id' => 'existingtenant']);
        tenancy()->initialize($tenant);
        $this->assertTrue(tenancy()->tenant !== null);
        $this->assertEquals('existingtenant', tenancy()->tenant->id);

        // Log::spy() を使用
        Log::spy();

        Livewire::test(TestComponent::class);

        // Log::info, Log::error が呼び出されないことを確認
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('error');

        // テナントが初期化されたままであることを確認
        $this->assertTrue(tenancy()->tenant !== null);
        $this->assertEquals('existingtenant', tenancy()->tenant->id);

        $tenant->delete();
    }

    #[Test]
    public function it_logs_error_if_tenant_id_not_found_in_route(): void
    {
        $this->assertFalse(tenancy()->tenant !== null);

        Log::spy();

        // ルートパラメータなしでテスト
        Livewire::test(TestComponent::class);

        // ログが出力されたことを確認
        Log::shouldHaveReceived('error')
            ->with('Tenant ID not found in route for InitializesTenantContext trait')
            ->once();

        // テナントが初期化されていないことを確認
        $this->assertFalse(tenancy()->tenant !== null);
    }

    #[Test]
    public function it_logs_error_if_tenant_not_found_for_given_id(): void
    {
        $this->assertFalse(tenancy()->tenant !== null);

        Log::spy();

        // 存在しないテナントIDを渡す
        Livewire::withRoute(function ($route) {
            $route->parameter('tenant', 'nonexistenttenant');
        })->test(TestComponent::class);

        // ログが出力されたことを確認
        Log::shouldHaveReceived('error')
            ->with('Tenant not found for ID from route in InitializesTenantContext trait', ['tenant_id' => 'nonexistenttenant'])
            ->once();

        // テナントが初期化されていないことを確認
        $this->assertFalse(tenancy()->tenant !== null);
    }
}