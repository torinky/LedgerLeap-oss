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

class RecordsTableCompositeScoreSortTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private LedgerDefine $ledgerDefine;

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

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 'title', 'name' => 'タイトル', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

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
    public function it_sorts_by_composite_score_desc_by_default()
    {
        // 異なるスコアの台帳を作成
        $ledgerHigh = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'High Score'],
            'composite_score' => 80.0,
        ]);

        $ledgerMedium = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Medium Score'],
            'composite_score' => 50.0,
        ]);

        $ledgerLow = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Low Score'],
            'composite_score' => 10.0,
        ]);

        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk()
            ->assertSet('orderBy', 'composite_score')
            ->assertSet('orderAsc', false);
    }

    #[Test]
    public function it_shows_zero_score_records_last()
    {
        // スコアあり・なしの台帳を作成
        $ledgerWithScore = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'With Score'],
            'composite_score' => 30.0,
        ]);

        $ledgerZeroScore = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Zero Score'],
            'composite_score' => 0.0,
        ]);

        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk();

        // "With Score" がビューに表示されることを確認（スコア順）
        $component->assertSeeInOrder(['With Score', 'Zero Score']);
    }

    #[Test]
    public function it_can_toggle_sort_order()
    {
        $ledger1 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Ledger 1'],
            'composite_score' => 60.0,
        ]);

        $ledger2 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Ledger 2'],
            'composite_score' => 40.0,
        ]);

        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        // デフォルトは降順
        $component->assertSet('orderAsc', false);

        // ソートボタンをクリックして昇順に
        $component->call('sort', 'composite_score')
            ->assertSet('orderAsc', true);

        // もう一度クリックして降順に戻す
        $component->call('sort', 'composite_score')
            ->assertSet('orderAsc', false);
    }

    #[Test]
    public function it_displays_score_badges_with_correct_styling()
    {
        // 異なるスコアレンジの台帳を作成
        $ledgerHigh = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Very Important'],
            'composite_score' => 75.0,
        ]);

        $ledgerMedium = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Important'],
            'composite_score' => 45.0,
        ]);

        $ledgerLow = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Normal'],
            'composite_score' => 25.0,
        ]);

        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        $component->assertOk()
            ->assertSee('75.0')
            ->assertSee('45.0')
            ->assertSee('25.0')
            ->assertSeeHtml('badge-success')  // 70+
            ->assertSeeHtml('badge-primary')  // 40-69
            ->assertSeeHtml('badge-info');    // 20-39
    }

    #[Test]
    public function it_maintains_compatibility_with_other_sort_columns()
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['title' => 'Test'],
            'composite_score' => 50.0,
        ]);

        $component = Livewire::withQueryParams([
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])->test(RecordsTable::class);

        // IDでソート
        $component->call('sort', 'id')
            ->assertSet('orderBy', 'id');

        // updated_atでソート
        $component->call('sort', 'updated_at')
            ->assertSet('orderBy', 'updated_at');

        // composite_scoreに戻す
        $component->call('sort', 'composite_score')
            ->assertSet('orderBy', 'composite_score');
    }
}
