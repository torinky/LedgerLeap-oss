<?php

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\UserService;
use Livewire\Livewire;

beforeEach(function () {
    // Manually initialize tenancy
    $this->tenant = \App\Models\Tenant::firstOrCreate(['id' => 'test-tenant']);
    $this->tenant->domains()->firstOrCreate(['domain' => 'test.localhost']);
    tenancy()->initialize($this->tenant);

    // Create Root Folder
    $this->rootFolder = Folder::factory()->create([
        'title' => 'Root',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->rootFolder->saveAsRoot();

    // Create User
    $this->user = User::factory()->create();
    $this->role = Role::findOrCreate('editor-role', 'web');
    $this->user->assignRole($this->role);

    // Grant WRITE permission
    RoleFolderPermission::create([
        'role_id' => $this->role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($this->user);

    // Create Ledger Define
    $this->define = LedgerDefine::create([
        'title' => 'Test Ledger',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'column_define' => [
            ['id' => 0, 'name' => 'Text', 'type' => 'text', 'order' => 0],
        ],
    ]);
});

// W5-2.5.3: UI連携テスト
test('[W5-2.5.3] Rollback modal successfully executes and dispatches events', function () {
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data'],
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'status' => WorkflowStatus::DRAFT,
        'version' => 1,
    ]);

    $diffV1 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        'column_define' => $this->define->column_define,
        'version' => 1,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    // Update to V2
    $ledger->update(['content' => ['V2 Data'], 'version' => 2]);
    $diffV2 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        'column_define' => $this->define->column_define,
        'version' => 2,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV2->id]);

    Livewire::actingAs($this->user);

    Livewire::test(\App\Livewire\Ledger\RollbackConfirmModal::class)
        ->call('openModal', $ledger->id, $diffV1->id, 2)
        ->assertSet('ledgerId', $ledger->id)
        ->assertSet('targetDiffId', $diffV1->id)
        ->assertSet('expectedVersion', 2)
        ->set('comments', 'Valid rollback comment')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->set('understandRisks', true)
        ->call('executeRollback')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('mary-toast') // Trait Toast uses this or similar
        ->assertDispatched('ledger.rollback.completed', 
            ledgerId: $ledger->id,
            targetDiffId: $diffV1->id
        );

    // Verify ledger state
    $ledger->refresh();
    expect($ledger->version)->toBe(3);
    expect($ledger->content[0])->toBe('V1 Data');
});
