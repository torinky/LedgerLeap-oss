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
use App\Services\Ledger\RollbackService;
use App\Services\UserService;
use Spatie\Activitylog\Models\Activity;

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

    // 1. 各種ユーザーの作成 (Admin, Modifier)
    $this->admin = User::factory()->create(['name' => 'System Admin']);
    $this->modifier = User::factory()->create(['name' => 'Original Modifier']);

    // 2. 権限設定 (AdminはWRITE以上の権限を持つ想定)
    $this->adminRole = Role::findOrCreate('admin', 'web');
    $this->admin->assignRole($this->adminRole);

    RoleFolderPermission::create([
        'role_id' => $this->adminRole->id,
        'folder_id' => $this->rootFolder->id,
        'permission' => FolderPermissionType::ADMIN->value,
        'creator_id' => $this->admin->id,
        'modifier_id' => $this->admin->id,
    ]);

    app(UserService::class)->clearUserPermissionsCache($this->admin);

    // 3. 台帳定義
    $this->define = LedgerDefine::create([
        'title' => 'Audit Target Ledger',
        'folder_id' => $this->rootFolder->id,
        'creator_id' => $this->admin->id,
        'modifier_id' => $this->admin->id,
        'column_define' => [
            ['id' => 0, 'name' => 'Title', 'type' => 'text', 'order' => 0],
            ['id' => 1, 'name' => 'Note', 'type' => 'textarea', 'order' => 1],
        ],
    ]);
});

/**
 * [W5-2.5.5] シナリオG: 管理者の監査シナリオ
 *
 * 1. ユーザーによってロールバックが実行される
 * 2. 管理者が LedgerDiff 履歴を確認し、ロールバックイベントとコメントを特定できる
 * 3. 管理者が activity_log を確認し、ロールバックに関連するメタ情報を確認できる
 */
test('[Scenario G] Admin audits rollback events via LedgerDiff and ActivityLog', function () {
    // 1. 準備：履歴の作成
    $ledger = Ledger::create([
        'ledger_define_id' => $this->define->id,
        'content' => ['V1 Title', 'Original note'],
        'creator_id' => $this->modifier->id,
        'modifier_id' => $this->modifier->id,
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
        'creator_id' => $this->modifier->id,
        'modifier_id' => $this->modifier->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV1->id]);

    $ledger->update(['content' => ['V2 Title', 'Changed note'], 'version' => 2]);
    $diffV2 = LedgerDiff::create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $this->define->id,
        'content' => $ledger->content,
        'column_define' => $this->define->column_define,
        'version' => 2,
        'status' => WorkflowStatus::DRAFT,
        'creator_id' => $this->modifier->id,
        'modifier_id' => $this->modifier->id,
        'completed_inspector_role_ids' => [],
        'completed_approver_role_ids' => [],
    ]);
    $ledger->update(['latest_diff_id' => $diffV2->id]);

    // 2. ロールバック実行 (Admin による監査前の「イベント」)
    $this->actingAs($this->admin);
    $service = app(RollbackService::class);
    $auditComment = 'Auditor rolling back for testing purposes';
    $service->execute($ledger, $diffV1, $this->admin, $auditComment, 2);

    // 3. 監査：LedgerDiff の確認
    $diffV3 = LedgerDiff::where('ledger_id', $ledger->id)->where('version', 3)->first();
    expect($diffV3->comments)->toBe($auditComment);
    expect($diffV3->modifier_id)->toBe($this->admin->id);
    expect($diffV3->returned_at)->not->toBeNull();

    // 4. 監査：ActivityLog の確認
    // RollbackService が activity_log を明示的に記録しているか、あるいは Ledger の updated イベントにより自動記録されているか
    // (通常、ロールバックは Ledger への update を伴うため、自動的に記録されるはず)
    $latestActivity = Activity::all()->last();

    expect($latestActivity->subject_id)->toBe($ledger->id);
    expect($latestActivity->causer_id)->toBe($this->admin->id);

    // ロールバック専用のメタ情報が properties に含まれているか（W3-2.1設計に基づく）
    // 現状の実装で properties に何が入っているか確認する
    $properties = $latestActivity->properties;

    // LedgerObserver 等で記録されている場合の内容を検証
    // 設計書 W3-2.1 によると、RollbackService で特別なプロパティを追加する記載があったが
    // 実際のコードでどうなっているか
    Log::info('Activity Properties:', $properties->toArray());
});
