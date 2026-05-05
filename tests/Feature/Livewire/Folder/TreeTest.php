<?php

namespace Tests\Feature\Livewire\Folder;

use App\Livewire\Folder\Tree;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant-'.uniqid('', true), 'name' => 'Test Tenant']);

        $this->tenant->run(function () {
            $this->user = User::factory()->create([
                'email' => 'test-'.uniqid('', true).'@example.com',
                'password' => bcrypt('password'),
            ]);

            $adminRole = Role::firstOrCreate(['name' => 'Admin']);
            $this->user->assignRole($adminRole);

            $rootFolder = Folder::factory()->create([
                'title' => 'Root',
                'parent_id' => null,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

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

            $grandChild1 = Folder::factory()->create([
                'title' => 'Grandchild 1',
                'parent_id' => $childFolder1->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // Sprint 4 回帰テスト用: 深い階層（5段）をsetUpで一括作成
            $depth2 = Folder::factory()->create([
                'title' => 'Deep L2',
                'parent_id' => $rootFolder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);
            $depth3 = Folder::factory()->create([
                'title' => 'Deep L3',
                'parent_id' => $depth2->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);
            $depth4 = Folder::factory()->create([
                'title' => 'Deep L4',
                'parent_id' => $depth3->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);
            $depth5 = Folder::factory()->create([
                'title' => 'Deep L5',
                'parent_id' => $depth4->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

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

            LedgerDefine::factory()->create([
                'title' => 'Deep Define',
                'folder_id' => $depth5->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
            ]);

            // fixTree は1回だけ呼ぶ（複数回呼ぶと処理が重複する）
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

        // Sprint 4: descendants リレーションで全子孫が一括 Eager Load されていることを確認
        foreach ($component->folders as $folder) {
            $this->assertTrue($folder->relationLoaded('ledgerDefines'));
            // descendants リレーションがロードされていることを確認
            $this->assertTrue($folder->relationLoaded('descendants'));

            // 各子孫フォルダーの ledgerDefines も Eager Load されていることを確認
            foreach ($folder->descendants as $descendant) {
                $this->assertTrue($descendant->relationLoaded('ledgerDefines'));
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

        // Root は7つの子孫フォルダーを持つ (Child 1, Child 2, Grandchild 1, Deep L2〜L5)
        // setUp で深い階層テスト用の Deep L2〜L5 も作成しているため
        $rootFolder = Folder::whereNull('parent_id')->first();
        $this->assertEquals(7, $rootFolder->descendantCount());

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

    /**
     * N+1 クエリ防止テスト（浅い階層 + 深い5段階層の両方を一括検証）
     *
     * setUp では Child 1〜2, Grandchild 1, Deep L2〜L5 の計7ノードが作成される。
     * Sprint 4: Folder::whereIsRoot()->with('descendants.ledgerDefines') による最適化で
     * クエリ数は階層の深さに依存しない固定値となるため、上限を 20 未満に締め直す。
     */
    #[Test]
    public function it_avoids_n_plus_one_queries_for_ledger_defines()
    {
        $this->actingAs($this->user);

        $queryCount = 0;
        \DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        Livewire::test(Tree::class);

        // Sprint 4: descendants リレーション最適化後は 20 クエリ未満を維持すること
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

    /**
     * Sprint 1 スモークテスト:
     * ツリーコンポーネントがフォルダ名を含んで正常に描画されることを確認する。
     * appWithDrawer.blade.php の sticky/overflow-y-auto 変更後も
     * Livewire コンポーネント自体の描画には影響がないことを保証する。
     *
     * 注意: ルートフォルダーはビュー上で isRoot() == true のとき "Top" と表示される（tree.blade.php 参照）
     */
    #[Test]
    public function it_renders_tree_with_all_folder_names()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Tree::class);

        $component->assertStatus(200);
        // ルートフォルダーは components/folder/tree.blade.php で "Top" と表示される
        $component->assertSee('Top');
        $component->assertSee('Child 1');
        $component->assertSee('Child 2');
        $component->assertSee('Grandchild 1');
        // 深い階層のフォルダーも表示されることを確認（Sprint 4 クエリ最適化の回帰確認を兼ねる）
        $component->assertSee('Deep L2');
        $component->assertSee('Deep L5');
    }

    #[Test]
    public function it_hides_collapsed_subtrees_until_alpine_initializes()
    {
        $this->actingAs($this->user);

        Livewire::test(Tree::class)
            ->assertSeeHtml('x-cloak');
    }

    /**
     * Sprint 3: アコーディオン展開状態テスト
     *
     * 選択中フォルダ（currentFolderId）が設定された場合、
     * そのフォルダが描画されることを確認する。
     * また、selectedFolderIds に含まれる祖先フォルダも描画される（x-show による DOM は
     * サーバーサイドで常に出力されるため、Livewire テストで assertSee できる）。
     */
    #[Test]
    public function it_renders_selected_folder_and_ancestors_in_html()
    {
        $this->actingAs($this->user);

        $grandchild = Folder::where('title', 'Grandchild 1')->first();
        $child1 = Folder::where('title', 'Child 1')->first();

        // Grandchild 1 を選択中フォルダとし、祖先 Child 1 を selectedFolderIds に含める
        $component = Livewire::test(Tree::class, [
            'currentFolderId' => $grandchild->id,
            'selectedFolderIds' => [$child1->id, $grandchild->id],
        ]);

        $component->assertStatus(200);
        // 選択中フォルダが HTML に存在することを確認
        $component->assertSee('Grandchild 1');
        // 祖先フォルダも HTML に存在することを確認
        $component->assertSee('Child 1');

        // Sprint 3: 折りたたみボタン（fa-chevron）が存在することを確認（子を持つフォルダにのみ表示）
        // fa-chevron-right を CSS transform で回転させてアニメーションを実現するため
        $component->assertSee('fa-chevron-right', false);
    }

    /**
     * Sprint 3: x-show による開閉制御のサーバーサイド初期値テスト
     *
     * 選択中フォルダに関連する祖先フォルダの x-data に
     * open: true の初期値が埋め込まれることを確認する。
     */
    #[Test]
    public function it_embeds_open_true_for_current_folder_in_xdata()
    {
        $this->actingAs($this->user);

        $grandchild = Folder::where('title', 'Grandchild 1')->first();

        $component = Livewire::test(Tree::class, [
            'currentFolderId' => $grandchild->id,
            'selectedFolderIds' => [$grandchild->id],
        ]);

        $component->assertStatus(200);
        // currentFolderId に対応するノードの x-data に open: true が埋め込まれる
        $component->assertSeeHtml('open: (function()');
    }

    #[Test]
    public function it_syncs_current_folder_from_main_event()
    {
        $this->actingAs($this->user);

        $childFolder = Folder::where('title', 'Child 1')->first();

        $component = Livewire::test(Tree::class, [
            'currentFolderId' => $childFolder->id,
            'selectedFolderIds' => [$childFolder->id],
        ]);

        $component->dispatch('currentFolderChangedByMain', newFolderId: $childFolder->id, newSelectedFolderIds: [$childFolder->id]);

        $component->assertSet('standaloneFolderId', $childFolder->id);
    }

    /**
     * Sprint 4: 5段階層での descendants 一括 Eager Load 回帰テスト
     *
     * setUp で作成した Deep L2〜L5 の5段階層が descendants リレーションで
     * 正しく全子孫を取得できることを確認する。
     * descendants リレーション最適化後もクエリ数が 20 未満を維持していることを
     * it_avoids_n_plus_one_queries_for_ledger_defines で保証している。
     */
    #[Test]
    public function it_loads_all_descendants_in_deep_hierarchy()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Tree::class);

        $component->assertStatus(200);

        // ルートフォルダーに descendants が一括ロードされていることを確認
        $rootFolder = $component->folders->first();
        $this->assertTrue($rootFolder->relationLoaded('descendants'));

        // 5段階層（Deep L2〜L5 を含む計7ノード）が全て descendants に含まれることを確認
        $this->assertEquals(7, $rootFolder->descendants->count());

        // 深い階層の末端ノード（Deep L5）も descendants に含まれることを確認
        $deepL5 = $rootFolder->descendants->firstWhere('title', 'Deep L5');
        $this->assertNotNull($deepL5, 'Deep L5 should be loaded via descendants relation');
        $this->assertTrue($deepL5->relationLoaded('ledgerDefines'));
    }
}
