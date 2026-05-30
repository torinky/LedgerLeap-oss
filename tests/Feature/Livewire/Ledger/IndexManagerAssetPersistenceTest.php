<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class IndexManagerAssetPersistenceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = false;

    protected User $user;

    protected Folder $folder;

    protected Folder $subFolder;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テナントを明示的に初期化
        if ($this->getTenant()) {
            tenancy()->initialize($this->getTenant());
        }

        $this->user = User::factory()->create();

        // フォルダ階層の作成
        $this->folder = Folder::factory()->create(['title' => 'Root Folder']);
        $this->subFolder = Folder::factory()->create([
            'title' => 'Sub Folder',
            'parent_id' => $this->folder->id,
        ]);

        // 台帳定義の作成（サブフォルダ内に1つ）
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'Test Ledger',
            'folder_id' => $this->subFolder->id,
        ]);

        // 別の台帳定義（ルートフォルダ内に1つ）
        LedgerDefine::factory()->create([
            'title' => 'Root Ledger',
            'folder_id' => $this->folder->id,
        ]);

        // RecordsTable は #[Lazy] のため、テスト時は実コンテンツをレンダリングする
        Livewire::withoutLazyLoading();
    }

    #[Test]
    public function it_maintains_counts_after_livewire_action()
    {
        // ルートフォルダを表示した状態で開始
        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, ['currentFolderId' => $this->folder->id]);

        // 初期状態で counts が存在することを確認
        $component->assertViewHas('folderRecords', function ($folders) {
            $folder = $folders->where('id', $this->subFolder->id)->first();

            return $folder && isset($folder->ledger_defines_count) && $folder->ledger_defines_count === 1;
        });

        $component->assertViewHas('ledgerDefineRecords', function ($defines) {
            $define = $defines->where('id', '!=', $this->ledgerDefine->id)->first();

            // ルートフォルダには1つの台帳定義があるはず
            return $define && isset($define->ledgers_count);
        });

        // Livewire アクションを実行（例: 検索や表示レベル変更など、何でも良い）
        $component->set('search', 'test')
            ->call('initSearchContext');

        // アクション後も counts が維持されているか確認
        // Computed プロパティの場合、再レンダリング時に再取得されるため、常に含まれるはず
        $component->assertViewHas('folderRecords', function ($folders) {
            $folder = $folders->where('id', $this->subFolder->id)->first();

            return $folder && isset($folder->ledger_defines_count) && $folder->ledger_defines_count === 1;
        });
    }

    #[Test]
    public function it_updates_assets_when_folder_is_changed()
    {
        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, ['currentFolderId' => $this->folder->id]);

        // フォルダをサブフォルダに切り替え
        $component->call('changeCurrentFolder', $this->subFolder->id);

        // currentFolderId が更新されていること
        $this->assertEquals($this->subFolder->id, $component->get('currentFolderId'));

        // 新しいフォルダのアセットが取得されていること
        $component->assertViewHas('ledgerDefineRecords', function ($defines) {
            return $defines->count() === 1 && $defines->first()->id === $this->ledgerDefine->id;
        });

        // カウントも含まれていること
        $component->assertViewHas('ledgerDefineRecords', function ($defines) {
            return isset($defines->first()->ledgers_count);
        });
    }

    #[Test]
    public function it_counts_ledger_defines_recursively()
    {
        // 階層の追加: Root -> SubFolder -> ChildFolder -> ChildLedger
        $childFolder = Folder::factory()->create([
            'title' => 'Child Folder',
            'parent_id' => $this->subFolder->id,
        ]);

        LedgerDefine::factory()->create([
            'title' => 'Child Ledger',
            'folder_id' => $childFolder->id,
        ]);

        // $this->folder (Root) を表示
        // $this->folder の子である $this->subFolder のカウントを確認
        // 直下の $this->ledgerDefine (1) + 孫の Child Ledger (1) = 2
        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, ['currentFolderId' => $this->folder->id]);

        $component->assertViewHas('folderRecords', function ($folders) {
            $folder = $folders->where('id', $this->subFolder->id)->first();

            return $folder && (int) $folder->ledger_defines_count === 2;
        });
    }
}
