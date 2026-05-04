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

class RecordsTableLedgerDefineSortTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = false; // RefreshDatabaseWithTenant で管理するため false に戻す

    private User $user;

    private Folder $folder;

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

        $this->user = User::factory()->create([
            'email' => 'test.'.\Illuminate\Support\Str::random(10).'@example.com',
        ]);

        $rootFolder = Folder::factory()->create(['parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => $rootFolder->id]);

        $this->actingAs($this->user);

        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        $this->user->givePermissionTo('view_ledger_defines');
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        $this->user->givePermissionTo('ledgerView');

        // RecordsTable は #[Lazy] のため、テスト時は実コンテンツをレンダリングする
        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[Test]
    public function it_sorts_ledger_defines_by_score_when_searching()
    {
        // 3つの台帳定義を作成
        $defineA = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define A',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineB = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define B',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineC = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define C',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        // 各台帳定義に異なる平均スコアのレコードを作成
        // Define A: 平均 20点
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => [0 => 'Test A1'],
            'composite_score' => 20.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => [0 => 'Test A2'],
            'composite_score' => 20.0,
        ]);

        // Define B: 平均 40点（最高）
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => [0 => 'Test B1'],
            'composite_score' => 40.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => [0 => 'Test B2'],
            'composite_score' => 40.0,
        ]);

        // Define C: 平均 30点
        Ledger::factory()->create([
            'ledger_define_id' => $defineC->id,
            'content' => [0 => 'Test C1'],
            'composite_score' => 30.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineC->id,
            'content' => [0 => 'Test C2'],
            'composite_score' => 30.0,
        ]);

        // Mroonga インデックス更新待ち
        sleep(1);

        // 検索時は平均スコア順（B > C > A）で表示されるはず
        // withQueryParams() を使用して子コンポーネントが確実にマウントされるようにする
        $component = Livewire::withQueryParams([
            'q' => 'Test',  // 検索語
            'f' => [$this->folder->id],
            'l' => [$defineA->id, $defineB->id, $defineC->id],
            'cf' => $this->folder->id,
        ])->test(IndexManager::class);

        $component->assertOk();

        // wire:key を使った台帳定義カードの順序検証
        // RecordsTable では各台帳定義カードに wire:key="ledger_record_{{ $ledgerDefineId }}" が付与されている
        $html = $component->html();

        // 各台帳定義のカードマーカーの位置を取得
        $posB = strpos($html, 'wire:key="ledger_record_'.$defineB->id.'"');
        $posC = strpos($html, 'wire:key="ledger_record_'.$defineC->id.'"');
        $posA = strpos($html, 'wire:key="ledger_record_'.$defineA->id.'"');

        $this->assertNotFalse($posB, 'Define B card should be found in HTML');
        $this->assertNotFalse($posC, 'Define C card should be found in HTML');
        $this->assertNotFalse($posA, 'Define A card should be found in HTML');

        // スコア順（B > C > A）で表示されていることを確認
        $this->assertLessThan($posC, $posB, 'Define B (avg score 40) should appear before Define C (avg score 30)');
        $this->assertLessThan($posA, $posC, 'Define C (avg score 30) should appear before Define A (avg score 20)');
    }

    #[Test]
    public function it_sorts_ledger_defines_by_custom_order_attribute()
    {
        // 3つの台帳定義を作成
        $defineA = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define A',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineB = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define B',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineC = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define C',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        // 各台帳定義に異なるスコアのレコードを作成（検索しないのでスコアでのソートは行われないはず）
        // Define A: スコア 10
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => [0 => 'Test A'],
            'composite_score' => 10.0,
        ]);

        // Define B: スコア 30
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => [0 => 'Test B'],
            'composite_score' => 30.0,
        ]);

        // Define C: スコア 20
        Ledger::factory()->create([
            'ledger_define_id' => $defineC->id,
            'content' => [0 => 'Test C'],
            'composite_score' => 20.0,
        ]);

        // folderId を明示的に渡して、そのフォルダーを表示している状態にする
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->assertOk();

        // 期待されるレコード（またはタイトル）が表示順通りにHTML内に存在するかを確認する。
        // RecordsTable は台帳定義（LedgerDefine）ごとにカードを表示し、その中にレコードが並ぶ。
        // search が空の場合は、選択された台帳定義 ID（作成順）で並ぶはず。
        $component->assertSeeInOrder([
            'Define A',
            'Define B',
            'Define C',
        ]);
    }

    #[Test]
    public function it_shows_score_order_indicator_when_searching()
    {
        $define = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Test Define',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => [0 => 'Test'],
            'composite_score' => 30.0,
        ]);

        // Mroonga インデックス更新待ち
        sleep(1);

        // 検索時は「スコア順」インジケーターが表示される
        $component = Livewire::withQueryParams([
            'q' => 'Test',
            'f' => [$this->folder->id],
            'l' => [$define->id],
            'cf' => $this->folder->id,
        ])->test(IndexManager::class); // IndexManager を対象に

        $component->assertOk()
            ->assertSee('スコア順');
    }

    #[Test]
    public function it_does_not_show_score_order_indicator_when_not_searching()
    {
        $define = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Test Define',
            'column_define' => [
                ['id' => 0, 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => [0 => 'Test'],
            'composite_score' => 30.0,
        ]);

        // Mroonga インデックス更新待ち
        sleep(1);

        // 検索なしの場合は「スコア順」インジケーターが表示されない
        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$define->id],
            'cf' => $this->folder->id,
        ])->test(IndexManager::class); // IndexManager を対象に

        $component->assertOk()
            ->assertDontSee('スコア順');
    }
}
