<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Livewire\Traits\HandlesPrefillLinks;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(CreateColumn::class)]
#[CoversClass(HandlesPrefillLinks::class)]
class PrefillParametersTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テナントとドメインを作成し、テナンシーを初期化（CI で複数テストが同ドメインを作らないようユニーク化）
        $this->tenant = $this->getTenant();

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
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // フォルダと台帳定義を作成
        $this->folder = Folder::create([
            'title' => 'Test Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        \App\Models\RoleFolderPermission::create([
            'role_id' => Role::findByName('test-creator-role', 'web')->id,
            'folder_id' => $this->folder->id,
            'permission' => \App\Enums\FolderPermissionType::WRITE,
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
            [
                'id' => 3,
                'name' => 'Checkbox Column',
                'type' => 'chk',
                'order' => 3,
                'required' => false,
                'unique' => false,
                'options' => ['オプションA', 'オプションB', 'オプションC'],
                'group' => null,
                'file' => null,
            ],
        ];
    }

    #[Test]
    public function it_applies_prefill_params_on_mount()
    {
        $prefillParams = [
            0 => 'テスト値',
            1 => '123',
            2 => '選択肢2',
        ];

        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefillParams' => $prefillParams,
        ])
            ->assertSet('content.0', 'テスト値')
            ->assertSet('content.1', '123')
            ->assertSet('content.2', '選択肢2');
    }

    #[Test]
    public function it_applies_array_prefill_params()
    {
        $prefillParams = [
            3 => ['オプションA' => true, 'オプションC' => true],
        ];

        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefillParams' => $prefillParams,
        ])
            ->assertSet('content.3', ['オプションA' => true, 'オプションC' => true]);
    }

    #[Test]
    public function it_normalizes_legacy_checkbox_prefill_params_on_mount()
    {
        $prefillParams = [
            3 => ['オプションA', 'オプションC'],
        ];

        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'prefillParams' => $prefillParams,
        ])
            ->assertSet('content.3', ['オプションA' => true, 'オプションC' => true]);
    }

    #[Test]
    public function it_generates_prefill_link_correctly()
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('content', [
                0 => 'テスト値',
                1 => '456',
                2 => '選択肢1',
                3 => ['オプションA' => true, 'オプションB' => false, 'オプションC' => true],
            ])
            ->set('tenantId', $this->tenant->id)
            ->call('generatePrefillLink');

        $component->assertSet('showPrefillModal', true);

        $url = $component->get('generatedPrefillURL');

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        $this->assertStringContainsString('prefill', $url);
        $this->assertStringContainsString($this->tenant->id, $url);
        $this->assertStringContainsString((string) $this->ledgerDefine->id, $url);
        $this->assertSame('1', $query['prefill'][3]['オプションA']);
        $this->assertSame('1', $query['prefill'][3]['オプションC']);
        $this->assertArrayNotHasKey('オプションB', $query['prefill'][3]);
    }

    #[Test]
    public function it_excludes_empty_values_from_prefill_url()
    {
        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('content', [
                0 => 'テスト値',
                1 => '', // 空の値
                2 => '選択肢1',
            ])
            ->set('tenantId', $this->tenant->id)
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // カラム0と2は含まれるが、カラム1は含まれない
        $this->assertStringContainsString('prefill%5B0%5D', $url);
        $this->assertStringNotContainsString('prefill%5B1%5D', $url);
        $this->assertStringContainsString('prefill%5B2%5D', $url);
    }

    #[Test]
    public function it_excludes_auto_generated_columns_with_initial_values()
    {
        // user_name カラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 4,
            'name' => 'User Name',
            'type' => 'user_name',
            'order' => 4,
            'required' => false,
            'unique' => false,
            'options' => [
                'format' => 'full_name',
                'organization_prefix' => 'none',
                'edit_mode' => 'overwrite',
            ],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);
        $this->ledgerDefine->refresh();

        // ユーザー名の初期値を取得
        $expectedUserName = $this->user->name;

        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('content', [
                0 => 'テスト値',
                4 => $expectedUserName, // 初期値と同じ
            ])
            ->set('tenantId', $this->tenant->id)
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // カラム0は含まれるが、カラム4（user_name）は初期値と同じなので含まれない
        $this->assertStringContainsString('prefill%5B0%5D', $url);
        $this->assertStringNotContainsString('prefill%5B4%5D', $url);
    }

    #[Test]
    public function it_includes_modified_auto_generated_columns()
    {
        // user_name カラムを追加
        $columnDefines = $this->ledgerDefine->column_define;
        $columnDefines[] = [
            'id' => 4,
            'name' => 'User Name',
            'type' => 'user_name',
            'order' => 4,
            'required' => false,
            'unique' => false,
            'options' => [
                'format' => 'full_name',
                'organization_prefix' => 'none',
                'edit_mode' => 'overwrite',
            ],
            'group' => null,
            'file' => null,
        ];

        $this->ledgerDefine->update(['column_define' => $columnDefines]);
        $this->ledgerDefine->refresh();

        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('content', [
                0 => 'テスト値',
                4 => '変更されたユーザー名', // 初期値と異なる
            ])
            ->set('tenantId', $this->tenant->id)
            ->call('generatePrefillLink');

        $url = $component->get('generatedPrefillURL');

        // カラム4は初期値と異なるので含まれる
        $this->assertStringContainsString('prefill%5B4%5D', $url);
    }

    #[Test]
    public function it_dispatches_copy_events()
    {
        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->set('generatedPrefillURL', 'http://example.com/test')
            ->call('copyPrefillLinkToClipboard')
            ->assertDispatched('copy-to-clipboard');
    }

    #[Test]
    public function it_dispatches_success_notification()
    {
        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->call('notifyCopySuccess')
            ->assertDispatched('prefill-copy-success');
    }

    #[Test]
    public function it_dispatches_failed_notification()
    {
        Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ])
            ->call('notifyCopyFailed')
            ->assertDispatched('prefill-copy-failed');
    }

    #[Test]
    public function it_can_generate_prefill_qr_code()
    {
        Livewire::test(CreateColumn::class, [
            'tenantId' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'folderId' => $this->folder->id,
        ])
            ->set('content.1', 'QRテスト')
            ->call('generatePrefillLink')
            ->assertSet('showPrefillModal', true)
            ->assertSeeHtml('svg') // SVGタグが含まれていることを確認
            ->assertSet('prefillQRCode', function ($value) {
                return str_contains($value, '<svg') && str_contains($value, '</svg>');
            });
    }

    #[Test]
    public function it_returns_empty_qr_code_for_too_long_prefill_url()
    {
        Livewire::test(CreateColumn::class, [
            'tenantId' => $this->tenant->id,
            'ledgerDefineId' => $this->ledgerDefine->id,
            'folderId' => $this->folder->id,
        ])
            ->set('generatedPrefillURL', 'http://example.com/?prefill='.str_repeat('a', 6000))
            ->assertSet('prefillQRCode', '');
    }

    #[Test]
    public function it_generates_prefill_download_file_name_from_ledger_define_title()
    {
        Carbon::setTestNow('2026-03-15 13:30:00');

        $this->ledgerDefine->update(['title' => '見積 / 依頼 : 2026 | draft']);

        $component = Livewire::test(CreateColumn::class, [
            'ledgerDefineId' => $this->ledgerDefine->id,
        ]);

        $this->assertSame(
            '見積_依頼_2026_draft_事前入力用QR_20260315_133000.svg',
            $component->instance()->getPrefillDownloadFileNameProperty()
        );

        Carbon::setTestNow();
    }
}
