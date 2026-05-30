<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LedgerCreateControllerPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントとドメインを作成し、テナンシーを初期化（CI で複数テストが同ドメインを作らないようユニーク化）
        $this->tenant = Tenant::create(['id' => 'prefill-'.uniqid('', true)]);
        $this->tenant->domains()->firstOrCreate(['domain' => 'ledger-prefill-test.localhost']);
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

        // Spatieの権限キャッシュをクリア
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // フォルダと台帳定義を作成
        $this->folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // フォルダへの書き込み権限を付与
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $this->getTestColumnDefines(),
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    protected function getTestColumnDefines(): array
    {
        return [
            [
                'id' => 0,
                'name' => 'Text Column',
                'type' => 'text',
                'order' => 0,
                'required' => false,
                'unique' => false,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 1,
                'name' => 'Number Column',
                'type' => 'number',
                'order' => 1,
                'required' => false,
                'unique' => false,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 2,
                'name' => 'Select Column',
                'type' => 'select',
                'order' => 2,
                'required' => false,
                'unique' => false,
                'options' => ['選択肢1', '選択肢2', '選択肢3'],
                'group' => null,
                'file' => null,
            ],
        ];
    }

    #[Test]
    public function it_accepts_prefill_parameters_in_query_string()
    {
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                0 => 'テスト値',
                1 => '123',
            ],
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('prefillParams');

        $prefillParams = $response->viewData('prefillParams');
        $this->assertEquals('テスト値', $prefillParams[0]);
        $this->assertEquals('123', $prefillParams[1]);
    }

    #[Test]
    public function it_sanitizes_prefill_parameters()
    {
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                0 => '<script>alert("XSS")</script>テスト値',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // HTMLタグが除去されていることを確認
        $this->assertStringNotContainsString('<script>', $prefillParams[0]);
        $this->assertStringNotContainsString('</script>', $prefillParams[0]);
        $this->assertStringContainsString('テスト値', $prefillParams[0]);
    }

    #[Test]
    public function it_rejects_non_existent_column_ids()
    {
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                999 => 'Invalid Column',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 存在しないカラムIDは除外される
        $this->assertArrayNotHasKey(999, $prefillParams);
    }

    #[Test]
    public function it_rejects_auto_number_columns()
    {
        // auto_numberカラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 3,
            'name' => 'Auto Number',
            'type' => 'auto_number',
            'order' => 3,
            'required' => false,
            'unique' => true,
            'options' => [],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                3 => 'AUTO-001',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // auto_numberカラムは除外される
        $this->assertArrayNotHasKey(3, $prefillParams);
    }

    #[Test]
    public function it_rejects_files_columns()
    {
        // filesカラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 3,
            'name' => 'Files',
            'type' => 'files',
            'order' => 3,
            'required' => false,
            'unique' => false,
            'options' => [],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                3 => 'file.pdf',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // filesカラムは除外される
        $this->assertArrayNotHasKey(3, $prefillParams);
    }

    #[Test]
    public function it_validates_select_options()
    {
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                2 => '無効な選択肢',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 選択肢に存在しない値は除外される
        $this->assertArrayNotHasKey(2, $prefillParams);
    }

    #[Test]
    public function it_accepts_valid_select_options()
    {
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                2 => '選択肢2',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        $this->assertEquals('選択肢2', $prefillParams[2]);
    }

    #[Test]
    public function it_limits_string_length()
    {
        $longString = str_repeat('あ', 6000); // 5000文字制限を超える

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                0 => $longString,
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 5000文字に制限されている
        $this->assertEquals(5000, mb_strlen($prefillParams[0]));
    }

    #[Test]
    public function it_accepts_multiline_textarea_prefill_parameters()
    {
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 6,
            'name' => 'Textarea Column',
            'type' => 'textarea',
            'order' => 6,
            'required' => false,
            'unique' => false,
            'options' => [
                'placeholder' => '複数行で入力',
                'hint' => '複数行テキスト',
            ],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $textareaValue = "1行目\n2行目";

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                6 => $textareaValue,
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        $this->assertSame($textareaValue, $prefillParams[6]);
    }

    #[Test]
    public function it_sanitizes_textarea_prefill_parameters_while_preserving_newlines()
    {
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 6,
            'name' => 'Textarea Column',
            'type' => 'textarea',
            'order' => 6,
            'required' => false,
            'unique' => false,
            'options' => [],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                6 => "<b>1行目</b>\n<script>alert('xss')</script>2行目",
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        $this->assertStringNotContainsString('<b>', $prefillParams[6]);
        $this->assertStringNotContainsString('</script>', $prefillParams[6]);
        $this->assertStringContainsString("1行目\n", $prefillParams[6]);
        $this->assertStringContainsString('2行目', $prefillParams[6]);
    }

    #[Test]
    public function it_handles_user_name_columns_correctly()
    {
        // user_nameカラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 3,
            'name' => 'User Name',
            'type' => 'user_name',
            'order' => 3,
            'required' => false,
            'unique' => false,
            'options' => [
                'overwrite_on_edit' => false,
                'include_organization' => true,
            ],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        // user_nameカラムの初期値は上書き可能
        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                3 => '手動入力ユーザー名',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // user_nameカラムは事前入力可能
        $this->assertEquals('手動入力ユーザー名', $prefillParams[3]);
    }

    #[Test]
    public function it_handles_date_columns_correctly()
    {
        // YMDカラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 4,
            'name' => 'Date Column',
            'type' => 'YMD',
            'order' => 4,
            'required' => false,
            'unique' => false,
            'options' => [
                'default_offset' => '0d',
            ],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                4 => '2025-12-25',
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 日付カラムも事前入力可能
        $this->assertEquals('2025-12-25', $prefillParams[4]);
    }

    #[Test]
    public function it_handles_array_prefill_parameters()
    {
        // chkカラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 5,
            'name' => 'Checkbox Column',
            'type' => 'chk',
            'order' => 5,
            'required' => false,
            'unique' => false,
            'options' => ['Option1', 'Option2', 'Option3'],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                5 => ['Option1', 'Option3'],
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 配列形式の事前入力も正しく処理される
        $this->assertIsArray($prefillParams[5]);
        $this->assertSame(['Option1' => true, 'Option3' => true], $prefillParams[5]);
    }

    #[Test]
    public function it_accepts_checkbox_prefill_boolean_map_parameters()
    {
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 5,
            'name' => 'Checkbox Column',
            'type' => 'chk',
            'order' => 5,
            'required' => false,
            'unique' => false,
            'options' => ['Option1', 'Option2', 'Option3'],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);

        $response = $this->get(route('ledger.create', [
            'tenant' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefill' => [
                5 => ['Option1' => '1', 'Option2' => '0', 'Option3' => '1'],
            ],
        ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        $this->assertSame(['Option1' => true, 'Option3' => true], $prefillParams[5]);
    }
}
