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
use App\Services\Ledger\RollbackService;
use App\Services\UserService;

beforeEach(function () {
    // Manually initialize tenancy
    $this->tenant = \App\Models\Tenant::firstOrCreate(['id' => 'test-tenant']);
    $this->tenant->domains()->firstOrCreate(['domain' => 'test.localhost']);
    tenancy()->initialize($this->tenant);

    // ユーザー作成
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // 権限付与
    $this->role = Role::findOrCreate('editor-role', 'web');
    $this->user->assignRole($this->role);

    // フォルダと権限の設定
    $this->rootFolder = Folder::whereNull('parent_id')->first();
    if (! $this->rootFolder) {
        $this->rootFolder = Folder::factory()->create([
            'title' => 'Root',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->rootFolder->saveAsRoot();
    }

    RoleFolderPermission::create([
        'role_id' => $this->role->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($this->user);

    // 台帳定義
    $this->define = LedgerDefine::create([
        'title' => 'Test Ledger',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'column_define' => [
            ['id' => 0, 'name' => 'Text', 'type' => 'text', 'order' => 0],
            ['id' => 1, 'name' => 'File', 'type' => 'text', 'order' => 1],
        ],
    ]);
});

test('RollbackService performs a basic rollback correctly', function () {
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data', []],
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

    // 更新 (Ver.2)
    $ledger->update(['content' => ['V2 Data', []], 'version' => 2]);
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

    // ロールバック実行
    $service = app(RollbackService::class);
    $service->execute($ledger, $diffV1, $this->user, 'Rolling back to V1', 2);

    $ledger->refresh();
    expect($ledger->version)->toBe(3);
    expect($ledger->content[0])->toBe('V1 Data');
});

// W5-2.5.1: LedgerDiff詳細検証テスト
test('[W5-2.5.1] Rollback creates LedgerDiff with correct metadata', function () {
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Data', []],
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

    // 更新 (Ver.2)
    $ledger->update(['content' => ['V2 Data', []], 'version' => 2]);
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

    // ロールバック実行
    $service = app(RollbackService::class);
    $testComment = 'Test rollback comment for verification';
    $service->execute($ledger, $diffV1, $this->user, $testComment, 2);

    // 新しいLedgerDiff（V3）を取得
    $diffV3 = LedgerDiff::where('ledger_id', $ledger->id)
        ->where('version', 3)
        ->first();

    // LedgerDiffの詳細検証
    expect($diffV3)->not->toBeNull();

    // status = DRAFTであることを検証
    expect($diffV3->status)->toBe(WorkflowStatus::DRAFT);

    // commentsが正しく記録されることを検証
    expect($diffV3->comments)->toContain($testComment);

    // returned_atが設定されることを検証（ロールバック時刻）
    expect($diffV3->returned_at)->not->toBeNull();
    expect($diffV3->returned_at)->toBeInstanceOf(\Carbon\Carbon::class);

    // 担当者情報が引き継がれることを検証
    expect($diffV3->creator_id)->toBe($this->user->id);
    expect($diffV3->modifier_id)->toBe($this->user->id);

    // tenant_id が正しく設定されていることを検証
    expect($diffV3->tenant_id)->not->toBeNull();
    expect($diffV3->tenant_id)->toBe($this->tenant->id);

    // コンテンツがV1に戻っていることを確認
    expect($diffV3->content[0])->toBe('V1 Data');
});
