<?php

namespace Database\Seeders;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Facades\Tenancy; // 追加
use Stancl\Tenancy\Database\Models\Tenant; // 追加

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 中央DBのユーザーを作成 (テナントに紐づかないユーザー)
        // 必要であればここに中央DB用のユーザー作成ロジックを追加
        // 例: User::factory()->create(['name' => 'Central Admin', 'email' => 'central@example.com']);

        // テナントを作成し、そのコンテキストでシーダーを実行
        $tenant = Tenant::create([
            'id' => 'testtenant', // テスト用のテナントID
            'data' => ['name' => 'Test Tenant'],
        ]);

        $tenant->run(function () {
            // テナントDBのユーザーを作成
            // UsersSeeder はテナントDBにユーザーを作成するため、ここで呼び出す
            $this->call([
                UsersSeeder::class,
                OrganizationSeeder::class,
                FolderSeeder::class,
                RolesAndPermissionsSeeder::class,
                AllUsersRoleSeeder::class,
                NotificationTypeSeeder::class,
            ]);

            // ファクトリで作成されるユーザーもテナントに紐づく
            $users = User::factory(10)->create();

            $ledgerDefines = LedgerDefine::factory(50)->recycle($users)->create();
            Ledger::factory(1000)->recycle($users)->recycle($ledgerDefines)->create();
            $tags = Tag::factory(100)->recycle($users)->recycle($ledgerDefines)->create();
        });
    }
}

