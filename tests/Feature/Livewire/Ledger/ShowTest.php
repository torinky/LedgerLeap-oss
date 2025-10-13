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
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $inspector;

    private User $approver;

    private Ledger $ledger;

    private Role $inspectorRole;

    private Role $approverRole;

    private Folder $folder;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        // ユーザーを作成
        $this->user = User::factory()->create();
        $this->inspector = User::factory()->create();
        $this->approver = User::factory()->create();

        // ロールを作成
        $this->inspectorRole = Role::create(['name' => 'inspector']);
        $this->approverRole = Role::create(['name' => 'approver']);
        $this->inspector->assignRole($this->inspectorRole);
        $this->approver->assignRole($this->approverRole);

        // 'view_ledgers' パーミッションを作成し、$this->user に付与
        $viewLedgersPermission = Permission::firstOrCreate(['name' => 'view_ledgers']);
        $this->user->givePermissionTo($viewLedgersPermission);

        // テスト用のロールを作成し、$this->user に割り当てる
        $testReaderRole = Role::firstOrCreate(['name' => 'test_reader_role']);
        $this->user->assignRole($testReaderRole);

        // フォルダと台帳定義を作成
        $this->folder = Folder::factory()
            ->withRequiredRoles(
                inspectors: [$this->inspectorRole],
                approvers: [$this->approverRole]
            )
            ->create();

        // RoleFolderPermission を作成し、テスト用のロールとフォルダ、READ権限を関連付ける
        RoleFolderPermission::create([
            'role_id' => $testReaderRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        // ユーザーのパーミッションキャッシュをクリア
        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['workflow_enabled' => true]);

        // 台帳レコードを作成
        $this->ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create(['status' => WorkflowStatus::DRAFT]);

        // 最新のDiffを作成
        $diff = LedgerDiff::factory()->for($this->ledger)->create([
            'inspector_id' => $this->inspector->id,
            'approver_id' => $this->approver->id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);
        $this->ledger->latest_diff_id = $diff->id;
        $this->ledger->save();
    }

    #[Test]
    public function component_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertStatus(200)
            ->assertSee($this->ledger->define->name);
    }

    #[Test]
    public function it_loads_ledger_record_on_mount()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        // ledgerRecord の id をアサート
        $component->assertSet('ledgerRecord.id', $this->ledger->id);

        // ledgerDefineRecord は protected なので、インスタンスから直接アクセスしてアサート
        $this->assertEquals($this->ledger->define->id, $component->instance()->ledgerRecord->define->id);
    }

    #[Test]
    public function it_shows_correct_buttons_when_status_is_pending_inspection()
    {
        $this->ledger->update(['status' => WorkflowStatus::PENDING_INSPECTION]);

        // 点検者でログイン
        $this->actingAs($this->inspector);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertSee(__('ledger.workflow.request_approval_short'))
            ->assertSee(__('ledger.workflow.return_to_draft_short'))
            ->assertDontSeeHtml('<button[^>]*>'.__('ledger.workflow.approve').'</button>');

        // 他のユーザーでログイン
        $this->actingAs($this->user);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(__('ledger.workflow.request_approval_short'))
            ->assertDontSee(__('ledger.workflow.return_to_draft_short'));
    }

    #[Test]
    public function it_shows_correct_buttons_when_status_is_pending_approval()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id],
        ]);
        $this->ledger->update(['status' => WorkflowStatus::PENDING_APPROVAL]);

        // 承認者でログイン
        $this->actingAs($this->approver);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component
            ->assertSee(__('ledger.workflow.approve'))
            ->assertSee(__('ledger.workflow.return_to_draft_short'));

        // Livewire の assertDontSeeHtml が部分文字列マッチングで誤動作する可能性があるため、PHPUnit の assertStringNotContainsString を直接使用
        // Livewire の assertDontSee が wire:snapshot の影響を受けるため、HTML から wire:snapshot を削除してアサート
        $cleanedHtml = preg_replace('/wire:snapshot=\"[^\"]*\"/s', '', $component->html());
        $this->assertStringNotContainsString(
            __('ledger.workflow.request_approval_short'),
            $cleanedHtml);
        $component->assertDontSeeHtml('<button[^>]*wire:click="openApproverSelectModal"[^>]*>');

        // 他のユーザーでログイン
        $this->actingAs($this->user);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee('>'.__('ledger.workflow.approve').'<')
            ->assertDontSee('>'.__('ledger.workflow.return_to_draft_short').'<');
    }

    #[Test]
    public function it_shows_no_workflow_buttons_when_status_is_approved()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id],
            'completed_approver_role_ids' => [$this->approverRole->id],
        ]);
        $this->ledger->update(['status' => WorkflowStatus::APPROVED]);

        $this->actingAs($this->user);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(__('ledger.workflow.request_approval_short'))
            ->assertDontSeeHtml('<button[^>]*>'.__('ledger.workflow.approve').'</button>')
            ->assertDontSee(__('ledger.workflow.return_to_draft_short'));
    }

    #[Test]
    public function it_retries_attached_file_processing()
    {
        Bus::fake();

        $attachedFile = AttachedFile::factory()->for($this->ledger)->create([
            'status' => \App\Enums\AttachedFileStatus::TIKA_FAILED,
        ]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', $attachedFile->id);

        $component->assertHasNoErrors();
        // $component->assertDispatchedJs('mary-toast', toast: ['title' => __('file.status.retry_success')]);

        $attachedFile->refresh();
        $this->assertEquals(\App\Enums\AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);

        Bus::assertDispatched(\App\Jobs\Ledger\ProcessAttachedFile::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        // サムネイル生成ジョブも再ディスパッチされるケース
        $attachedFile->update(['status' => \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED]);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', $attachedFile->id);

        Bus::assertDispatched(\App\Jobs\Ledger\GenerateThumbnail::class, function ($job) use ($attachedFile) {
            return $job->attachedFileId === $attachedFile->id;
        });

        // 存在しないattachedFileIdの場合
        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', 99999);

        $component->assertHasErrors(); // エラーが発生することを確認
    }
}
