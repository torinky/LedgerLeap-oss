<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
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

    private User $user;

    private Folder $folder;

    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid()]);
        tenancy()->initialize($this->tenant);

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
    }

    protected function getTablesToTruncate(): array
    {
        return [
            'folders',
            'ledgers',
            'ledger_defines',
            'personal_access_tokens',
        ];
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
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
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineB = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define B',
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineC = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define C',
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        // 各台帳定義に異なる平均スコアのレコードを作成
        // Define A: 平均 20点
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => ['title' => 'Test A1'],
            'composite_score' => 20.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => ['title' => 'Test A2'],
            'composite_score' => 20.0,
        ]);

        // Define B: 平均 40点（最高）
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => ['title' => 'Test B1'],
            'composite_score' => 40.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => ['title' => 'Test B2'],
            'composite_score' => 40.0,
        ]);

        // Define C: 平均 30点
        Ledger::factory()->create([
            'ledger_define_id' => $defineC->id,
            'content' => ['title' => 'Test C1'],
            'composite_score' => 30.0,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $defineC->id,
            'content' => ['title' => 'Test C2'],
            'composite_score' => 30.0,
        ]);

        // 検索時は平均スコア順（B > C > A）で表示されるはず
        $component = Livewire::withQueryParams([
            'q' => 'Test',
            'f' => [$this->folder->id],
            'l' => [$defineA->id, $defineB->id, $defineC->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk();

        // ビューで台帳定義がスコア順に表示されることを確認
        // Define B (40.0) が最初に表示されるべき
        $component->assertSeeInOrder(['Define B', 'Define C', 'Define A']);
    }

    #[Test]
    public function it_sorts_ledger_defines_by_id_when_not_searching()
    {
        // 3つの台帳定義を作成
        $defineA = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define A',
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        $defineB = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Define B',
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        // 各台帳定義にレコードを作成（スコアは逆順）
        // Define A: 高スコア
        Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'content' => ['title' => 'Test A'],
            'composite_score' => 50.0,
        ]);

        // Define B: 低スコア
        Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'content' => ['title' => 'Test B'],
            'composite_score' => 20.0,
        ]);

        // 検索なしの場合はID順（A > B）で表示されるはず（スコアに関係なく）
        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$defineA->id, $defineB->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk();

        // ビューで台帳定義がID順に表示されることを確認
        $component->assertSeeInOrder(['Define A', 'Define B']);
    }

    #[Test]
    public function it_shows_score_order_indicator_when_searching()
    {
        $define = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'title' => 'Test Define',
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => ['title' => 'Test'],
            'composite_score' => 30.0,
        ]);

        // 検索時は「スコア順」インジケーターが表示される
        $component = Livewire::withQueryParams([
            'q' => 'Test',
            'f' => [$this->folder->id],
            'l' => [$define->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

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
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => ['title' => 'Test'],
            'composite_score' => 30.0,
        ]);

        // 検索なしの場合は「スコア順」インジケーターが表示されない
        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$define->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk()
            ->assertDontSee('スコア順');
    }
}
