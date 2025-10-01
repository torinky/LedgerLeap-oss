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
    public function it_redirects_to_ledger_show_page_if_single_match_found()
    {
        $query = 'XYZ-9999';
        $expectedUrl = tenant_route($this->tenant2->id, 'ledger.show', ['tenant' => $this->tenant2->id, 'ledgerId' => $this->ledger2->id, 'highlight' => $query]);

        $response = $this->actingAs($this->user)->get("/ledgers/lookup/{$query}");

        $response->assertRedirect($expectedUrl);
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
}
