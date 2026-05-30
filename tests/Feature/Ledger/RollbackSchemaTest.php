<?php

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerDiffProcessor;
use App\Services\Ledger\RollbackService;
use App\Services\UserService;

beforeEach(function () {
    // Manually initialize tenancy
    $this->tenant = Tenant::firstOrCreate(['id' => 'test-tenant']);
    $this->tenant->domains()->firstOrCreate(['domain' => 'test.localhost']);
    tenancy()->initialize($this->tenant);

    // Create User (Tenant connection assumed or global)
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create Role explicitly using create to ensure it persists in the current connection
    $this->role = Role::create(['name' => 'editor-role', 'guard_name' => 'web']);
    $this->user->assignRole($this->role);

    // Create Root Folder
    $this->rootFolder = Folder::factory()->create([
        'title' => 'Root',
        'tenant_id' => $this->tenant->id,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
    ]);
    // Note: saveAsRoot logic omitted for simplicity unless required by observers

    // Create Permission
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
            ['id' => 0, 'name' => 'Col 1', 'type' => 'text', 'order' => 0],
        ],
    ]);
});

test('Rollback with schema change results in identical content detection', function () {
    // 1. Initial Ledger V1
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['Value 1'],
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

    // 2. Add New Column (Schema Change)
    $newCols = $this->define->column_define;
    $newCols[] = ['id' => 1, 'name' => 'Col 2', 'type' => 'text', 'order' => 1];
    $this->define->update(['column_define' => $newCols]);

    // 3. Update to V2 (with value for new column)
    $ledger->update([
        'content' => ['Value 1 Updated', 'Value 2'],
        'version' => 2,
    ]);
    $diffV2 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        // Important: diffV2 stores the NEW schema
        'column_define' => $this->define->column_define,
        'version' => 2,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV2->id]);

    // 4. Rollback to V1
    // RollbackService takes diffV1 content and puts it into Ledger status.
    // Ledger now has V1 content BUT current Schema (with col2).
    // Ledger content for col2 will be missing (or normalized to null/empty).

    // We mock RollbackService execution or verify logic manually if service is heavy.
    // Here we use the service.

    // Ensure permission check passes
    // (Already set WRITE permission in beforeEach)

    app(RollbackService::class)->execute($ledger, $diffV1, $this->user, 'Rollback', 2);

    $ledger->refresh();

    // 5. Check Diff
    // Compare Ledger(V3, content=V1, schema=V2) vs DiffV1(content=V1, schema=V1).
    // The processor will see col2 in V3 schema.
    // col2 is not in DiffV1 schema => Added.
    // Content for col2 in V3 is empty.
    // Logic should say: Added but empty => Unchanged.

    $processor = app(LedgerDiffProcessor::class);
    $diffResult = $processor->prepareContentDiff($ledger, $diffV1);

    if ($diffResult['hasChangedColumns']) {
        dump($diffResult['contentChanges']);
    }

    expect($diffResult['hasChangedColumns'])->toBeFalse('Expected no changed columns after rollback to identical content');
});
