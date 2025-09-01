<?php

namespace Database\Seeders;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application\'s database.
     */
    public function run(): void
    {
        // このシーダーはテナントDBに対して実行されることを想定

        // テナントDBの初期データを投入
        $this->call([
            FolderSeeder::class,
            UsersSeeder::class,
            OrganizationSeeder::class,
            RolesAndPermissionsSeeder::class,
            AllUsersRoleSeeder::class,
            NotificationTypeSeeder::class,
        ]);

        // ファクトリで作成されるユーザーもテナントに紐づく
        $users = User::factory(10)->create();

        $ledgerDefines = LedgerDefine::factory(50)->recycle($users)->create();
        Ledger::factory(1000)->recycle($users)->recycle($ledgerDefines)->create();
        $tags = Tag::factory(100)->recycle($users)->recycle($ledgerDefines)->create();
    }
}

