<?php

namespace Tests\Feature\Livewire\Ledger;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Livewire\Ledger\IndexManager;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Tests\Traits\RefreshDatabaseWithTenant;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IndexManagerIntegrationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = false; // RefreshDatabaseWithTenant で管理するため false に戻す

    private User $user;

    private Folder $rootFolder;

    private Folder $subFolder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テナントを明示的に初期化
        if ($this->getTenant()) {
            tenancy()->initialize($this->getTenant());
        }

        // Mroongaテーブル明示的クリア
        Ledger::query()->delete();

        $this->user = User::factory()->create();

        // 権限の付与
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        $this->user->givePermissionTo('ledgerView');
        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        $this->user->givePermissionTo('view_ledger_defines');

        $this->rootFolder = Folder::factory()->create(['parent_id' => null, 'title' => 'Root']);
        $this->subFolder = Folder::factory()->create(['parent_id' => $this->rootFolder->id, 'title' => 'Sub']);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->subFolder->id,
            'title' => 'Test Ledger',
            'column_define' => [
                ['id' => 0, 'name' => 'Name', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $this->be($this->user);
    }

    #[Test]
    public function it_renders_correctly_with_initial_state()
    {
        Livewire::test(IndexManager::class)
            ->assertStatus(200)
            ->assertSee('Root');
        // 'Test Ledger' は Root フォルダ直下ではないので初期状態では見えない可能性がある。
        // 確実に表示させるにはサブフォルダに移動するか、l パラメータで指定する。
    }

    #[Test]
    public function it_updates_search_query_reactively()
    {
        $ledger1 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->ledgerDefine->normalizeByColumnDefine([0 => 'TargetContent']),
        ]);
        $ledger2 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->ledgerDefine->normalizeByColumnDefine([0 => 'No Search Match']),
        ]);

        // Mroonga インデックス更新待ち
        sleep(1);

        Livewire::test(IndexManager::class)
            ->set('selectedLedgerDefineIds', [$this->ledgerDefine->id])
            ->set('search', 'Target')
            // 検索語 Target を使い、結果に含まれる 'TargetContent' が表示されることを期待
            ->assertSee('TargetContent')
            ->assertDontSee('No Search Match');
    }

    #[Test]
    public function it_changes_current_folder_reactively()
    {
        $otherFolder = Folder::factory()->create(['title' => 'Other Folder', 'parent_id' => $this->rootFolder->id]);

        Livewire::test(IndexManager::class)
            ->call('changeCurrentFolder', $otherFolder->id)
            ->assertSet('currentFolderId', $otherFolder->id)
            ->assertSee($otherFolder->title);
    }

    #[Test]
    public function it_handles_sort_requests_from_child()
    {
        Livewire::test(IndexManager::class)
            ->dispatch('sortRequested', columnName: 'created_at', columnLabel: 'Created Date')
            ->assertSet('orderBy', 'created_at')
            ->assertSet('orderByLabel', 'Created Date')
            ->assertSet('orderAsc', true); // Toggled from initial false
    }

    #[Test]
    public function it_syncs_url_parameters()
    {
        Livewire::withQueryParams(['q' => 'search-term', 'dl' => 2])
            ->test(IndexManager::class)
            ->assertSet('search', 'search-term')
            ->assertSet('displayLevel', 2);
    }

    #[Test]
    public function it_handles_current_folder_change_event()
    {
        $otherFolder = Folder::factory()->create(['title' => 'Event Folder', 'parent_id' => $this->rootFolder->id]);

        Livewire::test(IndexManager::class)
            ->dispatch('currentFolderChangeRequested', newFolderId: $otherFolder->id)
            ->assertSet('currentFolderId', $otherFolder->id)
            ->assertSet('selectedFolderIds', [$otherFolder->id]); // descendantsAndSelf includes the folder itself
    }
}
