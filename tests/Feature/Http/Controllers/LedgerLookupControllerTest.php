<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Support\Facades\DB; // 追加

class LedgerLookupControllerTest extends TestCase
{

    private User $adminUser;

    private LedgerDefine $ledgerDefine;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('ledgers')->truncate(); // 追加

        

        // Permissions
        $permissions = [
            'view ledgers',
            'create ledgers',
            'edit ledgers',
            'delete ledgers',
            'import ledgers',
            'export ledgers',
            'view ledgerDefines',
            'create ledgerDefines',
            'edit ledgerDefines',
            'delete ledgerDefines',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Roles
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo($permissions);

        // Users
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Acting as admin user for all tests in this class
        $this->actingAs($this->adminUser);

        // Grant folder permission to the admin role
        $this->tenant->run(function () use ($adminRole) {
            $folder = Folder::create(['title' => '/', 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            \App\Models\RoleFolderPermission::create([
                'role_id' => $adminRole->id,
                'folder_id' => $folder->id,
                'permission' => \App\Enums\FolderPermissionType::ADMIN,
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]);
        });

        // Ledger Definition
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'Test Ledger',
            'column_define' => [
                new ColumnDefine([
                    'id' => 1,
                    'name' => 'unique_text',
                    'label' => 'Unique Text',
                    'type' => 'text',
                    'unique' => true,
                    'order' => 1,
                ]),
            ],
        ]);
    }

    #[Test]
    public function it_redirects_to_show_page_on_unique_match()
    {
        $columnId = $this->ledgerDefine->column_define->first()->id;
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'unique-id-123'], // ColumnDefineのIDをキーとして使用
        ]);

                    $this->get('/' . $this->tenant->id . '/l/unique-id-123')
            ->assertRedirect('/' . $this->tenant->id . '/ledger/' . $ledger->id . '?highlight=unique-id-123');
    }

    #[Test]
    public function it_redirects_to_index_on_multiple_matches()
    {
        $columnId = $this->ledgerDefine->column_define->first()->id;
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'common-term'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'common-term'],
        ]);

        $this->get('/' . $this->tenant->id . '/l/common-term')
            ->assertRedirect('/' . $this->tenant->id . '/ledger?q=common-term&highlight=common-term&l=&f=');
    }

    #[Test]
    public function it_redirects_to_index_on_zero_matches()
    {
        $this->get('/' . $this->tenant->id . '/l/non-existent')
            ->assertRedirect('/' . $this->tenant->id . '/ledger?q=non-existent&highlight=non-existent&l=&f=');
    }

    #[Test]
    public function it_redirects_to_index_when_mode_is_list()
    {
        $columnId = $this->ledgerDefine->column_define->first()->id;
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [$columnId => 'unique-id-for-list'],
        ]);

        $this->get('/' . $this->tenant->id . '/l/unique-id-for-list?mode=list')
            ->assertRedirect('/' . $this->tenant->id . '/ledger?q=unique-id-for-list&highlight=unique-id-for-list&l=&f=');
    }
}
