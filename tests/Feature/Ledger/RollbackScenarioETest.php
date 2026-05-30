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

    // 1. 実務担当者 (Subordinate) と 現場リーダー (Field Leader) の作成
    $this->subordinate = User::factory()->create(['name' => 'Subordinate']);
    $this->leader = User::factory()->create(['name' => 'Field Leader']);

    // 2. 権限設定
    $this->editorRole = Role::findOrCreate('editor-role', 'web');
    $this->subordinate->assignRole($this->editorRole);
    $this->leader->assignRole($this->editorRole);

    // 現場リーダーにWRITE権限を付与 (実務担当者にも同様に付与)
    RoleFolderPermission::create([
        'role_id' => $this->editorRole->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::WRITE->value,
        'creator_id' => $this->leader->id,
        'modifier_id' => $this->leader->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($this->subordinate);
    app(UserService::class)->clearUserPermissionsCache($this->leader);

    // 3. 台帳定義
    $this->define = LedgerDefine::create([
        'title' => 'Daily Report',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => $this->leader->id,
        'modifier_id' => $this->leader->id,
        'column_define' => [
            ['id' => 0, 'name' => 'Department', 'type' => 'text', 'order' => 0],
            ['id' => 1, 'name' => 'Content', 'type' => 'textarea', 'order' => 1],
        ],
    ]);
});

/**
 * [W5-2.5.4] シナリオE: 現場リーダーの誤更新ロールバック
 *
 * 1. 実務担当者が「Ver.1: 正しいデータ」を作成
 * 2. 実務担当者が「Ver.2: 誤ったデータ」に更新してしまった
 * 3. 現場リーダーが台帳を開き、履歴を確認
 * 4. 現場リーダーが「Ver.1」に戻す操作を実行
 * 5. ロールバック成功（Ver.3作成）、トースト通知、イベント発火を確認
 */
test('[Scenario E] Field leader rolls back an erroneous update by a subordinate', function () {
    // 1. 実務担当者が Ver.1 を作成
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['Sales', 'Everything is fine.'],
        'creator_id' => $this->subordinate->id,
        'modifier_id' => $this->subordinate->id,
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
        'creator_id' => $this->subordinate->id,
        'modifier_id' => $this->subordinate->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    // 2. 実務担当者が Ver.2 （誤更新）を実行
    $ledger->update([
        'content' => ['Sales', 'Accidentally deleted important info!!!'],
        'version' => 2,
        'modifier_id' => $this->subordinate->id,
    ]);
    $diffV2 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        'column_define' => $this->define->column_define,
        'version' => 2,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->subordinate->id,
        'modifier_id' => $this->subordinate->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV2->id]);

    // 3. 現場リーダーが操作を開始
    Livewire::actingAs($this->leader);

    // ロールバックモーダルを立ち上げて実行
    Livewire::test(RollbackConfirmModal::class)
        ->call('openModal', $ledger->id, $diffV1->id, 2)
        ->set('comments', 'Subordinate made a mistake, rolling back to V1.')
        ->call('nextStep')
        ->set('understandRisks', true)
        ->call('executeRollback')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('mary-toast', type: 'success')
        ->assertDispatched('ledger.rollback.completed');

    // 4. 台帳の状態を確認
    $ledger->refresh();
    expect($ledger->version)->toBe(3);
    expect($ledger->content[1])->toBe('Everything is fine.');
    expect($ledger->modifier_id)->toBe($this->leader->id);

    // 5. 新しい履歴(V3)を確認
    $diffV3 = $ledger->latestDiff;
    expect($diffV3->version)->toBe(3);
    expect($diffV3->comments)->toContain('Subordinate made a mistake');
    expect($diffV3->modifier_id)->toBe($this->leader->id);
});
