<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RecordsTableQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LedgerDefine $ledgerDefine;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // The component expects a root folder with id=1 to exist.
        Folder::factory()->create(['id' => 1, 'parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => 1]);
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);
        $this->actingAs($this->user);
        // Add permission for the user to view LedgerDefines
        Permission::findOrCreate('view_ledger_defines');
        $this->user->givePermissionTo('view_ledger_defines');
    }

    #[Test]
    public function it_redirects_to_show_page_on_unique_match()
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['unique-id-123']
        ]);

        Livewire::withQueryParams([
            'q' => 'unique-id-123',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])
            ->test(RecordsTable::class)
            ->assertOk()
            ->assertSee('unique-id-123');
    }

    #[Test]
    public function it_shows_list_on_multiple_matches()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term']
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term']
        ]);

        Livewire::withQueryParams([
            'q' => 'common-term',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])
            ->test(RecordsTable::class)
            ->assertOk()
            ->assertSee('common-term');
    }

    #[Test]
    public function it_shows_list_on_zero_matches()
    {
        Livewire::withQueryParams([
            'q' => 'non-existent-term',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])
            ->test(RecordsTable::class)
            ->assertOk()
            ->assertSee(__('ledger.select_message'));
    }

    #[Test]
    public function it_forces_list_view_on_unique_match_with_mode_list()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['unique-id-for-list']
        ]);

        Livewire::withQueryParams([
            'q' => 'unique-id-for-list',
            'mode' => 'list',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])
            ->test(RecordsTable::class)
            ->assertOk()
            ->assertSee('unique-id-for-list');
    }
}
