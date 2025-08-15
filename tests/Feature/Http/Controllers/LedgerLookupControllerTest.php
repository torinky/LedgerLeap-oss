<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LedgerLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // 必要なパーミッションを付与
        Permission::findOrCreate('view_ledger_defines');
        Permission::findOrCreate('view_ledgers');
        $this->user->givePermissionTo('view_ledger_defines');
        $this->user->givePermissionTo('view_ledgers');

        $this->actingAs($this->user);

        // テスト用のLedgerDefineを作成
        // ColumnDefineを配列として定義し、LedgerDefineに渡す
        $columnDefineData = [
            'id' => 1, // ユニークなID
            'name' => 'description',
            'type' => 'text',
            'order' => 1, // order は 1 から始める
            'useOptions' => false,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sortBy' => false,
            'hint' => 'Hint',
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ];
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [$columnDefineData]
        ]);
    }

    #[Test]
    public function it_redirects_to_show_page_on_unique_match()
    {
        // setUpで作成したledgerDefineのcolumn_defineのIDを使用
        $columnId = $this->ledgerDefine->column_define->first()->id;
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'unique-id-123'] // ColumnDefineのIDをキーとして使用
        ]);

        $this->get(route('ledger.lookup', ['query' => 'unique-id-123']))
            ->assertRedirect(route('ledger.show', ['ledgerId' => $ledger->id, 'highlight' => 'unique-id-123']));
    }

    #[Test]
    public function it_redirects_to_index_on_multiple_matches()
    {
        $columnId = $this->ledgerDefine->column_define->first()->id;
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'common-term']
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'common-term']
        ]);

        $this->get(route('ledger.lookup', ['query' => 'common-term']))
            ->assertRedirect(route('ledger.index', ['q' => 'common-term', 'highlight' => 'common-term', 'l' => [], 'f' => []]));
    }

    #[Test]
    public function it_redirects_to_index_on_zero_matches()
    {
        $this->get(route('ledger.lookup', ['query' => 'non-existent']))
            ->assertRedirect(route('ledger.index', ['q' => 'non-existent', 'highlight' => 'non-existent', 'l' => [], 'f' => []]));
    }

    #[Test]
    public function it_redirects_to_index_when_mode_is_list()
    {
        $columnId = $this->ledgerDefine->column_define->first()->id;
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'unique-id-for-list']
        ]);

        $this->get(route('ledger.lookup', ['query' => 'unique-id-for-list', 'mode' => 'list']))
            ->assertRedirect(route('ledger.index', ['q' => 'unique-id-for-list', 'highlight' => 'unique-id-for-list', 'l' => [], 'f' => []]));
    }
}
