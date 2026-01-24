<?php

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Exceptions\Workflow\WorkflowConditionException;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\Ledger\RollbackService;
use App\Services\UserService;

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

    // Create Ledger Define
    $this->define = LedgerDefine::create([
        'title' => 'Test Ledger',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => 1,
        'modifier_id' => 1,
        'column_define' => [
            ['id' => 0, 'name' => 'Text', 'type' => 'text', 'order' => 0],
        ],
    ]);
});

// TC-SV-01: ワークフロー無効台帳のロールバック（正常系）
test('[TC-SV-01] Rollback succeeds for workflow-disabled ledger with WRITE permission', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('editor-role', 'web');
    $user->assignRole($role);

    RoleFolderPermission::create([
        'role_id' => $role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($user);

    // Create ledger with workflow disabled
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data'],
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
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
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
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
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV2->id]);

    // Execute rollback
    $service = app(RollbackService::class);
    $result = $service->execute($ledger, $diffV1, $user, 'Test rollback', 2);

    expect($result->version)->toBe(3);
    expect($result->content[0])->toBe('V1 Data');
    expect($result->status)->toBe(WorkflowStatus::DRAFT);

    // Verify new LedgerDiff was created
    $newDiff = LedgerDiff::where('ledger_id', $ledger->id)
        ->where('version', 3)
        ->first();
    expect($newDiff)->not->toBeNull();
    expect($newDiff->comments)->toContain('Test rollback');
});

// TC-SV-04: 承認済みレコードの拒否
test('[TC-SV-04] Rollback is rejected for approved ledger', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('editor-role', 'web');
    $user->assignRole($role);

    RoleFolderPermission::create([
        'role_id' => $role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($user);

    // Create approved ledger
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data'],
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
        'status' => WorkflowStatus::APPROVED, // Approved status
        'version' => 1,
    ]);

    $diffV1 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        'column_define' => $this->define->column_define,
        'version' => 1,
        'status' => WorkflowStatus::APPROVED,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    // Attempt rollback should throw exception
    $service = app(RollbackService::class);
    
    expect(fn() => $service->execute($ledger, $diffV1, $user, 'Test rollback', 1))
        ->toThrow(WorkflowConditionException::class);
});

// TC-SV-05: 楽観的ロック（バージョン不一致）の検知
test('[TC-SV-05] Rollback detects version mismatch (optimistic lock)', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('editor-role', 'web');
    $user->assignRole($role);

    RoleFolderPermission::create([
        'role_id' => $role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($user);

    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data'],
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
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
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    // Update to V2
    $ledger->update(['content' => ['V2 Data'], 'version' => 2]);

    // Attempt rollback with wrong expected version
    $service = app(RollbackService::class);
    
    expect(fn() => $service->execute($ledger, $diffV1, $user, 'Test rollback', 1)) // Expected version 1, but actual is 2
        ->toThrow(WorkflowConditionException::class);
});

// TC-SV-06: 権限なしアクセス
test('[TC-SV-06] Rollback is rejected for user without WRITE permission', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('reader-role', 'web');
    $user->assignRole($role);

    // Grant only READ permission
    RoleFolderPermission::create([
        'role_id' => $role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::READ->value,
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($user);

    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data'],
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
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
        'creator_id' => $user->id,
        'modifier_id' => $user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    // Attempt rollback should fail
    $service = app(RollbackService::class);
    
    expect($service->canExecute($user, $ledger))->toBeFalse();
});
