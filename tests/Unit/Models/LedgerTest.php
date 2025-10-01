<?php

namespace Tests\Unit\Models;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    private Role $inspectorRole;

    private Role $approverRole;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->inspectorRole = Role::create(['name' => 'inspector']);
        $this->approverRole = Role::create(['name' => 'approver']);

        $folder = Folder::factory()
            ->withRequiredRoles(
                inspectors: [$this->inspectorRole],
                approvers: [$this->approverRole]
            )
            ->create();

        $ledgerDefine = LedgerDefine::factory()->for($folder)->create(['workflow_enabled' => true]);

        $this->ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();

        $diff = LedgerDiff::factory()->for($this->ledger)->create();
        $this->ledger->latest_diff_id = $diff->id;
        $this->ledger->save();
    }

    #[Test]
    public function canBeFinallyApproved_returns_false_when_nothing_is_completed()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);

        $this->ledger->load('latestDiff');

        $this->assertFalse($this->ledger->canBeFinallyApproved());
    }

    #[Test]
    public function canBeFinallyApproved_returns_true_when_only_inspection_is_completed()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id],
            'completed_approver_role_ids' => [],
        ]);

        $this->ledger->load('latestDiff');

        $this->assertTrue($this->ledger->canBeFinallyApproved());
    }

    #[Test]
    public function canBeFinallyApproved_returns_false_when_all_roles_are_completed()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id],
            'completed_approver_role_ids' => [$this->approverRole->id],
        ]);

        $this->ledger->load('latestDiff');

        $this->assertFalse($this->ledger->canBeFinallyApproved());
    }
}
