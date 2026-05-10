<?php

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\RollbackConfirmModal;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Livewire\Livewire;

beforeEach(function () {
    // Manually initialize tenancy
    $this->tenant = Tenant::firstOrCreate(['id' => 'test-tenant']);
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

// W5-2.5.2: コメントバリデーションテスト
test('[W5-2.5.2] Rollback modal requires comment with minimum 5 characters', function () {
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

    Livewire::actingAs($this->user);

    Livewire::test(RollbackConfirmModal::class)
        ->call('openModal', $ledger->id, $diffV1->id, 1)
        ->set('comments', '')
        ->call('nextStep')
        ->assertHasErrors(['comments' => 'required'])
        ->set('comments', '1234')
        ->call('nextStep')
        ->assertHasErrors(['comments' => 'min'])
        ->set('comments', '12345')
        ->call('nextStep')
        ->assertHasNoErrors(['comments'])
        ->assertSet('step', 2);
});

test('[W5-2.5.2] Rollback modal enforces maximum 500 character limit for comments', function () {
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

    Livewire::actingAs($this->user);

    $longComment = str_repeat('a', 501);

    Livewire::test(RollbackConfirmModal::class)
        ->call('openModal', $ledger->id, $diffV1->id, 1)
        ->set('comments', $longComment)
        ->call('nextStep')
        ->assertHasErrors(['comments' => 'max'])
        ->set('comments', str_repeat('a', 500))
        ->call('nextStep')
        ->assertHasNoErrors(['comments'])
        ->assertSet('step', 2);
});
