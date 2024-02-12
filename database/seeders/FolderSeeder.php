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
//        $root=Folder::make(['title'=>Str::random(10)])->saveAsRoot();
        $root = Folder::make(['title' => 'Root structure', 'modifier_id' => 1, 'creator_id' => 1])->makeRoot();
        $root->save();

        $sub1 = Folder::make(['title' => 'sub1', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub1->appendTo($root)->save();
        $sub5 = Folder::make(['title' => 'sub5', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub5->appendTo($root)->save();
        $sub9 = Folder::make(['title' => 'sub9', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub9->appendTo($root)->save();
        $sub10 = Folder::make(['title' => 'sub10', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub10->appendTo($root)->save();

        $sub2 = Folder::make(['title' => 'sub2', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub2->appendTo($sub1)->save();
        $sub3 = Folder::make(['title' => 'sub3', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub3->appendTo($sub1)->save();
        $sub4 = Folder::make(['title' => 'sub4', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub4->appendTo($sub1)->save();

        $sub11 = Folder::make(['title' => 'sub11', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub11->appendTo($sub4)->save();


        $sub6 = Folder::make(['title' => 'sub6', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub6->appendTo($sub5)->save();
        $sub7 = Folder::make(['title' => 'sub7', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub7->appendTo($sub6)->save();
        $sub8 = Folder::make(['title' => 'sub8', 'modifier_id' => 1, 'creator_id' => 1]);
        $sub8->appendTo($sub7)->save();

        /*        $sub2 = Folder::make(['title' => 'sub2']);
        $sub1->children()->save($sub2);
        $sub3 = Folder::make(['title' => 'sub3']);
        $sub1->children()->save($sub3);
        $sub4 = Folder::make(['title' => 'sub4']);
        $sub1->children()->save($sub4);

        $sub11 = Folder::make(['title' => 'sub11']);
        $sub4->children()->save($sub11);


        $sub6 = Folder::make(['title' => 'sub6']);
        $sub5->children()->save($sub6);
        $sub7 = Folder::make(['title' => 'sub7']);
        $sub6->children()->save($sub7);
        $sub8 = Folder::make(['title' => 'sub8']);
        $sub7->children()->save($sub8);*/


    }
}
