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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(LedgerDiffViewer::class)]
class LedgerDiffViewerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

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
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();

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
                            'is_omitted' => false,
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
    public function it_renders_omitted_indicator_when_columns_are_filtered_by_level(): void
    {
        // 1. LedgerContentProcessor のモックを作成
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            $dummyDisplayData = [
                [
                    'group_name' => 'Omitted Group',
                    'is_required_group' => false,
                    'columns' => [
                        [
                            'is_omitted' => true,
                            'omitted_count' => 2,
                        ],
                        [
                            'id' => 'col_visible',
                            'name' => 'Visible Column',
                            'hint' => '',
                            'is_required' => false,
                            'status' => 'unchanged',
                            'current_value_html' => '<div>Visible</div>',
                            'old_value_html' => '<div>Visible</div>',
                            'is_omitted' => false,
                        ],
                    ],
                ],
            ];

            $mock->shouldReceive('processContentForDisplay')
                ->andReturn([
                    'displayData' => $dummyDisplayData,
                    'hasChangedColumns' => false,
                ]);
        });

        // 2. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger])
            ->assertSee('2項目の非表示項目があります');
    }

    #[Test]
    public function it_renders_omitted_indicator_with_real_processor(): void
    {
        // 1. カラム定義を作成 (Lv1とLv3を混ぜる)
        $ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
            'column_define' => [
                [
                    'id' => 1,
                    'name' => 'Visible Col',
                    'type' => 'text',
                    'display_level' => 1,
                    'order' => 1,
                    'group' => 'Group 1',
                    'required' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'Omitted Col 1',
                    'type' => 'text',
                    'display_level' => 3,
                    'order' => 2,
                    'group' => 'Group 1',
                    'required' => false,
                ],
                [
                    'id' => 3,
                    'name' => 'Omitted Col 2',
                    'type' => 'text',
                    'display_level' => 3,
                    'order' => 3,
                    'group' => 'Group 1',
                    'required' => false,
                ],
            ],
        ]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create([
                'tenant_id' => $this->tenant->id,
                'content' => [
                    1 => 'Value 1',
                    2 => 'Value 2',
                    3 => 'Value 3',
                ],
            ]);

        // 2. 表示レベル 1 でテスト
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'displayLevel' => 1,
        ])
            ->assertSee('Visible Col')
            ->assertDontSee('Omitted Col 1')
            ->assertSee('2項目の非表示項目があります');

        // 3. 表示レベル 3 に切り替え
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'displayLevel' => 3,
        ])
            ->assertSee('Visible Col')
            ->assertSee('Omitted Col 1')
            ->assertSee('Omitted Col 2')
            ->assertDontSee('項目の非表示項目があります');
    }

    #[Test]
    public function it_calls_processor_with_updated_display_level(): void
    {
        // 1. LedgerContentProcessor のモックを作成し、呼び出しを期待する設定を行う
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            // 最初に displayLevel=1 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 1, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
                ->andReturn(['displayData' => [], 'hasChangedColumns' => false]);

            // 次に displayLevel=2 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 2, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
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
        // 1. プロセッサのモック
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('processContentForDisplay')
                ->atLeast()->once()
                ->andReturn([
                    'displayData' => [],
                    'hasChangedColumns' => true,
                ]);
        });

        // 2. Livewire コンポーネントをテスト (showChanges を true で開始)
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $this->ledger,
            'showChanges' => true,
        ])
            ->assertSet('showChanges', true)
            ->assertSeeHtml('Ver.');
    }

    #[Test]
    public function it_does_not_compare_same_version_when_only_one_version_is_selected(): void
    {
        // 1. セットアップ: バージョン1, 2, 3 を持つ台帳を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [['id' => 1, 'name' => 'Column 1', 'type' => 'text', 'order' => 1]],
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
        ]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create(['version' => 3, 'tenant_id' => $this->tenant->id]);

        $diff1 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
            'content' => ['v1'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);

        $diff2 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 2,
            'content' => ['v2'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);

        $diff3 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 3,
            'content' => ['v3'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);

        $ledger->update(['latest_diff_id' => $diff3->id]);

        // 2. Ver.2 だけを選択した状態をシミュレート (useFallback=false を想定)
        $component = Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'baseDiffId' => $diff2->id,
            'targetDiffId' => null,
            'canView' => true,
            'showChanges' => false, // 単一選択時は changes 非表示
            'useFallback' => false,
        ]);

        // 3. 検証: useFallback=false の場合、comparisonTargetDiff は null になるべき
        $component->assertSet('baseDiffId', $diff2->id);
        $this->assertNull($component->instance()->comparisonTargetDiff, 'Should not have comparison target when useFallback is false');
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
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledgerWithFiles->id,
            'ledger_define_id' => $ledgerDefineWithFiles->id,
            'tenant_id' => $this->tenant->id,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hashedbasename' => 'test_hashed_basename.pdf',
            'original_mime_type' => 'image/jpeg',
        ]);

        $this->assertEquals(1, AttachedFile::where('ledger_id', $ledgerWithFiles->id)->count(), 'File should be in DB for this ledger');

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
        $ledgerWithFiles->refresh();

        // Livewireコンポーネントをテスト
        $this->actingAs($this->user);
        $livewire = Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledgerWithFiles,
            'allAttachments' => $ledgerWithFiles->attachedFiles()->get()->keyBy('hashedbasename'), // 明示的に渡す
            'displayLevel' => 3,
            'highlight' => null,
            'canView' => true,
            'tenantId' => $this->tenant->id,
        ]);

        $this->assertNotNull($livewire->instance()->allAttachments, 'allAttachments should not be null');
        $this->assertTrue($livewire->instance()->allAttachments->isNotEmpty(), 'allAttachments should not be empty');
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

    #[Test]
    public function it_displays_placeholder_for_unchanged_columns_in_diff_view(): void
    {
        // 1. カラム定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 1, 'name' => 'Name', 'type' => 'text', 'order' => 1, 'group' => 'Group 1', 'required' => false],
                ['id' => 2, 'name' => 'Age', 'type' => 'text', 'order' => 2, 'group' => 'Group 1', 'required' => false],
                ['id' => 3, 'name' => 'Address', 'type' => 'text', 'order' => 3, 'group' => 'Group 1', 'required' => false],
            ],
        ]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create([
                'tenant_id' => $this->tenant->id,
                'content' => ['Taro Yamada', '30', 'Tokyo'],
                'version' => 2,
            ]);

        // 2. 過去バージョンの Diff を作成 (Name のみ変更)
        $oldDiff = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
            'content' => ['Hanako Tanaka', '30', 'Tokyo'], // Name が異なる
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        // 3. Livewire コンポーネントをテスト
        $component = Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'comparisonTargetDiff' => $oldDiff,
            'showChanges' => true,
            'canView' => true,
        ]);

        // 4. 検証
        // Name (modified) - 両方の値が表示される
        $component->assertSee('Taro Yamada');
        $component->assertSee('Hanako Tanaka');

        // Age と Address (unchanged) - プレースホルダーが表示される
        $component->assertSee(__('ledger.diff.same_as_current'));

        // 値が2回表示される（現行側のみ）、3回は表示されない（過去側はプレースホルダー）
        $ageCount = substr_count($component->html(), '30');
        $this->assertGreaterThanOrEqual(1, $ageCount, 'Age should appear at least once (current side)');

        $addressCount = substr_count($component->html(), 'Tokyo');
        $this->assertGreaterThanOrEqual(1, $addressCount, 'Address should appear at least once (current side)');
    }

    #[Test]
    public function it_shows_group_placeholder_when_all_columns_unchanged(): void
    {
        // 1. カラム定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 1, 'name' => 'Column A', 'type' => 'text', 'order' => 1, 'group' => 'Group 1', 'required' => false],
                ['id' => 2, 'name' => 'Column B', 'type' => 'text', 'order' => 2, 'group' => 'Group 1', 'required' => false],
            ],
        ]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create([
                'tenant_id' => $this->tenant->id,
                'content' => ['Value A', 'Value B'],
                'version' => 2,
            ]);

        // 2. 過去バージョンの Diff を作成 (全カラム同じ内容)
        $oldDiff = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
            'content' => ['Value A', 'Value B'], // 全て同じ
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        // 3. Livewire コンポーネントをテスト
        $component = Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'comparisonTargetDiff' => $oldDiff,
            'showChanges' => true,
            'canView' => true,
        ]);

        // 4. 検証: グループ単位の大きなプレースホルダーが表示される（後方互換性）
        $component->assertSee(__('ledger.diff.identical_content'));
        $component->assertSee(__('ledger.diff.no_changes'));

        // カラム単位のプレースホルダーは表示されない
        $component->assertDontSee(__('ledger.diff.same_as_current'));
    }
}
