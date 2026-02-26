<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\WorkflowHistoryList;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class WorkflowHistoryListTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $ledgerDefine = LedgerDefine::factory()->create();
        $this->ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();

        // 履歴を作成
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()->subDays(2)]);
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()->subDay()]);
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()]);
    }

    #[Test]
    public function component_renders_successfully()
    {
        Livewire::test(WorkflowHistoryList::class, ['ledgerRecord' => $this->ledger])
            ->assertStatus(200);
    }

    #[Test]
    public function it_loads_workflow_history_on_mount()
    {
        $component = Livewire::test(WorkflowHistoryList::class, ['ledgerRecord' => $this->ledger]);

        $component->assertViewHas('workflowHistory', function ($history) {
            return $history->count() === 3 &&
                   $history[0]->created_at > $history[1]->created_at &&
                   $history[1]->created_at > $history[2]->created_at;
        });
    }
}
