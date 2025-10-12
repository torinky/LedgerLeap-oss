<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(int $seedCount = 50): void // 引数を追加
    {
        // 環境変数でデモデータモードかどうかを判定
        $isDemoMode = env('SEEDER_MODE') === 'demo';

        if ($isDemoMode) {
            $this->command->info('🎯 Demo Mode: Running Demo Complete Seeder...');

            // 権限システムを先に初期化
            $this->call(RolesAndPermissionsSeeder::class);

            // その後デモデータを作成
            $this->call(DemoCompleteSeeder::class);

            return;
        }

        // 通常モード: 既存のSeeder処理
        $this->command->info('🔧 Standard Mode: Running standard seeders...');

        // 中央DBの初期データを投入
        $this->call([
            UsersSeeder::class,
            OrganizationSeeder::class,
            RolesAndPermissionsSeeder::class,
            AllUsersRoleSeeder::class,
            NotificationTypeSeeder::class,
        ]);

        // テナントコンテキストが存在する場合のみ、テナントDBの初期データを投入
        if (tenancy()->tenant) {
            $this->call([
                FolderSeeder::class,
                ScoringConfigSeeder::class,
            ]);

            // ファクトリで作成されるユーザーもテナントに紐づく
            $users = User::factory(10)->create();

            // 作成されたすべてのフォルダを取得
            $folders = Folder::all();
            $ledgerDefines = collect();

            // フォルダが存在する場合のみLedgerDefineを作成
            if ($folders->isNotEmpty()) {
                $ledgerDefines = LedgerDefine::factory($seedCount) // $seedCount を渡す
                    ->recycle($users)
                    ->make() // DBにはまだ保存しない
                    ->each(function ($ledgerDefine) use ($folders) {
                        // ランダムなフォルダを割り当て
                        $ledgerDefine->folder_id = $folders->random()->id;
                        $ledgerDefine->save(); // ここでDBに保存
                    });
            }

            Ledger::factory($seedCount)->recycle($users)->recycle($ledgerDefines)->create(); // $seedCount を渡す
            $tags = Tag::factory(100)->recycle($users)->recycle($ledgerDefines)->recycle($folders)->create();
        }
    }
}
