<?php

namespace Tests\Unit\Repositories;

use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WritableFolderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_writable_folder_ids_returns_all_writable_folders_for_user()
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'role1']);
        $role2 = Role::create(['name' => 'role2']);
        $folder1 = Folder::factory()->create();
        $folder2 = Folder::factory()->create();
        $folder3 = Folder::factory()->create();

        $user->assignRole($role1);
        $user->assignRole($role2);

        // RoleFolderPermission を作成する前に、Role と Folder が存在することを確認
        $this->assertNotNull($role1);
        $this->assertNotNull($folder1);
        $this->assertNotNull($role2);
        $this->assertNotNull($folder2);
        // RoleFolderPermission を直接使用して関連付け
        RoleFolderPermission::create(['role_id' => $role1->id, 'folder_id' => $folder1->id, 'permission' => 'write', 'modifier_id' => $user->id]);
        RoleFolderPermission::create(['role_id' => $role2->id, 'folder_id' => $folder2->id, 'permission' => 'write', 'modifier_id' => $user->id]);

        $repository = new WritableFolderRepository;
        $writableFolderIds = $repository->getWritableFolderIds($user);

        $this->assertCount(2, $writableFolderIds);
        $this->assertContains($folder1->id, $writableFolderIds);
        $this->assertContains($folder2->id, $writableFolderIds);
        $this->assertNotContains($folder3->id, $writableFolderIds);
    }

    public function test_get_writable_folder_ids_returns_only_writable_folders_under_specified_folder()
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'role1']);
        $role2 = Role::create(['name' => 'role2']);
        $rootFolder = Folder::factory()->create(['title' => 'ルートフォルダ']);
        $childFolder1 = Folder::factory()->create(['title' => 'フォルダ1', 'parent_id' => $rootFolder->id]);
        $childFolder2 = Folder::factory()->create(['title' => 'フォルダ2', 'parent_id' => $rootFolder->id]);
        $grandChildFolder = Folder::factory()->create(['title' => 'フォルダ1-1', 'parent_id' => $childFolder1->id]);
        $otherFolder = Folder::factory()->create(['title' => 'その他フォルダ']);

        $user->assignRole($role1);
        $user->assignRole($role2);
        $role1->writableFolders()->attach($childFolder1, ['permission' => 'write', 'modifier_id' => $user->id]);
        $role2->writableFolders()->attach($grandChildFolder, ['permission' => 'write', 'modifier_id' => $user->id]);
        $role2->writableFolders()->attach($otherFolder, ['permission' => 'write', 'modifier_id' => $user->id]);

        $repository = new WritableFolderRepository;

        // ルートフォルダを指定した場合
        $writableFolderIds = $repository->getWritableFolderIds($user, $rootFolder);
        $this->assertCount(3, $writableFolderIds);
        $this->assertContains($childFolder1->id, $writableFolderIds);
        $this->assertContains($grandChildFolder->id, $writableFolderIds);

        // フォルダ1を指定した場合
        $writableFolderIds = $repository->getWritableFolderIds($user, $childFolder1);
        $this->assertCount(3, $writableFolderIds);
        $this->assertContains($childFolder1->id, $writableFolderIds);
        $this->assertContains($grandChildFolder->id, $writableFolderIds);

        // フォルダ2を指定した場合
        $writableFolderIds = $repository->getWritableFolderIds($user, $childFolder2);
        $this->assertCount(1, $writableFolderIds);

        // その他フォルダを指定した場合
        $writableFolderIds = $repository->getWritableFolderIds($user, $otherFolder);
        $this->assertCount(2, $writableFolderIds); // otherFolder自身は含まれる
        $this->assertContains($otherFolder->id, $writableFolderIds);
    }

    public function test_get_writable_folder_ids_cache()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'writable-role']);
        $folder = Folder::factory()->create();
        $user->assignRole($role);
        $role->writableFolders()->attach($folder, ['permission' => 'write', 'modifier_id' => $user->id]);

        // キャッシュをクリア
        Cache::flush();

        $repository = new WritableFolderRepository;

        // 初回呼び出し（キャッシュ生成）
        $writableFolderIds1 = $repository->getWritableFolderIds($user);

        // キャッシュから取得されることを確認
        $writableFolderIds2 = $repository->getWritableFolderIds($user);
        $this->assertEquals($writableFolderIds1, $writableFolderIds2);

        // フォルダを指定した場合のキャッシュのテスト
        $rootFolder = Folder::factory()->create(['title' => 'ルートフォルダ']);
        $childFolder = Folder::factory()->create(['title' => '子フォルダ', 'parent_id' => $rootFolder->id]);
        $role->writableFolders()->attach($childFolder, ['permission' => 'write', 'modifier_id' => $user->id]);

        // ルートフォルダ以下の書き込み可能フォルダIDを取得（キャッシュ生成）
        $writableFolderIdsUnderRoot = $repository->getWritableFolderIds($user, $rootFolder);
        $this->assertContains($childFolder->id, $writableFolderIdsUnderRoot);

        // キャッシュから取得されることを確認
        $writableFolderIdsUnderRoot2 = $repository->getWritableFolderIds($user, $rootFolder);
        $this->assertEquals($writableFolderIdsUnderRoot, $writableFolderIdsUnderRoot2);

        // 異なるフォルダではキャッシュが効かないことを確認
        $otherFolder = Folder::factory()->create(['title' => 'その他フォルダ']);
        $writableFolderIdsUnderOther = $repository->getWritableFolderIds($user, $otherFolder);
        $this->assertNotEquals($writableFolderIdsUnderRoot, $writableFolderIdsUnderOther);
    }

    public function test_refresh_writable_folder_cache()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'writable-role']);
        $folder = Folder::factory()->create();
        $user->assignRole($role);
        $role->writableFolders()->attach($folder, ['permission' => 'write', 'modifier_id' => $user->id]);

        $repository = new WritableFolderRepository;

        // 初回呼び出し（キャッシュ生成）
        $writableFolderIds1 = $repository->getWritableFolderIds($user);

        // ロールとフォルダの関連付けを変更
        $role->writableFolders()->detach($folder);
        $newFolder = Folder::factory()->create();
        $role->writableFolders()->attach($newFolder, ['permission' => 'write', 'modifier_id' => $user->id]);

        // キャッシュをリフレッシュ
        $repository->refreshFolderCache($user, 'write');

        // 変更後のフォルダIDが取得されることを確認
        $writableFolderIds2 = $repository->getWritableFolderIds($user);
        $this->assertNotEquals($writableFolderIds1, $writableFolderIds2);
        $this->assertNotContains($folder->id, $writableFolderIds2);
        $this->assertContains($newFolder->id, $writableFolderIds2);
    }

    public function test_clear_writable_folder_cache()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'writable-role']);
        $folder = Folder::factory()->create();
        $user->assignRole($role);
        $role->writableFolders()->attach($folder, ['permission' => 'write', 'modifier_id' => $user->id]);

        $repository = new WritableFolderRepository;

        // 初回呼び出し（キャッシュ生成）
        $writableFolderIds1 = $repository->getWritableFolderIds($user);

        // キャッシュをクリア
        $repository->clearFolderCache($user, 'write');

        // ロールとフォルダの関連付けを変更
        $role->writableFolders()->detach($folder);
        $newFolder = Folder::factory()->create();
        $role->writableFolders()->attach($newFolder, ['permission' => 'write', 'modifier_id' => $user->id]);

        // 変更後のフォルダIDが取得されることを確認（キャッシュが効いていない）
        $writableFolderIds2 = $repository->getWritableFolderIds($user);
        $this->assertNotEquals($writableFolderIds1, $writableFolderIds2);
        $this->assertNotContains($folder->id, $writableFolderIds2);
        $this->assertContains($newFolder->id, $writableFolderIds2);
    }
}
