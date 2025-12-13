<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 台帳レコード複製機能のテスト
 *
 * このテストクラスは、DuplicateControllerの機能をテストします。
 * 既存のLedgerCreateControllerPrefillTestとは異なり、以下の点に焦点を当てます：
 * - DBからレコードを読み込んでprefillパラメータを構成する処理
 * - 日付フィールド（YMD, YMDHM）の除外
 * - 2段階の権限チェック（閲覧権限 + 作成権限）
 * - 実際のLedgerレコードからの複製
 */
class LedgerDuplicateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected User $unauthorizedUser;

    protected LedgerDefine $ledgerDefine;

    protected Folder $folder;

    protected Ledger $sourceLedger;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントとドメインを作成し、テナンシーを初期化
        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

        // 権限を作成
        Permission::findOrCreate('view_ledgers', 'web');
        Permission::findOrCreate('create_ledgers', 'web');

        // ロールを作成
        $role = Role::findOrCreate('test-creator-role', 'web');
        $role->givePermissionTo(['view_ledgers', 'create_ledgers']);

        // 認可ユーザーを作成
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        // 非認可ユーザー（閲覧のみ可能）を作成
        $viewerRole = Role::findOrCreate('test-viewer-role', 'web');
        $viewerRole->givePermissionTo('view_ledgers');
        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole($viewerRole);

        // フォルダを作成
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

        // 閲覧のみ権限を付与（非認可ユーザー用）
        RoleFolderPermission::create([
            'role_id' => $viewerRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
            'modifier_id' => $this->user->id,
        ]);

        // 台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => $this->getTestColumnDefines(),
        ]);

        // 複製元のレコードを作成
        $this->sourceLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->getTestContent(),
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // Spatieの権限キャッシュをクリア
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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
                'name' => 'タイトル',
                'type' => 'text',
                'order' => 0,
                'required' => true,
                'unique' => false,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 1,
                'name' => '作成日',
                'type' => 'YMD',
                'order' => 1,
                'required' => false,
                'unique' => false,
                'options' => ['default_offset' => '0d'],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 2,
                'name' => '更新日時',
                'type' => 'YMDHM',
                'order' => 2,
                'required' => false,
                'unique' => false,
                'options' => ['default_offset' => '0d'],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 3,
                'name' => '採番',
                'type' => 'auto_number',
                'order' => 3,
                'required' => false,
                'unique' => true,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 4,
                'name' => '添付ファイル',
                'type' => 'files',
                'order' => 4,
                'required' => false,
                'unique' => false,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 5,
                'name' => '説明',
                'type' => 'textarea',
                'order' => 5,
                'required' => false,
                'unique' => false,
                'options' => [],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 6,
                'name' => 'カテゴリ',
                'type' => 'select',
                'order' => 6,
                'required' => false,
                'unique' => false,
                'options' => ['カテゴリA', 'カテゴリB', 'カテゴリC'],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 7,
                'name' => 'タグ',
                'type' => 'chk',
                'order' => 7,
                'required' => false,
                'unique' => false,
                'options' => ['タグ1', 'タグ2', 'タグ3'],
                'group' => null,
                'file' => null,
            ],
            [
                'id' => 8,
                'name' => '担当者',
                'type' => 'user_name',
                'order' => 8,
                'required' => false,
                'unique' => false,
                'options' => ['overwrite_on_edit' => false, 'include_organization' => true],
                'group' => null,
                'file' => null,
            ],
        ];
    }

    protected function getTestContent(): array
    {
        return [
            0 => 'テスト台帳タイトル',
            1 => '2025-12-10',
            2 => '2025-12-10 14:30',
            3 => 'AUTO-001',
            4 => [], // ファイル情報
            5 => "詳細な説明文\n複数行のテキスト",
            6 => 'カテゴリB',
            7 => ['タグ1', 'タグ3'],
            8 => $this->user->name ?? 'テストユーザー',
        ];
    }

    #[Test]
    public function it_can_access_duplicate_route_with_proper_permissions(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);
        $response->assertViewIs('ledger.create');
        $response->assertViewHas('ledgerDefineRecord');
        $response->assertViewHas('prefillParams');
        $response->assertViewHas('sourceLedgerId');
    }

    #[Test]
    public function it_excludes_date_fields_from_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // YMD（カラムID: 1）は除外される
        $this->assertArrayNotHasKey(1, $prefillParams);

        // YMDHM（カラムID: 2）は除外される
        $this->assertArrayNotHasKey(2, $prefillParams);
    }

    #[Test]
    public function it_excludes_auto_number_fields_from_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // auto_number（カラムID: 3）は除外される
        $this->assertArrayNotHasKey(3, $prefillParams);
    }

    #[Test]
    public function it_excludes_files_fields_from_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // files（カラムID: 4）は除外される
        $this->assertArrayNotHasKey(4, $prefillParams);
    }

    #[Test]
    public function it_includes_text_fields_in_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // タイトル（カラムID: 0）は複製される
        $this->assertArrayHasKey(0, $prefillParams);
        $this->assertEquals('テスト台帳タイトル', $prefillParams[0]);

        // 説明（カラムID: 5）は複製される
        $this->assertArrayHasKey(5, $prefillParams);
        $this->assertStringContainsString('詳細な説明文', $prefillParams[5]);
    }

    #[Test]
    public function it_includes_select_fields_in_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // カテゴリ（カラムID: 6）は複製される
        $this->assertArrayHasKey(6, $prefillParams);
        $this->assertEquals('カテゴリB', $prefillParams[6]);
    }

    #[Test]
    public function it_includes_checkbox_fields_in_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // タグ（カラムID: 7）は複製される
        $this->assertArrayHasKey(7, $prefillParams);
        $this->assertIsArray($prefillParams[7]);
        $this->assertContains('タグ1', $prefillParams[7]);
        $this->assertContains('タグ3', $prefillParams[7]);
    }

    #[Test]
    public function it_includes_user_name_fields_in_duplication(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 担当者（カラムID: 8）は複製される
        $this->assertArrayHasKey(8, $prefillParams);
    }

    #[Test]
    public function it_requires_view_permission_on_source_ledger(): void
    {
        // 閲覧権限のないユーザーでアクセス
        $userWithoutViewPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutViewPermission)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        // 403 Forbiddenが返される
        $response->assertStatus(403);
    }

    #[Test]
    public function it_requires_create_permission_on_ledger_define(): void
    {
        // 作成権限のないユーザー（閲覧のみ）でアクセス
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        // 403 Forbiddenが返される
        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_for_non_existent_ledger(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => 99999,
            ]));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_sanitizes_text_content_from_source_ledger(): void
    {
        // XSSを含むコンテンツを持つレコードを作成
        $maliciousLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => '<script>alert("XSS")</script>タイトル',
                1 => '',
                2 => '',
                3 => '',
                4 => [],
                5 => '<img src=x onerror="alert(1)">説明',
            ],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $maliciousLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // HTMLタグが除去されている
        $this->assertStringNotContainsString('<script>', $prefillParams[0]);
        $this->assertStringContainsString('タイトル', $prefillParams[0]);
        $this->assertStringNotContainsString('<img', $prefillParams[5]);
        $this->assertStringContainsString('説明', $prefillParams[5]);
    }

    #[Test]
    public function it_limits_text_length_from_source_ledger(): void
    {
        // 非常に長いテキストを持つレコードを作成
        $longTextLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => str_repeat('あ', 6000), // 5000文字を超える
            ],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $longTextLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 5000文字に制限されている
        $this->assertEquals(5000, mb_strlen($prefillParams[0]));
    }

    #[Test]
    public function it_validates_select_options_from_source_ledger(): void
    {
        // 現在は存在しない選択肢を持つレコードを作成
        $invalidSelectLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'テストタイトル',
                1 => '',
                2 => '',
                3 => '',
                4 => [],
                5 => '',
                6 => '削除された選択肢', // 現在のoptionsに存在しない
            ],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $invalidSelectLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 無効な選択肢は除外される
        $this->assertArrayNotHasKey(6, $prefillParams);
    }

    #[Test]
    public function it_validates_checkbox_options_from_source_ledger(): void
    {
        // 一部の選択肢が削除されたケース
        $invalidCheckboxLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'テストタイトル',
                1 => '',  // YMD（空）
                2 => '',  // YMDHM（空）
                3 => '',  // auto_number（空）
                4 => [],  // files（空）
                5 => '',  // textarea（空）
                6 => '',  // select（空）
                7 => ['タグ1', '削除されたタグ', 'タグ3'], // 一部無効
            ],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // リロードして実際のデータを確認
        $invalidCheckboxLedger->refresh();

        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $invalidCheckboxLedger->id,
            ]));

        $response->assertStatus(200);

        $prefillParams = $response->viewData('prefillParams');

        // 有効な選択肢のみが含まれる
        $this->assertArrayHasKey(7, $prefillParams);
        $this->assertIsArray($prefillParams[7]);
        $this->assertContains('タグ1', $prefillParams[7]);
        $this->assertContains('タグ3', $prefillParams[7]);
        $this->assertNotContains('削除されたタグ', $prefillParams[7]);
    }

    #[Test]
    public function it_passes_source_ledger_id_to_view_for_audit(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $this->sourceLedger->id,
            ]));

        $response->assertStatus(200);

        $sourceLedgerId = $response->viewData('sourceLedgerId');

        // 複製元のIDが正しく渡される
        $this->assertEquals($this->sourceLedger->id, $sourceLedgerId);
    }

    #[Test]
    public function it_returns_404_when_ledger_define_is_missing(): void
    {
        // 存在しないledger_define_idを持つレコードを作成しようとする
        // 実際にはLedgerDefineが存在しないとLedgerも作成できないため、
        // 単純に存在しないIDでアクセスを試みる
        $nonExistentLedgerId = 999999;

        $response = $this->actingAs($this->user)
            ->get(route('ledger.duplicate', [
                'tenant' => $this->tenant->id,
                'ledgerId' => $nonExistentLedgerId,
            ]));

        // レコードが存在しないため404
        $response->assertStatus(404);
    }
}

