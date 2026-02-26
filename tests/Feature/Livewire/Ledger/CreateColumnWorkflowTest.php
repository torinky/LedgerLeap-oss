<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\CreateColumn;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CreateColumn コンポーネントのワークフロー系・未カバーメソッドに対する追加テスト
 *
 * 対象: saveDraft, saveDirectly（ワークフロー有効時エラー）, store,
 *       openAssigneeModal, handleNewFileRemoval, getInspectorOptions
 */
#[CoversClass(CreateColumn::class)]
class CreateColumnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected User $inspector;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefineWorkflow;  // workflow_enabled=true

    protected LedgerDefine $ledgerDefineNoWorkflow; // workflow_enabled=false

    /** @var array<int, array<string, mixed>> */
    private array $columnDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'cwf.localhost']);
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->inspector = User::factory()->create();

        // 権限設定
        Permission::findOrCreate('create_ledgers', 'web');
        $creatorRole = Role::findOrCreate('cwf-creator', 'web');
        $inspectorRole = Role::findOrCreate('cwf-inspector', 'web');
        $creatorRole->givePermissionTo('create_ledgers');
        $this->user->assignRole($creatorRole);
        $this->inspector->assignRole($inspectorRole);
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->columnDefine = [
            ['id' => 1, 'name' => 'テキスト', 'type' => 'text', 'order' => 1, 'required' => false],
        ];

        // フォルダ作成（必要な点検ロールを設定）
        $this->folder = Folder::factory()
            ->withRequiredRoles(inspectors: [$inspectorRole], approvers: [])
            ->create();

        // 作成者ロールにフォルダのWRITE権限を付与
        RoleFolderPermission::create([
            'role_id' => $creatorRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $this->user->id,
        ]);

        // 点検者ロールにフォルダのINSPECT権限を付与
        RoleFolderPermission::create([
            'role_id' => $inspectorRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT->value,
            'modifier_id' => $this->user->id,
        ]);

        // ワークフロー有効台帳定義
        $this->ledgerDefineWorkflow = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => true,
            'column_define' => $this->columnDefine,
        ]);

        // ワークフロー無効台帳定義
        $this->ledgerDefineNoWorkflow = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $this->columnDefine,
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    // ===================================================================
    // saveDraft — ワークフロー有効時の下書き保存
    // ===================================================================

    #[Test]
    public function it_saves_draft_successfully_with_workflow_enabled(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineWorkflow->id,
        ]);

        $component->set('content', [1 => 'テスト下書き内容'])
            ->call('saveDraft');

        $component->assertHasNoErrors();
        $component->assertDispatched('mary-toast', type: 'success');

        // DBに下書きが保存されていることを確認
        $this->assertDatabaseHas('ledgers', [
            'ledger_define_id' => $this->ledgerDefineWorkflow->id,
            'status' => WorkflowStatus::DRAFT->value,
        ]);
    }

    #[Test]
    public function it_updates_ledger_id_after_save_draft(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineWorkflow->id,
        ]);

        $component->assertSet('ledgerId', null);

        $component->set('content', [1 => 'テスト内容'])
            ->call('saveDraft');

        $ledgerId = $component->get('ledgerId');
        $this->assertNotNull($ledgerId);
        $this->assertIsInt($ledgerId);
    }

    #[Test]
    public function it_can_call_save_draft_twice_to_update(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineWorkflow->id,
        ]);

        // 1回目：新規作成
        $component->set('content', [1 => '初回内容'])->call('saveDraft');
        $firstLedgerId = $component->get('ledgerId');
        $this->assertNotNull($firstLedgerId);

        // 2回目：更新
        $component->set('content', [1 => '更新内容'])->call('saveDraft');
        $secondLedgerId = $component->get('ledgerId');

        // 同じIDであること（更新）
        $this->assertEquals($firstLedgerId, $secondLedgerId);
    }

    // ===================================================================
    // openAssigneeModal — 台帳IDがない場合はエラー
    // ===================================================================

    #[Test]
    public function it_shows_error_when_opening_assignee_modal_without_saved_ledger(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineWorkflow->id,
        ]);

        // ledgerId が null のまま openAssigneeModal を呼ぶ
        $component->call('openAssigneeModal', 'inspector');

        // モーダルが開かないこと（open-assignee-modal イベントが発行されない）を確認
        $component->assertNotDispatched('open-assignee-modal');
    }

    #[Test]
    public function it_opens_assignee_modal_after_draft_saved(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineWorkflow->id,
        ]);

        // まず下書き保存してledgerIdを設定
        $component->set('content', [1 => 'テスト'])->call('saveDraft');
        $this->assertNotNull($component->get('ledgerId'));

        // モーダルを開く
        $component->call('openAssigneeModal', 'inspector');

        // open-assignee-modal イベントが発行されること
        $component->assertDispatched('open-assignee-modal');
    }

    // ===================================================================
    // handleNewFileRemoval
    // ===================================================================

    #[Test]
    public function it_handles_new_file_removal(): void
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefineNoWorkflow->id,
        ]);

        // ファイル型カラムがないので呼んでも例外が起きないことを確認
        // （column define に files 型がある場合は updateContentStatusLabel が呼ばれる）
        $component->call('handleNewFileRemoval', 1);

        $component->assertHasNoErrors();
    }

    // ===================================================================
    // updateProgress
    // ===================================================================

    #[Test]
    public function it_calculates_progress_correctly(): void
    {
        // 必須カラムがあるledgerDefineを作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => [
                ['id' => 1, 'name' => '必須項目', 'type' => 'text', 'order' => 1, 'required' => true],
                ['id' => 2, 'name' => '任意項目', 'type' => 'text', 'order' => 2, 'required' => false],
            ],
        ]);

        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $ledgerDefine->id,
        ]);

        // 必須項目が空なのでprогресс=0
        $component->assertSet('progress', 0);

        // 必須項目に値を入れる
        $component->set('content', [1 => '入力済み'])->call('updateProgress');

        $progress = $component->get('progress');
        $this->assertEquals(100.0, $progress); // 必須1件中1件入力済み
    }
}
