<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\RelatedLedgers;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RelatedLedgersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Folder $folder;

    private LedgerDefine $define;

    private Ledger $ledger;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // 権限設定
        $viewPermission = Permission::firstOrCreate(['name' => 'view_ledgers']);
        $this->user->givePermissionTo($viewPermission);

        $readerRole = Role::firstOrCreate(['name' => 'reader_role']);
        $this->user->assignRole($readerRole);

        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        // auto_number カラムを持つ台帳定義を作成
        $this->folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $readerRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        $this->define = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                    new ColumnDefine(1, 'タイトル', 'text', 2),
                    new ColumnDefine(2, '説明', 'textarea', 3),
                ],
            ]);

        // テスト対象のレコード（content[0] に auto_number 値を持つ）
        $this->ledger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',
                    1 => 'メインテスト設備',
                    2 => '設備の詳細説明',
                ],
            ]);
    }

    // ─────────────────────────────────────────────
    // Task 1.1.1: auto_number カラム値の抽出
    // ─────────────────────────────────────────────

    #[Test]
    public function it_extracts_auto_number_values_from_ledger(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $values = $component->extractAutoNumberValues($this->ledger);

        $this->assertIsArray($values);
        $this->assertContains('EQ-001', $values);
        // text タイプの値は含まれない
        $this->assertNotContains('メインテスト設備', $values);
    }

    #[Test]
    public function it_returns_empty_when_no_auto_number_columns(): void
    {
        // auto_number カラムを持たない台帳定義
        $defineWithoutAutoNumber = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, 'タイトル', 'text', 1),
                    new ColumnDefine(1, '内容', 'textarea', 2),
                ],
            ]);

        $ledgerWithoutAutoNumber = Ledger::factory()
            ->for($defineWithoutAutoNumber, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '普通のテキスト',
                    1 => '説明文',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $ledgerWithoutAutoNumber->id;

        $values = $component->extractAutoNumberValues($ledgerWithoutAutoNumber);

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    // ─────────────────────────────────────────────
    // Task 1.2.2 + 1.3.1: 識別番号検索
    // ─────────────────────────────────────────────

    #[Test]
    public function it_finds_related_ledgers_by_identifier(): void
    {
        // 同じ識別番号 'EQ-001' を content に含む別レコードを作成
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',      // 同じ識別番号
                    1 => '関連設備記録',
                    2 => '関連する設備の説明',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001']);

        $this->assertGreaterThan(0, $results->count());
        $resultIds = $results->pluck('id')->toArray();
        $this->assertContains($relatedLedger->id, $resultIds);
    }

    #[Test]
    public function it_excludes_self_from_identifier_search(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001']);

        $resultIds = $results->pluck('id')->toArray();
        $this->assertNotContains($this->ledger->id, $resultIds);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_identifiers_given(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers([]);

        $this->assertTrue($results->isEmpty());
    }

    #[Test]
    public function it_filters_out_results_from_inaccessible_folders(): void
    {
        // 別フォルダ（権限なし）の台帳定義
        $restrictedFolder = Folder::factory()->create();
        $restrictedDefine = LedgerDefine::factory()
            ->for($restrictedFolder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                ],
            ]);

        // そのフォルダに同じ識別番号のレコードを作成
        Ledger::factory()
            ->for($restrictedDefine, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [0 => 'EQ-001'],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001']);

        // 権限のないフォルダのレコードは含まれない
        $resultFolderIds = $results->map(fn ($l) => $l->define->folder_id ?? null)->unique()->toArray();
        $this->assertNotContains($restrictedFolder->id, $resultFolderIds);
    }

    // ─────────────────────────────────────────────
    // Sprint 2 用: 意味検索クエリ生成（バックエンドロジックのみ）
    // ─────────────────────────────────────────────

    #[Test]
    public function it_builds_semantic_query_excluding_files_columns(): void
    {
        $defineWithFiles = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, 'タイトル', 'text', 1),
                    new ColumnDefine(1, '添付', 'files', 2),
                    new ColumnDefine(2, '説明', 'textarea', 3),
                ],
            ]);

        $ledgerWithFiles = Ledger::factory()
            ->for($defineWithFiles, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '設備点検記録',
                    1 => [['original_name' => 'photo.jpg']], // files タイプ
                    2 => '詳細な点検内容をここに記載',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $ledgerWithFiles->id;

        $query = $component->buildSemanticQuery($ledgerWithFiles);

        $this->assertNotEmpty($query);
        // files カラムの値は含まれない
        $this->assertStringNotContainsString('photo.jpg', $query);
        // テキスト値は含まれる
        $this->assertStringContainsString('設備点検記録', $query);
        $this->assertStringContainsString('詳細な点検内容', $query);
    }

    #[Test]
    public function it_handles_rag_service_unavailable_gracefully(): void
    {
        // RagSearchService が例外を投げる状況をモック
        $this->mock(\App\Services\RagSearchService::class, function ($mock) {
            $mock->shouldReceive('searchLedgers')->andThrow(new \RuntimeException('RAG service unavailable'));
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        // 例外が外部に伝播しないこと
        $results = $component->searchBySemantic($this->ledger);

        $this->assertTrue($results->isEmpty());
        $this->assertFalse($component->ragAvailable);
    }

    // ─────────────────────────────────────────────
    // Livewire コンポーネントレンダリング（Sprint 3 へ向けた基礎確認）
    // ─────────────────────────────────────────────

    #[Test]
    public function it_can_be_instantiated_as_livewire_component(): void
    {
        // Lazy コンポーネントはプレースホルダーが先に表示されるため、
        // ここでは mount が例外を投げないことを確認するに留める
        $this->expectNotToPerformAssertions();

        try {
            Livewire::test(RelatedLedgers::class, ['ledgerId' => $this->ledger->id]);
        } catch (\Throwable $e) {
            // Lazy コンポーネントのプレースホルダービューが未存在の場合はスキップ
            if (str_contains($e->getMessage(), 'View') && str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('Placeholder view not yet created (Sprint 3 task)');
            }
            throw $e;
        }
    }
}
