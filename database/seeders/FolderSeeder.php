<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Seeder;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // テナントに最初のユーザーが存在するか確認し、なければ作成する
        // このユーザーはフォルダの作成者・更新者として使用される
        $user = User::first();
        if (! $user) {
            $user = User::factory()->create();
        }
        $userId = $user->id;

        // ルートフォルダを作成
        $root = Folder::create(['title' => '/', 'creator_id' => $userId, 'modifier_id' => $userId]);

        // デモ用の階層フォルダを作成
        $sub1 = $root->children()->create(['title' => 'Subfolder 1', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $sub5 = $root->children()->create(['title' => 'Subfolder 5', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $root->children()->create(['title' => 'Subfolder 9', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $root->children()->create(['title' => 'Subfolder 10', 'creator_id' => $userId, 'modifier_id' => $userId]);

        $sub1->children()->create(['title' => 'Subfolder 2', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $sub1->children()->create(['title' => 'Subfolder 3', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $sub4 = $sub1->children()->create(['title' => 'Subfolder 4', 'creator_id' => $userId, 'modifier_id' => $userId]);

        $sub4->children()->create(['title' => 'Subfolder 11', 'creator_id' => $userId, 'modifier_id' => $userId]);

        $sub6 = $sub5->children()->create(['title' => 'Subfolder 6', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $sub7 = $sub6->children()->create(['title' => 'Subfolder 7', 'creator_id' => $userId, 'modifier_id' => $userId]);
        $sub7->children()->create(['title' => 'Subfolder 8', 'creator_id' => $userId, 'modifier_id' => $userId]);
    }
}
