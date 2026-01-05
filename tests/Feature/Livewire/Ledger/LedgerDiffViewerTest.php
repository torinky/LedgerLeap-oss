<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\LedgerDiffViewer;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LedgerDiffViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Ledger $ledger;

    protected Tenant $tenant;

    private User $inspector;

    private User $approver;

    private Role $inspectorRole;

    private Role $approverRole;

    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // ロールを作成 (ShowTestからコピー)
        $this->inspectorRole = Role::create(['name' => 'inspector']);
        $this->approverRole = Role::create(['name' => 'approver']);
        $this->inspector = User::factory()->create(); // ShowTestではinspectorとapproverも作成していた
        $this->approver = User::factory()->create();
        $this->inspector->assignRole($this->inspectorRole);
        $this->approver->assignRole($this->approverRole);

        // 'view_ledgers' パーミッションを作成し、$this->user に付与 (ShowTestからコピー)
        $viewLedgersPermission = Permission::firstOrCreate(['name' => 'view_ledgers']);
        $this->user->givePermissionTo($viewLedgersPermission);

        // テスト用のロールを作成し、$this->user に割り当てる (ShowTestからコピー)
        $testReaderRole = Role::firstOrCreate(['name' => 'test_reader_role']);
        $this->user->assignRole($testReaderRole);

        // フォルダと台帳定義を作成 (ShowTestからコピー)
        $this->folder = Folder::factory()
            ->withRequiredRoles(
                inspectors: [$this->inspectorRole],
                approvers: [$this->approverRole]
            )
            ->create();

        // RoleFolderPermission を作成し、テスト用のロールとフォルダ、READ権限を関連付ける (ShowTestからコピー)
        RoleFolderPermission::create([
            'role_id' => $testReaderRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        // ユーザーのパーミッションキャッシュをクリア (ShowTestからコピー)
        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        $ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            // ShowTestではfolderに紐付けていたので、ここも修正
            'folder_id' => $this->folder->id,
        ]);
        $this->ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create(['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function it_renders_correctly_with_data_from_processor(): void
    {
        // 1. LedgerContentProcessor のモックを作成
        $mock = $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            // processContentForDisplay が呼び出された際に返すダミーデータを定義
            $dummyDisplayData = [
                [
                    'group_name' => 'Test Group',
                    'is_required_group' => true,
                    'columns' => [
                        [
                            'id' => 'col1',
                            'name' => 'Test Column',
                            'hint' => 'A hint',
                            'is_required' => true,
                            'status' => 'modified',
                            'current_value_html' => '<div>Current Value</div>',
                            'old_value_html' => '<div>Old Value</div>',
                        ],
                    ],
                ],
            ];

            $mock->shouldReceive('processContentForDisplay')
                ->atLeast()->once()
                ->with(
                    Mockery::type(Ledger::class),
                    Mockery::any(),
                    Mockery::type('int'),
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::any()
                )
                ->andReturn([
                    'displayData' => $dummyDisplayData,
                    'hasChangedColumns' => true,
                ]);
        });

        // 2. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger])
            ->assertOk()
            ->assertSet('hasChangedColumns', true)
            ->assertSee('Test Group')
            ->assertSee('Test Column')
            ->assertSeeHtml('<div>Current Value</div>');
    }

    #[Test]
    public function it_calls_processor_with_updated_display_level(): void
    {
        // 1. LedgerContentProcessor のモックを作成し、呼び出しを期待する設定を行う
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            // 最初に displayLevel=1 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 1, Mockery::any(), Mockery::any(), Mockery::any())
                ->andReturn(['displayData' => [], 'hasChangedColumns' => false]);

            // 次に displayLevel=2 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 2, Mockery::any(), Mockery::any(), Mockery::any())
                ->andReturn(['displayData' => [], 'hasChangedColumns' => false]);
        });

        // 2. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger, 'displayLevel' => 1])
            ->dispatch('displayLevelUpdated', displayLevel: 2) // イベントを発行
            ->assertSet('displayLevel', 2);
    }

    #[Test]
    public function it_hides_diff_view_by_default(): void
    {
        // 1. プロセッサのモック
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('processContentForDisplay')
                ->with(
                    Mockery::type(Ledger::class),
                    Mockery::any(),
                    Mockery::type('int'),
                    Mockery::any(),
                    Mockery::any(),
                    Mockery::any()
                )
                ->andReturn([
                    'displayData' => [],
                    'hasChangedColumns' => true,
                ]);
        });

        // 2. Livewire コンポーネントをテスト (showChanges はデフォルトで false)
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger])
            ->assertSet('showChanges', false)
            ->assertDontSeeHtml('Ver.');
    }

    #[Test]
    public function it_shows_diff_view_when_show_changes_is_true(): void
    {
        // このテストでは、プロセッサが実際に動作して差分を検出し、
        // ビューが正しくレンダリングされることを確認するため、モックは使用しない。

        // 1. データベースの状態を正確にセットアップ
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 1, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ],
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
        ]);

        // 2. version 1 の Ledger と LedgerDiff を作成
        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create([
                'version' => 1,
                'content' => ['old value'],
                'tenant_id' => $this->tenant->id,
            ]);

        $diffV1 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
            'content' => ['old value'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);
        $ledger->latest_diff_id = $diffV1->id;
        $ledger->save();

        // 3. Ledger を更新して version 2 にする
        $ledger->version = 2;
        $ledger->content = ['current value'];

        // 4. version 2 の LedgerDiff を作成
        $diffV2 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 2,
            'content' => ['current value'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);
        $ledger->latest_diff_id = $diffV2->id;
        $ledger->save();

        // 5. 最終状態をDBから読み込んでコンポーネントに渡す
        $ledger->refresh();

        // 6. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger, // version 2 の Ledger
            'canView' => true,
            'hasChangedColumns' => true,
            'showChanges' => true,
        ])
            ->set('hasChangedColumns', true) // ->set() を使ってプロパティを有効化
            ->set('showChanges', true) // ->set() を使ってプロパティを有効化
            ->dump()
            ->assertSeeHtml('Ver.1'); // 比較対象の version 1 が表示されることを確認
    }

    #[Test]
    public function it_displays_attached_files_correctly_in_diff_viewer()
    {
        Bus::fake(); // ジョブがディスパッチされないようにモック

        Storage::fake('public');

        // 添付ファイルを持つLedgerDefineを作成
        $ledgerDefineWithFiles = LedgerDefine::factory()->for($this->folder)->create([
            'workflow_enabled' => false,
            'column_define' => [
                new ColumnDefine(1, 'File Column', 'files', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        // 添付ファイルを持つLedgerレコードを作成
        $ledgerWithFiles = Ledger::factory()
            ->for($ledgerDefineWithFiles, 'define')
            ->for($this->user, 'creator')
            ->create(['tenant_id' => $this->tenant->id]);

        // ダミーの添付ファイルを作成し、ストレージに配置
        $file = \Illuminate\Http\UploadedFile::fake()->image('test_document.pdf', 100, 100);
        $attachedFile = AttachedFile::factory()->for($ledgerWithFiles)->create([
            'ledger_define_id' => $ledgerDefineWithFiles->id,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hashedbasename' => 'test_hashed_basename.pdf',
            'original_mime_type' => 'image/jpeg',
        ]);
        Storage::disk('public')->putFileAs(
            'tenants/'.$this->tenant->id.'/Ledger/Attachments/'.$ledgerDefineWithFiles->id.'/',
            $file,
            $attachedFile->hashedbasename
        );
        Storage::disk('public')->put(
            'tenants/'.$this->tenant->id.'/Ledger/thumbs/'.$attachedFile->hashedbasename,
            'dummy_thumbnail_content'
        );

        // Ledgerのcontentに添付ファイルIDを設定
        $ledgerWithFiles->content = [
            1 => [ // ColumnDefineのIDと合わせる
                $attachedFile->hashedbasename => $attachedFile->filename,
            ],
        ];
        $ledgerWithFiles->save();

        // Livewireコンポーネントをテスト
        $this->actingAs($this->user);
        $livewire = Livewire::test(LedgerDiffViewer::class, [ // ★ここを変更
            'ledgerRecord' => $ledgerWithFiles, // ★ここを変更
            'displayLevel' => 3, // ★ここを変更
            'highlight' => null, // ★ここを追加
            'canView' => true,
        ]);

        $this->assertNotEmpty($livewire->instance()->allAttachments);
        $this->assertArrayHasKey('test_hashed_basename.pdf', $livewire->instance()->allAttachments);
        $this->assertEquals($attachedFile->id, $livewire->instance()->allAttachments->get('test_hashed_basename.pdf')->id);

        // HTMLに期待されるURLの断片が含まれていることをアサート
        $livewire->assertSee('files/', false);
        $livewire->assertSee('/download', false);
        $livewire->assertSee((string) $attachedFile->id, false);
        $livewire->assertSee('thumbnail=true', false);

        // ファイル名も表示されていることを確認
        $livewire->assertSee($attachedFile->filename);
    }
}
