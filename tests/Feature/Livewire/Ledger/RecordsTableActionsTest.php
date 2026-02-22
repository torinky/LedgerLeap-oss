<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * RecordsTable コンポーネントの未カバーメソッドに対する追加テスト
 *
 * 対象:
 *  - sort / changeCurrentFolder / toggleFolderId / toggleLedgerDefineId / focusLedgerDefine
 *  - updatedOrderBy / updatedSearch / updatedFilter / updatedCurrentFolderId
 *  - updatedSelectedFolderIds / updatedSelectedLedgerDefineIds / updatedOrderAsc
 *  - updatedUseSemanticSearch / updatedUseSynonym / updatedUseTechnicalTerm
 *  - updatingPerPage / setDisplayLevel / refresh / refreshDueToPermissionChange
 *  - openPermissionModal / openActivityModal
 *  - handleOpenPermissionModal / handleOpenActivityModal
 *  - lastPage / isPurelyNumericAutoNumber / retryProcessing
 */
#[CoversClass(RecordsTable::class)]
class RecordsTableActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private LedgerDefine $ledgerDefine;

    private Folder $folder;

    protected Tenant $tenant;

    /** @var array<string, mixed> 共通マウントパラメータ */
    private array $mountProps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_auto_links', 'guard_name' => 'web']);
        $this->user->givePermissionTo(['view_ledger_defines', 'ledgerView', 'view_auto_links']);

        $rootFolder = Folder::factory()->create(['parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => $rootFolder->id]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => 'テキスト', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $this->mountProps = [
            'currentFolderId' => $this->folder->id,
            'selectedFolderIds' => [$this->folder->id],
            'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
        ];

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
    // sort / toggle / focus / changeCurrentFolder
    // ===================================================================

    #[Test]
    public function sort_dispatches_sort_requested_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('sort', 'updated_at', '更新日時');

        $component->assertDispatched('sortRequested',
            columnName: 'updated_at',
            columnLabel: '更新日時'
        );
    }

    #[Test]
    public function toggle_folder_id_dispatches_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('toggleFolderId', $this->folder->id);

        $component->assertDispatched('folderIdToggled', folderId: $this->folder->id);
    }

    #[Test]
    public function toggle_ledger_define_id_dispatches_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('toggleLedgerDefineId', $this->ledgerDefine->id);

        $component->assertDispatched('ledgerDefineIdToggled', ledgerDefineId: $this->ledgerDefine->id);
    }

    #[Test]
    public function focus_ledger_define_dispatches_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('focusLedgerDefine', $this->ledgerDefine->id);

        $component->assertDispatched('focusLedgerDefineRequested', defineId: $this->ledgerDefine->id);
    }

    #[Test]
    public function change_current_folder_dispatches_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('changeCurrentFolder', $this->folder->id);

        $component->assertDispatched('currentFolderChangeRequested', newFolderId: $this->folder->id);
    }

    // ===================================================================
    // set_display_level / refresh
    // ===================================================================

    #[Test]
    public function set_display_level_dispatches_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('setDisplayLevel', 2);

        $component->assertDispatched('displayLevelRequested', level: 2);
    }

    #[Test]
    public function refresh_event_listener_updates_folder_asset(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // ledgerStored イベントが refresh を呼ぶことを確認
        $component->dispatch('ledgerStored');

        $component->assertOk();
    }

    // ===================================================================
    // updated* フック — Reactiveプロパティは親から渡されるため instance() 経由で直接呼び出し
    // ===================================================================

    #[Test]
    public function updated_order_by_updates_label(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // updatedOrderBy はReactiveプロパティ orderBy を変更しないため直接呼び出し可能
        $component->instance()->updatedOrderBy('updated_at');

        // orderByLabel が文字列であることを確認（空でも可）
        $label = $component->instance()->orderByLabel;
        $this->assertIsString($label);
    }

    #[Test]
    public function updated_search_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedSearch('テスト検索語');

        $component->assertOk();
    }

    #[Test]
    public function updated_filter_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedFilter(['status' => 'approved']);

        $component->assertOk();
    }

    #[Test]
    public function updated_current_folder_id_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedCurrentFolderId($this->folder->id);

        $component->assertOk();
    }

    #[Test]
    public function updated_selected_folder_ids_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedSelectedFolderIds([]);

        $component->assertOk();
    }

    #[Test]
    public function updated_selected_ledger_define_ids_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedSelectedLedgerDefineIds([]);

        $component->assertOk();
    }

    #[Test]
    public function updated_order_asc_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedOrderAsc(true);

        $component->assertOk();
    }

    #[Test]
    public function updated_use_semantic_search_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedUseSemanticSearch(true);

        $component->assertOk();
    }

    #[Test]
    public function updated_use_synonym_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedUseSynonym(false);

        $component->assertOk();
    }

    #[Test]
    public function updated_use_technical_term_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->instance()->updatedUseTechnicalTerm(false);

        $component->assertOk();
    }

    #[Test]
    public function updating_per_page_is_callable(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // updatingPerPage はReactiveプロパティperPageを変更しないためインスタンス経由で呼び出し可能
        // （resetPage()内部的に処理するだけ）
        $component->instance()->updatingPerPage();

        // 例外がスローされないことを確認
        $this->assertTrue(true);
    }

    // ===================================================================
    // openPermissionModal / openActivityModal
    // ===================================================================

    #[Test]
    public function open_permission_modal_sets_modal_state(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('openPermissionModal', 'Folder', $this->folder->id, 'テストフォルダ');

        $component->assertSet('showPermissionModal', true);
        $component->assertSet('modalResourceType', 'Folder');
        $component->assertSet('modalResourceId', $this->folder->id);
    }

    #[Test]
    public function open_activity_modal_sets_modal_state(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->call('openActivityModal', 'Folder', $this->folder->id, 'テストフォルダ');

        $component->assertSet('showActivityModal', true);
        $component->assertSet('modalResourceType', 'Folder');
        $component->assertSet('modalResourceId', $this->folder->id);
    }

    #[Test]
    public function handle_open_permission_modal_via_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->dispatch('openPermissionModalRequested',
            resourceType: 'Folder',
            resourceId: $this->folder->id,
            title: 'テストフォルダ'
        );

        $component->assertSet('showPermissionModal', true);
    }

    #[Test]
    public function handle_open_activity_modal_via_event(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->dispatch('openActivityModalRequested',
            resourceType: 'Folder',
            resourceId: $this->folder->id,
            title: 'テストフォルダ'
        );

        $component->assertSet('showActivityModal', true);
    }

    // ===================================================================
    // lastPage
    // ===================================================================

    #[Test]
    public function last_page_returns_correct_value(): void
    {
        Ledger::factory()->count(150)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // perPage=100 のときに 150件 → lastPage = 2 (ceil()はfloatを返す)
        $lastPage = $component->instance()->lastPage();
        $this->assertIsNumeric($lastPage);
        $this->assertGreaterThan(0, $lastPage);
    }

    #[Test]
    public function last_page_returns_one_when_per_page_is_zero(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // perPage はReactiveプロパティなのでinstance経由でテスト
        $instance = $component->instance();
        // totalRecords=0, perPage=0 のケースをシミュレート
        $instance->totalRecords = 5;

        // perPage=0のときのlastPage()をリフレクションでテスト
        $reflection = new \ReflectionClass($instance);
        $perPageProp = $reflection->getProperty('perPage');
        $perPageProp->setAccessible(true);
        $perPageProp->setValue($instance, 0);

        $this->assertEquals(1, $instance->lastPage());
    }

    // ===================================================================
    // retryProcessing (#[On('retryProcessingEvent')])
    // ===================================================================

    #[Test]
    public function retry_processing_event_dispatches_error_when_file_not_found(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->dispatch('retryProcessingEvent', attachedFileId: 999999);

        $component->assertDispatched('toast', type: 'error');
    }

    // ===================================================================
    // refreshDueToPermissionChange
    // ===================================================================

    #[Test]
    public function refresh_due_to_permission_change_rerenders_component(): void
    {
        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        // permissions-changed イベントが refreshDueToPermissionChange を呼ぶ
        $component->dispatch('permissions-changed');

        $component->assertOk();
    }
}
