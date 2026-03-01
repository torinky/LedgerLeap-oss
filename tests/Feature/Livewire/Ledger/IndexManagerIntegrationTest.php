<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

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
        // テナントコンテキストを確実に設定した上で currentFolderId を初期値として渡す。
        // Livewire::test() はHTTPリクエストをシミュレートするため、テナントミドルウェアが
        // 動作せず mount() 内の Folder::root()->first() が null になる場合がある。
        // currentFolderId を公開プロパティとして直接渡すことでテナントなしでも初期化できる。
        Livewire::test(IndexManager::class)
            ->set('currentFolderId', $this->rootFolder->id)
            ->assertStatus(200)
            ->assertSee('Root');
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

        // IndexManager が検索状態を正しく管理できることを確認
        $component = Livewire::withQueryParams([
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->subFolder->id,
            'f' => [$this->subFolder->id],
        ])->test(IndexManager::class);

        // 初期状態の確認
        $component->assertSet('selectedLedgerDefineIds', [$this->ledgerDefine->id])
            ->assertSet('search', '');

        // 検索語を設定
        $component->set('search', 'Target')
            ->assertSet('search', 'Target');

        // SearchContext が正しく初期化されることを確認
        $keywords = $component->get('keywords');
        $this->assertNotEmpty($keywords, 'Keywords should be initialized from search term');
        $this->assertContains('Target', $keywords, 'Keywords should contain the search term');

        // 検索語がクリアできることを確認
        $component->set('search', '')
            ->assertSet('search', '');

        $keywordsAfterClear = $component->get('keywords');
        $this->assertEmpty($keywordsAfterClear, 'Keywords should be empty when search is cleared');
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
