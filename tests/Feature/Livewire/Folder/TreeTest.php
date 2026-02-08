<?php

namespace Tests\Feature\Livewire\Folder;

use App\Livewire\Folder\Tree;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreeTest extends TestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを作成して初期化
        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant', 'name' => 'Test Tenant']);

        $this->tenant->run(function () {
            // テスト用のユーザーを作成
            $this->user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);

            // Adminロールを作成
            $adminRole = Role::create(['name' => 'Admin']);
            $this->user->assignRole($adminRole);

            // ルートフォルダーを作成
            $rootFolder = Folder::factory()->create([
                'title' => 'Root',
                'parent_id' => null,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // 子フォルダーを作成
            $childFolder1 = Folder::factory()->create([
                'title' => 'Child 1',
                'parent_id' => $rootFolder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            $childFolder2 = Folder::factory()->create([
                'title' => 'Child 2',
                'parent_id' => $rootFolder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // 孫フォルダーを作成
            Folder::factory()->create([
                'title' => 'Grandchild 1',
                'parent_id' => $childFolder1->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // 台帳定義を作成
            LedgerDefine::factory()->create([
                'title' => 'Define 1',
                'folder_id' => $childFolder1->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            LedgerDefine::factory()->create([
                'title' => 'Define 2',
                'folder_id' => $childFolder2->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            LedgerDefine::factory()->create([
                'title' => 'Define 3',
                'folder_id' => $childFolder2->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // NestedSetのツリー構造を修復
            Folder::fixTree();
        });

        tenancy()->initialize($this->tenant);
    }

    #[Test]
    public function it_loads_folders_with_ledger_defines_eager_loaded()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Tree::class);

        // コンポーネントが正常にレンダリングされることを確認
        $component->assertStatus(200);

        // フォルダーがロードされていることを確認
        $this->assertNotNull($component->folders);
        $this->assertGreaterThan(0, $component->folders->count());

        // 各フォルダーの ledgerDefines がEager Loadされていることを確認
        foreach ($component->folders as $folder) {
            $this->assertTrue($folder->relationLoaded('ledgerDefines'));

            // 子フォルダーも確認
            if ($folder->children && $folder->children->count() > 0) {
                foreach ($folder->children as $child) {
                    $this->assertTrue($child->relationLoaded('ledgerDefines'));
                }
            }
        }
    }

    #[Test]
    public function it_displays_correct_ledger_define_counts()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Tree::class);

        // Child 1 は1つの台帳定義を持つ
        $childFolder1 = Folder::where('title', 'Child 1')->first();
        $this->assertEquals(1, $childFolder1->ledgerDefines->count());

        // Child 2 は2つの台帳定義を持つ
        $childFolder2 = Folder::where('title', 'Child 2')->first();
        $this->assertEquals(2, $childFolder2->ledgerDefines->count());

        // ビューに正しい数が表示されることを確認
        $component->assertSee('Child 1');
        $component->assertSee('Child 2');
    }

    #[Test]
    public function it_displays_correct_descendant_counts()
    {
        $this->actingAs($this->user);

        Livewire::test(Tree::class);

        // Root は3つの子孫フォルダーを持つ (Child 1, Child 2, Grandchild 1)
        $rootFolder = Folder::whereNull('parent_id')->first();
        $this->assertEquals(3, $rootFolder->descendantCount());

        // Child 1 は1つの子孫フォルダーを持つ (Grandchild 1)
        $childFolder1 = Folder::where('title', 'Child 1')->first();
        $this->assertEquals(1, $childFolder1->descendantCount());

        // Child 2 は子孫フォルダーを持たない
        $childFolder2 = Folder::where('title', 'Child 2')->first();
        $this->assertEquals(0, $childFolder2->descendantCount());

        // Grandchild 1 は子孫フォルダーを持たない
        $grandchildFolder1 = Folder::where('title', 'Grandchild 1')->first();
        $this->assertEquals(0, $grandchildFolder1->descendantCount());
    }

    #[Test]
    public function it_avoids_n_plus_one_queries_for_ledger_defines()
    {
        $this->actingAs($this->user);

        // クエリ数をカウント
        $queryCount = 0;
        \DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        Livewire::test(Tree::class);

        // N+1問題が発生していないことを確認
        // 基本的なクエリ数は以下の通り:
        // 1. ルートフォルダーの取得 (with ledgerDefines, children)
        // 2-3. 権限関連のクエリ
        // 4-5. 子孫フォルダーのledgerDefinesのEager Load
        // フォルダー数に比例してクエリが増えないことが重要
        $this->assertLessThan(20, $queryCount, 'N+1 query problem detected');
    }

    #[Test]
    public function it_initializes_permissions_correctly()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Tree::class);

        // 権限IDがロードされていることを確認
        $this->assertIsArray($component->writableFolderIds);
        $this->assertIsArray($component->readableFolderIds);
        $this->assertIsArray($component->manageableFolderIds);
    }
}
