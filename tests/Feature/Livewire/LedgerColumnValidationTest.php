<?php

namespace Tests\Feature\Livewire;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\CreateColumn;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerColumnValidationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create();

        $role = Role::firstOrCreate([
            'name' => 'test-validator-role',
            'guard_name' => 'web',
        ]);
        $permission = Permission::firstOrCreate([
            'name' => 'create_ledgers',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $this->user->assignRole($role);

        $this->actingAs($this->user);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function assignFolderPermission(Folder $folder): void
    {
        $role = Role::findByName('test-validator-role', 'web');
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);
    }

    protected function createLedgerDefineWithWritableFolder(array $columnDefine): LedgerDefine
    {
        $folder = Folder::create([
            'title' => 'Validation Folder '.uniqid('', true),
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        return LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefine,
        ]);
    }

    #[Test]
    public function create_column_fails_validation_if_unique_column_is_duplicated()
    {
        // 準備: このテスト専用の「unique」カラムを持つ台帳定義を作成
        $ledgerDefine = $this->createLedgerDefineWithWritableFolder([
            new ColumnDefine([
                'id' => 0, 'name' => 'Non-Unique', 'type' => 'text', 'order' => 1,
                'required' => false,
                'unique' => false,
                'sort_index' => null,
                'hint' => '',
                'file' => [],
                'options' => [],
            ]),
            new ColumnDefine([
                'id' => 1, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 2,
                'required' => false, 'sort_index' => null, 'hint' => '', 'file' => [], 'options' => [],
            ]),
        ]);

        // 準備: 既存データを作成
        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $ledgerDefine->normalizeByColumnDefine($content);
        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => $normalizedContent,
        ]);

        // 実行 & 確認
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.1', 'EXISTING_VALUE')
            ->call('saveDirectly')
            ->assertHasErrors(['content.1' => '指定のUnique Textは既に使用されています。']);
    }

    #[Test]
    public function create_column_passes_validation_if_unique_column_is_not_duplicated()
    {
        $ledgerDefine = $this->createLedgerDefineWithWritableFolder([
            new ColumnDefine([
                'id' => 0, 'name' => 'Non-Unique', 'type' => 'text', 'order' => 1,
                'required' => false,
                'unique' => false,
                'sort_index' => null,
                'hint' => '',
                'file' => [],
                'options' => [],
            ]),
            new ColumnDefine([
                'id' => 1, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 2,
                'required' => false, 'sort_index' => null, 'hint' => '', 'file' => [], 'options' => [],
            ]),
        ]);

        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $ledgerDefine->normalizeByColumnDefine($content);
        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => $normalizedContent,
        ]);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.1', 'NEW_UNIQUE_VALUE')
            ->call('saveDirectly')
            ->assertHasNoErrors('content.1');
    }

    #[Test]
    public function number_column_validation_works_correctly()
    {
        $numberLedgerDefine = $this->createLedgerDefineWithWritableFolder([
            new ColumnDefine([
                'id' => 0,
                'name' => 'Number Input',
                'type' => 'number',
                'order' => 1,
                'options' => [
                    'min' => 10,
                    'max' => 20,
                    'step' => 0.5,
                    'unit' => '℃',
                ],
                'required' => true,
                'unique' => false,
                'sort_index' => null,
                'hint' => '',
                'file' => [],
            ]),
        ]);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.0', 15.5)
            ->call('saveDirectly')
            ->assertHasNoErrors();

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.0', 9.9)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'min']);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.0', 20.1)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'max']);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.0', 15.6)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'multiple_of']);

        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('tenantId', $this->tenant->id)
            ->set('content.0', 'not a number')
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'numeric']);
    }

    #[Test]
    public function create_column_passes_validation_across_tenants()
    {
        // テナント1を作成
        $tenant1 = Tenant::factory()->create();
        // テナント2を作成
        $tenant2 = Tenant::factory()->create();

        // テナント1で台帳定義を作成
        $ledgerDefine1 = $tenant1->run(function () {
            return LedgerDefine::factory()->create([
                'column_define' => [
                    new ColumnDefine([
                        'id' => 0, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 1,
                        'required' => false, 'sort_index' => null, 'hint' => '', 'file' => [], 'options' => [],
                    ]),
                ],
            ]);
        });

        // テナント2で台帳定義を作成 (同じ内容だが別テナント)
        $ledgerDefine2 = $tenant2->run(function () {
            return LedgerDefine::factory()->create([
                'column_define' => [
                    new ColumnDefine([
                        'id' => 0, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 1,
                        'required' => false, 'sort_index' => null, 'hint' => '', 'file' => [], 'options' => [],
                    ]),
                ],
            ]);
        });

        // Assign permission for ledgerDefine2's folder
        RoleFolderPermission::create([
            'role_id' => Role::findByName('test-validator-role', 'web')->id,
            'folder_id' => $ledgerDefine2->folder_id,
            'permission' => FolderPermissionType::WRITE,
            'modifier_id' => auth()->id(),
        ]);

        // テナント1で既存データを作成
        $tenant1->run(function () use ($ledgerDefine1) {
            $content = [0 => 'SHARED_UNIQUE_VALUE'];
            $normalizedContent = $ledgerDefine1->normalizeByColumnDefine($content);
            Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine1->id,
                'content' => $normalizedContent,
            ]);
        });

        // テナント2に切り替えて、テナント1と同じユニーク値で台帳を作成しようとする
        $tenant2->run(function () use ($ledgerDefine2, $tenant2) {
            Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine2->id])
                ->set('tenantId', $tenant2->id)
                ->set('content.0', 'SHARED_UNIQUE_VALUE') // テナント1と同じ値をセット
                ->call('saveDirectly')
                ->assertHasNoErrors('content.0'); // エラーが発生しないことを確認
        });
    }
}
