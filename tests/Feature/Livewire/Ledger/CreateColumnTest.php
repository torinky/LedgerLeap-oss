<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase; // ColumnDefine をインポート

class CreateColumnTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントとドメインを作成し、テナンシーを初期化
        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

        // ユーザーを作成
        $this->user = User::factory()->create();

        // 台帳作成に必要な権限を作成し、ユーザーに付与
        Permission::findOrCreate('create_ledgers', 'web');
        $role = Role::findOrCreate('test-creator-role', 'web');
        $role->givePermissionTo('create_ledgers');
        $this->user->assignRole($role);

        // ユーザーを認証
        $this->actingAs($this->user);

        // ★ Spatieの権限キャッシュをクリア
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ★ tearDownメソッドを追加
    protected function tearDown(): void
    {
        // テナントコンテキストを終了
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
    public function it_creates_ledger_with_correct_tenant_id()
    {
        // 1. テストデータの準備
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $this->assignFolderPermission($folder);

        // ColumnDefineオブジェクトを作成
        $columnDefineArray = [
            'id' => 1,
            'name' => 'Test Column',
            'type' => 'text',
            'order' => 1,
            'required' => true,
            'unique' => false,
            'options' => [],
            'group' => 'Group 1',
            'file' => null,
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => [$columnDefineArray], // 連想配列の配列を渡す
        ]);

        // 2. Livewireコンポーネントのテスト
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'Test Value'])
            ->set('tenantId', $this->tenant->id)
            ->call('saveDirectly')
            ->assertHasNoErrors();

        // 3. アサーション
        $this->assertDatabaseHas('ledgers', [
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // contentの内容も検証
        $ledger = \App\Models\Ledger::first();
        $this->assertNotNull($ledger);
        // normalizeByColumnDefine は 0..maxId で欠番を埋めるため、実際の保存時は元のカラムIDがそのまま
        // 反映される（このテストではカラムID=1）。したがって content[1] に値が入る。
        $this->assertEquals('Test Value', $ledger->content[1]);
    }

    #[Test]
    public function it_generates_prefill_link_correctly()
    {
        // テストデータの準備
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => true, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'Number Column', 'type' => 'number', 'order' => 2, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'テスト値', 2 => '123'])
            ->call('generatePrefillLink')
            ->assertSet('showPrefillModal', true);

        // 生成されたURLを検証
        $url = $component->get('generatedPrefillURL');
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringContainsString('prefill%5B2%5D=', $url);
    }

    #[Test]
    public function it_excludes_empty_values_from_prefill_link()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'Number Column', 'type' => 'number', 'order' => 2, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'テスト値', 2 => ''])
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // 空の値は含まれない
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringNotContainsString('prefill%5B2%5D=', $url);
    }

    #[Test]
    public function it_excludes_auto_number_from_prefill_link()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'Auto Number', 'type' => 'auto_number', 'order' => 2, 'required' => false, 'unique' => true, 'options' => [], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'テスト値', 2 => 'AUTO-001'])
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // auto_numberは含まれない
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringNotContainsString('prefill%5B2%5D=', $url);
        $this->assertStringNotContainsString('AUTO-001', $url);
    }

    #[Test]
    public function it_excludes_files_from_prefill_link()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'Files', 'type' => 'files', 'order' => 2, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'テスト値', 2 => ['file1.pdf' => 'file1.pdf']])
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // filesは含まれない
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringNotContainsString('prefill%5B2%5D=', $url);
        $this->assertStringNotContainsString('file1.pdf', $url);
    }

    #[Test]
    public function it_excludes_unchanged_auto_generated_values_from_prefill_link()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'User Name', 'type' => 'user_name', 'order' => 2, 'required' => false, 'unique' => false, 'options' => ['overwrite_on_edit' => false, 'include_organization' => false], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        // 初期値と同じ値の場合は含まれない
        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id]);

        $initialUserName = $component->get('content')[2];

        $component->set('content', [1 => 'テスト値', 2 => $initialUserName])
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // 初期値と同じuser_nameは含まれない
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringNotContainsString('prefill%5B2%5D=', $url);
    }

    #[Test]
    public function it_includes_changed_auto_generated_values_in_prefill_link()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'User Name', 'type' => 'user_name', 'order' => 2, 'required' => false, 'unique' => false, 'options' => ['overwrite_on_edit' => false, 'include_organization' => false], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        // 初期値と異なる値の場合は含まれる
        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'テスト値', 2 => '変更されたユーザー名'])
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // 変更されたuser_nameは含まれる
        $this->assertStringContainsString('prefill%5B1%5D=', $url);
        $this->assertStringContainsString('prefill%5B2%5D=', $url);
    }

    #[Test]
    public function it_applies_prefill_params_on_mount()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            ['id' => 1, 'name' => 'Text Column', 'type' => 'text', 'order' => 1, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
            ['id' => 2, 'name' => 'Number Column', 'type' => 'number', 'order' => 2, 'required' => false, 'unique' => false, 'options' => [], 'group' => null, 'file' => null],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        // prefillParamsを渡してマウント
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefillParams' => [
                1 => '事前入力テキスト',
                2 => '999',
            ],
        ]);

        // contentに正しく設定されているか確認
        $this->assertEquals('事前入力テキスト', $component->get('content')[1]);
        $this->assertEquals('999', $component->get('content')[2]);
    }

    #[Test]
    public function it_initializes_user_name_column_with_current_user()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'User Name',
                'type' => 'user_name',
                'order' => 1,
                'required' => false,
                'unique' => false,
                'options' => [
                    'overwrite_on_edit' => false,
                    'include_organization' => false,
                ],
                'group' => null,
                'file' => null,
            ],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id]);

        // user_nameカラムに現在のユーザー名が設定されているか確認
        $this->assertEquals($this->user->name, $component->get('content')[1]);
    }

    public function it_initializes_user_name_column_with_organization(): void
    {
        // ユーザーに組織を設定 (primaryOrganizationリレーションを使用)
        $organization = \App\Models\Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->user->update(['primary_organization_id' => $organization->id]);
        $this->user->refresh();

        // 認証ユーザーを再設定
        $this->actingAs($this->user);

        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'User Name',
                'type' => 'user_name',
                'order' => 1,
                'required' => false,
                'unique' => false,
                'options' => [
                    'name_format' => 'full_name',
                    'org_prefix' => 'primary',
                    'edit_mode' => 'overwrite',
                ],
                'group' => null,
                'file' => null,
            ],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        $component = Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id]);

        // user_nameカラムに組織付きのユーザー名が設定されているか確認
        $expectedName = $organization->name.' '.$this->user->name;
        $this->assertEquals($expectedName, $component->get('content')[1]);
    }

    #[Test]
    public function it_allows_overwriting_user_name_with_prefill()
    {
        $folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->assignFolderPermission($folder);

        $columnDefineArray = [
            [
                'id' => 1,
                'name' => 'User Name',
                'type' => 'user_name',
                'order' => 1,
                'required' => false,
                'unique' => false,
                'options' => [
                    'overwrite_on_edit' => false,
                    'include_organization' => false,
                ],
                'group' => null,
                'file' => null,
            ],
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $columnDefineArray,
        ]);

        // prefillParamsでuser_nameを上書き
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefillParams' => [
                1 => '別のユーザー名',
            ],
        ]);

        // prefillパラメータで上書きされたuser_nameが設定されているか確認
        $this->assertEquals('別のユーザー名', $component->get('content')[1]);
    }
}
