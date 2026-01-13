<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateColumnValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        Permission::findOrCreate('create_ledgers', 'web');
        $role = Role::findOrCreate('test-creator-role', 'web');
        $role->givePermissionTo('create_ledgers');
        $this->user->assignRole($role);
        $this->actingAs($this->user);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    protected function assignFolderPermission(Folder $folder): void
    {
        \App\Models\RoleFolderPermission::create([
            'role_id' => Role::findByName('test-creator-role', 'web')->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_updates_validation_state_on_realtime_validation_failure()
    {
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'Required Field',
                'type' => 'text',
                'order' => 1,
                'required' => true,
                'group' => 'Group A',
            ],
            [
                'id' => 2,
                'name' => 'Optional Field',
                'type' => 'text',
                'order' => 2,
                'required' => false,
                'group' => 'Group B',
            ],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $test = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id]);

        $test->set('content.1', 'Initial')
            ->assertHasNoErrors();

        $test->set('content.1', '')
            ->assertHasErrors(['content.1' => 'required'])
            ->assertSet('validationErrors', function ($errors) {
                return isset($errors['content.1']);
            })
            ->assertSet('errorsByField', function ($errors) {
                return isset($errors['content.1']) && $errors['content.1'] === true;
            })
            ->assertSet('errorsByGroup', function ($errors) {
                return isset($errors['Group A']) && $errors['Group A'] === 1;
            })
            ->set('content.1', 'Filled')
            ->assertHasNoErrors()
            ->assertSet('validationErrors', function ($errors) {
                return empty($errors);
            })
            ->assertSet('errorsByField', function ($errors) {
                return empty($errors);
            })
            ->assertSet('errorsByGroup', function ($errors) {
                return empty($errors);
            });
    }

    #[Test]
    public function it_updates_validation_state_on_save_failure()
    {
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'Required Field',
                'type' => 'text',
                'order' => 1,
                'required' => true,
                'group' => 'Group A',
            ],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->call('store')
            ->assertHasErrors(['content.1' => 'required'])
            ->assertSet('validationErrors', function ($errors) {
                return isset($errors['content.1']);
            })
            ->assertSet('errorsByField', function ($errors) {
                return isset($errors['content.1']) && $errors['content.1'] === true;
            })
            ->assertSet('errorsByGroup', function ($errors) {
                return isset($errors['Group A']) && $errors['Group A'] === 1;
            });
    }
}
