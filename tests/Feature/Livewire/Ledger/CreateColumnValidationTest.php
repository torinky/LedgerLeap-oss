<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\CreateColumn;
use App\Models\Folder;
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

class CreateColumnValidationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create();
        Permission::findOrCreate('create_ledgers', 'web');
        $role = Role::findOrCreate('test-creator-role', 'web');
        $role->givePermissionTo('create_ledgers');
        $this->user->assignRole($role);
        $this->actingAs($this->user);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function assignFolderPermission(Folder $folder): void
    {
        RoleFolderPermission::create([
            'role_id' => Role::findByName('test-creator-role', 'web')->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE,
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

        // 初期状態では全グループが閉じられている（HandlesFormGroups trait の設計による）
        $this->assertTrue($test->get('collapsedStates')['Group A']);

        // 明示的に開いてみる（テストの一環として）
        $test->call('toggleGroup', 'Group A', false);
        $this->assertFalse($test->get('collapsedStates')['Group A']);

        // 手動で再度閉じる
        $test->call('toggleGroup', 'Group A', true);
        $this->assertTrue($test->get('collapsedStates')['Group A']);

        $test->set('content.1', 'Initial')
            ->assertHasNoErrors();

        // エラーを発生させる
        $test->set('content.1', '')
            ->assertHasErrors(['content.1' => 'required']);

        // 自動展開されていることを確認 (Issue #16)
        $this->assertFalse($test->get('collapsedStates')['Group A']);

        $this->assertEquals(1, $test->get('errorsByGroup')['Group A']);
        $this->assertTrue($test->get('errorsByField')['content.1']);
    }

    #[Test]
    public function it_marks_field_as_fixed_when_error_is_corrected()
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

        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $test = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // 1. 最初は正常
        $test->set('content.1', 'Initial')
            ->assertHasNoErrors();

        // 2. 空にしてエラーを発生させる
        try {
            $test->set('content.1', '');
        } catch (ValidationException $e) {
            // Livewire test utilities handles this, but we catch if necessary
        }

        $test->assertHasErrors(['content.1' => 'required']);
        $this->assertArrayNotHasKey('content.1', $test->get('fixedFields'));

        // 3. 修正して「成功マーク」が付くことを確認
        $test->set('content.1', 'Fixed')
            ->assertHasNoErrors('content.1')
            ->assertDispatched('field-fixed', field: 'content.1');

        $this->assertTrue($test->get('fixedFields')['content.1'] ?? false);

        // 4. 再び変更（エラーなし）した場合はマークが消えることを確認
        $test->set('content.1', 'Changed Again')
            ->assertHasNoErrors('content.1');

        $this->assertArrayNotHasKey('content.1', $test->get('fixedFields'));
    }

    #[Test]
    public function it_dispatches_toast_notification_when_errors_are_fixed()
    {
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'Field 1',
                'type' => 'text',
                'order' => 1,
                'required' => true,
            ],
            [
                'id' => 2,
                'name' => 'Field 2',
                'type' => 'text',
                'order' => 2,
                'required' => true,
            ],
            [
                'id' => 3,
                'name' => 'Field 3',
                'type' => 'text',
                'order' => 3,
                'required' => true,
            ],
        ];

        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $test = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // 1. 最初は空なのでエラーを発生させる (3つ)
        $test->set('content.1', '')
            ->set('content.2', '')
            ->set('content.3', '')
            ->assertHasErrors(['content.1', 'content.2', 'content.3']);

        $this->assertEquals(3, $test->get('previousErrorCount'));

        // 2. 1つだけ解消する -> トーストは出ないはず (Issue #25 改良)
        $test->set('content.1', 'Value 1')
            ->assertHasNoErrors('content.1')
            ->assertHasErrors(['content.2', 'content.3'])
            ->assertNotDispatched('mary-toast');

        $this->assertEquals(2, $test->get('previousErrorCount'));

        // 3. 残りの2つを同時に解消する (例えば Save 時に全バリデーションが走るケースを想定して個別にセットした後に状態更新)
        // ここでは set したものから updated フックが走るので、内部的に updateValidationState が呼ばれる。
        // content.2 をセットすると 2->1 になる。この時、他にもエラー(content.3)が残っているのでトーストは出ない。
        $test->set('content.2', 'Value 2')
            ->assertNotDispatched('mary-toast');

        // content.3 をセットすると 1->0 になる。この時は「すべて解消」トーストが出る。
        $test->set('content.3', 'Value 3');

        $test->assertDispatched('mary-toast', function ($name, $data) {
            return $data['type'] === 'success' &&
                   $data['title'] === 'すべてのバリデーションエラーが解消されました！';
        });

        $this->assertEquals(0, $test->get('previousErrorCount'));
    }
}
