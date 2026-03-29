<?php

namespace Tests\Feature;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;

    protected Tenant $tenant2;

    protected Ledger $ledger1;

    protected Ledger $ledger2;

    protected Ledger $ledger3;

    protected User $user;

    protected LedgerDefine $define1;

    protected LedgerDefine $define2;

    protected LedgerDefine $define3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // --- Tenant 1 Setup ---
        $this->tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        tenancy()->initialize($this->tenant1);

        $this->define1 = LedgerDefine::factory()->create([
            'title' => 'Test Ledger Define 1',
            'column_define' => [
                ['id' => 0, 'name' => 'DocID', 'type' => 'auto_number', 'order' => 1, 'options' => []],
                ['id' => 1, 'name' => 'Title', 'type' => 'text', 'order' => 2, 'options' => []],
            ],
        ]);
        $this->ledger1 = Ledger::factory()->create([
            'ledger_define_id' => $this->define1->id,
            'content' => [
                'ABC-1001',
                'First Ledger Title',
            ],
        ]);

        tenancy()->end();

        // --- Tenant 2 Setup ---
        $this->tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        tenancy()->initialize($this->tenant2);

        $this->define2 = LedgerDefine::factory()->create([
            'title' => 'Another Ledger Define 2',
            'column_define' => [
                ['id' => 0, 'name' => 'SpecCode', 'type' => 'auto_number', 'order' => 1, 'options' => []],
            ],
        ]);
        $this->ledger2 = Ledger::factory()->create([
            'ledger_define_id' => $this->define2->id,
            'content' => [
                'XYZ-9999',
            ],
        ]);

        $this->define3 = LedgerDefine::factory()->create([
            'title' => 'Yet Another Ledger Define 3',
            'column_define' => [
                ['id' => 0, 'name' => 'DocID', 'type' => 'auto_number', 'order' => 1, 'options' => []],
            ],
        ]);
        $this->ledger3 = Ledger::factory()->create([
            'ledger_define_id' => $this->define3->id,
            'content' => [
                'ABC-1001', // Duplicate auto_number in different tenant
            ],
        ]);

        tenancy()->end();
    }

    #[Test]
    public function it_redirects_to_ledger_show_page_with_correct_url_format()
    {
        // 単一結果のリダイレクトと、URL形式の正しさを同時に検証
        $query = 'XYZ-9999';
        $baseUrl = config('ledgerleap.auto_links.base_url', config('app.url'));
        $path = route('ledger.show', ['tenant' => $this->tenant2->id, 'ledgerId' => $this->ledger2->id], false);
        $expectedUrl = rtrim($baseUrl, '/').$path;

        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        // リダイレクト先が正しいことを確認
        $response->assertRedirect($expectedUrl);

        // URL形式の検証（バグ検知用）
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringStartsWith($baseUrl, $redirectUrl, 'URL should start with base URL');
        $this->assertStringContainsString('/tenant2/ledger/', $redirectUrl, 'URL should contain tenant path');
        $this->assertStringNotContainsString('highlight=', $redirectUrl, 'URL should not contain highlight parameter');

        // 誤ったパターン（http://tenant2/tenant2/...）ではないことを確認
        $this->assertStringNotContainsString('://tenant2/', $redirectUrl, 'URL should not have tenant as hostname');
    }

    #[Test]
    public function it_shows_results_page_if_multiple_matches_found()
    {
        $query = 'ABC-1001';
        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        $response->assertOk();
        $response->assertViewIs('ledger.lookup.results');
        $response->assertViewHas('results', function ($results) {
            return $results->count() === 2;
        });
        $response->assertSee($this->define1->title);
        $response->assertSee($this->define3->title);
    }

    #[Test]
    public function it_generates_correct_url_format_for_multiple_matches()
    {
        // 複数件の場合、結果画面に表示されるURLが正しいことを確認
        $query = 'ABC-1001';
        $baseUrl = config('ledgerleap.auto_links.base_url', config('app.url'));

        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        $response->assertOk();
        $response->assertViewHas('results', function ($results) use ($baseUrl) {
            foreach ($results as $result) {
                // 各結果のURLが正しい形式であることを確認
                if (! str_starts_with($result['url'], $baseUrl)) {
                    return false;
                }
                // 誤ったパターンではないことを確認
                if (str_contains($result['url'], 'highlight=') || str_contains($result['url'], '://tenant1/') || str_contains($result['url'], '://tenant2/')) {
                    return false;
                }
            }

            return true;
        });
    }

    #[Test]
    public function it_shows_no_results_page_if_no_matches_found()
    {
        $query = 'NON-EXISTENT-000';
        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        $response->assertOk();
        $response->assertViewIs('ledger.lookup.no-results');
        $response->assertViewHas('query', $query);
    }

    #[Test]
    public function it_redirects_to_global_my_portal_if_query_is_empty()
    {
        $response = $this->actingAs($this->user)->get('/ledgers/lookup/');
        $response->assertRedirect(route('global.my-portal'));
    }

    #[Test]
    public function it_uses_base_url_configuration_for_url_generation()
    {
        // AUTO_LINK_BASE_URL 設定が正しく使用されることを確認
        $query = 'XYZ-9999';

        // 設定値を一時的に変更
        config(['ledgerleap.auto_links.base_url' => 'http://test-base-url.example.com']);

        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        $redirectUrl = $response->headers->get('Location');

        // カスタムベースURLが使用されていることを確認
        $this->assertStringStartsWith('http://test-base-url.example.com', $redirectUrl, 'URL should use configured base URL');
        $this->assertStringNotContainsString('highlight=', $redirectUrl, 'URL should not contain highlight parameter');
    }
}
