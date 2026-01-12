<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerHistoryManagerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_displays_editor_and_approver_info_with_popover()
    {
        // 0. Tenancy Setup (Best Practice #1)
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        // 1. Setup User and Organization
        $org = Organization::factory()->create(['name' => 'Test Org']);
        
        $modifier = User::factory()->create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'chat_link' => 'https://chat.example.com/editor',
        ]);
        $modifier->organizations()->attach($org, ['is_primary' => true]);

        $approver = User::factory()->create([
            'name' => 'Approver User',
            'email' => 'approver@example.com',
            'chat_link' => 'https://chat.example.com/approver',
        ]);
        $approver->organizations()->attach($org, ['is_primary' => true]);

        // 2. Setup Ledger and LedgerDiff (Explicitly)
        $ledgerDefine = \App\Models\LedgerDefine::factory()->create([
             'tenant_id' => $tenant->id,
             'column_define' => [
                 ['id' => 0, 'name' => 'Col1', 'type' => 'text', 'order' => 1]
             ], 
        ]);

        $ledger = Ledger::factory()->create([
            'tenant_id' => $tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'version' => 2,
            'content' => [0 => 'Content'], // Explicit zero-indexed content (Best Practice #2)
        ]);
        
        $diff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'version' => 1,
            'content' => [0 => 'Old Content'],
            'column_define' => $ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $modifier->id,
            'approver_id' => $approver->id,
            'creator_id' => $modifier->id,
            'status' => WorkflowStatus::APPROVED, // Approved
            'comment' => 'Approved version',
            'created_at' => now()->subDay(),
        ]);
        
        // Set latest diff
        $ledger->update(['latest_diff_id' => $diff->id]);

        // 3. Test Component Rendering
        Livewire::test(LedgerHistoryManager::class, ['ledgerId' => $ledger->id])
            ->assertSee($modifier->name) 
            ->assertSee($approver->name) 
            ->assertSee($org->name) 
            ->assertSee('Test Org')
            ->assertSee('https://chat.example.com/editor')
            ->assertSee('https://chat.example.com/approver')
            ->assertSee(__('ledger.workflow.label.editor'))
            ->assertSee(__('ledger.workflow.approved_by'));
    }
}
