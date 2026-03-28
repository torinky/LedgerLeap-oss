<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\Show;
use App\Models\Ledger;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class RelatedLedgersTabTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();
        $this->user = User::factory()->create();
        // Skip tenants() attach as it seems missing on User model in this environment
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_renders_related_tab_placeholders_and_component()
    {
        $ledger = Ledger::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(Show::class, ['ledgerId' => $ledger->id])
            ->assertSee(__('ledger.tab.related'))
            ->assertSet('selectedTab', 'details')
            ->assertSet('loadedTabs', ['details'])
            ->call('navigateToTab', 'related')
            ->assertSet('selectedTab', 'related')
            ->assertSet('loadedTabs', ['details', 'related']);
    }

    #[Test]
    public function it_renders_related_tab_immediately_if_specified_in_url()
    {
        $ledger = Ledger::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::withQueryParams(['tab' => 'related'])
            ->test(Show::class, ['ledgerId' => $ledger->id])
            ->assertSet('selectedTab', 'related')
            ->assertSet('loadedTabs', ['related']);
    }
}
