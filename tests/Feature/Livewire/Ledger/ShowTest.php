<?php

namespace tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\Show;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\AutoLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use tests\TestCase;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Jobs\Ledger\GenerateThumbnail;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $inspector;
    private User $approver;
    private Ledger $ledger;
    private Role $inspectorRole;
    private Role $approverRole;
    private Folder $folder; // ADDED

    protected function setUp(): void
    {
        parent::setUp();


        // ユーザーを作成
        $this->user = User::factory()->create();
        $this->inspector = User::factory()->create();
        $this->approver = User::factory()->create();

        // ロールを作成
        $this->inspectorRole = Role::create(['name' => 'inspector']);
        $this->approverRole = Role::create(['name' => 'approver']);
        $this->inspector->assignRole($this->inspectorRole);
        $this->approver->assignRole($this->approverRole);

        // フォルダと台帳定義を作成
        $this->folder = Folder::factory()
            ->withRequiredRoles(
                inspectors: [$this->inspectorRole],
                approvers: [$this->approverRole]
            )
            ->create();

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
            ->assertDontSeeHtml('<button[^>]*>' . __('ledger.workflow.approve') . '</button>');

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
            'completed_inspector_role_ids' => [$this->inspectorRole->id]
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
        $cleanedHtml = preg_replace('/wire:snapshot="[^"]*"/s', '', $component->html());
        $this->assertStringNotContainsString(
            __('ledger.workflow.request_approval_short'),
            $cleanedHtml);
        $component->assertDontSeeHtml('<button[^>]*wire:click="openApproverSelectModal"[^>]*>');

        // 他のユーザーでログイン
        $this->actingAs($this->user);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(">" . __('ledger.workflow.approve') . "<")
            ->assertDontSee(">" . __('ledger.workflow.return_to_draft_short') . "<");
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
            ->assertDontSeeHtml('<button[^>]*>' . __('ledger.workflow.approve') . '</button>')
            ->assertDontSee(__('ledger.workflow.return_to_draft_short'));
    }

    
    

    #[Test]
    public function it_retries_attached_file_processing()
    {
        Bus::fake();

        $attachedFile = AttachedFile::factory()->for($this->ledger)->create([
            'status' => \App\Enums\AttachedFileStatus::PROCESSING_FAILED,
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

    #[Test]
    public function it_sets_display_level_correctly_and_filters_columns()
    {
        $this->actingAs($this->user);

        // LedgerDefineのcolumn_defineを更新して、display_levelを持つカラムを設定
        $columnDefine = [
            ['id' => 1, 'name' => 'Column 1', 'display_level' => 1, 'order' => 1],
            ['id' => 2, 'name' => 'Column 2', 'display_level' => 2, 'order' => 2],
            ['id' => 3, 'name' => 'Column 3', 'display_level' => 3, 'order' => 3],
            ['id' => 4, 'name' => 'Column 4', 'display_level' => 4, 'order' => 4], // Should not be shown at level 3
        ];

        // このテスト専用の台帳定義と台帳を作成
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['column_define' => $columnDefine]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->create();

        // 初期状態 (displayLevel = 1)
        $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id]);
        $component->assertSet('displayLevel', 1);
        // ledgerDefineRecord は protected なので、インスタンスから直接アクセス
        $this->assertNotEmpty($component->instance()->ledgerRecord->define->column_define);
        $this->assertCount(1, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);

        // displayLevel を 2 に設定
        $component->call('setDisplayLevel', 2);
        $component->assertSet('displayLevel', 2);
        $this->assertCount(2, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);
        $this->assertEquals(2, $component->get('filteredColumns')[2]['id']);

        // displayLevel を 3 に設定
        $component->call('setDisplayLevel', 3);
        $component->assertSet('displayLevel', 3);
        $this->assertCount(3, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);
        $this->assertEquals(2, $component->get('filteredColumns')[2]['id']);
        $this->assertEquals(3, $component->get('filteredColumns')[3]['id']);

        // 無効な displayLevel を設定した場合、変更されないことを確認
        $component->call('setDisplayLevel', 99);
        $component->assertSet('displayLevel', 3); // 3のまま
    }

    #[Test]
    public function it_highlights_keywords_in_detail_view()
    {
        $this->actingAs($this->user);

        // 台帳データの準備
        $keyword = '詳細キーワード';
        $contentWithKeyword = ['text_column' => 'これは' . $keyword . 'を含むテキストです。'];

        // テスト用の台帳定義を作成し、text_column を含むようにする
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    ['id' => 0, 'name' => 'text_column', 'type' => 'text', 'order' => 1],
                ],
            ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => $contentWithKeyword,
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::withQueryParams(['highlight' => $keyword])
            ->test(Show::class, ['ledgerId' => $ledger->id])
            ->assertOk();

        $displayColumns = $component->get('displayColumns');
        $this->assertStringContainsString(
            '<mark class="text-error font-bold text-lg">' . $keyword . '</mark>',
            $displayColumns[0]['html']
        );
    }

    #[Test]
    public function it_displays_auto_links_in_detail_view()
    {
        $this->actingAs($this->user);

        // 自動リンク定義の準備
        AutoLink::factory()->create([
            'label' => 'Test AutoLink Detail',
            'pattern' => '/(DOC-\\d{3})/',
            'url_template' => '/l/$1',
            'is_enabled' => true,
        ]);

        // 台帳データの準備
        $autoLinkText = 'これはDOC-001を含むテキストです。';

        // テスト用の台帳定義を作成し、text_column を含むようにする
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    ['id' => 0, 'name' => 'text_column', 'type' => 'text', 'order' => 1],
                ],
            ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['text_column' => $autoLinkText],
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id])
            ->assertOk();

        $displayColumns = $component->get('displayColumns');
        $this->assertStringContainsString(
            '<a href="/l/DOC-001"',
            $displayColumns[0]['html']
        );
        $this->assertStringContainsString(
            'DOC-001',
            $displayColumns[0]['html']
        );
    }
}