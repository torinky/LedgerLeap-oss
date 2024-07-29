<?php

namespace Database\Seeders;

use App\Models\Folder;
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
        $root = Folder::create(['title' => 'Root structure', 'modifier_id' => 1, 'creator_id' => 1]);

        $sub1 = $root->children()->create(['title' => 'sub1', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub5 = $root->children()->create(['title' => 'sub5', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub9 = $root->children()->create(['title' => 'sub9', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub10 = $root->children()->create(['title' => 'sub10', 'modifier_id' => 1, 'creator_id' => 1]);

        $sub2 = $sub1->children()->create(['title' => 'sub2', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub3 = $sub1->children()->create(['title' => 'sub3', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub4 = $sub1->children()->create(['title' => 'sub4', 'modifier_id' => 1, 'creator_id' => 1]);

        $sub11 = $sub4->children()->create(['title' => 'sub11', 'modifier_id' => 1, 'creator_id' => 1]);

        $sub6 = $sub5->children()->create(['title' => 'sub6', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub7 = $sub6->children()->create(['title' => 'sub7', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub8 = $sub7->children()->create(['title' => 'sub8', 'modifier_id' => 1, 'creator_id' => 1]);
    }
}
