<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\AutoLink;
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
    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = \App\Models\Tenant::create();
        tenancy()->initialize($this->tenant);
        // Use a unique email for each test to avoid constraint violations
        $this->user = User::factory()->create([
            'email' => 'test.' . \Illuminate\Support\Str::random(10) . '@example.com',
        ]);
        // The component expects a root folder with id=1 to exist.
        Folder::factory()->create(['id' => 1, 'parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => 1]);
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 'text_column', 'name' => 'テキストカラム', 'type' => 'text', 'order' => 1, 'display_level' => 1],
                // Add other column definitions as needed for other tests
            ],
        ]);
        $this->actingAs($this->user);
        // Add permission for the user to view LedgerDefines
        Permission::findOrCreate('view_ledger_defines');
        $this->user->givePermissionTo('view_ledger_defines');
        // Add permission for the user to view Ledgers
        Permission::findOrCreate('ledgerView');
        $this->user->givePermissionTo('ledgerView');
        // Add permission for the user to view AutoLinks (追加)
        Permission::findOrCreate('view_auto_links');
        $this->user->givePermissionTo('view_auto_links');
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

    #[Test]
    public function it_highlights_keywords_in_list_view()
    {
        // テストデータの準備
        $keyword = 'テストキーワード';
        $contentWithKeyword = ['text_column' => 'これは' . $keyword . 'を含むテキストです。'];
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $contentWithKeyword,
        ]);

        // Livewireコンポーネントのテスト
        Livewire::withQueryParams([
            'q' => $keyword,
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])
            ->test(RecordsTable::class)
            ->assertOk()
            ->assertSeeHtml('<mark class="text-error font-bold text-lg">' . $keyword . '</mark>');
    }

    #[Test]
    public function it_displays_auto_links_in_list_view()
    {
        // 自動リンク定義の準備
        AutoLink::factory()->create([
            'label' => 'Test AutoLink',
            'pattern' => '/(SPEC-\\d{3})/',
            'url_template' => '/l/$1',
            'is_enabled' => true,
        ]);

        // 台帳データの準備
        $autoLinkText = 'これはSPEC-007を含むテキストです。';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['text_column' => $autoLinkText],
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::withQueryParams([
            'q' => 'SPEC-007',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id
        ])->test(RecordsTable::class);
        $component->assertOk()
            ->assertSeeHtml('href="/l/SPEC-007"')
            ->assertSee('SPEC-007');
    }
}
