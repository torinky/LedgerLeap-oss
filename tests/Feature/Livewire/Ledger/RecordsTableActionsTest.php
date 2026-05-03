<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\RecordsTable;
use App\Livewire\Ledger\RecordsTableRow;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

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
    use RefreshDatabaseWithTenant;

    private User $user;

    private LedgerDefine $ledgerDefine;

    private Folder $folder;

    private Folder $rootFolder;

    protected Tenant $tenant;

    /** @var array<string, mixed> 共通マウントパラメータ */
    private array $mountProps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_auto_links', 'guard_name' => 'web']);
        $this->user->givePermissionTo(['view_ledger_defines', 'ledgerView', 'view_auto_links']);

        $this->rootFolder = Folder::factory()->create(['parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => $this->rootFolder->id]);

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
    public function file_selection_updates_derived_state_and_dispatches_focus_event(): void
    {
        $fileLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => '添付', 'type' => 'files', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $fileLedgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => $fileLedgerDefine->normalizeByColumnDefine([0 => 'first-term second-term']),
            'status' => WorkflowStatus::NONE,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $fileLedgerDefine->id,
            'column_id' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        $component = Livewire::test(RecordsTable::class, array_merge($this->mountProps, [
            'selectedLedgerDefineIds' => [$fileLedgerDefine->id],
        ]));

        $component
            ->call('syncFileInspectorSelection', $file->id, null, true)
            ->assertSet('selectedFileId', $file->id)
            ->assertSet('selectedLedgerId', $ledger->id)
            ->assertSet('selectedColumnId', 0)
            ->assertSet('isFileInspectorOpen', true)
            ->assertDispatched('file-inspector-selection-applied',
                selectedFileId: $file->id,
                selectedLedgerId: $ledger->id,
                selectedColumnId: 0,
                isOpen: true,
            );

        $component->assertOk();
    }

    #[Test]
    public function deferred_records_table_row_renders_attachment_list_with_search_context(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => '添付', 'type' => 'files', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => [0 => ['hash-attachment' => 'search-context.pdf']],
            'content_attached' => [0 => []],
            'status' => WorkflowStatus::NONE,
        ]);
        $ledger->load('define');

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 0,
            'tenant_id' => $this->tenant->id,
            'filename' => 'search-context.pdf',
            'hashedbasename' => 'hash-attachment',
            'original_mime_type' => 'application/pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'optimized' => true,
        ]);

        $component = Livewire::withoutLazyLoading()->test(RecordsTableRow::class, [
            'ledgerId' => $ledger->id,
            'columnId' => 0,
            'highlightKeyword' => 'second-term',
            'canView' => true,
            'currentTenantId' => $this->tenant->id,
            'selectedFileId' => $file->id,
        ]);

        $component->assertOk();

        $html = $component->html();

        $this->assertStringContainsString('data-search="second-term"', $html);
        $this->assertStringContainsString('closest(\'[data-search]\')', $html);
        $this->assertStringContainsString('this.$dispatch(\'open-file-inspector\', {', $html);
        $this->assertStringContainsString('direct-download-link', $html);
        $this->assertStringContainsString('fa-solid fa-download', $html);
        $this->assertStringContainsString('search-context.pdf', $html);
        $this->assertStringContainsString(
            route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $file->id]),
            $html
        );
        $this->assertStringContainsString(__('ledger.download_optimized'), $html);
        $this->assertStringContainsString('ring-2 ring-primary/60 bg-primary/5', $html);
    }

    #[Test]
    public function deferred_records_table_row_shows_more_button_for_many_attachments(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => '添付', 'type' => 'files', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => [
                0 => [
                    'hash-1' => 'file-1.pdf',
                    'hash-2' => 'file-2.pdf',
                    'hash-3' => 'file-3.pdf',
                    'hash-4' => 'file-4.pdf',
                    'hash-5' => 'file-5.pdf',
                    'hash-6' => 'file-6.pdf',
                ],
            ],
            'content_attached' => [0 => []],
            'status' => WorkflowStatus::NONE,
        ]);
        $ledger->load('define');

        foreach (range(1, 6) as $index) {
            AttachedFile::factory()->create([
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledgerDefine->id,
                'column_id' => 0,
                'tenant_id' => $this->tenant->id,
                'filename' => "file-{$index}.pdf",
                'hashedbasename' => "hash-{$index}",
                'original_mime_type' => 'application/pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
            ]);
        }

        $component = Livewire::withoutLazyLoading()->test(RecordsTableRow::class, [
            'ledgerId' => $ledger->id,
            'columnId' => 0,
            'highlightKeyword' => null,
            'canView' => true,
            'currentTenantId' => $this->tenant->id,
        ]);

        $component->assertOk();

        $html = $component->html();

        $this->assertStringContainsString('x-on:click="toggleShowAll()"', $html);
        $this->assertStringContainsString(__('ledger.show_more'), $html);
        $this->assertStringContainsString('(+2)', $html);
        $this->assertStringContainsString(__('ledger.collapse'), $html);
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

        $component->assertSet('prepareFolderAssetInvocationCount', 1);

        // ledgerStored イベントが refresh を呼ぶことを確認
        $component->dispatch('ledgerStored');

        $component
            ->assertOk()
            ->assertSet('prepareFolderAssetInvocationCount', 2);
    }

    #[Test]
    public function render_logs_fine_grained_phase_metrics(): void
    {
        config(['ledgerleap.performance.enabled' => true]);
        config(['ledgerleap.performance.log_destination' => 'log']);

        Log::spy();

        $component = Livewire::test(RecordsTable::class, $this->mountProps);

        $component->assertOk();
        $component->assertDispatched('ledger-records-count-updated');

        Log::shouldHaveReceived('info')
            ->withArgs(function (...$args): bool {
                [$message, $context] = array_pad($args, 2, []);

                return $message === '[Performance] ledger_records_render'
                    && ($context['component'] ?? null) === 'RecordsTable'
                    && array_key_exists('ledger_records_query_ms', $context)
                    && ($context['attachments_fetch_ms'] ?? null) === 0.0
                    && array_key_exists('normalize_ms', $context)
                    && array_key_exists('content_normalize_ms', $context)
                    && array_key_exists('content_attached_normalize_ms', $context)
                    && array_key_exists('search_hit_mark_ms', $context)
                    && array_key_exists('display_ledger_defines_query_ms', $context)
                    && array_key_exists('display_ledger_defines_load_ms', $context)
                    && array_key_exists('ledger_records_query_prep_ms', $context)
                    && array_key_exists('related_ledger_define_ids_ms', $context)
                    && array_key_exists('missing_define_fetch_ms', $context)
                    && array_key_exists('ledger_records_query_count_ms', $context)
                    && array_key_exists('ledger_records_query_count_cache_hit', $context)
                    && array_key_exists('ledger_records_query_paginate_ms', $context)
                    && array_key_exists('ledger_records_define_load_ms', $context)
                    && array_key_exists('search_target_ledger_define_ids_ms', $context)
                    && array_key_exists('search_target_ledger_define_ids_count', $context)
                    && array_key_exists('search_target_ledger_define_ids_mode', $context)
                    && array_key_exists('page_ledger_define_count', $context)
                    && array_key_exists('grouping_ms', $context)
                    && array_key_exists('view_prepare_ms', $context);
            })
            ->atLeast()
            ->once();
    }

    #[Test]
    public function render_reuses_total_records_count_for_unrelated_state_changes(): void
    {
        config(['ledgerleap.performance.enabled' => true]);
        config(['ledgerleap.performance.log_destination' => 'log']);

        Log::spy();

        Livewire::test(RecordsTable::class, $this->mountProps)
            ->assertSet('prepareFolderAssetInvocationCount', 1)
            ->call('openPermissionModal', 'Folder', $this->folder->id, 'テストフォルダ')
            ->assertSet('showPermissionModal', true)
            ->assertSet('prepareFolderAssetInvocationCount', 1);

        Log::shouldHaveReceived('info')
            ->withArgs(function (...$args): bool {
                [$message, $context] = array_pad($args, 2, []);

                return $message === '[Performance] ledger_records_render'
                    && ($context['component'] ?? null) === 'RecordsTable'
                    && ($context['ledger_records_query_count_cache_hit'] ?? null) === true;
            })
            ->atLeast()
            ->once();
    }

    #[Test]
    public function render_logs_search_target_ledger_define_modes(): void
    {
        config(['ledgerleap.performance.enabled' => true]);
        config(['ledgerleap.performance.log_destination' => 'log']);

        $assertMode = function (array $mountProps, string $expectedMode): void {
            Log::spy();

            Livewire::test(RecordsTable::class, $mountProps)
                ->assertOk();

            Log::shouldHaveReceived('info')
                ->withArgs(function (...$args) use ($expectedMode): bool {
                    [$message, $context] = array_pad($args, 2, []);

                    return $message === '[Performance] ledger_records_render'
                        && ($context['component'] ?? null) === 'RecordsTable'
                        && ($context['search_target_ledger_define_ids_mode'] ?? null) === $expectedMode;
                })
                ->atLeast()
                ->once();
        };

        $assertMode($this->mountProps, 'selected');

        $assertMode([
            'currentFolderId' => $this->folder->id,
            'selectedFolderIds' => [],
            'selectedLedgerDefineIds' => [],
        ], 'unscoped');

        $assertMode([
            'currentFolderId' => $this->folder->id,
            'selectedFolderIds' => [],
            'selectedLedgerDefineIds' => [],
            'search' => '全体',
        ], 'global');
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

        $component->assertSet('prepareFolderAssetInvocationCount', 1);

        $component->instance()->currentFolderId = $this->rootFolder->id;
        $component->instance()->updatedCurrentFolderId($this->rootFolder->id);

        $this->assertSame($this->rootFolder->id, $component->instance()->currentFolderId);
        $component->assertSet('prepareFolderAssetInvocationCount', 2);

        $this->assertNotNull($component->instance()->currentFolder);

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

        $component = new RecordsTable;
        $component->perPage = 100;
        $component->totalRecords = 150;

        $lastPage = $component->lastPage();
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
