<?php

namespace Tests\Feature;

use App\Models\Ledger;
use App\Models\Tenant;
use App\Models\User; // 追加
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
    protected User $user; // 追加

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $this->user = User::factory()->create();

        // テナント1を作成し、初期化
        $this->tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        tenancy()->initialize($this->tenant1);
        $ledgerDefine1 = \App\Models\LedgerDefine::factory()->create(['title' => 'Test Ledger Define 1']);
        $this->ledger1 = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine1->id]);
        tenancy()->end();

        // テナント2を作成し、初期化
        $this->tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        tenancy()->initialize($this->tenant2);
        $ledgerDefine2 = \App\Models\LedgerDefine::factory()->create(['title' => 'Another Ledger Define 2']);
        $this->ledger2 = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine2->id]);
        $ledgerDefine3 = \App\Models\LedgerDefine::factory()->create(['title' => 'Yet Another Ledger Define 3']);
        $this->ledger3 = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine3->id]);
        tenancy()->end();
    }

    #[Test]
    public function it_redirects_to_ledger_show_page_if_single_match_found()
    {
        $query = 'Test Ledger Define 1';
        $expectedUrl = tenant_route($this->tenant1->id, 'ledger.show', ['ledgerId' => $this->ledger1->id, 'highlight' => $query]);

        $response = $this->get("/ledgers/lookup/{$query}");

        $response->assertRedirect($expectedUrl);
    }

    #[Test]
    public function it_shows_results_page_if_multiple_matches_found()
    {
        $query = 'Ledger Define'; // 共通部分
        $response = $this->get("/ledgers/lookup/{$query}");

        $response->assertOk();
        $response->assertViewIs('ledger.lookup.results');
        $response->assertViewHas('results');
        $response->assertSee('Multiple results found for "Ledger Define"');
        $response->assertSee('Test Ledger Define 1');
        $response->assertSee('Another Ledger Define 2');
    }

    #[Test]
    public function it_shows_no_results_page_if_no_matches_found()
    {
        $query = 'NonExistentLedger';
        $response = $this->get("/ledgers/lookup/{$query}");

        $response->assertOk();
        $response->assertViewIs('ledger.lookup.no-results');
        $response->assertSee('No results found for "NonExistentLedger"');
    }

    #[Test]
    public function it_redirects_to_global_my_portal_if_query_is_empty()
    {
        $response = $this->actingAs($this->user)->get('/ledgers/lookup/');
        $response->assertRedirect(route('global.my-portal'));
    }
}