<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\Show;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Show コンポーネントの未カバーメソッドに対する追加テスト
 *
 * 対象: deleteAttachedFile, handleRollbackCompleted, refreshLedgerRecord,
 *       navigateToTab, setDisplayLevel, updatedShowChanges, updateVersions
 */
#[CoversClass(Show::class)]
class ShowAdditionalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Ledger $ledger;

    private LedgerDiff $diff;

    private Folder $folder;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $inspectorRole = Role::create(['name' => 'inspector_show_add']);
        $approverRole = Role::create(['name' => 'approver_show_add']);

        $viewPerm = Permission::firstOrCreate(['name' => 'view_ledgers']);
        $this->user->givePermissionTo($viewPerm);

        $readerRole = Role::firstOrCreate(['name' => 'reader_show_add']);
        $this->user->assignRole($readerRole);

        $this->folder = Folder::factory()
            ->withRequiredRoles(inspectors: [$inspectorRole], approvers: [$approverRole])
            ->create();

        RoleFolderPermission::create([
            'role_id' => $readerRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        $ledgerDefine = LedgerDefine::factory()->for($this->folder)->create(['workflow_enabled' => true]);

        $this->ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create(['status' => WorkflowStatus::DRAFT]);

        $this->diff = LedgerDiff::factory()->for($this->ledger)->create([
            'inspector_id' => null,
            'approver_id' => null,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);
        $this->ledger->update(['latest_diff_id' => $this->diff->id]);
    }

    // ===================================================================
    // deleteAttachedFile
    // ===================================================================

    #[Test]
    public function it_deletes_attached_file_successfully(): void
    {
        Bus::fake();
        $this->actingAs($this->user);

        $file = AttachedFile::factory()->for($this->ledger)->create([
            'status' => \App\Enums\AttachedFileStatus::COMPLETED,
        ]);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('deleteAttachedFile', $file->id);

        $component->assertHasNoErrors();
        $component->assertDispatched('mary-toast', title: __('file.delete_success'));

        // ソフトデリートされていることを確認
        $this->assertSoftDeleted('attached_files', ['id' => $file->id]);
    }

    #[Test]
    public function it_handles_delete_attached_file_failure_gracefully(): void
    {
        $this->actingAs($this->user);

        // 存在しないファイルIDで呼び出す
        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('deleteAttachedFile', 999999);

        // 例外がスローされてもコンポーネントがエラートーストを表示することを確認
        $component->assertDispatched('mary-toast', title: __('file.delete_failed'));
    }

    // ===================================================================
    // refreshLedgerRecord (#[On('workflowUpdated')])
    // ===================================================================

    #[Test]
    public function it_refreshes_ledger_record_on_workflow_updated_event(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        // 台帳のステータスを変更してからイベントを発行
        $this->ledger->update(['status' => WorkflowStatus::PENDING_INSPECTION]);

        $component->dispatch('workflowUpdated');

        // リフレッシュ後に最新の台帳情報が取得されることを確認
        $component->assertSet('ledgerRecord.status', WorkflowStatus::PENDING_INSPECTION);
    }

    // ===================================================================
    // navigateToTab (#[On('navigate-to-ledger-tab')])
    // ===================================================================

    #[Test]
    public function it_navigates_to_specified_tab_on_event(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $component->assertSet('selectedTab', 'details'); // 初期値

        $component->dispatch('navigate-to-ledger-tab', tab: 'history');

        $component->assertSet('selectedTab', 'history');
    }

    // ===================================================================
    // setDisplayLevel
    // ===================================================================

    #[Test]
    public function it_sets_display_level_to_valid_value(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component->call('setDisplayLevel', 1);
        $component->assertSet('displayLevel', 1);
        $component->assertDispatched('displayLevelUpdated', displayLevel: 1);

        $component->call('setDisplayLevel', 2);
        $component->assertSet('displayLevel', 2);
    }

    #[Test]
    public function it_ignores_invalid_display_level(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $component->assertSet('displayLevel', 3); // 初期値

        // 無効な値（4）を設定しても変更されないことを確認
        $component->call('setDisplayLevel', 4);
        $component->assertSet('displayLevel', 3); // 変わらない
    }

    // ===================================================================
    // updateDisplayLevel (#[On('displayLevelUpdated')])
    // ===================================================================

    #[Test]
    public function it_updates_display_level_on_event(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component->dispatch('displayLevelUpdated', displayLevel: 2);

        $component->assertSet('displayLevel', 2);
    }

    // ===================================================================
    // updatedShowChanges
    // ===================================================================

    #[Test]
    public function it_activates_compare_with_previous_when_show_changes_enabled_without_target(): void
    {
        // 2バージョン以上作成
        $v1 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 1]);
        $v2 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 2]);
        $this->ledger->update(['latest_diff_id' => $v2->id]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $component->assertSet('showChanges', false);
        $component->assertSet('targetDiffId', null);

        // showChanges をトグル（targetDiffId がないので activateCompareWithPrevious が呼ばれる）
        $component->set('showChanges', true);

        $component->assertSet('showChanges', true);
        $component->assertSet('targetDiffId', $v1->id);
    }

    // ===================================================================
    // updateVersions (#[On('versionsSelected')])
    // ===================================================================

    #[Test]
    public function it_updates_target_diff_id_on_versions_selected_event(): void
    {
        $v1 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 1]);
        $v2 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 2]);
        $this->ledger->update(['latest_diff_id' => $v2->id]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component->dispatch('versionsSelected', baseId: $v2->id, targetId: $v1->id);

        $component->assertSet('targetDiffId', $v1->id);
        $component->assertDispatched('targetDiffIdUpdated', targetDiffId: $v1->id);
    }

    // ===================================================================
    // handleRollbackCompleted (#[On('ledger.rollback.completed')])
    // ===================================================================

    #[Test]
    public function it_handles_rollback_completed_event_with_target_diff(): void
    {
        $v1 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 1]);
        $v2 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 2]);
        $this->ledger->update(['latest_diff_id' => $v2->id]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component->dispatch('ledger.rollback.completed', ledgerId: $this->ledger->id, targetDiffId: $v1->id);

        $component->assertSet('selectedTab', 'details');
        $component->assertSet('showChanges', true);
        $component->assertSet('targetDiffId', $v1->id);
        $component->assertDispatched('targetDiffIdUpdated', targetDiffId: $v1->id);
    }

    #[Test]
    public function it_ignores_rollback_completed_for_different_ledger(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $component->assertSet('selectedTab', 'details');

        // 別の台帳IDのイベントは無視される
        $component->dispatch('ledger.rollback.completed', ledgerId: 99999, targetDiffId: null);

        // 変化なし
        $component->assertSet('selectedTab', 'details');
        $component->assertSet('showChanges', false);
    }

    #[Test]
    public function it_handles_rollback_completed_without_target_diff(): void
    {
        $v1 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 1]);
        $v2 = LedgerDiff::factory()->for($this->ledger)->create(['version' => 2]);
        $this->ledger->update(['latest_diff_id' => $v2->id]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        // targetDiffId なしのロールバック完了（activateCompareWithPrevious が呼ばれる）
        $component->dispatch('ledger.rollback.completed', ledgerId: $this->ledger->id, targetDiffId: null);

        $component->assertSet('showChanges', true);
        $component->assertSet('targetDiffId', $v1->id); // 直前バージョンが自動設定
    }

    // ===================================================================
    // switchToHistoryTab (#[On('switchToHistoryTab')])
    // ===================================================================

    #[Test]
    public function it_switches_to_history_tab_on_event(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $component->assertSet('selectedTab', 'details');

        $component->dispatch('switchToHistoryTab');

        $component->assertSet('selectedTab', 'history');
    }
}
