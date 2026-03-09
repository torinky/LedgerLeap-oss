<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\LedgerHistoryManager;
use App\Livewire\Ledger\Show;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SelectionSyncTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    public function test_it_syncs_selection_from_show_to_history_manager()
    {
        // テナント初期化
        $tenant = \App\Models\Tenant::create(['id' => 'demo-tenant']);
        tenancy()->initialize($tenant);

        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        // Create 2 versions
        $diff1 = LedgerDiff::factory()->create(['ledger_id' => $ledger->id, 'version' => 1]);
        $diff2 = LedgerDiff::factory()->create(['ledger_id' => $ledger->id, 'version' => 2]);

        // 最新の diff を Ledger に紐付ける
        $ledger->update(['latest_diff_id' => $diff2->id, 'version' => 2]);

        $historyManager = Livewire::actingAs($user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $ledger->id]);

        $show = Livewire::actingAs($user)
            ->test(Show::class, ['ledgerId' => $ledger->id]);

        // Trigger 'activateCompareWithPrevious' on Show component
        $show->call('activateCompareWithPrevious');

        // Check if event was dispatched
        $show->assertDispatched('versionsSelected');

        // Manually trigger the event on HistoryManager
        $historyManager->dispatch('versionsSelected', baseId: $diff2->id, targetId: $diff1->id);

        $historyManager->assertSet('baseDiffId', $diff2->id);
        $historyManager->assertSet('targetDiffId', $diff1->id);
    }
}
