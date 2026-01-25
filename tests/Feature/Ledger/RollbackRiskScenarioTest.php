<?php

use App\Enums\AttachedFileStatus;
use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Exceptions\Workflow\WorkflowConditionException;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\UserService;
use App\Services\Ledger\RollbackService;
use Illuminate\Support\Facades\Bus;
use App\Jobs\Ledger\ProcessAttachedFile;

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

    // User
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->editorRole = Role::findOrCreate('editor-role', 'web');
    $this->user->assignRole($this->editorRole);

    RoleFolderPermission::create([
        'role_id' => $this->editorRole->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($this->user);

    // Ledger Define with file column
    $this->define = LedgerDefine::create([
        'title' => 'Risk Test Ledger',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'column_define' => [
            ['id' => 0, 'name' => 'FileField', 'type' => 'files', 'order' => 0],
        ],
        'tenant_id' => $this->tenant->id,
    ]);
});

/**
 * [W5-2.5.6] リスクシナリオ：Auto-healing
 * 
 * 添付ファイルの中身（Tikaメタデータ）が欠落している状態でロールバックした場合、
 * RollbackService が異常を検知し、再処理ジョブを投入することを検証する。
 */
test('[Risk] Rollback triggers Auto-healing for missing attached file content', function () {
    Bus::fake();

    $hashedName = 'deadbeef12345678.pdf';
    
    // 1. Ver.1 作成（ファイル付き）
    // Ledger::content は [column_id => [hashed => original_name]] の形式で正規化されている必要がある
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => [0 => [$hashedName => 'test.pdf']],
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'status' => WorkflowStatus::DRAFT,
        'version' => 1,
        'tenant_id' => $this->tenant->id,
    ]);

    // AttachedFile レコードをファクトリで作成
    // forLedgerにより、ledger_id, ledger_define_id, tenant_id, creator_id, modifier_id が補完される
    $attachedFile = AttachedFile::factory()->forLedger($ledger)->create([
        'column_id' => 0,
        'hashedbasename' => $hashedName,
        'filename' => 'test.pdf',
        'status' => AttachedFileStatus::COMPLETED, // 完了済みと詐称
        'contain_content' => false,
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

    // 2. Ver.2 （更新）
    $ledger->increment('version');
    
    // 3. ロールバック実行
    $service = app(RollbackService::class);
    $service->execute($ledger, $diffV1, $this->user, 'Healing test', 2);

    // 4. 検証：再処理ジョブが投入されたか
    Bus::assertDispatched(ProcessAttachedFile::class, function ($job) use ($attachedFile) {
        return $job->attachedFile->id === $attachedFile->id;
    });

    // ステータスがリセットされているか
    $attachedFile->refresh();
    expect($attachedFile->status)->toBe(AttachedFileStatus::PENDING_INITIAL_PROCESSING);
    expect($attachedFile->tika_processed_at)->toBeNull();
});

/**
 * [W5-2.5.6] リスクシナリオ：楽観的ロック競合
 * 
 * 期待されるバージョンと現在のバージョンが異なる場合、ロールバックが拒否されることを検証する。
 */
test('[Risk] Rollback fails when expected version mismatches', function () {
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => [],
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'status' => WorkflowStatus::DRAFT,
        'version' => 1,
        'tenant_id' => $this->tenant->id,
    ]);
    $diffV1 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => [],
        'column_define' => $this->define->column_define,
        'version' => 1,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->user->id,
        'modifier_id' => $this->user->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);

    // 他の誰かが Ver.2 に更新してしまった
    $ledger->update(['version' => 2]);

    $service = app(RollbackService::class);

    // 期待バージョンを 1 と指定して実行（現在は 2 なので失敗すべき）
    expect(fn() => $service->execute($ledger, $diffV1, $this->user, 'Mismatch test', 1))
        ->toThrow(WorkflowConditionException::class);
});
