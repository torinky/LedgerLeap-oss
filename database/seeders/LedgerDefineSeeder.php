<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Database\Seeder;

class LedgerDefineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $folderIds = Folder::pluck('id');

        if ($folderIds->isEmpty()) {
            // フォルダが存在しない場合は、警告を出すか、何もしない
            $this->command->warn('No folders found, skipping LedgerDefineSeeder.');
            return;
        }

        LedgerDefine::factory()->count(50)->create([
            'folder_id' => fn () => $folderIds->random(),
        ]);
    }
}