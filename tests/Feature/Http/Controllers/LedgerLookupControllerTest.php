<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_redirects_to_show_page_on_unique_match()
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['unique-id-123']
        ]);

        $this->get(route('ledger.lookup', ['query' => 'unique-id-123']))
            ->assertRedirect(route('ledger.show', ['ledgerId' => $ledger->id]));
    }

    #[Test]
    public function it_redirects_to_index_on_multiple_matches()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term']
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term']
        ]);

        $this->get(route('ledger.lookup', ['query' => 'common-term']))
            ->assertRedirect(route('ledger.index', ['q' => 'common-term']));
    }

    #[Test]
    public function it_redirects_to_index_on_zero_matches()
    {
        $this->get(route('ledger.lookup', ['query' => 'non-existent']))
            ->assertRedirect(route('ledger.index', ['q' => 'non-existent']));
    }

    #[Test]
    public function it_redirects_to_index_when_mode_is_list()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['unique-id-for-list']
        ]);

        $this->get(route('ledger.lookup', ['query' => 'unique-id-for-list', 'mode' => 'list']))
            ->assertRedirect(route('ledger.index', ['q' => 'unique-id-for-list']));
    }
}
