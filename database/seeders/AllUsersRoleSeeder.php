<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class AllUsersRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // All Users ロールを作成 (存在しない場合のみ)
        $allUsersRole = Role::firstOrCreate(['name' => 'All Users', 'guard_name' => 'web']);

        // 既存の全ユーザーに All Users ロールを付与
        $users = User::all();
        foreach ($users as $user) {
            // 既に All Users ロールを持っている場合は付与しない
            if (!$user->hasRole($allUsersRole)) {
                $user->assignRole($allUsersRole);
            }
        }
    }
}
